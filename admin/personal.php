<?php
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/includes/extras.php';
[$u, $n] = requiere_negocio(['admin', 'gerente']);
$admin = es_admin($u);
$sucursales = q('SELECT id, nombre FROM sucursales WHERE negocio_id = ? ORDER BY id', [$n['id']])->fetchAll();
$multi = count($sucursales) > 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && plan_permite($n, 'personal')) {
    csrf_verificar();
    if (!$admin) { flash('error', 'Solo el administrador puede hacer cambios en el personal.'); redirigir('admin/personal.php'); }
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $obj = $id ? q('SELECT * FROM personal WHERE id = ? AND negocio_id = ?', [$id, $n['id']])->fetch() : null;
    $sid = (int)($_POST['sucursal_id'] ?? 0);
    $sid = in_array($sid, array_map('intval', array_column($sucursales, 'id')), true) ? $sid : null;
    try {
        if ($accion === 'config') {
            q('UPDATE negocios SET preguntar_personal = ? WHERE id = ?', [isset($_POST['preguntar_personal']) ? 1 : 0, $n['id']]);
            flash('ok', isset($_POST['preguntar_personal']) ? 'Listo: la encuesta ahora pregunta quién atendió.' : 'La encuesta ya no pregunta quién atendió.');
        } elseif ($accion === 'crear' || ($accion === 'editar' && $obj)) {
            $nom = trim(mb_substr((string)($_POST['nombre'] ?? ''), 0, 60));
            $puesto = trim(mb_substr((string)($_POST['puesto'] ?? ''), 0, 40)) ?: null;
            if ($nom === '') throw new RuntimeException('Escribí el nombre.');
            $foto = $obj['foto'] ?? null;
            if (!empty($_FILES['foto']['name'])) {
                $nueva = guardar_imagen($_FILES['foto'], 'personal', 'p' . $n['id'], 2 * 1048576);
                if ($foto) @unlink(APP_DIR . '/uploads/personal/' . $foto);
                $foto = $nueva;
            }
            if ($accion === 'crear') {
                q('INSERT INTO personal (negocio_id, sucursal_id, nombre, puesto, foto) VALUES (?, ?, ?, ?, ?)', [$n['id'], $sid, $nom, $puesto, $foto]);
                flash('ok', "$nom agregado. Ya aparece en la encuesta" . ((int)$n['preguntar_personal'] ? '.' : ' cuando actives la pregunta “¿Quién te atendió?”.'));
            } else {
                q('UPDATE personal SET nombre = ?, puesto = ?, sucursal_id = ?, foto = ? WHERE id = ?', [$nom, $puesto, $sid, $foto, $id]);
                flash('ok', "$nom actualizado.");
            }
        } elseif ($accion === 'toggle' && $obj) {
            q('UPDATE personal SET activo = 1 - activo WHERE id = ?', [$id]);
            flash('ok', (int)$obj['activo'] ? $obj['nombre'] . ' ya no aparece en la encuesta.' : $obj['nombre'] . ' vuelve a aparecer en la encuesta.');
        } elseif ($accion === 'quitar_foto' && $obj && $obj['foto']) {
            @unlink(APP_DIR . '/uploads/personal/' . $obj['foto']);
            q('UPDATE personal SET foto = NULL WHERE id = ?', [$id]);
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirigir('admin/personal.php');
}

$dias = in_array((int)($_GET['dias'] ?? 30), [7, 30, 90], true) ? (int)($_GET['dias'] ?? 30) : 30;
$desde = date('Y-m-d 00:00:00', strtotime('-' . ($dias - 1) . ' days'));
$um = sprintf('%.2F', $n['umbral_alerta']);
$ranking = q("SELECT pe.*, s.nombre sucursal, COUNT(e.id) total, AVG(e.promedio) prom, AVG(e.recomendaria) * 100 rec,
                     SUM(CASE WHEN e.promedio <= $um THEN 1 ELSE 0 END) bajas,
                     SUM(CASE WHEN e.promedio >= 4.5 THEN 1 ELSE 0 END) excelentes
                FROM personal pe
           LEFT JOIN sucursales s ON s.id = pe.sucursal_id
           LEFT JOIN encuestas e ON e.personal_id = pe.id AND e.creado_en >= ?
               WHERE pe.negocio_id = ?" . (fijo_sucursal($u) ? ' AND (pe.sucursal_id IS NULL OR pe.sucursal_id = ' . (int)$u['sucursal_id'] . ')' : '') . "
            GROUP BY pe.id, s.nombre ORDER BY pe.activo DESC, (COUNT(e.id) >= 3) DESC, prom DESC, pe.nombre", [$desde, $n['id']])->fetchAll();
$identificadas = (int)q('SELECT COUNT(*) FROM encuestas WHERE negocio_id = ? AND creado_en >= ? AND personal_id IS NOT NULL', [$n['id'], $desde])->fetchColumn();
$totalEnc = (int)q('SELECT COUNT(*) FROM encuestas WHERE negocio_id = ? AND creado_en >= ?', [$n['id'], $desde])->fetchColumn();

$titulo = 'Personal';
$seccion = 'personal';
include dirname(__DIR__) . '/includes/admin_header.php';
if (!plan_permite($n, 'personal')) {
    $upsell_plan = 'premium'; $upsell_titulo = '¿Quién atiende mejor en tu equipo?'; $upsell_texto = 'El cliente toca la foto de quien lo atendió y vos ves el ranking de tu personal, ideal para dar bonos. Incluido en el plan Premium.';
    include dirname(__DIR__) . '/includes/upsell.php';
    include dirname(__DIR__) . '/includes/admin_footer.php';
    exit;
}

function sel_suc(array $sucs, ?int $sel): string
{
    $h = '<select name="sucursal_id"><option value="">Todas las sucursales</option>';
    foreach ($sucs as $s) $h .= '<option value="' . (int)$s['id'] . '"' . ($sel === (int)$s['id'] ? ' selected' : '') . '>' . e($s['nombre']) . '</option>';
    return $h . '</select>';
}
?>
<?php if ($admin): ?>
<form method="post" class="panel interruptor-panel">
  <?= csrf_campo() ?><input type="hidden" name="accion" value="config">
  <label class="interruptor"><input type="checkbox" name="preguntar_personal" value="1" <?= (int)$n['preguntar_personal'] ? 'checked' : '' ?> onchange="this.form.submit()"><span></span>
    <span><strong>Preguntar “¿Quién te atendió?” en la encuesta</strong><small class="muted">El cliente toca la foto de quien lo atendió. Es opcional para él.</small></span></label>
</form>
<?php endif; ?>

<form class="filtros" method="get">
  <div class="segmento" role="group" aria-label="Período">
    <?php foreach ([7 => '7 días', 30 => '30 días', 90 => '90 días'] as $v => $t): ?><button name="dias" value="<?= $v ?>" class="<?= $dias === $v ? 'on' : '' ?>"><?= $t ?></button><?php endforeach; ?>
  </div>
  <span class="muted"><?= $identificadas ?> de <?= $totalEnc ?> encuestas dicen quién atendió</span>
</form>

<section class="grid-lateral">
  <article class="panel">
    <header class="panel-head"><h2>Ranking del equipo</h2><span class="panel-nota">Se ordena con al menos 3 encuestas</span></header>
    <?php if (!$ranking): ?><p class="muted">Agregá a tu equipo a la derecha para empezar a medir quién atiende mejor.</p><?php endif; ?>
    <ol class="ranking-personal">
      <?php $pos = 0; foreach ($ranking as $r): $pr = $r['prom'] !== null ? (float)$r['prom'] : null; $cuenta = (int)$r['total'] >= 3 && (int)$r['activo']; if ($cuenta) $pos++; $foto = foto_personal($r['foto']); ?>
        <li class="<?= (int)$r['activo'] ? '' : 'inactivo' ?>">
          <span class="rp-pos <?= $cuenta && $pos === 1 ? 'oro' : '' ?>"><?= $cuenta ? ($pos === 1 ? '🏆' : $pos) : '–' ?></span>
          <?php if ($foto): ?><img class="rp-foto" src="<?= e($foto) ?>" alt=""><?php else: ?><span class="rp-foto rp-ini"><?= e(mb_strtoupper(mb_substr($r['nombre'], 0, 1))) ?></span><?php endif; ?>
          <span class="rp-nom"><strong><?= e($r['nombre']) ?></strong><small><?= e(implode(' · ', array_filter([$r['puesto'], $multi ? ($r['sucursal'] ?: 'Todas') : null]))) ?: '&nbsp;' ?></small></span>
          <span class="rp-datos"><?= (int)$r['total'] ?> encuestas<?php if ((int)$r['total']): ?> · <?= (int)$r['excelentes'] ?> excelentes · <?= (int)$r['bajas'] ?> bajas<?php endif; ?></span>
          <span class="rp-nota nota-<?= clase_nota($pr, $n['umbral_alerta']) ?>"><?= nota($pr) ?></span>
          <?php if ($admin): ?>
            <details class="menu-acciones">
              <summary>Editar</summary>
              <div>
                <form method="post" enctype="multipart/form-data" class="form-col">
                  <?= csrf_campo() ?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <label class="campo"><span>Nombre</span><input name="nombre" value="<?= e($r['nombre']) ?>" maxlength="60" required></label>
                  <label class="campo"><span>Puesto</span><input name="puesto" value="<?= e($r['puesto']) ?>" maxlength="40"></label>
                  <?php if ($multi): ?><label class="campo"><span>Sucursal</span><?= sel_suc($sucursales, $r['sucursal_id'] ? (int)$r['sucursal_id'] : null) ?></label><?php endif; ?>
                  <label class="campo"><span>Foto nueva</span><input type="file" name="foto" accept="image/png,image/jpeg,image/webp"></label>
                  <button class="btn btn-sm" type="submit">Guardar</button>
                </form>
                <form method="post"><?= csrf_campo() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-borde" type="submit"><?= (int)$r['activo'] ? 'Ya no trabaja aquí' : 'Volver a mostrar' ?></button></form>
              </div>
            </details>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </article>

  <?php if ($admin): ?>
    <article class="panel">
      <header class="panel-head"><h2>Agregar al equipo</h2></header>
      <form method="post" enctype="multipart/form-data" class="form-col">
        <?= csrf_campo() ?><input type="hidden" name="accion" value="crear">
        <div class="campo"><label for="pn">Nombre</label><input id="pn" name="nombre" maxlength="60" required placeholder="Como lo conocen los clientes"></div>
        <div class="campo"><label for="pp">Puesto <small>Opcional</small></label><input id="pp" name="puesto" maxlength="40" placeholder="Mesero, barista, estilista…"></div>
        <?php if ($multi): ?><div class="campo"><label>Sucursal</label><?= sel_suc($sucursales, null) ?></div><?php endif; ?>
        <div class="campo"><label for="pf">Foto <small>Opcional, ayuda a que el cliente lo reconozca</small></label><input id="pf" type="file" name="foto" accept="image/png,image/jpeg,image/webp"></div>
        <button class="btn" type="submit">Agregar</button>
      </form>
    </article>
  <?php endif; ?>
</section>
<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
