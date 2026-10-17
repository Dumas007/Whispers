<?php
require dirname(__DIR__) . '/config.php';
$u = requiere_login();
$n = null;
$nid = $u['rol'] === 'super' ? (int)($_SESSION['ver_negocio'] ?? 0) : (int)$u['negocio_id'];
if ($nid) {
    $n = q('SELECT * FROM negocios WHERE id = ?', [$nid])->fetch() ?: null;
    if ($n) { $n['umbral_alerta'] = (float)$n['umbral_alerta']; $n['umbral_resena'] = (float)$n['umbral_resena']; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $row = q('SELECT password_hash FROM usuarios WHERE id = ?', [$u['id']])->fetch();
    $nombre = trim(mb_substr((string)($_POST['nombre'] ?? ''), 0, 100));
    $nueva = (string)($_POST['nueva'] ?? '');
    if (!password_verify((string)($_POST['actual'] ?? ''), $row['password_hash'])) {
        flash('error', 'La contraseña actual no es correcta.');
    } elseif ($nueva !== '' && strlen($nueva) < 8) {
        flash('error', 'La nueva contraseña necesita al menos 8 caracteres.');
    } elseif ($nueva !== '' && $nueva !== (string)($_POST['repetir'] ?? '')) {
        flash('error', 'Las contraseñas nuevas no coinciden.');
    } else {
        if ($nombre !== '') q('UPDATE usuarios SET nombre = ? WHERE id = ?', [$nombre, $u['id']]);
        if ($nueva !== '') q('UPDATE usuarios SET password_hash = ? WHERE id = ?', [password_hash($nueva, PASSWORD_DEFAULT), $u['id']]);
        flash('ok', 'Tu cuenta quedó actualizada.');
    }
    redirigir('admin/cuenta.php');
}

$titulo = 'Mi cuenta';
$seccion = 'cuenta';
include dirname(__DIR__) . '/includes/admin_header.php';
?>
<article class="panel panel-form">
  <form method="post" class="form-col">
    <?= csrf_campo() ?>
    <div class="form-grid">
      <div class="campo"><label for="nm">Nombre</label><input id="nm" name="nombre" value="<?= e($u['nombre']) ?>" required></div>
      <div class="campo"><label>Correo</label><input value="<?= e($u['email']) ?>" disabled></div>
      <div class="campo"><label for="nv">Nueva contraseña <small>Dejala vacía para no cambiarla</small></label><input id="nv" name="nueva" type="password" minlength="8" autocomplete="new-password"></div>
      <div class="campo"><label for="rp">Repetí la nueva contraseña</label><input id="rp" name="repetir" type="password" autocomplete="new-password"></div>
      <div class="campo campo-full"><label for="ac">Contraseña actual <small>Para confirmar que sos vos</small></label><input id="ac" name="actual" type="password" required autocomplete="current-password"></div>
    </div>
    <button class="btn" type="submit">Guardar cambios</button>
  </form>
</article>
<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
