<?php
require dirname(__DIR__) . '/config.php';
[$u, $n] = requiere_negocio();

$sucVis = sucursales_visibles($u, $n);
$sid = filtro_sucursal($u, $sucVis);
$multi = count($sucVis) > 1;
$mesaId = (int)($_GET['mesa'] ?? 0);
$estado = in_array($_GET['estado'] ?? '', ['todas', 'alertas', 'pendientes', 'comentarios', 'contacto'], true) ? $_GET['estado'] : 'todas';
$desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde'] ?? '') ? $_GET['desde'] : date('Y-m-d', strtotime('-29 days'));
$hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta'] ?? '') ? $_GET['hasta'] : date('Y-m-d');
$buscar = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 60));
$pagina = max(1, (int)($_GET['pag'] ?? 1));
$porPag = 25;

$w = ['e.negocio_id = ?', 'e.creado_en >= ?', 'e.creado_en < DATE_ADD(?, INTERVAL 1 DAY)'];
$p = [$n['id'], $desde . ' 00:00:00', $hasta];
if ($sid)    { $w[] = 'e.sucursal_id = ?'; $p[] = $sid; }
if ($mesaId) { $w[] = 'e.mesa_id = ?'; $p[] = $mesaId; }
if ($estado === 'alertas')     { $w[] = 'e.promedio <= ?'; $p[] = $n['umbral_alerta']; }
if ($estado === 'pendientes')  { $w[] = 'e.promedio <= ? AND e.revisada = 0'; $p[] = $n['umbral_alerta']; }
if ($estado === 'comentarios') { $w[] = "e.comentario IS NOT NULL AND e.comentario <> ''"; }
if ($estado === 'contacto')    { $w[] = "e.telefono IS NOT NULL AND e.telefono <> ''"; }
if ($buscar !== '') { $w[] = '(e.comentario LIKE ? OR e.nombre_cliente LIKE ?)'; $p[] = "%$buscar%"; $p[] = "%$buscar%"; }
$sqlW = implode(' AND ', $w);

if (($_GET['exportar'] ?? '') === 'csv') {
    $preg = q('SELECT id, texto FROM preguntas WHERE negocio_id = ? ORDER BY orden, id', [$n['id']])->fetchAll();
    $filas = q("SELECT e.*, m.numero, m.nombre mesa, s.nombre sucursal FROM encuestas e JOIN mesas m ON m.id = e.mesa_id JOIN sucursales s ON s.id = e.sucursal_id WHERE $sqlW ORDER BY e.creado_en DESC", $p)->fetchAll();
    $resp = [];
    if ($filas) {
        $ids = implode(',', array_map('intval', array_column($filas, 'id')));
        foreach (db()->query("SELECT encuesta_id, pregunta_id, calificacion FROM respuestas WHERE encuesta_id IN ($ids)") as $r) {
            $resp[$r['encuesta_id']][$r['pregunta_id']] = $r['calificacion'];
        }
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="encuestas_' . $desde . '_a_' . $hasta . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_merge(['Fecha', 'Sucursal', 'Mesa', 'Promedio'], array_column($preg, 'texto'), ['Recomendaría', 'Comentario', 'Nombre', 'WhatsApp', 'Revisada', 'Seguimiento']));
    foreach ($filas as $f) {
        $l = [$f['creado_en'], $f['sucursal'], $f['mesa'] ?: 'Mesa ' . $f['numero'], $f['promedio']];
        foreach ($preg as $pr) $l[] = $resp[$f['id']][$pr['id']] ?? '';
        $l[] = $f['recomendaria'] === null ? '' : ((int)$f['recomendaria'] ? 'Sí' : 'No');
        array_push($l, $f['comentario'], $f['nombre_cliente'], $f['telefono'], (int)$f['revisada'] ? 'Sí' : 'No', $f['nota_interna']);
        fputcsv($out, $l);
    }
    fclose($out);
    exit;
}

$total = (int)q("SELECT COUNT(*) FROM encuestas e WHERE $sqlW", $p)->fetchColumn();
$paginas = max(1, (int)ceil($total / $porPag));
$pagina = min($pagina, $paginas);
$offset = ($pagina - 1) * $porPag;
$filas = q("SELECT e.id, e.promedio, e.recomendaria, e.comentario, e.nombre_cliente, e.telefono, e.revisada, e.creado_en, m.numero, m.nombre mesa, s.nombre sucursal
              FROM encuestas e JOIN mesas m ON m.id = e.mesa_id JOIN sucursales s ON s.id = e.sucursal_id
             WHERE $sqlW ORDER BY e.creado_en DESC LIMIT $porPag OFFSET $offset", $p)->fetchAll();
$mesas = q('SELECT m.id, m.numero, m.nombre, s.nombre suc FROM mesas m JOIN sucursales s ON s.id = m.sucursal_id WHERE m.negocio_id = ?' . ($sid ? ' AND m.sucursal_id = ' . (int)$sid : '') . ' ORDER BY s.id, m.numero', [$n['id']])->fetchAll();

function qs(array $c): string { return '?' . http_build_query(array_merge($_GET, $c)); }

$titulo = 'Encuestas';
$seccion = 'encuestas';
include dirname(__DIR__) . '/includes/admin_header.php';
?>
<form class="filtros filtros-wrap" method="get">
  <label class="select-inline buscar"><span class="sr">Buscar</span><input type="search" name="q" value="<?= e($buscar) ?>" placeholder="Buscar en comentarios o nombre"></label>
  <?php if ($multi && !(fijo_sucursal($u))): ?>
    <label class="select-inline">Sucursal
      <select name="s"><option value="">Todas</option><?php foreach ($sucVis as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $sid === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option><?php endforeach; ?></select>
    </label>
  <?php endif; ?>
  <label class="select-inline">Mesa
    <select name="mesa"><option value="0">Todas</option><?php foreach ($mesas as $mm): ?><option value="<?= (int)$mm['id'] ?>" <?= $mesaId === (int)$mm['id'] ? 'selected' : '' ?>><?= e(($multi && !$sid ? $mm['suc'] . ' · ' : '') . ($mm['nombre'] ?: 'Mesa ' . $mm['numero'])) ?></option><?php endforeach; ?></select>
  </label>
  <label class="select-inline">Mostrar
    <select name="estado">
      <?php foreach (['todas' => 'Todas', 'pendientes' => 'Bajas sin revisar', 'alertas' => 'Calificaciones bajas', 'comentarios' => 'Con comentario', 'contacto' => 'Dejaron WhatsApp'] as $k => $t): ?>
        <option value="<?= $k ?>" <?= $estado === $k ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="select-inline">Desde <input type="date" name="desde" value="<?= e($desde) ?>"></label>
  <label class="select-inline">Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>"></label>
  <button class="btn btn-sm" type="submit">Filtrar</button>
  <a class="btn btn-sm btn-borde" href="<?= e(qs(['exportar' => 'csv', 'pag' => null])) ?>">Descargar Excel (CSV)</a>
</form>

<p class="muted"><?= $total ?> encuesta<?= $total === 1 ? '' : 's' ?></p>

<?php if (!$filas): ?>
  <section class="vacio"><h2>No hay encuestas con esos filtros</h2><p>Probá ampliando las fechas o eligiendo “Todas”.</p></section>
<?php else: ?>
<div class="tabla-wrap">
  <table class="tabla">
    <thead><tr><th>Fecha</th><th><?= $multi ? 'Sucursal / mesa' : 'Mesa' ?></th><th>Nota</th><th>Comentario</th><th>Recomienda</th><th>Estado</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $f): $pf = (float)$f['promedio']; $url = url('admin/encuesta_ver.php?id=' . $f['id']); ?>
      <tr class="fila-link" data-href="<?= e($url) ?>">
        <td class="nowrap"><a href="<?= e($url) ?>"><?= e(fecha_corta($f['creado_en'])) ?></a></td>
        <td class="nowrap"><?= e(($multi ? $f['sucursal'] . ' · ' : '') . ($f['mesa'] ?: 'Mesa ' . $f['numero'])) ?></td>
        <td><span class="pill nota-<?= clase_nota($pf, $n['umbral_alerta']) ?>"><?= nota($pf) ?></span></td>
        <td class="col-com"><?= $f['comentario'] ? e(texto_corto($f['comentario'], 100)) : '<span class="muted">Sin comentario</span>' ?>
          <?php if ($f['nombre_cliente']): ?><small class="muted"> — <?= e($f['nombre_cliente']) ?></small><?php endif; ?>
          <?php if ($f['telefono']): ?><span class="tag-wa" title="Dejó su WhatsApp">WhatsApp</span><?php endif; ?></td>
        <td><?= $f['recomendaria'] === null ? '—' : ((int)$f['recomendaria'] ? 'Sí' : 'No') ?></td>
        <td><?php if ($pf <= $n['umbral_alerta']): ?><?= (int)$f['revisada'] ? '<span class="estado ok">Revisada</span>' : '<span class="estado mal">Sin revisar</span>' ?><?php else: ?><span class="muted">—</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if ($paginas > 1): ?>
  <nav class="paginacion" aria-label="Páginas">
    <?php if ($pagina > 1): ?><a href="<?= e(qs(['pag' => $pagina - 1])) ?>">Anterior</a><?php endif; ?>
    <span>Página <?= $pagina ?> de <?= $paginas ?></span>
    <?php if ($pagina < $paginas): ?><a href="<?= e(qs(['pag' => $pagina + 1])) ?>">Siguiente</a><?php endif; ?>
  </nav>
<?php endif; ?>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
