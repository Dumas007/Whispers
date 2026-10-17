<?php
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/includes/metricas.php';
require dirname(__DIR__) . '/includes/ia.php';
[$u, $n] = requiere_negocio();

$sucVis = sucursales_visibles($u, $n);
$sid = filtro_sucursal($u, $sucVis);
$multi = count($sucVis) > 1;
$mes = preg_match('/^\d{4}-\d{2}$/', $_GET['mes'] ?? '') ? $_GET['mes'] : date('Y-m');
$ini = $mes . '-01';
$fin = date('Y-m-d', strtotime($ini . ' +1 month'));
$iniPrev = date('Y-m-d', strtotime($ini . ' -1 month'));

$m = metricas((int)$n['id'], $ini . ' 00:00:00', $fin . ' 00:00:00', $sid, null, $n['umbral_alerta']);
$p = metricas((int)$n['id'], $iniPrev . ' 00:00:00', $ini . ' 00:00:00', $sid, null, $n['umbral_alerta']);
$prevPreg = [];
foreach ($p['porPregunta'] as $x) $prevPreg[$x['id']] = (float)$x['prom'];
$mejores = comentarios_periodo($m, 'e.promedio DESC, e.creado_en DESC', 3);
$peores = comentarios_periodo($m, 'e.promedio ASC, e.creado_en DESC', 3);
$peores = array_values(array_filter($peores, fn($c) => (float)$c['promedio'] < 4));
$sucNombre = $sid ? (string)q('SELECT nombre FROM sucursales WHERE id = ?', [$sid])->fetchColumn() : ($multi ? 'Todas las sucursales' : ($sucVis[0]['nombre'] ?? ''));

$ia = null;
if (true) {
    $ia = q('SELECT * FROM analisis_ia WHERE negocio_id = ? AND desde = ? AND hasta = ? AND ' . ($sid ? 'sucursal_id = ' . (int)$sid : 'sucursal_id IS NULL') . ' ORDER BY id DESC LIMIT 1',
        [$n['id'], $ini, date('Y-m-t', strtotime($ini))])->fetch();
}

$meses = [];
for ($i = 0; $i < 12; $i++) $meses[] = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));

function cambio(?float $a, ?float $b, int $dec = 1, string $suf = ''): string
{
    if ($a === null || $b === null) return '<span class="delta igual">Sin mes anterior</span>';
    $d = $a - $b;
    if (abs($d) < 0.05) return '<span class="delta igual">Igual que el mes anterior</span>';
    return '<span class="delta ' . ($d > 0 ? 'sube' : 'baja') . '">' . ($d > 0 ? '+' : '−') . number_format(abs($d), $dec) . $suf . ' vs. mes anterior</span>';
}

$titulo = 'Reporte mensual';
$seccion = 'reporte';
$accion_barra = '<button class="btn btn-sm" type="button" onclick="window.print()">Descargar PDF</button>';
include dirname(__DIR__) . '/includes/admin_header.php';
?>
<form class="filtros no-print" method="get">
  <label class="select-inline">Mes
    <select name="mes" onchange="this.form.submit()"><?php foreach ($meses as $mm): ?><option value="<?= $mm ?>" <?= $mm === $mes ? 'selected' : '' ?>><?= e(nombre_mes($mm)) ?></option><?php endforeach; ?></select>
  </label>
  <?php if ($multi && !(fijo_sucursal($u))): ?>
    <label class="select-inline">Sucursal
      <select name="s" onchange="this.form.submit()"><option value="">Todas</option><?php foreach ($sucVis as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $sid === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option><?php endforeach; ?></select>
    </label>
  <?php endif; ?>
  <span class="muted">Para guardarlo, tocá “Descargar PDF” y elegí “Guardar como PDF”.</span>
</form>

<article class="hoja">
  <header class="hoja-head">
    <div class="hoja-marca">
      <?php if ($n['logo']): ?><img src="<?= e(url('uploads/logos/' . $n['logo'])) ?>" alt=""><?php endif; ?>
      <div><strong><?= e($n['nombre']) ?></strong><span><?= e($sucNombre) ?></span></div>
    </div>
    <div class="hoja-titulo">
      <h2>Reporte de <?= e(nombre_mes($mes)) ?></h2>
      <span>Generado el <?= e(fecha_larga(date('Y-m-d'))) ?></span>
    </div>
  </header>

  <?php if ($m['total'] === 0): ?>
    <p class="hoja-vacio">No hubo encuestas en <?= e(nombre_mes($mes)) ?>.</p>
  <?php else: ?>
  <section class="hoja-kpis">
    <div class="hk-principal nota-<?= clase_nota($m['prom'], $n['umbral_alerta']) ?>"><span>Calificación general</span><b><?= nota($m['prom']) ?><small>/5</small></b><?= cambio($m['prom'], $p['prom']) ?></div>
    <div><span>Encuestas</span><b><?= $m['total'] ?></b><?= cambio((float)$m['total'], $p['total'] ? (float)$p['total'] : null, 0) ?></div>
    <div><span>Nos recomendarían</span><b><?= $m['rec'] === null ? '—' : round($m['rec']) . '<small>%</small>' ?></b><?= cambio($m['rec'], $p['rec'], 0, ' pts') ?></div>
    <div><span>Calificaciones bajas</span><b><?= $m['alertas'] ?></b><span class="delta igual"><?= $m['atendidas'] ?> atendidas por el equipo</span></div>
  </section>

  <section class="hoja-sec">
    <h3>Cómo calificaron cada aspecto</h3>
    <ul class="barras">
      <?php foreach ($m['porPregunta'] as $q): $v = (float)$q['prom']; $ant = $prevPreg[$q['id']] ?? null; ?>
        <li>
          <span class="barras-txt"><?= e($q['texto']) ?> <?php if ($ant !== null && abs($v - $ant) >= .05): ?><em class="<?= $v > $ant ? 'sube' : 'baja' ?>"><?= ($v > $ant ? '+' : '−') . number_format(abs($v - $ant), 1) ?></em><?php endif; ?></span>
          <span class="barras-pista"><span class="barras-relleno nota-<?= clase_nota($v, $n['umbral_alerta']) ?>" style="width: <?= round($v / 5 * 100) ?>%"></span></span>
          <b><?= nota($v) ?></b>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="hoja-sec">
    <h3><?= $multi && !$sid ? 'Por sucursal' : 'Por mesa' ?></h3>
    <table class="tabla tabla-hoja">
      <thead><tr><th><?= $multi && !$sid ? 'Sucursal' : 'Mesa' ?></th><th>Encuestas</th><th>Promedio</th><th>Bajas</th></tr></thead>
      <tbody>
        <?php foreach (($multi && !$sid) ? $m['porSucursal'] : $m['porMesa'] as $r): ?>
          <tr><td><?= e($r['nombre'] ?? '') ?: 'Mesa ' . (int)($r['numero'] ?? 0) ?></td><td><?= (int)$r['total'] ?></td><td><span class="pill nota-<?= clase_nota((float)$r['prom'], $n['umbral_alerta']) ?>"><?= nota((float)$r['prom']) ?></span></td><td><?= (int)$r['alertas'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="hoja-sec hoja-coments">
    <div>
      <h3>Lo mejor del mes</h3>
      <?php foreach ($mejores as $c): ?><blockquote><q><?= e($c['comentario']) ?></q><small><?= nota((float)$c['promedio']) ?> · <?= e($c['mesa'] ?: 'Mesa ' . $c['numero']) ?></small></blockquote><?php endforeach; ?>
      <?php if (!$mejores): ?><p class="muted">Sin comentarios este mes.</p><?php endif; ?>
    </div>
    <div>
      <h3>Lo que hay que mejorar</h3>
      <?php foreach ($peores as $c): ?><blockquote class="mal"><q><?= e($c['comentario']) ?></q><small><?= nota((float)$c['promedio']) ?> · <?= e($c['mesa'] ?: 'Mesa ' . $c['numero']) ?></small></blockquote><?php endforeach; ?>
      <?php if (!$peores): ?><p class="muted">Ningún comentario negativo este mes.</p><?php endif; ?>
    </div>
  </section>

  <?php if ($ia): ?>
    <section class="hoja-sec hoja-ia">
      <h3>Análisis con IA</h3>
      <?= ia_html(json_decode($ia['resultado'], true) ?: []) ?>
    </section>
  <?php elseif ($m['comentarios'] >= 3 && (plan_permite($n, 'ia_ilimitada') || !q('SELECT 1 FROM analisis_ia WHERE negocio_id = ? AND creado_en >= ?', [$n['id'], date('Y-m-01')])->fetch())): ?>
    <form method="post" action="<?= e(url('admin/ia.php')) ?>" class="hoja-sec hoja-ia-cta no-print" data-cargando="Leyendo comentarios… esto toma unos segundos">
      <?= csrf_campo() ?><input type="hidden" name="periodo" value="mes:<?= e($mes) ?>"><input type="hidden" name="volver" value="reporte"><?php if ($sid): ?><input type="hidden" name="s" value="<?= $sid ?>"><?php endif; ?>
      <p>Agregá al reporte lo que la IA encuentra en los <?= $m['comentarios'] ?> comentarios de este mes.</p>
      <button class="btn btn-sm" type="submit">Generar análisis con IA</button>
    </form>
  <?php endif; ?>
  <?php endif; ?>

  <footer class="hoja-pie"><?php include dirname(__DIR__) . '/includes/logo.php'; ?> Reporte generado por Whispers</footer>
</article>
<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
