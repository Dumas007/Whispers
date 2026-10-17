<?php
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/includes/extras.php';
[$u, $n] = requiere_negocio();
$admin = es_admin($u);
$multiPlan = plan_permite($n, 'multisucursal');
$limite = $multiPlan ? PLANES['premium']['sucursales'] : PLANES[$n['plan']]['sucursales'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $volver = 'admin/sucursales.php' . (isset($_POST['s']) ? '?s=' . (int)$_POST['s'] : '');

    if (!$admin) {
        flash('error', 'Solo el administrador puede hacer cambios aquí.');
        redirigir($volver);
    }
    $sucOk = fn(int $sid) => (bool)q('SELECT 1 FROM sucursales WHERE id = ? AND negocio_id = ?', [$sid, $n['id']])->fetch();
    $mesaOk = fn(int $mid) => (bool)q('SELECT 1 FROM mesas WHERE id = ? AND negocio_id = ?', [$mid, $n['id']])->fetch();

    if ($accion === 'nueva_sucursal') {
        $cuantas = (int)q('SELECT COUNT(*) FROM sucursales WHERE negocio_id = ?', [$n['id']])->fetchColumn();
        $nombre = trim(mb_substr((string)($_POST['nombre'] ?? ''), 0, 120));
        if ($cuantas >= $limite) {
            flash('error', 'Tu plan permite ' . $limite . ' sucursal' . ($limite === 1 ? '' : 'es') . '. Para agregar más, pasate al plan Premium.');
        } elseif ($nombre === '') {
            flash('error', 'Escribí el nombre de la sucursal.');
        } else {
            q('INSERT INTO sucursales (negocio_id, nombre, direccion) VALUES (?, ?, ?)', [$n['id'], $nombre, trim((string)($_POST['direccion'] ?? '')) ?: null]);
            $nuevo = (int)db()->lastInsertId();
            $mesas = max(1, min(100, (int)($_POST['mesas'] ?? 1)));
            for ($i = 1; $i <= $mesas; $i++) {
                q('INSERT INTO mesas (negocio_id, sucursal_id, numero, nombre, token) VALUES (?, ?, ?, ?, ?)', [$n['id'], $nuevo, $i, 'Mesa ' . $i, token_mesa()]);
            }
            flash('ok', "Sucursal $nombre creada con $mesas mesa" . ($mesas === 1 ? '' : 's') . '.');
            $volver = 'admin/sucursales.php?s=' . $nuevo;
        }
    } elseif ($accion === 'editar_sucursal' && $sucOk($id)) {
        $nombre = trim(mb_substr((string)($_POST['nombre'] ?? ''), 0, 120));
        $google = trim((string)($_POST['google_review_url'] ?? ''));
        if ($nombre === '') {
            flash('error', 'La sucursal necesita un nombre.');
        } elseif ($google !== '' && !filter_var($google, FILTER_VALIDATE_URL)) {
            flash('error', 'El link de reseñas de Google no es válido. Copialo completo, empezando con https://');
        } else {
            q('UPDATE sucursales SET nombre = ?, direccion = ?, google_review_url = ? WHERE id = ?', [$nombre, trim((string)($_POST['direccion'] ?? '')) ?: null, $google ?: null, $id]);
            flash('ok', 'Datos de la sucursal guardados.');
        }
    } elseif ($accion === 'toggle_sucursal' && $sucOk($id)) {
        q('UPDATE sucursales SET activa = 1 - activa WHERE id = ?', [$id]);
        flash('ok', 'Estado de la sucursal actualizado.');
    } elseif ($accion === 'nueva_mesa' && $sucOk($id)) {
        $cant = max(1, min(50, (int)($_POST['cantidad'] ?? 1)));
        $num = (int)q('SELECT COALESCE(MAX(numero), 0) FROM mesas WHERE sucursal_id = ?', [$id])->fetchColumn();
        for ($i = 1; $i <= $cant; $i++) {
            $num++;
            q('INSERT INTO mesas (negocio_id, sucursal_id, numero, nombre, token) VALUES (?, ?, ?, ?, ?)', [$n['id'], $id, $num, 'Mesa ' . $num, token_mesa()]);
        }
        flash('ok', $cant === 1 ? "Mesa $num agregada." : "$cant mesas agregadas.");
    } elseif ($accion === 'renombrar' && $mesaOk($id)) {
        q('UPDATE mesas SET nombre = ? WHERE id = ?', [trim(mb_substr((string)($_POST['nombre'] ?? ''), 0, 60)) ?: null, $id]);
        flash('ok', 'Nombre de la mesa actualizado.');
    } elseif ($accion === 'toggle_mesa' && $mesaOk($id)) {
        q('UPDATE mesas SET activa = 1 - activa WHERE id = ?', [$id]);
        flash('ok', 'Estado de la mesa actualizado.');
    } elseif ($accion === 'regenerar' && $mesaOk($id)) {
        q('UPDATE mesas SET token = ? WHERE id = ?', [token_mesa(), $id]);
        flash('ok', 'QR nuevo generado. El QR anterior de esa mesa ya no funciona: imprimí el nuevo.');
    }
    redirigir($volver);
}

$sucVis = sucursales_visibles($u, $n);
if (!$sucVis) {
    flash('error', 'No hay sucursales asignadas a tu usuario.');
    redirigir('admin/dashboard.php');
}
$sel = filtro_sucursal($u, $sucVis) ?? (int)$sucVis[0]['id'];
$suc = null;
foreach ($sucVis as $s) if ((int)$s['id'] === $sel) $suc = $s;
$mesas = q('SELECT m.*, (SELECT COUNT(*) FROM encuestas e WHERE e.mesa_id = m.id) total FROM mesas m WHERE m.sucursal_id = ? ORDER BY m.numero', [$sel])->fetchAll();

$varsQr = plan_permite($n, 'marca') ? vars_marca($n) : '';
$titulo = $multiPlan ? 'Sucursales y QR' : 'Mesas y QR';
$seccion = 'sucursales';
$scripts_extra = '<script src="' . e(url('assets/vendor/qrcode.min.js')) . '"></script><script>whQRs();</script>';
include dirname(__DIR__) . '/includes/admin_header.php';
?>
<p class="muted intro-seccion">Cada mesa tiene su propio QR, así sabés de dónde viene cada opinión. Descargalo, imprimilo y ponelo en un portacartas o junto a la cuenta.</p>

<?php if (count($sucVis) > 1 || ($admin && $multiPlan)): ?>
  <nav class="tabs" aria-label="Sucursales">
    <?php foreach ($sucVis as $s): ?>
      <a href="?s=<?= (int)$s['id'] ?>" class="<?= (int)$s['id'] === $sel ? 'on' : '' ?> <?= (int)$s['activa'] ? '' : 'inactivo' ?>"><?= e($s['nombre']) ?></a>
    <?php endforeach; ?>
    <?php if ($admin && $multiPlan): ?><a href="#nueva-sucursal" class="tab-mas">+ Nueva sucursal</a><?php endif; ?>
  </nav>
<?php endif; ?>

<?php if ($mesas && count(array_filter($mesas, fn($x) => (int)$x['activa'])) > 1): ?>
  <div class="barra-acciones"><button class="btn btn-sm btn-borde" type="button" data-descargar-todos>Descargar todos los QR</button></div>
<?php endif; ?>

<section class="qr-grid">
  <?php foreach ($mesas as $m): $link = url_absoluta('encuesta.php?m=' . $m['token']); ?>
    <article class="qr-card <?= (int)$m['activa'] ? '' : 'inactivo' ?>">
      <div class="qr-print" id="print-<?= (int)$m['id'] ?>" style="<?= e($varsQr) ?>">
        <?php if ($n['logo'] && plan_permite($n, 'marca')): ?><img class="qr-logo" src="<?= e(url('uploads/logos/' . $n['logo'])) ?>" alt=""><?php endif; ?>
        <span class="qr-negocio"><?= e($n['nombre']) ?></span>
        <div class="qr" data-qr="<?= e($link) ?>" data-nombre="qr-<?= e(preg_replace('/[^a-z0-9]+/', '-', strtolower($suc['nombre']))) ?>-mesa-<?= (int)$m['numero'] ?>"></div>
        <strong class="qr-mesa"><?= e($m['nombre'] ?: 'Mesa ' . $m['numero']) ?></strong>
        <span class="qr-cta">Escaneá y contanos cómo te fue</span>
      </div>
      <div class="qr-info">
        <p class="muted"><?= (int)$m['total'] ?> encuestas<?= (int)$m['activa'] ? '' : ' · Desactivada' ?></p>
        <div class="copiar"><input readonly value="<?= e($link) ?>" aria-label="Link de la encuesta"><button class="btn btn-sm btn-borde" type="button" data-copiar="<?= e($link) ?>">Copiar</button></div>
        <div class="qr-acciones">
          <button class="btn btn-sm" type="button" data-descargar-qr="<?= (int)$m['id'] ?>">Descargar QR</button>
          <a class="btn btn-sm btn-borde" href="<?= e($link) ?>" target="_blank" rel="noopener">Probar</a>
        </div>
        <?php if (plan_permite($n, 'tablet')): $linkK = $link . '&k=' . firma_kiosko($m['token']); ?>
        <details class="mas-opciones">
          <summary>📱 Modo tablet de mostrador</summary>
          <p class="muted" style="font-size:.85rem">Abrí este link en una tablet junto a la caja. Después de cada respuesta se limpia sola para el siguiente cliente.</p>
          <div class="copiar"><input readonly value="<?= e($linkK) ?>" aria-label="Link para tablet"><button class="btn btn-sm btn-borde" type="button" data-copiar="<?= e($linkK) ?>">Copiar</button></div>
          <a class="btn btn-sm btn-borde" href="<?= e($linkK) ?>" target="_blank" rel="noopener">Abrir en modo tablet</a>
        </details>
        <?php endif; ?>
        <?php if ($admin): ?>
          <details class="mas-opciones">
            <summary>Más opciones</summary>
            <form method="post" class="preg-edit">
              <?= csrf_campo() ?><input type="hidden" name="accion" value="renombrar"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="s" value="<?= $sel ?>">
              <input name="nombre" value="<?= e($m['nombre']) ?>" maxlength="60" aria-label="Nombre de la mesa" placeholder="Ej.: Terraza 1">
              <button class="btn btn-sm btn-borde" type="submit">Renombrar</button>
            </form>
            <div class="qr-acciones">
              <form method="post" class="inline"><?= csrf_campo() ?><input type="hidden" name="accion" value="toggle_mesa"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="s" value="<?= $sel ?>"><button class="link-btn" type="submit"><?= (int)$m['activa'] ? 'Desactivar mesa' : 'Activar mesa' ?></button></form>
              <form method="post" class="inline" data-confirmar="El QR impreso de esta mesa dejará de funcionar. ¿Generar uno nuevo?"><?= csrf_campo() ?><input type="hidden" name="accion" value="regenerar"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="s" value="<?= $sel ?>"><button class="link-btn txt-mal" type="submit">Generar QR nuevo</button></form>
            </div>
          </details>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>

  <?php if ($admin): ?>
    <form method="post" class="qr-agregar">
      <?= csrf_campo() ?><input type="hidden" name="accion" value="nueva_mesa"><input type="hidden" name="id" value="<?= $sel ?>"><input type="hidden" name="s" value="<?= $sel ?>">
      <span class="qr-agregar-ico" aria-hidden="true">+</span>
      <label class="select-inline">Agregar <input type="number" name="cantidad" value="1" min="1" max="50"> mesa(s)</label>
      <button class="btn btn-sm" type="submit">Agregar</button>
    </form>
  <?php endif; ?>
</section>

<?php if ($admin): ?>
<section class="grid-2">
  <article class="panel">
    <header class="panel-head"><h2>Datos de <?= e($suc['nombre']) ?></h2></header>
    <form method="post" class="form-col">
      <?= csrf_campo() ?><input type="hidden" name="accion" value="editar_sucursal"><input type="hidden" name="id" value="<?= $sel ?>"><input type="hidden" name="s" value="<?= $sel ?>">
      <div class="campo"><label for="sn">Nombre</label><input id="sn" name="nombre" value="<?= e($suc['nombre']) ?>" required></div>
      <div class="campo"><label for="sd">Dirección</label><input id="sd" name="direccion" value="<?= e($suc['direccion']) ?>"></div>
      <div class="campo">
        <label for="sg">Link para dejar reseña en Google</label>
        <input id="sg" name="google_review_url" type="url" value="<?= e($suc['google_review_url']) ?>" placeholder="https://g.page/r/.../review">
        <small class="muted"><?= plan_permite($n, 'resenas') ? 'Se lo mostramos solo a clientes que calificaron con ' . number_format($n['umbral_resena'], 1) . ' o más. Lo sacás desde tu Perfil de Empresa en Google, en “Pedir reseñas”.' : 'La invitación a reseñar en Google está incluida desde el plan Profesional.' ?></small>
      </div>
      <button class="btn" type="submit">Guardar</button>
    </form>
    <?php if (count($sucVis) > 1): ?>
      <form method="post" class="eliminar-enc" data-confirmar="<?= (int)$suc['activa'] ? '¿Pausar esta sucursal? Sus QR dejarán de recibir encuestas.' : '¿Reactivar esta sucursal?' ?>">
        <?= csrf_campo() ?><input type="hidden" name="accion" value="toggle_sucursal"><input type="hidden" name="id" value="<?= $sel ?>"><input type="hidden" name="s" value="<?= $sel ?>">
        <button class="link-btn <?= (int)$suc['activa'] ? 'txt-mal' : '' ?>" type="submit"><?= (int)$suc['activa'] ? 'Pausar sucursal' : 'Reactivar sucursal' ?></button>
      </form>
    <?php endif; ?>
  </article>

  <?php if ($multiPlan): ?>
    <article class="panel" id="nueva-sucursal">
      <header class="panel-head"><h2>Nueva sucursal</h2></header>
      <form method="post" class="form-col">
        <?= csrf_campo() ?><input type="hidden" name="accion" value="nueva_sucursal">
        <div class="campo"><label for="nn">Nombre</label><input id="nn" name="nombre" required placeholder="Ej.: Sucursal Cayalá"></div>
        <div class="campo"><label for="nd">Dirección</label><input id="nd" name="direccion"></div>
        <div class="campo"><label for="nm">Cantidad de mesas</label><input id="nm" name="mesas" type="number" min="1" max="100" value="5"></div>
        <button class="btn" type="submit">Crear sucursal</button>
      </form>
    </article>
  <?php else: ?>
    <?php $upsell_plan = 'premium'; $upsell_titulo = '¿Tenés más de una sucursal?'; $upsell_texto = 'Con el plan Premium manejás todas tus sucursales en un solo panel y las comparás entre sí.'; include dirname(__DIR__) . '/includes/upsell.php'; ?>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
