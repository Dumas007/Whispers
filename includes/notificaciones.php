<?php
/**
 * Alertas al dueño/gerente cuando entra una calificación baja.
 * Canales: WhatsApp (API oficial de Meta, con plantilla aprobada) y correo.
 */
function http_json(string $url, array $headers, array $body, int $timeout = 12): array
{
    if (!function_exists('curl_init')) {
        // Hostings sin la extensión cURL: usamos las funciones de flujo de PHP
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", array_merge(['Content-Type: application/json'], $headers)),
            'content'       => json_encode($body, JSON_UNESCAPED_UNICODE),
            'timeout'       => $timeout,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1];
        }
        $err = $raw === false ? 'No se pudo conectar con ' . parse_url($url, PHP_URL_HOST) . ' (revisá que tu hosting permita conexiones externas).' : '';
        return ['code' => $code, 'body' => $raw === false ? '' : (string)$raw, 'error' => $err];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'body' => $raw === false ? '' : (string)$raw, 'error' => $err];
}

function whatsapp_configurado(): bool
{
    return ajuste('wa_token') !== '' && ajuste('wa_phone_id') !== '';
}

/** Envía la plantilla de alerta. Devuelve [ok, detalle]. */
function whatsapp_enviar_plantilla(string $numero, array $parametros): array
{
    return whatsapp_enviar_plantilla_nombre(ajuste('wa_plantilla', 'alerta_calificacion'), $numero, $parametros);
}

/** Envía cualquier plantilla aprobada de WhatsApp. */
function whatsapp_enviar_plantilla_nombre(string $plantilla, string $numero, array $parametros): array
{
    if (!whatsapp_configurado()) {
        return [false, 'WhatsApp no está configurado en Ajustes de Whispers.'];
    }
    $to = preg_replace('/\D/', '', $numero);
    if (strlen($to) === 8) $to = '502' . $to;

    $body = [
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'template',
        'template'          => [
            'name'       => $plantilla,
            'language'   => ['code' => ajuste('wa_idioma', 'es')],
            'components' => [[
                'type'       => 'body',
                'parameters' => array_map(fn($t) => ['type' => 'text', 'text' => texto_corto(str_replace(["\n", "\t"], ' ', (string)$t), 200)], $parametros),
            ]],
        ],
    ];
    $url = 'https://graph.facebook.com/' . ajuste('wa_version', 'v21.0') . '/' . ajuste('wa_phone_id') . '/messages';
    $r = http_json($url, ['Authorization: Bearer ' . ajuste('wa_token')], $body);
    $ok = $r['code'] >= 200 && $r['code'] < 300;
    return [$ok, $ok ? 'Enviado' : ('HTTP ' . $r['code'] . ' ' . ($r['error'] ?: texto_corto($r['body'], 400)))];
}

function correo_enviar(string $para, string $asunto, string $texto): array
{
    $de = ajuste('email_remitente', 'alertas@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
    $headers = "From: Whispers <$de>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    $ok = @mail($para, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $texto, $headers);
    return [$ok, $ok ? 'Enviado' : 'El servidor no pudo enviar el correo (revisá que mail() esté habilitado en tu hosting).'];
}

function registrar_alerta(int $negocioId, ?int $encuestaId, string $canal, string $destino, array $res): void
{
    q('INSERT INTO alertas_log (negocio_id, encuesta_id, canal, destino, ok, detalle) VALUES (?, ?, ?, ?, ?, ?)',
        [$negocioId, $encuestaId, $canal, $destino, $res[0] ? 1 : 0, $res[1]]);
}

/** Avisa de una encuesta con calificación baja por todos los canales configurados del negocio. */
function notificar_calificacion_baja(int $encuestaId): void
{
    $e = q('SELECT e.*, n.nombre negocio, n.plan, n.alerta_whatsapp, n.alerta_email, s.nombre sucursal, m.numero, m.nombre mesa
              FROM encuestas e JOIN negocios n ON n.id = e.negocio_id
              JOIN sucursales s ON s.id = e.sucursal_id JOIN mesas m ON m.id = e.mesa_id
             WHERE e.id = ?', [$encuestaId])->fetch();
    if (!$e) return;

    $lugar = $e['negocio'] . ' · ' . $e['sucursal'];
    $mesa = $e['mesa'] ?: 'Mesa ' . $e['numero'];
    $notaTxt = number_format((float)$e['promedio'], 1) . '/5';
    $coment = $e['comentario'] ?: 'Sin comentario';
    $link = url_absoluta('admin/encuesta_ver.php?id=' . $e['id']);

    $numerosWa = negocio_permite((int)$e['negocio_id'], $e['plan'], 'alertas_whatsapp') ? lista_contactos($e['alerta_whatsapp']) : [];
    foreach ($numerosWa as $numero) {
        $res = whatsapp_enviar_plantilla($numero, [$lugar, $mesa, $notaTxt, $coment]);
        registrar_alerta((int)$e['negocio_id'], $encuestaId, 'whatsapp', $numero, $res);
    }
    foreach (lista_contactos($e['alerta_email']) as $correo) {
        $txt = "Calificación baja en $lugar\n\n$mesa calificó con $notaTxt.\nComentario: $coment\n"
            . ($e['telefono'] ? "WhatsApp del cliente: {$e['telefono']}\n" : '')
            . "\nVer la encuesta: $link\n\n— Whispers";
        $res = correo_enviar($correo, "Alerta: $mesa calificó $notaTxt", $txt);
        registrar_alerta((int)$e['negocio_id'], $encuestaId, 'email', $correo, $res);
    }
}
