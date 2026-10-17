<?php
require dirname(__DIR__) . '/config.php';
$u = requiere_login(['super']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'entrar') {
        $nid = (int)($_POST['id'] ?? 0);
        if (q('SELECT 1 FROM negocios WHERE id = ?', [$nid])->fetch()) {
            $_SESSION['ver_negocio'] = $nid;
            redirigir('admin/dashboard.php');
        }
    } elseif ($accion === 'salir_negocio') {
        unset($_SESSION['ver_negocio']);
        redirigir('super/index.php');
    } elseif ($accion === 'crear') {
        $d = [
            'nombre' => trim(mb_substr((string)($_POST['nombre'] ?? ''), 0, 120)),
            'rubro' => trim(mb_substr((string)($_POST['rubro'] ?? ''), 0, 60)),
            'plan' => array_key_exists($_POST['plan'] ?? '', PLANES) ? $_POST['plan'] : 'basico',
            'sucursal' => trim(mb_substr((string)($_POST['sucursal'] ?? ''), 0, 120)),
            'mesas' => (int)($_POST['mesas'] ?? 2),
            'admin_nombre' => trim((string)($_POST['admin_nombre'] ?? '')),
            'admin_email' => strtolower(trim((string)($_POST['admin_email'] ?? ''))),
            'admin_password' => (string)($_POST['admin_password'] ?? ''),
            'alerta_email' => strtolower(trim((string)($_POST['admin_email'] ?? ''))) ?: null,
            'proximo_cobro' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['proximo_cobro'] ?? '') ? $_POST['proximo_cobro'] : date('Y-m-d', strtotime('+1 month')),
        ];
        if ($d['nombre'] === '' || $d['admin_nombre'] === '' || !filter_var($d['admin_email'], FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Completá el nombre del negocio y el nombre y correo del dueño.');
        } elseif (strlen($d['admin_password']) < 8) {
            flash('error', 'La contraseña del dueño necesita al menos 8 caracteres.');
        } elseif (q('SELECT 1 FROM usuarios WHERE email = ?', [$d['admin_email']])->fetch()) {
            flash('error', 'Ese correo ya tiene un usuario en Whispers.');
        } else {
            $nid = crear_negocio($d);
            if (!empty($_POST['demo'])) sembrar_demo($nid, 50);
            $_SESSION['credenciales'] = ['negocio' => $d['nombre'], 'email' => $d['admin_email'], 'password' => $d['admin_password'], 'link' => url_absoluta('login.php')];
            flash('ok', 'Cliente ' . $d['nombre'] . ' creado con ' . max(1, $d['mesas']) . ' mesas y las preguntas base.');
        }
    }
    redirigir('super/index.php');
}

$cred = $_SESSION['credenciales'] ?? null;
unset($_SESSION['credenciales']);

$negocios = db()->query("SELECT n.*,
      (SELECT COUNT(*) FROM sucursales s WHERE s.negocio_id = n.id) sucursales,
      (SELECT COUNT(*) FROM mesas m WHERE m.negocio_id = n.id) mesas,
      (SELECT COUNT(*) FROM encuestas e WHERE e.negocio_id = n.id AND e.creado_en >= DATE_SUB(NOW(), INTERVAL 30 DAY)) enc30,
      (SELECT AVG(e.promedio) FROM encuestas e WHERE e.negocio_id = n.id AND e.creado_en >= DATE_SUB(NOW(), INTERVAL 30 DAY)) prom30,
      (SELECT MAX(e.creado_en) FROM encuestas e WHERE e.negocio_id = n.id) ultima,
      (SELECT COUNT(*) FROM negocio_funciones nf WHERE nf.negocio_id = n.id AND nf.hasta >= CURDATE()) en_prueba,
      (SELECT MIN(nf.hasta) FROM negocio_funciones nf WHERE nf.negocio_id = n.id AND nf.hasta >= CURDATE()) prueba_hasta,
      (SELECT COUNT(*) FROM negocio_funciones nf WHERE nf.negocio_id = n.id AND nf.hasta IS NULL) extra_fijas
    FROM negocios n ORDER BY n.activo DESC, n.nombre")->fetchAll();

$activos = array_filter($negocios, fn($x) => (int)$x['activo']);
$mrr = array_sum(array_map(fn($x) => PLANES[$x['plan']]['precio'], $activos));
$vencidos = array_filter($activos, fn($x) => $x['proximo_cobro'] && $x['proximo_cobro'] < date('Y-m-d'));
$enc30 = array_sum(array_column($negocios, 'enc30'));

$n = null;
$titulo = 'Clientes';
$seccion = 'clientes';
include dirname(__DIR__) . '/includes/admin_header.php';
?>
<?php if ($cred): ?>
  <div class="credenciales">
    <h2>Datos de acceso para <?= e($cred['negocio']) ?></h2>
    <p class="muted">Copialos y mandáselos al cliente. Esta es la única vez que se muestra la contraseña.</p>
    <?php $txtCred = "¡Bienvenido a Whispers!\n\nEntrá a tu panel aquí: {$cred['link']}\nCorreo: {$cred['email']}\nContraseña: {$cred['password']}\n\nTe recomiendo cambiar la contraseña en “Mi cuenta” la primera vez que entrés."; ?>
    <pre><?= e($txtCred) ?></pre>
    <button class="btn btn-sm" type="button" data-copiar="<?= e($txtCred) ?>">Copiar mensaje</button>
  </div>
<?php endif; ?>

<section class="kpis">
  <div class="kpi kpi-principal nota-ok"><span class="kpi-lbl">Ingreso mensual</span><span class="kpi-num"><?= quetzales($mrr) ?></span><span class="kpi-sub"><?= count($activos) ?> clientes activos</span></div>
  <div class="kpi"><span class="kpi-lbl">Clientes</span><span class="kpi-num"><?= count($negocios) ?></span><span class="kpi-sub"><?= count($negocios) - count($activos) ?> pausados</span></div>
  <div class="kpi"><span class="kpi-lbl">Encuestas (30 días)</span><span class="kpi-num"><?= $enc30 ?></span><span class="kpi-sub">entre todos los clientes</span></div>
  <div class="kpi kpi-alerta <?= $vencidos ? 'hay' : '' ?>"><span class="kpi-lbl">Pagos vencidos</span><span class="kpi-num"><?= count($vencidos) ?></span><span class="kpi-sub"><?= $vencidos ? quetzales(array_sum(array_map(fn($x) => PLANES[$x['plan']]['precio'], $vencidos))) . ' por cobrar' : 'Todo al día' ?></span></div>
</section>

  <details class="panel nuevo-cliente" <?= $negocios ? '' : 'open' ?>>
    <summary><span class="btn btn-sm">+ Nuevo cliente</span></summary>
    <form method="post" class="form-col form-nuevo">
      <?= csrf_campo() ?><input type="hidden" name="accion" value="crear">
      <div class="campo"><label for="nn">Nombre del negocio</label><input id="nn" name="nombre" required></div>
      <div class="campo"><label for="rb">Tipo de negocio</label><input id="rb" name="rubro" placeholder="Restaurante, café, barbería…"></div>
      <div class="campo"><label for="pl">Plan</label><select id="pl" name="plan"><?php foreach (PLANES as $k => $pl): ?><option value="<?= $k ?>" <?= $k === 'profesional' ? 'selected' : '' ?>><?= $pl['nombre'] ?> — <?= quetzales($pl['precio']) ?>/mes</option><?php endforeach; ?></select></div>
      <div class="form-grid">
        <div class="campo"><label for="su">Primera sucursal</label><input id="su" name="sucursal" placeholder="Sucursal principal"></div>
        <div class="campo"><label for="me">Mesas</label><input id="me" name="mesas" type="number" min="1" max="100" value="2"></div>
      </div>
      <div class="campo"><label for="pc">Primer cobro mensual</label><input id="pc" name="proximo_cobro" type="date" value="<?= date('Y-m-d', strtotime('+1 month')) ?>"></div>
      <h3 class="form-tit">Usuario del dueño</h3>
      <div class="campo"><label for="an">Nombre</label><input id="an" name="admin_nombre" required></div>
      <div class="campo"><label for="ae">Correo</label><input id="ae" name="admin_email" type="email" required></div>
      <div class="campo"><label for="ap">Contraseña inicial</label>
        <div class="copiar"><input id="ap" name="admin_password" minlength="8" required autocomplete="off"><button class="btn btn-sm btn-borde" type="button" data-generar="ap">Generar</button></div>
      </div>
      <label class="check-linea"><input type="checkbox" name="demo" value="1"> Llenar con encuestas de ejemplo (para demos)</label>
      <button class="btn" type="submit">Crear cliente</button>
    </form>
  </details>
  <article class="panel">
    <header class="panel-head"><h2>Tus clientes</h2></header>
    <?php if (!$negocios): ?><p class="muted">Todavía no hay clientes. Creá el primero con el formulario.</p><?php else: ?>
    <div class="tabla-wrap">
      <table class="tabla">
        <thead><tr><th>Negocio</th><th>Plan</th><th>Últimos 30 días</th><th>Próximo pago</th><th><span class="sr">Acciones</span></th></tr></thead>
        <tbody>
        <?php foreach ($negocios as $x): $venc = (int)$x['activo'] && $x['proximo_cobro'] && $x['proximo_cobro'] < date('Y-m-d'); ?>
          <tr class="<?= (int)$x['activo'] ? '' : 'inactivo' ?>">
            <td><strong><?= e($x['nombre']) ?></strong><br><small class="muted"><?= e($x['rubro'] ?: 'Sin rubro') ?> · <?= (int)$x['sucursales'] ?> suc. · <?= (int)$x['mesas'] ?> mesas<?= (int)$x['activo'] ? '' : ' · Pausado' ?></small></td>
            <td><span class="plan-chip plan-<?= e($x['plan']) ?>"><?= e(plan_nombre($x['plan'])) ?></span>
              <?php if ((int)$x['en_prueba']): ?><br><span class="chip-prueba">🎁 <?= (int)$x['en_prueba'] ?> en prueba · hasta <?= e(date('d/m', strtotime($x['prueba_hasta']))) ?></span><?php endif; ?>
              <?php if ((int)$x['extra_fijas']): ?><br><span class="chip-prueba chip-fija">+<?= (int)$x['extra_fijas'] ?> extra</span><?php endif; ?></td>
            <td class="nowrap"><?= (int)$x['enc30'] ?> encuestas<?php if ($x['prom30'] !== null): ?> · <span class="pill nota-<?= clase_nota((float)$x['prom30'], (float)$x['umbral_alerta']) ?>"><?= nota((float)$x['prom30']) ?></span><?php endif; ?>
              <br><small class="muted"><?= $x['ultima'] ? 'Última: ' . e(fecha_corta($x['ultima'])) : 'Sin encuestas aún' ?></small></td>
            <td class="nowrap"><?= $x['proximo_cobro'] ? '<span class="' . ($venc ? 'txt-mal' : '') . '">' . e(date('j/n/Y', strtotime($x['proximo_cobro']))) . ($venc ? ' · vencido' : '') . '</span>' : '—' ?></td>
            <td class="acciones"><div class="acciones-col">
              <form method="post"><?= csrf_campo() ?><input type="hidden" name="accion" value="entrar"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="btn btn-sm" type="submit">Abrir panel</button></form>
              <a class="btn btn-sm btn-borde" href="<?= e(url('super/negocio.php?id=' . $x['id'])) ?>">Editar</a>
            </div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </article>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
