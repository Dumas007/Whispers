<?php
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/includes/metricas.php';
[$u, $n] = requiere_negocio();

$dias = in_array((int)($_GET['dias'] ?? 30), [7, 30, 90], true) ? (int)($_GET['dias'] ?? 30) : 30;
$sucVis = sucursales_visibles($u, $n);
$sid = filtro_sucursal($u, $sucVis);
$mid = (int)($_GET['mesa'] ?? 0) ?: null;

$hasta = date('Y-m-d H:i:s', strtotime('+1 minute'));
$desde = date('Y-m-d 00:00:00', strtotime('-' . ($dias - 1) . ' days'));
$desdePrev = date('Y-m-d 00:00:00', strtotime('-' . ($dias * 2 - 1) . ' days'));

$m = metricas((int)$n['id'], $desde, $hasta, $sid, $mid, $n['umbral_alerta']);
$prev = metricas((int)$n['id'], $desdePrev, $desde, $sid, $mid, $n['umbral_alerta']);
$pendientes = (int)q('SELECT COUNT(*) FROM encuestas WHERE negocio_id = ? AND promedio <= ? AND revisada = 0' . ($sid ? ' AND sucursal_id = ' . (int)$sid : ''), [$n['id'], $n['umbral_alerta']])->fetchColumn();

$mesasFiltro = q('SELECT m.id, m.numero, m.nombre, s.nombre suc FROM mesas m JOIN sucursales s ON s.id = m.sucursal_id WHERE m.negocio_id = ?' . ($sid ? ' AND m.sucursal_id = ' . (int)$sid : '') . ' ORDER BY s.id, m.numero', [$n['id']])->fetchAll();
$comentarios = comentarios_periodo($m);
$ultimaIa = true ? q('SELECT * FROM analisis_ia WHERE negocio_id = ? ORDER BY creado_en DESC LIMIT 1', [$n['id']])->fetch() : null;

/* Por día de la semana y turno */
$mapa = [];
$analitica = plan_permite($n, 'analitica');
if ($analitica) foreach (q("SELECT WEEKDAY(e.creado_en) dw, CASE WHEN HOUR(e.creado_en) < 12 THEN 'manana' WHEN HOUR(e.creado_en) < 17 THEN 'tarde' ELSE 'noche' END t,
                   COUNT(*) n, AVG(e.promedio) p FROM encuestas e WHERE {$m['where']} GROUP BY dw, t", $m['params']) as $r) {
    $mapa[(int)$r['dw']][$r['t']] = ['n' => (int)$r['n'], 'p' => (float)$r['p']];
}
$peorCelda = null;
foreach ($mapa as $dw => $ts) foreach ($ts as $t => $c) if ($c['n'] >= 3 && (!$peorCelda || $c['p'] < $peorCelda['p'])) $peorCelda = ['dw' => $dw, 't' => $t, 'p' => $c['p'], 'n' => $c['n']];
$diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$turnos = ['manana' => 'Mañana', 'tarde' => 'Tarde', 'noche' => 'Noche'];

/* Comparación con negocios del mismo rubro en Whispers (anónimo) */
$rubro = null;
if ($analitica && trim((string)$n['rubro']) !== '' && $m['prom'] !== null) {
    $otros = q("SELECT AVG(e.promedio) p, AVG(e.recomendaria) * 100 r FROM encuestas e JOIN negocios n2 ON n2.id = e.negocio_id
                 WHERE n2.id <> ? AND n2.activo = 1 AND LOWER(TRIM(n2.rubro)) = LOWER(TRIM(?)) AND e.creado_en >= ?
              GROUP BY n2.id HAVING COUNT(*) >= 10", [$n['id'], $n['rubro'], $desde])->fetchAll();
    if (count($otros) >= 3) {
        $rubro = [
            'n' => count($otros),
            'prom' => array_sum(array_column($otros, 'p')) / count($otros),
            'rec' => array_sum(array_map(fn($o) => (float)$o['r'], $otros)) / count($otros),
            'mejores' => count(array_filter($otros, fn($o) => (float)$o['p'] < $m['prom'])),
        ];
    }
}

$labels = $conteos = $promedios = [];
for ($i = $dias - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('j/n', strtotime($d));
    $conteos[] = isset($m['serie'][$d]) ? (int)$m['serie'][$d]['n'] : 0;
    $promedios[] = isset($m['serie'][$d]) ? round((float)$m['serie'][$d]['pr'], 2) : null;
}

function delta_html(?float $actual, ?float $anterior, int $dec = 0): string
{
    if ($actual === null || $anterior === null) return '<span class="delta igual">Sin datos del período anterior</span>';
    $v = $actual - $anterior;
    if (abs($v) < 0.05) return '<span class="delta igual">Igual que el período anterior</span>';
    return '<span class="delta ' . ($v > 0 ? 'sube' : 'baja') . '">' . ($v > 0 ? '+' : '−') . number_format(abs($v), $dec) . ' vs. período anterior</span>';
}

$titulo = 'Resumen';
$seccion = 'dashboard';
$scripts_extra = '<script src="' . e(url('assets/vendor/chart.umd.min.js')) . '"></script><script>window.WH_GRAFICA = '
    . json_encode(['labels' => $labels, 'conteos' => $conteos, 'promedios' => $promedios]) . '; whGrafica();</script>';
include dirname(__DIR__) . '/includes/admin_header.php';
$multi = count($sucVis) > 1;
?>

<form class="filtros" method="get">
  <input type="hidden" name="dias" value="<?= $dias ?>">
  <div class="segmento" role="group" aria-label="Período">
    <?php foreach ([7 => '7 días', 30 => '30 días', 90 => '90 días'] as $val => $t): ?>
      <button name="dias" value="<?= $val ?>" class="<?= $dias === $val ? 'on' : '' ?>"><?= $t ?></button>
    <?php endforeach; ?>
  </div>
  <?php if ($multi && !(fijo_sucursal($u))): ?>
    <label class="select-inline">Sucursal
      <select name="s" onchange="this.form.mesa.value='';this.form.submit()">
        <option value="">Todas</option>
        <?php foreach ($sucVis as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $sid === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option><?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>
  <label class="select-inline">Mesa
    <select name="mesa" onchange="this.form.submit()">
      <option value="">Todas</option>
      <?php foreach ($mesasFiltro as $mf): ?>
        <option value="<?= (int)$mf['id'] ?>" <?= $mid === (int)$mf['id'] ? 'selected' : '' ?>><?= e(($multi && !$sid ? $mf['suc'] . ' · ' : '') . ($mf['nombre'] ?: 'Mesa ' . $mf['numero'])) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
</form>

<?php if ($m['total'] === 0): ?>
  <section class="vacio">
    <h2>Todavía no hay encuestas en este período</h2>
    <p>Imprimí los QR de tus mesas y colocalos donde el cliente los vea al pedir la cuenta.</p>
    <a class="btn" href="<?= e(url('admin/sucursales.php')) ?>">Ver los QR</a>
  </section>
<?php else: ?>

<section class="kpis">
  <div class="kpi kpi-principal nota-<?= clase_nota($m['prom'], $n['umbral_alerta']) ?>">
    <span class="kpi-lbl">Calificación general</span>
    <span class="kpi-num"><?= nota($m['prom']) ?><small>/5</small></span>
    <span class="kpi-sub"><?= texto_nota($m['prom'], $n['umbral_alerta']) ?></span>
    <?= delta_html($m['prom'], $prev['prom'], 1) ?>
  </div>
  <div class="kpi">
    <span class="kpi-lbl">Encuestas recibidas</span>
    <span class="kpi-num"><?= $m['total'] ?></span>
    <?= delta_html((float)$m['total'], $prev['total'] ? (float)$prev['total'] : null) ?>
  </div>
  <div class="kpi">
    <span class="kpi-lbl">Nos recomendarían</span>
    <span class="kpi-num"><?= $m['rec'] === null ? '—' : round($m['rec']) . '<small>%</small>' ?></span>
    <span class="kpi-sub">de quienes respondieron</span>
  </div>
  <a class="kpi kpi-alerta <?= $pendientes ? 'hay' : '' ?>" href="<?= e(url('admin/encuestas.php?estado=pendientes')) ?>">
    <span class="kpi-lbl">Alertas sin revisar</span>
    <span class="kpi-num"><?= $pendientes ?></span>
    <span class="kpi-sub"><?= $m['alertas'] ?> calificaciones bajas en el período</span>
  </a>
</section>

<?php if ($ultimaIa): $ia = json_decode($ultimaIa['resultado'], true); ?>
  <a class="ia-banda" href="<?= e(url('admin/ia.php?ver=' . $ultimaIa['id'])) ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l1.8 4.7L18.5 9.5 13.8 11.3 12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/></svg>
    <span><strong>Lo que dice la IA</strong><?= e(texto_corto((string)($ia['resumen'] ?? ''), 220)) ?></span>
    <small>Análisis del <?= e(date('j/n', strtotime($ultimaIa['desde']))) ?> al <?= e(date('j/n', strtotime($ultimaIa['hasta']))) ?></small>
  </a>
<?php endif; ?>

<?php if ($rubro): $dif = $m['prom'] - $rubro['prom']; ?>
  <article class="panel comparacion">
    <div class="comp-txt">
      <span class="comp-eti">Comparado con otros <?= e(mb_strtolower($n['rubro'])) ?>s en Whispers</span>
      <strong><?= abs($dif) < .05 ? 'Estás en el promedio' : ($dif > 0 ? 'Estás ' . number_format($dif, 1) . ' puntos arriba del promedio' : 'Estás ' . number_format(abs($dif), 1) . ' puntos abajo del promedio') ?></strong>
      <small class="muted">Superás a <?= $rubro['mejores'] ?> de <?= $rubro['n'] ?> negocios similares. Los datos de los demás son anónimos.</small>
    </div>
    <div class="comp-barras">
      <div><span>Tu nota</span><b class="nota-<?= clase_nota($m['prom'], $n['umbral_alerta']) ?>"><?= nota($m['prom']) ?></b></div>
      <div><span>Promedio del rubro</span><b><?= nota($rubro['prom']) ?></b></div>
      <div><span>Te recomiendan</span><b><?= $m['rec'] === null ? '—' : round($m['rec']) . '%' ?></b><small>vs <?= round($rubro['rec']) ?>%</small></div>
    </div>
  </article>
<?php endif; ?>

<section class="grid-2">
  <article class="panel panel-ancho">
    <header class="panel-head"><h2>Encuestas por día</h2><span class="panel-nota">Barras: cantidad · Línea: promedio</span></header>
    <div class="grafica"><canvas id="grafica-dias" aria-label="Encuestas y promedio por día"></canvas></div>
  </article>

  <article class="panel">
    <header class="panel-head"><h2>¿Dónde estamos fallando?</h2></header>
    <ul class="barras">
      <?php foreach ($m['porPregunta'] as $p): $val = (float)$p['prom']; ?>
        <li>
          <span class="barras-txt"><?= e($p['texto']) ?></span>
          <span class="barras-pista"><span class="barras-relleno nota-<?= clase_nota($val, $n['umbral_alerta']) ?>" style="width: <?= round($val / 5 * 100) ?>%"></span></span>
          <b><?= nota($val) ?></b>
        </li>
      <?php endforeach; ?>
    </ul>
  </article>

  <article class="panel">
    <?php if ($multi && !$sid): ?>
      <header class="panel-head"><h2>Sucursal contra sucursal</h2></header>
      <div class="ranking">
        <?php foreach ($m['porSucursal'] as $i => $s): $ps = (float)$s['prom']; ?>
          <a class="ranking-fila" href="?dias=<?= $dias ?>&s=<?= (int)$s['id'] ?>">
            <span class="ranking-pos"><?= $i + 1 ?></span>
            <span class="ranking-nom"><?= e($s['nombre']) ?><small><?= (int)$s['total'] ?> encuestas · <?= (int)$s['alertas'] ?> bajas</small></span>
            <span class="ranking-nota nota-<?= clase_nota($ps, $n['umbral_alerta']) ?>"><?= nota($ps) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <header class="panel-head"><h2>Mesa contra mesa</h2></header>
      <div class="mesas-comp">
        <?php foreach ($m['porMesa'] as $mm): $pm = (float)$mm['prom']; ?>
          <a class="mesa-comp" href="?dias=<?= $dias ?><?= $sid ? '&s=' . $sid : '' ?>&mesa=<?= (int)$mm['id'] ?>">
            <span class="mesa-comp-num"><?= (int)$mm['numero'] ?></span>
            <span class="mesa-comp-nom"><?= e($mm['nombre'] ?: 'Mesa ' . $mm['numero']) ?></span>
            <span class="mesa-comp-nota nota-<?= clase_nota($pm, $n['umbral_alerta']) ?>"><?= nota($pm) ?></span>
            <span class="mesa-comp-meta"><?= (int)$mm['total'] ?> encuestas · <?= (int)$mm['alertas'] ?> bajas</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>

  <?php if ($analitica): ?>
  <article class="panel panel-ancho">
    <header class="panel-head"><h2>¿Qué día y turno falla?</h2><span class="panel-nota">Mañana antes de 12 · Tarde 12 a 5 · Noche después de 5</span></header>
    <?php if ($peorCelda && $peorCelda['p'] < 4): ?><p class="mapa-alerta">⚠️ Tu punto más débil: <strong><?= $diasSemana[$peorCelda['dw']] ?> en la <?= mb_strtolower($turnos[$peorCelda['t']]) ?></strong>, con <?= nota($peorCelda['p']) ?> de promedio (<?= $peorCelda['n'] ?> encuestas).</p><?php endif; ?>
    <div class="tabla-wrap mapa-wrap">
      <table class="mapa">
        <thead><tr><th></th><?php foreach ($diasSemana as $d): ?><th><?= mb_substr($d, 0, 3) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
          <?php foreach ($turnos as $tk => $tn): ?>
            <tr><th><?= $tn ?></th>
              <?php for ($dw = 0; $dw < 7; $dw++): $c = $mapa[$dw][$tk] ?? null; ?>
                <td class="<?= $c ? 'nota-' . clase_nota($c['p'], $n['umbral_alerta']) : 'vacia' ?>" title="<?= $c ? $c['n'] . ' encuestas' : 'Sin encuestas' ?>">
                  <?php if ($c): ?><b><?= nota($c['p']) ?></b><small><?= $c['n'] ?></small><?php else: ?>·<?php endif; ?>
                </td>
              <?php endfor; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </article>
  <?php else: ?>
  <article class="panel panel-ancho teaser">
    <header class="panel-head"><h2>¿Qué día y turno falla?</h2><i class="candado">Premium</i></header>
    <p class="muted">Mirá en qué día y turno baja tu calificación (por ejemplo: “jueves en la noche: 3.2”) y comparate con otros negocios como el tuyo. Incluido en el plan Premium.</p>
  </article>
  <?php endif; ?>

  <article class="panel panel-ancho">
    <header class="panel-head"><h2>Lo que dicen los clientes</h2><a href="<?= e(url('admin/encuestas.php?estado=comentarios')) ?>">Ver todos</a></header>
    <?php if (!$comentarios): ?><p class="muted">Nadie ha dejado comentarios en este período.</p><?php endif; ?>
    <ul class="comentarios">
      <?php foreach ($comentarios as $c): $pc = (float)$c['promedio']; ?>
        <li><a href="<?= e(url('admin/encuesta_ver.php?id=' . $c['id'])) ?>">
          <span class="pill nota-<?= clase_nota($pc, $n['umbral_alerta']) ?>"><?= nota($pc) ?></span>
          <q><?= e($c['comentario']) ?></q>
          <small><?= e(($multi ? $c['sucursal'] . ' · ' : '') . ($c['mesa'] ?: 'Mesa ' . $c['numero'])) ?> · <?= e(fecha_corta($c['creado_en'])) ?><?= ($pc <= $n['umbral_alerta'] && !(int)$c['revisada']) ? ' · <b class="txt-mal">Sin revisar</b>' : '' ?></small>
        </a></li>
      <?php endforeach; ?>
    </ul>
  </article>
</section>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
