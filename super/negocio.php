<?php
require dirname(__DIR__) . '/config.php';
$u = requiere_login(['super']);
$id = (int)($_GET['id'] ?? 0);
$x = q('SELECT * FROM negocios WHERE id = ?', [$id])->fetch();
if (!$x) { flash('error', 'Ese cliente no existe.'); redirigir('super/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar') {
        $plan = array_key_exists($_POST['plan'] ?? '', PLANES) ? $_POST['plan'] : $x['plan'];
        $cobro = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['proximo_cobro'] ?? '') ? $_POST['proximo_cobro'] : null;
        q('UPDATE negocios SET nombre = ?, rubro = ?, plan = ?, proximo_cobro = ?, notas = ? WHERE id = ?', [
            trim(mb_substr((string)($_POST['nombre'] ?? ''), 0, 120)) ?: $x['nombre'], trim((string)($_POST['rubro'] ?? '')) ?: null,
            $plan, $cobro, trim((string)($_POST['notas'] ?? '')) ?: null, $id,
        ]);
        flash('ok', 'Cliente actualizado.');
    } elseif (in_array($accion, ['func_prueba', 'func_siempre', 'func_quitar', 'prueba_todo'], true)) {
        $f = (string)($_POST['funcion'] ?? '');
        $dias = in_array((int)($_POST['dias'] ?? 15), [7, 15, 30, 60], true) ? (int)$_POST['dias'] : 15;
        $hastaPrueba = date('Y-m-d', strtotime('+' . $dias . ' days'));
        $upsert = 'INSERT INTO negocio_funciones (negocio_id, funcion, hasta, otorgado_por) VALUES (?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE hasta = VALUES(hasta), otorgado_por = VALUES(otorgado_por), creado_en = NOW()';
        if ($accion === 'prueba_todo') {
            $cnt = 0;
            foreach (array_keys(FUNCIONES_NOMBRE) as $fx) {
                if (in_array($x['plan'], FUNCIONES_PLAN[$fx], true)) continue;
                $act = q('SELECT hasta FROM negocio_funciones WHERE negocio_id = ? AND funcion = ?', [$id, $fx])->fetch();
                if ($act && $act['hasta'] === null) continue; // ya la tiene para siempre
                q($upsert, [$id, $fx, $hastaPrueba, $u['id']]);
                $cnt++;
            }
            flash('ok', "Listo: $cnt funciones Premium de prueba hasta el " . date('d/m/Y', strtotime($hastaPrueba)) . '. El cliente ya las ve en su panel.');
        } elseif (isset(FUNCIONES_NOMBRE[$f])) {
            if ($accion === 'func_prueba') {
                $act = q('SELECT hasta FROM negocio_funciones WHERE negocio_id = ? AND funcion = ?', [$id, $f])->fetchColumn();
                if ($act && $act >= date('Y-m-d')) $hastaPrueba = date('Y-m-d', strtotime($act . ' +' . $dias . ' days')); // extender desde la fecha actual de fin
                q($upsert, [$id, $f, $hastaPrueba, $u['id']]);
                flash('ok', FUNCIONES_NOMBRE[$f] . " de prueba por $dias días (hasta el " . date('d/m/Y', strtotime($hastaPrueba)) . ').');
            } elseif ($accion === 'func_siempre') {
                q($upsert, [$id, $f, null, $u['id']]);
                flash('ok', FUNCIONES_NOMBRE[$f] . ' activada sin fecha de fin.');
            } else {
                q('DELETE FROM negocio_funciones WHERE negocio_id = ? AND funcion = ?', [$id, $f]);
                flash('ok', FUNCIONES_NOMBRE[$f] . ' quitada. Si su plan no la incluye, deja de verla.');
            }
        }
    } elseif ($accion === 'pago') {
        $base = ($x['proximo_cobro'] && $x['proximo_cobro'] > '2000-01-01') ? $x['proximo_cobro'] : date('Y-m-d');
        $nuevo = date('Y-m-d', strtotime($base . ' +1 month'));
        $nota = trim(($x['notas'] ?? '') . "\n" . date('j/n/Y') . ': pago recibido (' . quetzales(PLANES[$x['plan']]['precio']) . ').');
        q('UPDATE negocios SET proximo_cobro = ?, notas = ? WHERE id = ?', [$nuevo, $nota, $id]);
        flash('ok', 'Pago registrado. Próximo cobro: ' . date('j/n/Y', strtotime($nuevo)) . '.');
    } elseif ($accion === 'toggle') {
        q('UPDATE negocios SET activo = 1 - activo WHERE id = ?', [$id]);
        flash('ok', (int)$x['activo'] ? 'Cliente pausado: sus QR y su panel quedan detenidos.' : 'Cliente reactivado.');
    } elseif ($accion === 'password') {
        $uid = (int)($_POST['usuario_id'] ?? 0);
        $pass = (string)($_POST['password'] ?? '');
        if (strlen($pass) < 8) {
            flash('error', 'La contraseña necesita al menos 8 caracteres.');
        } else {
            q('UPDATE usuarios SET password_hash = ? WHERE id = ? AND negocio_id = ?', [password_hash($pass, PASSWORD_DEFAULT), $uid, $id]);
            flash('ok', 'Contraseña cambiada a: ' . $pass);
        }
    } elseif ($accion === 'eliminar') {
        if (trim((string)($_POST['confirmar'] ?? '')) !== $x['nombre']) {
            flash('error', 'Para eliminar, escribí el nombre exacto del negocio.');
        } else {
            if ($x['logo']) @unlink(dirname(__DIR__) . '/uploads/logos/' . $x['logo']);
            q('DELETE FROM negocios WHERE id = ?', [$id]);
            if ((int)($_SESSION['ver_negocio'] ?? 0) === $id) unset($_SESSION['ver_negocio']);
            flash('ok', 'Cliente eliminado con todas sus encuestas.');
            redirigir('super/index.php');
        }
    }
    redirigir('super/negocio.php?id=' . $id);
}

$usuarios = q('SELECT * FROM usuarios WHERE negocio_id = ? ORDER BY rol, nombre', [$id])->fetchAll();
$extras = [];
foreach (q('SELECT * FROM negocio_funciones WHERE negocio_id = ?', [$id]) as $r) $extras[$r['funcion']] = $r;
$stats = q('SELECT COUNT(*) total, AVG(promedio) prom, MIN(creado_en) primera FROM encuestas WHERE negocio_id = ?', [$id])->fetch();
$n = null;
$titulo = $x['nombre'];
$seccion = 'clientes';
include dirname(__DIR__) . '/includes/admin_header.php';
?>
<a class="volver" href="<?= e(url('super/index.php')) ?>">Volver a clientes</a>
<section class="grid-lateral">
  <article class="panel">
    <form method="post" class="form-col">
      <?= csrf_campo() ?><input type="hidden" name="accion" value="guardar">
      <div class="form-grid">
        <div class="campo"><label for="nn">Nombre</label><input id="nn" name="nombre" value="<?= e($x['nombre']) ?>" required></div>
        <div class="campo"><label for="rb">Tipo de negocio</label><input id="rb" name="rubro" value="<?= e($x['rubro']) ?>"></div>
        <div class="campo"><label for="pl">Plan</label><select id="pl" name="plan"><?php foreach (PLANES as $k => $pl): ?><option value="<?= $k ?>" <?= $x['plan'] === $k ? 'selected' : '' ?>><?= $pl['nombre'] ?> — <?= quetzales($pl['precio']) ?>/mes</option><?php endforeach; ?></select></div>
        <div class="campo"><label for="pc">Próximo cobro</label><input id="pc" name="proximo_cobro" type="date" value="<?= e($x['proximo_cobro']) ?>"></div>
        <div class="campo campo-full"><label for="nt">Notas internas <small>Solo las ve Whispers</small></label><textarea id="nt" name="notas" rows="5"><?= e($x['notas']) ?></textarea></div>
      </div>
      <button class="btn" type="submit">Guardar</button>
    </form>
  </article>

  <div class="col-lateral">
    <article class="panel">
      <header class="panel-head"><h2>Cobro</h2></header>
      <p class="plan-nombre"><?= e(plan_nombre($x['plan'])) ?> <span><?= quetzales(PLANES[$x['plan']]['precio']) ?>/mes</span></p>
      <p class="muted">Próximo cobro: <?= $x['proximo_cobro'] ? e(fecha_larga($x['proximo_cobro'])) : 'sin fecha' ?></p>
      <form method="post" class="fila-botones"><?= csrf_campo() ?><input type="hidden" name="accion" value="pago"><button class="btn btn-sm btn-verde" type="submit">Registrar pago del mes</button></form>
    </article>
    <article class="panel">
      <header class="panel-head"><h2>Actividad</h2></header>
      <p><?= (int)$stats['total'] ?> encuestas en total<?= $stats['prom'] !== null ? ' · promedio ' . nota((float)$stats['prom']) : '' ?></p>
      <p class="muted">Cliente desde <?= e(fecha_larga($x['creado_en'])) ?></p>
      <form method="post" action="<?= e(url('super/index.php')) ?>" class="fila-botones"><?= csrf_campo() ?><input type="hidden" name="accion" value="entrar"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-sm" type="submit">Abrir su panel</button></form>
      <form method="post" class="fila-botones" data-confirmar="<?= (int)$x['activo'] ? '¿Pausar este cliente? Sus QR dejan de funcionar y su equipo no podrá entrar.' : '¿Reactivar este cliente?' ?>">
        <?= csrf_campo() ?><input type="hidden" name="accion" value="toggle"><button class="btn btn-sm <?= (int)$x['activo'] ? 'btn-borde' : 'btn-verde' ?>" type="submit"><?= (int)$x['activo'] ? 'Pausar cliente' : 'Reactivar cliente' ?></button>
      </form>
    </article>
  </div>
</section>

<article class="panel">
  <header class="panel-head"><h2>Funciones del cliente</h2><span class="panel-nota">Plan <?= e(plan_nombre($x['plan'])) ?></span></header>
  <div class="funciones-top">
    <p class="muted">Dale cualquier función de otro plan, de prueba por unos días o para siempre. Al vencer la prueba, deja de verla sola; sus datos no se borran.</p>
    <?php if ($x['plan'] !== 'premium'): ?>
      <form method="post" class="inline-fila"><?= csrf_campo() ?><input type="hidden" name="accion" value="prueba_todo">
        <select name="dias" aria-label="Días de prueba"><option value="7">7 días</option><option value="15" selected>15 días</option><option value="30">30 días</option></select>
        <button class="btn btn-sm" type="submit">🎁 Prueba Premium completa</button></form>
    <?php endif; ?>
  </div>
  <ul class="funciones-lista">
    <?php foreach (FUNCIONES_NOMBRE as $f => $nombreF):
        $enPlan = in_array($x['plan'], FUNCIONES_PLAN[$f], true);
        $ex = $extras[$f] ?? null;
        $vigente = $ex && ($ex['hasta'] === null || $ex['hasta'] >= date('Y-m-d'));
        $quedan = ($ex && $ex['hasta']) ? (int)floor((strtotime($ex['hasta']) - strtotime(date('Y-m-d'))) / 86400) : null; ?>
      <li>
        <span class="fn-nom"><?= e($nombreF) ?><small><?= $enPlan ? '' : 'Desde el plan ' . e(plan_nombre(plan_para($f))) ?></small></span>
        <span class="fn-estado">
          <?php if ($enPlan): ?><span class="estado gris">Incluida en su plan</span>
          <?php elseif ($vigente && $ex['hasta'] === null): ?><span class="estado ok">Activada siempre</span>
          <?php elseif ($vigente): ?><span class="estado prueba">Prueba hasta <?= e(date('d/m', strtotime($ex['hasta']))) ?> · <?= $quedan === 0 ? 'último día' : 'quedan ' . $quedan . ' día' . ($quedan === 1 ? '' : 's') ?></span>
          <?php elseif ($ex): ?><span class="estado mal">Prueba vencida el <?= e(date('d/m', strtotime($ex['hasta']))) ?></span>
          <?php else: ?><span class="muted">No la tiene</span><?php endif; ?>
        </span>
        <span class="fn-acc">
          <?php if (!$enPlan): ?>
            <?php if (!$vigente || $ex['hasta'] !== null): ?>
              <form method="post" class="inline-fila"><?= csrf_campo() ?><input type="hidden" name="accion" value="func_prueba"><input type="hidden" name="funcion" value="<?= e($f) ?>">
                <select name="dias" aria-label="Días"><option value="7">7 días</option><option value="15" selected>15 días</option><option value="30">30 días</option></select>
                <button class="btn btn-sm btn-borde" type="submit"><?= $vigente ? 'Extender' : 'Dar prueba' ?></button></form>
              <form method="post" class="inline"><?= csrf_campo() ?><input type="hidden" name="accion" value="func_siempre"><input type="hidden" name="funcion" value="<?= e($f) ?>"><button class="link-btn" type="submit">Activar siempre</button></form>
            <?php endif; ?>
            <?php if ($ex): ?>
              <form method="post" class="inline"><?= csrf_campo() ?><input type="hidden" name="accion" value="func_quitar"><input type="hidden" name="funcion" value="<?= e($f) ?>"><button class="link-btn txt-mal" type="submit">Quitar</button></form>
            <?php endif; ?>
          <?php endif; ?>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
</article>

<article class="panel">
  <header class="panel-head"><h2>Usuarios del cliente</h2></header>
  <div class="tabla-wrap">
    <table class="tabla">
      <thead><tr><th>Nombre</th><th>Rol</th><th>Último acceso</th><th>Nueva contraseña</th></tr></thead>
      <tbody>
      <?php foreach ($usuarios as $us): ?>
        <tr class="<?= (int)$us['activo'] ? '' : 'inactivo' ?>">
          <td><strong><?= e($us['nombre']) ?></strong><br><small class="muted"><?= e($us['email']) ?></small></td>
          <td><?= $us['rol'] === 'admin' ? 'Administrador' : 'Gerente' ?></td>
          <td class="nowrap"><?= $us['ultimo_acceso'] ? e(fecha_corta($us['ultimo_acceso'])) : '<span class="muted">Nunca</span>' ?></td>
          <td><form method="post" class="preg-edit"><?= csrf_campo() ?><input type="hidden" name="accion" value="password"><input type="hidden" name="usuario_id" value="<?= (int)$us['id'] ?>"><input name="password" minlength="8" required placeholder="Mínimo 8" aria-label="Nueva contraseña"><button class="btn btn-sm btn-borde" type="submit">Cambiar</button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</article>

<details class="zona-peligro">
  <summary>Eliminar cliente</summary>
  <form method="post" class="form-col">
    <?= csrf_campo() ?><input type="hidden" name="accion" value="eliminar">
    <p>Se borran el negocio, sus sucursales, mesas, usuarios y todas sus encuestas. No se puede deshacer.</p>
    <div class="campo"><label for="cf">Escribí <strong><?= e($x['nombre']) ?></strong> para confirmar</label><input id="cf" name="confirmar" autocomplete="off"></div>
    <button class="btn btn-sm btn-peligro" type="submit">Eliminar definitivamente</button>
  </form>
</details>
<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
