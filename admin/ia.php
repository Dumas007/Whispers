<?php
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/includes/metricas.php';
require dirname(__DIR__) . '/includes/ia.php';
[$u, $n] = requiere_negocio();
$permitido = true;
$ilimitada = plan_permite($n, 'ia_ilimitada');
$usadosMes = (int)q("SELECT COUNT(*) FROM analisis_ia WHERE negocio_id = ? AND creado_en >= ?", [$n['id'], date('Y-m-01 00:00:00')])->fetchColumn();
$disponibleMes = $ilimitada || $usadosMes < 1;
$sucVis = sucursales_visibles($u, $n);
$multi = count($sucVis) > 1;

/** Convierte la opción de período en [desde, hasta, etiqueta]. */
function periodo_ia(string $op): array
{
    if (preg_match('/^mes:(\d{4}-\d{2})$/', $op, $mm)) {
        $d = $mm[1] . '-01';
        return [$d, date('Y-m-t', strtotime($d)), nombre_mes($mm[1])];
    }
    switch ($op) {
        case 'mes_pasado': $d = date('Y-m-01', strtotime('first day of last month')); return [$d, date('Y-m-t', strtotime($d)), nombre_mes(substr($d, 0, 7))];
        case '90': return [date('Y-m-d', strtotime('-89 days')), date('Y-m-d'), 'Últimos 90 días'];
        case '30': return [date('Y-m-d', strtotime('-29 days')), date('Y-m-d'), 'Últimos 30 días'];
        default: return [date('Y-m-01'), date('Y-m-d'), nombre_mes(date('Y-m')) . ' (hasta hoy)'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $permitido) {
    csrf_verificar();
    @set_time_limit(120);
    [$desde, $hasta, $etiqueta] = periodo_ia((string)($_POST['periodo'] ?? 'mes_actual'));
    $sid = (int)($_POST['s'] ?? 0);
    $sid = in_array($sid, array_map('intval', array_column($sucVis, 'id')), true) ? $sid : null;
    if (fijo_sucursal($u)) $sid = (int)$u['sucursal_id'];
    $volver = ($_POST['volver'] ?? '') === 'reporte' ? 'admin/reporte.php?mes=' . substr($desde, 0, 7) . ($sid ? '&s=' . $sid : '') : null;

    if (!$disponibleMes) {
        flash('error', 'Tu plan Básico incluye 1 análisis con IA al mes y ya lo usaste. El próximo se habilita el 1 de ' . mb_strtolower(nombre_mes(date('Y-m', strtotime('first day of next month')))) . '.');
        redirigir($volver ?? 'admin/ia.php');
    }
    $hoy = (int)q('SELECT COUNT(*) FROM analisis_ia WHERE negocio_id = ? AND creado_en >= CURDATE()', [$n['id']])->fetchColumn();
    if ($hoy >= 10) {
        flash('error', 'Ya hiciste 10 análisis hoy. Mañana podés generar más.');
        redirigir($volver ?? 'admin/ia.php');
    }
    try {
        $m = metricas((int)$n['id'], $desde . ' 00:00:00', date('Y-m-d', strtotime($hasta . ' +1 day')) . ' 00:00:00', $sid, null, $n['umbral_alerta']);
        $coms = comentarios_periodo($m, 'e.creado_en DESC', 300);
        $sucNombre = $sid ? (string)q('SELECT nombre FROM sucursales WHERE id = ?', [$sid])->fetchColumn() : '';
        $res = ia_analizar_comentarios($n['nombre'] . ($sucNombre ? ' — ' . $sucNombre : ''), (string)$n['rubro'], $etiqueta, $coms, $m['porPregunta']);
        $modelo = $res['_modelo'];
        unset($res['_modelo']);
        $res['_etiqueta'] = $etiqueta;
        q('INSERT INTO analisis_ia (negocio_id, sucursal_id, desde, hasta, total_comentarios, resultado, modelo, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$n['id'], $sid, $desde, $hasta, count($coms), json_encode($res, JSON_UNESCAPED_UNICODE), $modelo, $u['id']]);
        $nuevo = (int)db()->lastInsertId();
        flash('ok', 'Análisis listo: se leyeron ' . count($coms) . ' comentarios.');
        redirigir($volver ?? 'admin/ia.php?ver=' . $nuevo);
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
        redirigir($volver ?? 'admin/ia.php');
    }
}

$historial = q('SELECT a.id, a.desde, a.hasta, a.total_comentarios, a.creado_en, a.resultado, s.nombre sucursal FROM analisis_ia a LEFT JOIN sucursales s ON s.id = a.sucursal_id WHERE a.negocio_id = ?'
    . (fijo_sucursal($u) ? ' AND a.sucursal_id = ' . (int)$u['sucursal_id'] : '') . ' ORDER BY a.creado_en DESC LIMIT 15', [$n['id']])->fetchAll();
$verId = (int)($_GET['ver'] ?? 0);
$actual = null;
foreach ($historial as $h) if ($verId && (int)$h['id'] === $verId) $actual = $h;
if (!$actual && $historial) $actual = $historial[0];

$titulo = 'Análisis con IA';
$seccion = 'ia';
include dirname(__DIR__) . '/includes/admin_header.php';
?>
<?php if (!$permitido): ?>
  <?php $upsell_plan = 'profesional'; $upsell_titulo = 'Dejá que la IA lea todos los comentarios por vos'; $upsell_texto = 'Te decimos de qué se quejan más, qué valoran y qué hacer esta semana para mejorar. Incluido desde el plan Profesional.'; include dirname(__DIR__) . '/includes/upsell.php'; ?>
<?php else: ?>
  <?php if (!ia_configurada()): ?><div class="alerta alerta-error">El análisis con IA todavía no está activado.<?php if ($u['rol'] === 'super'): ?> Pegá tu llave de OpenAI o de Anthropic en <a href="<?= e(url('super/ajustes.php')) ?>">Ajustes de Whispers</a>.<?php else: ?> Pedile a Whispers que lo active.<?php endif; ?></div><?php endif; ?>
  <?php if (!$ilimitada): ?>
    <div class="limite-plan">
      <span><?= $disponibleMes ? '🧠 Tu plan incluye <strong>1 análisis con IA al mes</strong>. Todavía tenés el de este mes.' : '🧠 Ya usaste tu análisis de este mes. El próximo se habilita el 1 de ' . e(mb_strtolower(nombre_mes(date('Y-m', strtotime('first day of next month'))))) . '.' ?></span>
      <?php if ($v = ajuste('whatsapp_ventas')): ?><a href="<?= e(whatsapp_link($v, 'Hola, soy de ' . $n['nombre'] . ' y quiero análisis con IA sin límite (plan Profesional).')) ?>" target="_blank" rel="noopener">Sin límite con Profesional →</a><?php endif; ?>
    </div>
  <?php endif; ?>
  <form method="post" class="panel ia-form" data-cargando="Leyendo comentarios… esto toma unos segundos">
    <?= csrf_campo() ?>
    <div>
      <h2>Nuevo análisis</h2>
      <p class="muted">La IA lee los comentarios del período y te dice qué se repite y qué conviene hacer.</p>
    </div>
    <div class="ia-form-campos">
      <label class="select-inline">Período
        <select name="periodo">
          <option value="mes_actual">Este mes</option>
          <option value="mes_pasado">Mes pasado</option>
          <option value="30">Últimos 30 días</option>
          <option value="90">Últimos 90 días</option>
        </select>
      </label>
      <?php if ($multi && !(fijo_sucursal($u))): ?>
        <label class="select-inline">Sucursal
          <select name="s"><option value="">Todas</option><?php foreach ($sucVis as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['nombre']) ?></option><?php endforeach; ?></select>
        </label>
      <?php endif; ?>
      <button class="btn" type="submit" <?= $disponibleMes ? '' : 'disabled' ?>>Analizar comentarios</button>
    </div>
  </form>

  <?php if (!$historial): ?>
    <section class="vacio"><h2>Todavía no hay análisis</h2><p>Elegí un período y tocá “Analizar comentarios”. Funciona mejor con al menos 10 comentarios.</p></section>
  <?php else: ?>
    <section class="ia-layout">
      <article class="panel">
        <?php $r = json_decode($actual['resultado'], true) ?: []; ?>
        <header class="panel-head">
          <h2><?= e($r['_etiqueta'] ?? (date('j/n', strtotime($actual['desde'])) . ' al ' . date('j/n', strtotime($actual['hasta'])))) ?><?= $actual['sucursal'] ? ' · ' . e($actual['sucursal']) : '' ?></h2>
          <span class="panel-nota"><?= (int)$actual['total_comentarios'] ?> comentarios leídos</span>
        </header>
        <?= ia_html($r) ?>
      </article>
      <aside class="panel ia-historial">
        <header class="panel-head"><h2>Anteriores</h2></header>
        <ul>
          <?php foreach ($historial as $h): $hr = json_decode($h['resultado'], true) ?: []; ?>
            <li><a href="?ver=<?= (int)$h['id'] ?>" class="<?= (int)$h['id'] === (int)$actual['id'] ? 'on' : '' ?>">
              <strong><?= e($hr['_etiqueta'] ?? $h['desde']) ?></strong>
              <small><?= e($h['sucursal'] ?: 'Todas las sucursales') ?> · <?= e(fecha_corta($h['creado_en'])) ?></small>
            </a></li>
          <?php endforeach; ?>
        </ul>
      </aside>
    </section>
  <?php endif; ?>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
