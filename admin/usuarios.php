<?php
require dirname(__DIR__) . '/config.php';
[$u, $n] = requiere_negocio(['admin']);
$sucursales = q('SELECT id, nombre FROM sucursales WHERE negocio_id = ? ORDER BY id', [$n['id']])->fetchAll();
$multi = count($sucursales) > 1;
$rolesDisponibles = ['gerente' => 'Gerente', 'admin' => 'Administrador'];
$rolValido = fn($r) => array_key_exists($r, $rolesDisponibles) ? $r : 'gerente';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $sucIn = (int)($_POST['sucursal_id'] ?? 0);
    $sucIn = in_array($sucIn, array_map('intval', array_column($sucursales, 'id')), true) ? $sucIn : null;
    $obj = $id ? q('SELECT * FROM usuarios WHERE id = ? AND negocio_id = ?', [$id, $n['id']])->fetch() : null;

    if ($accion === 'crear') {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass = (string)($_POST['password'] ?? '');
        $rol = $rolValido($_POST['rol'] ?? '');
        if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Escribí un nombre y un correo válido.');
        } elseif (strlen($pass) < 8) {
            flash('error', 'La contraseña necesita al menos 8 caracteres.');
        } elseif (q('SELECT 1 FROM usuarios WHERE email = ?', [$email])->fetch()) {
            flash('error', 'Ese correo ya está registrado en Whispers.');
        } else {
            q('INSERT INTO usuarios (negocio_id, sucursal_id, nombre, email, password_hash, rol) VALUES (?, ?, ?, ?, ?, ?)',
                [$n['id'], $rol === 'admin' ? null : $sucIn, $nombre, $email, password_hash($pass, PASSWORD_DEFAULT), $rol]);
            flash('ok', "Usuario de $nombre creado. Pasale su correo, la contraseña y el link: " . url_absoluta('login.php'));
        }
    } elseif (!$obj) {
        flash('error', 'Ese usuario no existe.');
    } elseif ((int)$obj['id'] === (int)$u['id'] && in_array($accion, ['toggle', 'eliminar', 'rol'], true)) {
        flash('error', 'No podés desactivar, eliminar ni cambiarle el rol a tu propio usuario.');
    } elseif ($accion === 'toggle') {
        q('UPDATE usuarios SET activo = 1 - activo WHERE id = ?', [$id]);
        flash('ok', 'Estado del usuario actualizado.');
    } elseif ($accion === 'rol') {
        $rol = $rolValido($_POST['rol'] ?? '');
        q('UPDATE usuarios SET rol = ?, sucursal_id = ? WHERE id = ?', [$rol, $rol === 'admin' ? null : $sucIn, $id]);
        flash('ok', 'Permisos actualizados.');
    } elseif ($accion === 'password') {
        $pass = (string)($_POST['password'] ?? '');
        if (strlen($pass) < 8) {
            flash('error', 'La nueva contraseña necesita al menos 8 caracteres.');
        } else {
            q('UPDATE usuarios SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
            flash('ok', 'Contraseña cambiada.');
        }
    } elseif ($accion === 'eliminar') {
        q('DELETE FROM usuarios WHERE id = ?', [$id]);
        flash('ok', 'Usuario eliminado.');
    }
    redirigir('admin/usuarios.php');
}

$usuarios = q('SELECT u.*, s.nombre sucursal FROM usuarios u LEFT JOIN sucursales s ON s.id = u.sucursal_id WHERE u.negocio_id = ? ORDER BY u.activo DESC, u.nombre', [$n['id']])->fetchAll();
$titulo = 'Usuarios';
$seccion = 'usuarios';
include dirname(__DIR__) . '/includes/admin_header.php';

function select_sucursal(array $sucursales, ?int $actual, string $id = ''): string
{
    $h = '<select name="sucursal_id"' . ($id ? ' id="' . $id . '"' : '') . '><option value="">Todas las sucursales</option>';
    foreach ($sucursales as $s) $h .= '<option value="' . (int)$s['id'] . '"' . ($actual === (int)$s['id'] ? ' selected' : '') . '>' . e($s['nombre']) . '</option>';
    return $h . '</select>';
}
?>
<section class="grid-lateral">
  <article class="panel">
    <header class="panel-head"><h2>Equipo con acceso</h2></header>
    <div class="tabla-wrap">
      <table class="tabla">
        <thead><tr><th>Nombre</th><th>Permisos</th><th>Último acceso</th><th>Estado</th><th><span class="sr">Acciones</span></th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $us): $yo = (int)$us['id'] === (int)$u['id']; ?>
          <tr class="<?= (int)$us['activo'] ? '' : 'inactivo' ?>">
            <td><strong><?= e($us['nombre']) ?></strong><?= $yo ? ' <small class="muted">(vos)</small>' : '' ?><br><small class="muted"><?= e($us['email']) ?></small></td>
            <td><?= NOMBRES_ROL[$us['rol']] ?? $us['rol'] ?><?php if ($multi && $us['rol'] !== 'admin'): ?><br><small class="muted"><?= e($us['sucursal'] ?: 'Todas las sucursales') ?></small><?php endif; ?></td>
            <td class="nowrap"><?= $us['ultimo_acceso'] ? e(fecha_corta($us['ultimo_acceso'])) : '<span class="muted">Nunca</span>' ?></td>
            <td><?= (int)$us['activo'] ? '<span class="estado ok">Activo</span>' : '<span class="estado gris">Desactivado</span>' ?></td>
            <td class="acciones">
              <details class="menu-acciones">
                <summary>Opciones</summary>
                <div>
                  <?php if (!$yo): ?>
                    <form method="post" class="form-col">
                      <?= csrf_campo() ?><input type="hidden" name="accion" value="rol"><input type="hidden" name="id" value="<?= (int)$us['id'] ?>">
                      <label class="campo"><span>Rol</span><select name="rol"><?php foreach ($rolesDisponibles as $rk => $rt): ?><option value="<?= $rk ?>" <?= $us['rol'] === $rk ? 'selected' : '' ?>><?= $rt ?></option><?php endforeach; ?></select></label>
                      <?php if ($multi): ?><label class="campo"><span>Sucursal (si no es administrador)</span><?= select_sucursal($sucursales, $us['sucursal_id'] ? (int)$us['sucursal_id'] : null) ?></label><?php endif; ?>
                      <button class="btn btn-sm btn-borde" type="submit">Guardar permisos</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" class="form-col">
                    <?= csrf_campo() ?><input type="hidden" name="accion" value="password"><input type="hidden" name="id" value="<?= (int)$us['id'] ?>">
                    <label class="campo"><span>Nueva contraseña</span><input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
                    <button class="btn btn-sm" type="submit">Cambiar contraseña</button>
                  </form>
                  <?php if (!$yo): ?>
                    <form method="post"><?= csrf_campo() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="id" value="<?= (int)$us['id'] ?>"><button class="btn btn-sm btn-borde" type="submit"><?= (int)$us['activo'] ? 'Desactivar' : 'Activar' ?></button></form>
                    <form method="post" data-confirmar="¿Eliminar a <?= e($us['nombre']) ?>?"><?= csrf_campo() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$us['id'] ?>"><button class="btn btn-sm btn-peligro" type="submit">Eliminar</button></form>
                  <?php endif; ?>
                </div>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </article>

  <article class="panel">
    <header class="panel-head"><h2>Agregar usuario</h2></header>
    <form method="post" class="form-col">
      <?= csrf_campo() ?><input type="hidden" name="accion" value="crear">
      <div class="campo"><label for="n">Nombre</label><input id="n" name="nombre" required></div>
      <div class="campo"><label for="em">Correo</label><input id="em" name="email" type="email" required></div>
      <div class="campo"><label for="pw">Contraseña</label><input id="pw" name="password" type="password" minlength="8" required autocomplete="new-password"><small class="muted">Mínimo 8 caracteres.</small></div>
      <div class="campo"><label for="rl">Rol</label>
        <select id="rl" name="rol">
          <option value="gerente">Gerente — ve resultados y da seguimiento</option>
          <option value="admin">Administrador — además cambia ajustes, preguntas y usuarios</option>
        </select>
      </div>
      <?php if ($multi): ?><div class="campo"><label for="su">Sucursal (si no es administrador)</label><?= select_sucursal($sucursales, null, 'su') ?></div><?php endif; ?>
      <button class="btn" type="submit">Crear usuario</button>
    </form>
  </article>
</section>
<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
