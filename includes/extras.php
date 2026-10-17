<?php
require_once __DIR__ . '/notificaciones.php';
/**
 * Funciones de: cupones, personal, idioma de la encuesta y modo tablet.
 */

/* ---------- Clave interna para firmar links (modo tablet) ---------- */
function app_secret(): string
{
    $s = ajuste('app_secret');
    if ($s === '') {
        $s = (string)db()->query("SELECT valor FROM ajustes WHERE clave = 'app_secret'")->fetchColumn();
        if ($s === '') { $s = bin2hex(random_bytes(24)); ajuste_guardar('app_secret', $s); }
    }
    return $s;
}

function firma_kiosko(string $tokenMesa): string
{
    return substr(hash_hmac('sha256', 'kiosko|' . $tokenMesa, app_secret()), 0, 16);
}

/* ---------- Idioma de la encuesta ---------- */
function idioma_encuesta(): string
{
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true)) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    if (!empty($_SESSION['lang'])) return $_SESSION['lang'];
    $al = strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    return (strpos($al, 'es') === 0 || $al === '' || strpos($al, 'en') !== 0) ? 'es' : 'en';
}

const TEXTOS_ENCUESTA = [
    'titulo'        => ['Contanos cómo te fue', 'Tell us about your visit'],
    'sub'           => ['Tocá las estrellas: 1 es muy malo, 5 es excelente.', 'Tap the stars: 1 is very bad, 5 is excellent.'],
    'muy_malo'      => ['Muy malo', 'Very bad'],
    'malo'          => ['Malo', 'Bad'],
    'regular'       => ['Regular', 'Okay'],
    'bueno'         => ['Bueno', 'Good'],
    'excelente'     => ['Excelente', 'Excellent'],
    'falta'         => ['Falta calificar esta', 'Please rate this one'],
    'recomienda'    => ['¿Nos recomendarías con alguien?', 'Would you recommend us?'],
    'si'            => ['Sí, claro', 'Yes, of course'],
    'no'            => ['Todavía no', 'Not yet'],
    'quien'         => ['¿Quién te atendió?', 'Who served you?'],
    'quien_sub'     => ['Opcional. Nos ayuda a reconocer al equipo.', 'Optional. It helps us recognize our team.'],
    'no_se'         => ['No me acuerdo', 'I don\'t remember'],
    'algo_mas'      => ['¿Algo más que quieras contarnos?', 'Anything else you\'d like to tell us?'],
    'opcional'      => ['Opcional', 'Optional'],
    'placeholder'   => ['Lo que más te gustó o lo que podríamos mejorar…', 'What you liked most or what we could improve…'],
    'sug'           => [['Todo excelente', 'Muy buena atención', 'Tardaron un poco', 'Volvería sin duda'], ['Everything was great', 'Great service', 'It took a while', 'I\'ll be back for sure']],
    'contacto'      => ['¿Querés que te contactemos? Dejanos tus datos.', 'Want us to contact you? Leave your details.'],
    'nombre'        => ['Tu nombre', 'Your name'],
    'whatsapp'      => ['Tu WhatsApp', 'Your WhatsApp'],
    'enviar'        => ['Enviar mi opinión', 'Send my feedback'],
    'enviando'      => ['Enviando…', 'Sending…'],
    'privado'       => ['Tus respuestas solo las ve el equipo de', 'Your answers are only seen by the team at'],
    'gracias_bien'  => ['¡Qué bueno que te gustó!', 'So glad you enjoyed it!'],
    'gracias_resena'=> ['¿Nos ayudás con una reseña en Google? Son 20 segundos y para nosotros vale muchísimo.', 'Could you leave us a Google review? It takes 20 seconds and means a lot to us.'],
    'btn_resena'    => ['Dejar reseña en Google', 'Leave a Google review'],
    'gracias_mal'   => ['Gracias por decírnoslo', 'Thanks for letting us know'],
    'gracias_mal_t' => ['Sentimos que la visita no estuvo a la altura. El encargado ya tiene tu comentario y lo va a revisar hoy mismo.', 'We\'re sorry your visit wasn\'t up to standard. The manager already has your comment and will review it today.'],
    'gracias'       => ['¡Gracias por tu tiempo!', 'Thank you for your time!'],
    'gracias_t'     => ['Tus respuestas ya llegaron. Las usamos para que la próxima visita salga mejor.', 'We received your answers. We use them to make your next visit even better.'],
    'premio'        => ['Tu regalo por responder', 'Your gift for answering'],
    'premio_cod'    => ['Mostrá este código en tu próxima visita', 'Show this code on your next visit'],
    'premio_vence'  => ['Válido hasta el', 'Valid until'],
    'premio_cap'    => ['Tomale captura para no perderlo 📸', 'Take a screenshot so you don\'t lose it 📸'],
    'ya_enviada'    => ['Ya recibimos tu encuesta de esta mesa hace un momento. ¡Gracias!', 'We already received your survey from this table. Thank you!'],
    'califica_todo' => ['Calificá todas las preguntas con estrellas antes de enviar.', 'Please rate every question before sending.'],
    'no_funciona'   => ['Este código ya no funciona', 'This code no longer works'],
    'no_funciona_t' => ['Puede que el QR haya cambiado. Pedile al personal el código actualizado.', 'The QR may have changed. Please ask the staff for the current code.'],
    'pregunta_mes'  => ['Pregunta especial', 'Special question'],
    'siguiente'     => ['Siguiente cliente en', 'Next guest in'],
];

function tx(string $clave, string $lang)
{
    $v = TEXTOS_ENCUESTA[$clave] ?? [$clave, $clave];
    return $v[$lang === 'en' ? 1 : 0];
}

/* ---------- Cupones ---------- */
/** Texto sugerido para la plantilla de Meta (variables: nombre, negocio, premio, código, vencimiento). */
const PLANTILLA_CUPON_META = "Hola {{1}}, te escribimos de {{2}}. Leímos tu comentario y lamentamos que tu visita no haya sido como esperabas. Queremos compensarte con: {{3}}.\n\nTu código es {{4}}, válido hasta el {{5}}.\n\n¡Esperamos verte pronto!";

const CUPON_DEFECTO = [
    'premio'   => ['titulo' => '10% de descuento en tu próxima visita', 'condiciones' => 'Válido una sola vez. No acumulable con otras promociones.', 'vigencia_dias' => 30, 'prefijo' => 'REGALO',
                   'mensaje' => null],
    'disculpa' => ['envio_auto' => 0, 'titulo' => 'Postre de cortesía en tu próxima visita', 'condiciones' => 'Válido una sola vez presentando este código.', 'vigencia_dias' => 30, 'prefijo' => 'VUELVE',
                   'mensaje' => "Hola {nombre}, te escribimos de {negocio}. Leímos tu comentario y lamentamos que tu visita no haya sido como esperabas. Queremos compensarte: {premio}.\n\nTu código es {codigo} (válido hasta el {vence}).\n\nGracias por decírnoslo, nos ayuda a mejorar. ¡Esperamos verte pronto!"],
];

function cupon_config(int $negocioId, string $tipo): array
{
    $c = q('SELECT * FROM cupones WHERE negocio_id = ? AND tipo = ?', [$negocioId, $tipo])->fetch();
    return $c ?: array_merge(['id' => 0, 'negocio_id' => $negocioId, 'tipo' => $tipo, 'activo' => 0], CUPON_DEFECTO[$tipo]);
}

function generar_codigo(string $prefijo): string
{
    $abc = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    do {
        $c = '';
        for ($i = 0; $i < 5; $i++) $c .= $abc[random_int(0, strlen($abc) - 1)];
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prefijo) ?: 'WH') . '-' . $c;
    } while (q('SELECT 1 FROM cupones_emitidos WHERE codigo = ?', [$codigo])->fetch());
    return $codigo;
}

/** Emite (o devuelve el ya emitido) un cupón para una encuesta. */
function emitir_cupon(int $negocioId, string $tipo, ?int $encuestaId): ?array
{
    $cfg = cupon_config($negocioId, $tipo);
    if (!(int)$cfg['activo'] && $tipo === 'premio') return null;
    if ($encuestaId) {
        $ya = q('SELECT * FROM cupones_emitidos WHERE encuesta_id = ? AND tipo = ?', [$encuestaId, $tipo])->fetch();
        if ($ya) return $ya;
    }
    $codigo = generar_codigo($cfg['prefijo']);
    $vence = (int)$cfg['vigencia_dias'] > 0 ? date('Y-m-d', strtotime('+' . (int)$cfg['vigencia_dias'] . ' days')) : null;
    q('INSERT INTO cupones_emitidos (negocio_id, tipo, encuesta_id, codigo, titulo, condiciones, vence_en) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$negocioId, $tipo, $encuestaId, $codigo, $cfg['titulo'], $cfg['condiciones'], $vence]);
    return q('SELECT * FROM cupones_emitidos WHERE codigo = ?', [$codigo])->fetch();
}

function mensaje_cupon(string $plantilla, array $datos): string
{
    return strtr($plantilla, [
        '{nombre}' => $datos['nombre'] ?? '', '{negocio}' => $datos['negocio'] ?? '', '{codigo}' => $datos['codigo'] ?? '',
        '{premio}' => mb_strtolower(mb_substr($datos['premio'] ?? '', 0, 1)) . mb_substr($datos['premio'] ?? '', 1),
        '{vence}' => $datos['vence'] ?? '',
    ]);
}

/* ---------- Personal ---------- */
function foto_personal(?string $foto): ?string
{
    return $foto ? url('uploads/personal/' . $foto) : null;
}

/** Guarda una imagen subida (logo o foto) y devuelve el nombre del archivo, o lanza error. */
function guardar_imagen(array $f, string $carpeta, string $prefijo, int $maxBytes = 1048576): string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('No se recibió la imagen.');
    if ($f['size'] > $maxBytes) throw new RuntimeException('La imagen pesa más de ' . round($maxBytes / 1048576) . ' MB.');
    $info = @getimagesize($f['tmp_name']);
    $ext = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$ext) throw new RuntimeException('La imagen tiene que ser PNG, JPG o WEBP.');
    $dir = APP_DIR . '/uploads/' . $carpeta . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $nombre = $prefijo . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . $nombre)) throw new RuntimeException('No se pudo guardar la imagen. Revisá permisos de la carpeta uploads.');
    return $nombre;
}

/* ---------- Turnos ---------- */
function turno_de_hora(int $h): string
{
    return $h < 12 ? 'manana' : ($h < 17 ? 'tarde' : 'noche');
}

/**
 * Emite el cupón de disculpa de una encuesta y lo manda por la API de WhatsApp (plantilla aprobada).
 * No abre WhatsApp Web. Devuelve [ok, detalle, cupon].
 */
function enviar_cupon_disculpa_api(int $encuestaId): array
{
    $e = q('SELECT e.*, n.nombre negocio, n.plan FROM encuestas e JOIN negocios n ON n.id = e.negocio_id WHERE e.id = ?', [$encuestaId])->fetch();
    if (!$e || !$e['telefono']) return [false, 'La encuesta no tiene WhatsApp del cliente.', null];
    if (!negocio_permite((int)$e['negocio_id'], $e['plan'], 'cupones')) return [false, 'Los cupones están incluidos en el plan Premium.', null];
    if (!whatsapp_configurado()) return [false, 'La API de WhatsApp todavía no está conectada en Whispers.', null];
    $cfg = cupon_config((int)$e['negocio_id'], 'disculpa');
    if (!(int)$cfg['activo']) return [false, 'El cupón de disculpa está apagado en Cupones.', null];

    $ya = q("SELECT 1 FROM alertas_log WHERE encuesta_id = ? AND canal = 'cupon' AND ok = 1", [$encuestaId])->fetch();
    $cup = emitir_cupon((int)$e['negocio_id'], 'disculpa', $encuestaId);
    if ($ya) return [true, 'Este cupón ya se había enviado antes.', $cup];

    $nombre = $e['nombre_cliente'] ? trim((string)strtok($e['nombre_cliente'], ' ')) : 'cliente';
    $vence = $cup['vence_en'] ? date('d/m/Y', strtotime($cup['vence_en'])) : 'sin fecha de vencimiento';
    $res = whatsapp_enviar_plantilla_nombre(ajuste('wa_plantilla_cupon', 'cupon_disculpa'), $e['telefono'],
        [$nombre, $e['negocio'], $cup['titulo'], $cup['codigo'], $vence]);
    registrar_alerta((int)$e['negocio_id'], $encuestaId, 'cupon', $e['telefono'], $res);
    if ($res[0]) {
        $nota = trim(($e['nota_interna'] ?? '') . "\n" . 'Cupón de disculpa ' . $cup['codigo'] . ' enviado por WhatsApp automático, ' . date('j/n g:i a'));
        q('UPDATE encuestas SET nota_interna = ? WHERE id = ?', [$nota, $encuestaId]);
    }
    return [$res[0], $res[1], $cup];
}
