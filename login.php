<?php
require __DIR__ . '/config.php';

if ($s = usuario()) {
    redirigir(inicio_rol($s['rol']));
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass  = (string)($_POST['password'] ?? '');

    $_SESSION['intentos'] = array_filter($_SESSION['intentos'] ?? [], fn($t) => $t > time() - 900);
    if (count($_SESSION['intentos']) >= 5) {
        $error = 'Demasiados intentos. Esperá 15 minutos y volvé a probar.';
    } else {
        $u = q('SELECT u.*, n.activo AS negocio_activo FROM usuarios u LEFT JOIN negocios n ON n.id = u.negocio_id WHERE u.email = ? LIMIT 1', [$email])->fetch();
        if ($u && password_verify($pass, $u['password_hash'])) {
            if (!(int)$u['activo'] || !isset(NOMBRES_ROL[$u['rol']])) {
                $error = 'Tu usuario está desactivado. Pedile al administrador que lo active.';
            } elseif ($u['rol'] !== 'super' && !(int)$u['negocio_activo']) {
                $error = 'El acceso de tu negocio está pausado. Comunicate con Whispers.';
            } else {
                session_regenerate_id(true);
                $_SESSION['usuario'] = ['id' => (int)$u['id'], 'nombre' => $u['nombre'], 'rol' => $u['rol']];
                $_SESSION['intentos'] = [];
                q('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?', [$u['id']]);
                redirigir(inicio_rol($u['rol']));
            }
        } else {
            $_SESSION['intentos'][] = time();
            $error = 'El correo o la contraseña no coinciden.';
        }
    }
}

$titulo = 'Iniciar sesión — Whispers';
include __DIR__ . '/includes/head.php';
?>
<body class="publico login-page">
<main class="login-box">
  <a class="marca marca-oscura" href="<?= e(url('index.php')) ?>"><?php include __DIR__ . '/includes/logo.php'; ?> Whispers</a>
  <h1>Entrá a tu panel</h1>
  <p class="lead-sm">Mirá lo que opinan tus clientes, mesa por mesa.</p>

  <?php foreach (flashes() as $f): ?><div class="alerta alerta-<?= e($f['tipo']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
  <?php if ($error): ?><div class="alerta alerta-error" role="alert"><?= e($error) ?></div><?php endif; ?>

  <form method="post" class="form-col">
    <?= csrf_campo() ?>
    <div class="campo"><label for="email">Correo</label><input id="email" name="email" type="email" value="<?= e($email) ?>" required autocomplete="username" autofocus></div>
    <div class="campo"><label for="password">Contraseña</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
    <button class="btn btn-grande" type="submit">Entrar</button>
  </form>
  <?php if ($ventas = ajuste('whatsapp_ventas')): ?>
    <p class="login-ayuda">¿Olvidaste tu contraseña? Pedísela al administrador de tu negocio o <a href="<?= e(whatsapp_link($ventas, 'Hola, necesito ayuda para entrar a mi panel de Whispers.')) ?>" target="_blank" rel="noopener">escribile a Whispers</a>.</p>
  <?php endif; ?>
</main>
</body>
</html>
