<?php
/**
 * Envía el resumen semanal a todos los negocios activos que lo tengan encendido.
 *
 * Programalo una vez por semana (por ejemplo, lunes 8:00 a. m.):
 *   - cPanel → Cron Jobs:  php /home/TU_USUARIO/public_html/cron/resumen_semanal.php
 *   - O por URL (cron-job.org):  https://tudominio/cron/resumen_semanal.php?clave=LA_CLAVE
 *     La clave aparece en Whispers → Ajustes de Whispers.
 * Si se ejecuta dos veces en la misma semana, no repite: cada negocio recibe máximo uno cada 6 días.
 */
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/includes/extras.php';
require dirname(__DIR__) . '/includes/resumen.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    $clave = substr(hash_hmac('sha256', 'cron-resumen', app_secret()), 0, 24);
    if (!hash_equals($clave, (string)($_GET['clave'] ?? ''))) {
        http_response_code(403);
        exit("Clave incorrecta.\n");
    }
}
@set_time_limit(300);
$negocios = array_values(array_filter(q('SELECT * FROM negocios WHERE activo = 1 AND resumen_semanal = 1')->fetchAll(), fn($x) => plan_permite($x, 'resumen_semanal')));
$enviados = 0;
foreach ($negocios as $n) {
    $n['umbral_alerta'] = (float)$n['umbral_alerta'];
    try {
        $res = enviar_resumen_semanal($n);
        foreach ($res as [$canal, $dest, $ok, $det]) {
            echo $n['nombre'] . " · $canal $dest · " . ($ok ? 'OK' : "FALLÓ: $det") . "\n";
            if ($ok) $enviados++;
        }
    } catch (Throwable $t) {
        echo $n['nombre'] . ' · ERROR: ' . $t->getMessage() . "\n";
    }
}
echo "Listo. Envíos correctos: $enviados\n";
