<?php
/**
 * Resumen semanal para el dueño: por WhatsApp (plantilla de Meta) y por correo.
 */
require_once __DIR__ . '/notificaciones.php';
require_once __DIR__ . '/metricas.php';

function resumen_semanal_datos(array $n): array
{
    $hasta = date('Y-m-d 00:00:00');
    $desde = date('Y-m-d 00:00:00', strtotime('-7 days'));
    $antes = date('Y-m-d 00:00:00', strtotime('-14 days'));
    $um = (float)$n['umbral_alerta'];
    $m = metricas((int)$n['id'], $desde, $hasta, null, null, $um);
    $p = metricas((int)$n['id'], $antes, $desde, null, null, $um);

    $preg = array_filter($m['porPregunta'], fn($x) => (int)$x['n'] >= 3);
    usort($preg, fn($a, $b) => (float)$b['prom'] <=> (float)$a['prom']);
    $mejor = $preg[0] ?? null;
    $peor = $preg ? end($preg) : null;
    if ($mejor && $peor && $mejor['id'] === $peor['id']) $peor = null;

    $pend = (int)q('SELECT COUNT(*) FROM encuestas WHERE negocio_id = ? AND promedio <= ? AND revisada = 0', [$n['id'], $um])->fetchColumn();
    $estrella = q('SELECT pe.nombre, AVG(e.promedio) p, COUNT(*) c FROM encuestas e JOIN personal pe ON pe.id = e.personal_id
                    WHERE e.negocio_id = ? AND e.creado_en >= ? AND e.creado_en < ? GROUP BY pe.id, pe.nombre HAVING c >= 3 ORDER BY p DESC LIMIT 1',
        [$n['id'], $desde, $hasta])->fetch();
    $mejorCom = q("SELECT comentario FROM encuestas WHERE negocio_id = ? AND creado_en >= ? AND creado_en < ? AND promedio >= 4.5
                    AND comentario IS NOT NULL AND CHAR_LENGTH(comentario) BETWEEN 15 AND 160 ORDER BY promedio DESC, creado_en DESC LIMIT 1",
        [$n['id'], $desde, $hasta])->fetchColumn();

    $notaTxt = $m['prom'] === null ? 'Sin encuestas' : number_format($m['prom'], 1) . '/5';
    if ($m['prom'] !== null && $p['prom'] !== null) {
        $d = $m['prom'] - $p['prom'];
        $notaTxt .= abs($d) < 0.05 ? ' (igual que la semana pasada)' : sprintf(' (%s%.1f vs. semana pasada)', $d > 0 ? '+' : '−', abs($d));
    }
    $mejorTxt = $mejor ? $mejor['texto'] . ' (' . number_format((float)$mejor['prom'], 1) . ')' : 'Sin datos suficientes';
    $peorTxt = $peor ? $peor['texto'] . ' (' . number_format((float)$peor['prom'], 1) . ')' : 'Nada en rojo esta semana';

    $lineas = [
        "Resumen semanal de {$n['nombre']}",
        date('j/n', strtotime($desde)) . ' al ' . date('j/n', strtotime('-1 day')),
        '',
        "⭐ Nota de la semana: $notaTxt",
        "📝 Encuestas: {$m['total']}" . ($p['total'] ? " (semana pasada: {$p['total']})" : ''),
        "👍 Lo mejor: $mejorTxt",
        "🔧 A mejorar: $peorTxt",
    ];
    if ($m['rec'] !== null) $lineas[] = '🔁 Los recomendarían: ' . round($m['rec']) . '%';
    if ($estrella) $lineas[] = "🏆 Estrella del equipo: {$estrella['nombre']} (" . number_format((float)$estrella['p'], 1) . ')';
    $lineas[] = $pend ? "⚠️ Tenés $pend calificación" . ($pend > 1 ? 'es bajas' : ' baja') . ' sin revisar' : '✅ No tenés alertas pendientes';
    if ($mejorCom) $lineas[] = "\n💬 \"$mejorCom\"";

    return [
        'texto' => implode("\n", $lineas),
        'params' => [$n['nombre'], $notaTxt, (string)$m['total'], $mejorTxt, $peorTxt],
        'total' => $m['total'],
    ];
}

/** Envía el resumen. Devuelve lista de resultados [canal, destino, ok, detalle]. */
function enviar_resumen_semanal(array $n, bool $forzar = false): array
{
    if (!$forzar) {
        $reciente = q("SELECT 1 FROM alertas_log WHERE negocio_id = ? AND canal = 'resumen' AND ok = 1 AND creado_en >= DATE_SUB(NOW(), INTERVAL 6 DAY)", [$n['id']])->fetch();
        if ($reciente) return [];
    }
    $r = resumen_semanal_datos($n);
    $out = [];
    $link = rtrim(ajuste('url_publica', url_absoluta('')), '/') . '/login.php';
    foreach ((plan_permite($n, 'alertas_whatsapp') ? lista_contactos($n['alerta_whatsapp']) : []) as $num) {
        if (!whatsapp_configurado()) { $out[] = ['whatsapp', $num, false, 'WhatsApp no está configurado en Whispers.']; continue; }
        $res = whatsapp_enviar_plantilla_nombre(ajuste('wa_plantilla_resumen', 'resumen_semanal'), $num, $r['params']);
        registrar_alerta((int)$n['id'], null, 'resumen', 'WhatsApp ' . $num, $res);
        $out[] = ['whatsapp', $num, $res[0], $res[1]];
    }
    foreach (lista_contactos($n['alerta_email']) as $correo) {
        $res = correo_enviar($correo, 'Tu resumen semanal de Whispers — ' . $n['nombre'], $r['texto'] . "\n\nVer el detalle en tu panel: $link\n\n— Whispers");
        registrar_alerta((int)$n['id'], null, 'resumen', $correo, $res);
        $out[] = ['email', $correo, $res[0], $res[1]];
    }
    return $out;
}
