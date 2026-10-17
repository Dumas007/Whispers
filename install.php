<?php
require __DIR__ . '/config.php';

$errores = [];
$configManual = '';
$v = array_merge([
    'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => 'whispers', 'db_user' => 'root', 'db_pass' => '',
    'nombre' => '', 'email' => '', 'whatsapp' => '', 'demo' => '1',
], $_POST);

if (INSTALADO) {
    $titulo = 'Whispers ya está instalado';
    include __DIR__ . '/includes/head.php'; ?>
    <body class="publico login-page"><main class="login-box">
      <span class="marca marca-oscura"><?php include __DIR__ . '/includes/logo.php'; ?> Whispers</span>
      <h1>Ya está instalado</h1>
      <p class="lead-sm">Para reinstalar, borrá el archivo <code>config.local.php</code> del servidor. Esto evita que alguien más lo reinstale.</p>
      <a class="btn btn-grande" href="<?= e(url('login.php')) ?>">Ir a iniciar sesión</a>
    </main></body></html>
    <?php exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v = array_map(fn($x) => is_string($x) ? trim($x) : $x, $v);
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || $v['nombre'] === '') $errores[] = 'Escribí tu nombre y un correo válido.';
    if (strlen((string)($_POST['password'] ?? '')) < 8) $errores[] = 'La contraseña necesita al menos 8 caracteres.';
    if (!preg_match('/^[A-Za-z0-9_]+$/', $v['db_name'])) $errores[] = 'El nombre de la base de datos solo puede tener letras, números y guion bajo.';

    if (!$errores) {
        try {
            $pdo = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $v['db_host'], $v['db_port']), $v['db_user'], $v['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $v['db_name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . $v['db_name'] . '`');
            if ($pdo->query("SHOW TABLES LIKE 'usuarios'")->fetch()) {
                throw new RuntimeException('La base de datos "' . $v['db_name'] . '" ya tiene tablas de Whispers. Usá otra o vaciala primero.');
            }
            ejecutar_sql_archivo($pdo, __DIR__ . '/includes/schema.sql');
            ejecutar_sql_archivo($pdo, __DIR__ . '/includes/schema_v5.sql');
        } catch (Throwable $t) {
            $errores[] = $t instanceof PDOException ? 'No se pudo conectar o crear la base de datos: ' . $t->getMessage() : $t->getMessage();
        }
    }

    if (!$errores) {
        $contenido = "<?php\n// Generado por install.php el " . date('Y-m-d H:i') . "\n"
            . 'define(\'DB_HOST\', ' . var_export($v['db_host'], true) . ");\n"
            . 'define(\'DB_PORT\', ' . var_export($v['db_port'], true) . ");\n"
            . 'define(\'DB_NAME\', ' . var_export($v['db_name'], true) . ");\n"
            . 'define(\'DB_USER\', ' . var_export($v['db_user'], true) . ");\n"
            . 'define(\'DB_PASS\', ' . var_export($v['db_pass'], true) . ");\n"
            . "// Si la plataforma está en una subcarpeta y los links fallan, descomentá y ajustá:\n// define('BASE_URL', '/whispers');\n";

        define('DB_HOST', $v['db_host']); define('DB_PORT', $v['db_port']); define('DB_NAME', $v['db_name']);
        define('DB_USER', $v['db_user']); define('DB_PASS', $v['db_pass']);

        q("INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, 'super')",
            [$v['nombre'], strtolower($v['email']), password_hash((string)$_POST['password'], PASSWORD_DEFAULT)]);
        ajuste_guardar('whatsapp_ventas', preg_replace('/[^0-9]/', '', $v['whatsapp']));
        ajuste_guardar('modelo_ia', 'claude-haiku-5-5');
        ajuste_guardar('modelo_openai', 'gpt-4o-mini');
        ajuste_guardar('ia_proveedor', 'openai');
        ajuste_guardar('db_version', (string)DB_VERSION);
        ajuste_guardar('app_secret', bin2hex(random_bytes(24)));
        if (!empty($_POST['demo'])) {
            $nid = crear_negocio(['nombre' => 'Restaurante Demo', 'rubro' => 'Restaurante', 'plan' => 'profesional', 'sucursal' => 'Sucursal Central', 'direccion' => 'Zona 10, Ciudad de Guatemala', 'mesas' => 2]);
            q("UPDATE sucursales SET google_review_url = 'https://g.page/r/TU-LINK/review' WHERE negocio_id = ?", [$nid]);
            sembrar_demo($nid, 70);
        }

        if (@file_put_contents(__DIR__ . '/config.local.php', $contenido) === false) {
            $configManual = $contenido;
        } else {
            flash('ok', 'Whispers quedó instalado. Entrá con el correo y la contraseña que acabás de crear.');
            redirigir('login.php');
        }
    }
}

$titulo = 'Instalar Whispers';
include __DIR__ . '/includes/head.php';
?>
<body class="publico login-page">
<main class="login-box login-ancho">
  <span class="marca marca-oscura"><?php include __DIR__ . '/includes/logo.php'; ?> Whispers</span>

  <?php if ($configManual): ?>
    <h1>Falta un paso</h1>
    <p class="lead-sm">La base de datos quedó lista, pero el servidor no dejó crear el archivo de configuración. Creá un archivo llamado <code>config.local.php</code> junto a <code>config.php</code> con este contenido:</p>
    <textarea class="codigo" rows="10" readonly><?= e($configManual) ?></textarea>
    <a class="btn btn-grande" href="<?= e(url('login.php')) ?>">Ya lo creé, ir a iniciar sesión</a>
  <?php else: ?>
    <h1>Instalar Whispers</h1>
    <p class="lead-sm">Esto se hace una sola vez. Al terminar ya podés crear clientes desde el panel.</p>

    <?php if ($errores): ?><div class="alerta alerta-error" role="alert"><?php foreach ($errores as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div><?php endif; ?>

    <form method="post" class="form-col">
      <fieldset class="grupo">
        <legend>Base de datos MySQL</legend>
        <div class="form-grid">
          <div class="campo"><label for="h">Servidor</label><input id="h" name="db_host" value="<?= e($v['db_host']) ?>" required></div>
          <div class="campo"><label for="pt">Puerto</label><input id="pt" name="db_port" value="<?= e($v['db_port']) ?>" required></div>
          <div class="campo"><label for="dn">Nombre de la base</label><input id="dn" name="db_name" value="<?= e($v['db_name']) ?>" required></div>
          <div class="campo"><label for="du">Usuario</label><input id="du" name="db_user" value="<?= e($v['db_user']) ?>" required autocomplete="off"></div>
          <div class="campo campo-full"><label for="dp">Contraseña de la base</label><input id="dp" name="db_pass" type="password" value="<?= e($v['db_pass']) ?>" autocomplete="off"></div>
        </div>
      </fieldset>
      <fieldset class="grupo">
        <legend>Tu usuario de Whispers</legend>
        <div class="form-grid">
          <div class="campo"><label for="n">Tu nombre</label><input id="n" name="nombre" value="<?= e($v['nombre']) ?>" required></div>
          <div class="campo"><label for="em">Correo</label><input id="em" name="email" type="email" value="<?= e($v['email']) ?>" required></div>
          <div class="campo"><label for="pw">Contraseña</label><input id="pw" name="password" type="password" minlength="8" required autocomplete="new-password"></div>
          <div class="campo"><label for="wa">Tu WhatsApp de ventas</label><input id="wa" name="whatsapp" inputmode="tel" value="<?= e($v['whatsapp']) ?>" placeholder="50255551234"><small class="muted">A este número llegan los mensajes de la página principal.</small></div>
        </div>
      </fieldset>
      <label class="check-linea"><input type="checkbox" name="demo" value="1" <?= !empty($v['demo']) ? 'checked' : '' ?>> Crear un “Restaurante Demo” con encuestas de ejemplo para mostrar a prospectos</label>
      <button class="btn btn-grande" type="submit">Instalar</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
