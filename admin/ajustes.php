<?php
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/includes/notificaciones.php';
require dirname(__DIR__) . '/includes/resumen.php';
[$u, $n] = requiere_negocio(['admin']);
$dirLogos = dirname(__DIR__) . '/uploads/logos/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'datos') {
        $nombre = trim(mb_substr((string)($_POST['nombre'] ?? ''), 0, 120));
        $was = plan_permite($n, 'alertas_whatsapp')
            ? array_map(fn($x) => preg_replace('/[^0-9]/', '', $x), lista_contactos($_POST['alerta_whatsapp'] ?? []))
            : lista_contactos($n['alerta_whatsapp']);
        $was = lista_contactos($was);
        $ems = array_map('strtolower', lista_contactos($_POST['alerta_email'] ?? []));
        $malWa = array_filter($was, fn($x) => strlen($x) < 8 || strlen($x) > 15);
        $malEm = array_filter($ems, fn($x) => !filter_var($x, FILTER_VALIDATE_EMAIL));
        $ua = round(max(1, min(4.5, (float)($_POST['umbral_alerta'] ?? 3))), 1);
        $ur = round(max(3, min(5, (float)($_POST['umbral_resena'] ?? 4))), 1);
        if ($nombre === '') {
            flash('error', 'El negocio necesita un nombre.');
        } elseif ($malEm) {
            flash('error', 'Este correo no es válido: ' . reset($malEm));
        } elseif ($malWa) {
            flash('error', 'Este WhatsApp no es válido: ' . reset($malWa) . '. Usá 8 dígitos o el número con 502.');
        } elseif (count($was) > MAX_CONTACTOS_ALERTA || count($ems) > MAX_CONTACTOS_ALERTA) {
            flash('error', 'Podés poner hasta ' . MAX_CONTACTOS_ALERTA . ' números y ' . MAX_CONTACTOS_ALERTA . ' correos.');
        } else {
            $wa = implode(',', $was);
            $em = implode(',', $ems);
            q('UPDATE negocios SET nombre = ?, rubro = ?, alerta_whatsapp = ?, alerta_email = ?, umbral_alerta = ?, umbral_resena = ? WHERE id = ?',
                [$nombre, trim(mb_substr((string)($_POST['rubro'] ?? ''), 0, 60)) ?: null, $wa ?: null, $em ?: null, $ua, $ur, $n['id']]);
            flash('ok', 'Ajustes guardados.');
        }
    } elseif (in_array($accion, ['logo', 'colores', 'colores_reset', 'quitar_logo'], true) && !plan_permite($n, 'marca')) {
        flash('error', 'El logo y los colores están incluidos en el plan Premium.');
    } elseif ($accion === 'resumen' && !plan_permite($n, 'resumen_semanal')) {
        flash('error', 'El resumen semanal está incluido en el plan Premium.');
    } elseif ($accion === 'logo') {
        $f = $_FILES['logo'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'No se recibió la imagen. Probá de nuevo.');
        } elseif ($f['size'] > 1024 * 1024) {
            flash('error', 'El logo pesa más de 1 MB. Usá una imagen más liviana.');
        } else {
            $info = @getimagesize($f['tmp_name']);
            $ext = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
            if (!$ext) {
                flash('error', 'El logo tiene que ser PNG, JPG o WEBP.');
            } else {
                if (!is_dir($dirLogos)) @mkdir($dirLogos, 0755, true);
                $nombreArchivo = 'n' . $n['id'] . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
                if (move_uploaded_file($f['tmp_name'], $dirLogos . $nombreArchivo)) {
                    if ($n['logo'] && is_file($dirLogos . $n['logo'])) @unlink($dirLogos . $n['logo']);
                    q('UPDATE negocios SET logo = ? WHERE id = ?', [$nombreArchivo, $n['id']]);
                    flash('ok', 'Logo actualizado. Ya aparece en la encuesta y en tu panel.');
                } else {
                    flash('error', 'No se pudo guardar el logo. Revisá que la carpeta uploads/logos tenga permisos de escritura.');
                }
            }
        }
    } elseif ($accion === 'colores') {
        $cp = color_valido($_POST['color_principal'] ?? '');
        $ca = color_valido($_POST['color_acento'] ?? '');
        if (!$cp || !$ca) {
            flash('error', 'Elegí los dos colores (formato #RRGGBB).');
        } else {
            q('UPDATE negocios SET color_principal = ?, color_acento = ?, colores_panel = ? WHERE id = ?', [$cp, $ca, isset($_POST['colores_panel']) ? 1 : 0, $n['id']]);
            flash('ok', 'Colores guardados. Ya se ven en tu encuesta' . (isset($_POST['colores_panel']) ? ' y en tu panel.' : '.'));
        }
    } elseif ($accion === 'colores_reset') {
        q('UPDATE negocios SET color_principal = NULL, color_acento = NULL, colores_panel = 0 WHERE id = ?', [$n['id']]);
        flash('ok', 'Volviste a los colores de Whispers.');
    } elseif ($accion === 'resumen') {
        q('UPDATE negocios SET resumen_semanal = ? WHERE id = ?', [isset($_POST['resumen_semanal']) ? 1 : 0, $n['id']]);
        flash('ok', isset($_POST['resumen_semanal']) ? 'Vas a recibir el resumen cada semana.' : 'Ya no vas a recibir el resumen semanal.');
    } elseif ($accion === 'resumen_prueba') {
        $res = enviar_resumen_semanal($n, true);
        if (!$res) flash('error', 'Primero guardá un WhatsApp o un correo para recibir alertas.');
        foreach ($res as [$canal, $dest, $ok, $det]) flash($ok ? 'ok' : 'error', ($canal === 'email' ? 'Correo ' : 'WhatsApp ') . "$dest: " . ($ok ? 'resumen enviado.' : $det));
    } elseif ($accion === 'quitar_logo') {
        if ($n['logo'] && is_file($dirLogos . $n['logo'])) @unlink($dirLogos . $n['logo']);
        q('UPDATE negocios SET logo = NULL WHERE id = ?', [$n['id']]);
        flash('ok', 'Logo quitado.');
    } elseif ($accion === 'probar') {
        $enviados = 0;
        foreach ((plan_permite($n, 'alertas_whatsapp') ? lista_contactos($n['alerta_whatsapp']) : []) as $num) {
            $r = whatsapp_enviar_plantilla($num, [$n['nombre'] . ' · Prueba', 'Mesa de prueba', '2.0/5', 'Esto es una alerta de prueba de Whispers.']);
            registrar_alerta((int)$n['id'], null, 'whatsapp', $num, $r);
            flash($r[0] ? 'ok' : 'error', "WhatsApp $num: " . ($r[0] ? 'mensaje de prueba enviado.' : $r[1]));
            $enviados++;
        }
        foreach (lista_contactos($n['alerta_email']) as $correo) {
            $r = correo_enviar($correo, 'Prueba de alertas de Whispers', "Si te llegó este correo, las alertas de {$n['nombre']} están funcionando.\n\n— Whispers");
            registrar_alerta((int)$n['id'], null, 'email', $correo, $r);
            flash($r[0] ? 'ok' : 'error', "Correo $correo: " . ($r[0] ? 'mensaje de prueba enviado.' : $r[1]));
            $enviados++;
        }
        if (!$enviados) flash('error', 'Primero guardá un WhatsApp o un correo para recibir alertas.');
    }
    redirigir('admin/ajustes.php');
}

$log = q('SELECT * FROM alertas_log WHERE negocio_id = ? ORDER BY id DESC LIMIT 8', [$n['id']])->fetchAll();
$titulo = 'Ajustes del negocio';
$seccion = 'ajustes';
include dirname(__DIR__) . '/includes/admin_header.php';
$cp = color_valido($n['color_principal']) ?: '#141A3D';
$ca = color_valido($n['color_acento']) ?: '#9D8CFF';
$paletas = [
    'Whispers'   => ['#141A3D', '#9D8CFF'],
    'Café'       => ['#4A2C2A', '#E0A458'],
    'Bosque'     => ['#1E4D3A', '#F2C14E'],
    'Vino'       => ['#7A1C2E', '#F4C9A8'],
    'Océano'     => ['#0B3D91', '#FFB400'],
    'Carbón'     => ['#222222', '#E8C547'],
    'Coral'      => ['#E85D4A', '#2B2D42'],
    'Menta'      => ['#E8F5EE', '#1FB57A'],
];
$tok = q('SELECT token FROM mesas WHERE negocio_id = ? AND activa = 1 LIMIT 1', [$n['id']])->fetchColumn();
?>
<?php if (plan_permite($n, 'marca')): ?>
<article class="panel">
  <header class="panel-head"><h2>Logo y colores de tu marca</h2><?php if ($tok): ?><a href="<?= e(url('encuesta.php?m=' . $tok)) ?>" target="_blank" rel="noopener">Ver mi encuesta</a><?php endif; ?></header>
  <div class="marca-grid">
    <div class="marca-campos">
      <div>
        <h3 class="form-tit">Logo</h3>
        <div class="logo-subir">
          <div class="logo-actual">
            <?php if ($n['logo']): ?><img src="<?= e(url('uploads/logos/' . $n['logo'])) ?>" alt="Logo actual"><?php else: ?><span class="logo-vacio"><?= e(mb_strtoupper(mb_substr($n['nombre'], 0, 1))) ?></span><?php endif; ?>
          </div>
          <form method="post" enctype="multipart/form-data" class="form-col">
            <?= csrf_campo() ?><input type="hidden" name="accion" value="logo">
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" required aria-label="Archivo del logo">
            <div class="fila-botones" style="margin-top:0">
              <button class="btn btn-sm" type="submit">Subir logo</button>
              <small class="muted">PNG, JPG o WEBP, máximo 1 MB. Mejor cuadrado.</small>
            </div>
          </form>
        </div>
        <?php if ($n['logo']): ?><form method="post"><?= csrf_campo() ?><input type="hidden" name="accion" value="quitar_logo"><button class="link-btn txt-mal" type="submit">Quitar logo</button></form><?php endif; ?>
      </div>

      <form method="post" class="form-col" id="form-colores">
        <?= csrf_campo() ?><input type="hidden" name="accion" value="colores">
        <h3 class="form-tit">Colores</h3>
        <div class="color-fila">
          <div class="campo">
            <label for="cp_txt">Color principal <small>Encabezado y botones</small></label>
            <div class="color-campo"><input type="color" id="cp" value="<?= e($cp) ?>" aria-label="Elegir color principal"><input type="text" id="cp_txt" name="color_principal" value="<?= e($cp) ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}"></div>
          </div>
          <div class="campo">
            <label for="ca_txt">Color de acento <small>Estrellas y detalles</small></label>
            <div class="color-campo"><input type="color" id="ca" value="<?= e($ca) ?>" aria-label="Elegir color de acento"><input type="text" id="ca_txt" name="color_acento" value="<?= e($ca) ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}"></div>
          </div>
        </div>
        <div>
          <p class="muted" style="margin-bottom:.5rem">O elegí una combinación lista:</p>
          <div class="paletas">
            <?php foreach ($paletas as $nom => [$a, $b]): ?>
              <button type="button" class="paleta" data-p="<?= $a ?>" data-a="<?= $b ?>"><i style="background:<?= $a ?>"></i><i style="background:<?= $b ?>"></i><?= e($nom) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
        <label class="check-linea"><input type="checkbox" name="colores_panel" value="1" <?= (int)$n['colores_panel'] ? 'checked' : '' ?>> Usar estos colores también en mi panel</label>
        <div class="fila-botones" style="margin-top:0">
          <button class="btn" type="submit">Guardar colores</button>
        </div>
      </form>
      <?php if ($n['color_principal'] || $n['color_acento']): ?>
        <form method="post"><?= csrf_campo() ?><input type="hidden" name="accion" value="colores_reset"><button class="link-btn" type="submit">Volver a los colores de Whispers</button></form>
      <?php endif; ?>
    </div>

    <div class="vista" id="vista" aria-label="Vista previa de la encuesta">
      <p class="vista-tit">Así la ven tus clientes</p>
      <div class="vista-tel">
        <div class="vista-pant">
          <div class="vista-top">
            <span><?php if ($n['logo']): ?><img src="<?= e(url('uploads/logos/' . $n['logo'])) ?>" alt=""><?php endif; ?><?= e(texto_corto($n['nombre'], 18)) ?></span>
            <span class="vista-chip">Mesa 1</span>
          </div>
          <div class="vista-cuerpo">
            <h4>Contanos cómo te fue</h4>
            <?php foreach ([[4, '¿Qué tal estuvo la comida?'], [5, '¿Cómo te atendió el personal?']] as [$on, $txt]): ?>
              <div class="vista-preg"><?= e($txt) ?>
                <div class="vista-est"><?php for ($k = 1; $k <= 5; $k++): ?><svg viewBox="0 0 24 24" class="<?= $k <= $on ? 'on' : '' ?>"><path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4L2.8 9.5l6.4-.8z"/></svg><?php endfor; ?></div>
              </div>
            <?php endforeach; ?>
            <div class="vista-btn">Enviar mi opinión</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>
<?php else: ?>
  <?php $upsell_plan = 'premium'; $upsell_titulo = 'Tu encuesta con tu logo y tus colores'; $upsell_texto = 'Con el plan Premium la encuesta, los QR y tu panel llevan tu marca, y tus clientes sienten que es de tu negocio.'; include dirname(__DIR__) . '/includes/upsell.php'; ?>
<?php endif; ?>

<section class="grid-lateral">
  <article class="panel">
    <form method="post" class="form-col">
      <?= csrf_campo() ?><input type="hidden" name="accion" value="datos">
      <h2 class="form-tit">Tu negocio</h2>
      <div class="form-grid">
        <div class="campo"><label for="nn">Nombre del negocio</label><input id="nn" name="nombre" value="<?= e($n['nombre']) ?>" required><small class="muted">Así aparece en la encuesta y en los QR.</small></div>
        <div class="campo"><label for="rb">Tipo de negocio</label><input id="rb" name="rubro" value="<?= e($n['rubro']) ?>" placeholder="Restaurante, café, barbería…"></div>
      </div>

      <h2 class="form-tit">Alertas de calificaciones bajas</h2>
      <p class="muted">Cuando un cliente califica bajo, te avisamos al momento por estos medios.</p>
      <div class="form-grid">
        <?php $camposAlerta = ['alerta_whatsapp' => ['WhatsApp que reciben alertas', 'tel', '50255551234', 'Agregar otro número'], 'alerta_email' => ['Correos que reciben alertas', 'email', 'gerente@minegocio.com', 'Agregar otro correo']];
          if (!plan_permite($n, 'alertas_whatsapp')) unset($camposAlerta['alerta_whatsapp']);
          if (!plan_permite($n, 'alertas_whatsapp')): ?>
          <div class="campo bloqueado"><label>WhatsApp que reciben alertas</label><p class="muted">Las alertas por WhatsApp están incluidas desde el plan Profesional. Mientras tanto te llegan al panel y por correo.</p></div>
        <?php endif;
          foreach ($camposAlerta as $campo => [$lbl, $tipo, $ph, $mas]):
            $vals = lista_contactos($n[$campo]); if (!$vals) $vals = ['']; ?>
          <div class="campo multi" data-max="<?= MAX_CONTACTOS_ALERTA ?>">
            <label><?= $lbl ?> <small>Hasta <?= MAX_CONTACTOS_ALERTA ?></small></label>
            <div class="multi-lista">
              <?php foreach ($vals as $val): ?>
                <div class="multi-fila">
                  <input name="<?= $campo ?>[]" type="<?= $tipo === 'tel' ? 'text' : 'email' ?>" <?= $tipo === 'tel' ? 'inputmode="tel"' : '' ?> value="<?= e($val) ?>" placeholder="<?= $ph ?>" aria-label="<?= $lbl ?>">
                  <button type="button" class="multi-quitar" aria-label="Quitar" title="Quitar">×</button>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="link-btn multi-mas">+ <?= $mas ?></button>
          </div>
        <?php endforeach; ?>
        <div class="campo">
          <label for="ua">Avisar cuando la nota sea de</label>
          <select id="ua" name="umbral_alerta"><?php foreach ([2.0, 2.5, 3.0, 3.5] as $o): ?><option value="<?= $o ?>" <?= abs($n['umbral_alerta'] - $o) < .01 ? 'selected' : '' ?>><?= number_format($o, 1) ?> o menos</option><?php endforeach; ?></select>
        </div>
        <div class="campo">
          <label for="ur">Pedir reseña en Google con nota de</label>
          <select id="ur" name="umbral_resena"><?php foreach ([4.0, 4.5, 5.0] as $o): ?><option value="<?= $o ?>" <?= abs($n['umbral_resena'] - $o) < .01 ? 'selected' : '' ?>><?= number_format($o, 1) ?> o más</option><?php endforeach; ?></select>
          <?php if (!plan_permite($n, 'resenas')): ?><small class="muted">Disponible desde el plan Profesional.</small><?php endif; ?>
        </div>
      </div>
      <div class="fila-botones"><button class="btn" type="submit">Guardar ajustes</button></div>
    </form>
    <form method="post" class="fila-botones">
      <?= csrf_campo() ?><input type="hidden" name="accion" value="probar">
      <button class="btn btn-sm btn-borde" type="submit">Enviar alerta de prueba</button>
      <?php if (!whatsapp_configurado() && $n['alerta_whatsapp']): ?><small class="muted">Las alertas por WhatsApp se activan cuando Whispers conecte la API de WhatsApp. Mientras tanto, las recibís en el panel y por correo.</small><?php endif; ?>
    </form>
    <?php if ($log): ?>
      <div class="log-alertas">
        <h3>Últimos avisos</h3>
        <?php foreach ($log as $l): ?>
          <p><span class="estado <?= (int)$l['ok'] ? 'ok' : 'mal' ?>"><?= (int)$l['ok'] ? 'Enviado' : 'Falló' ?></span> <?= e(fecha_corta($l['creado_en'])) ?> · <?= e(ucfirst($l['canal'])) ?> a <?= e($l['destino']) ?><?= (int)$l['ok'] ? '' : ' — ' . e(texto_corto((string)$l['detalle'], 120)) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>

  <div class="col-lateral">
  <?php if (plan_permite($n, 'resumen_semanal')): ?>
  <article class="panel">
    <header class="panel-head"><h2>📊 Resumen semanal</h2></header>
    <p class="muted" style="margin-bottom:.8rem">Cada lunes te llega a los mismos WhatsApp y correos de alertas: tu nota, lo mejor, lo que hay que mejorar y la estrella del equipo.</p>
    <form method="post"><?= csrf_campo() ?><input type="hidden" name="accion" value="resumen">
      <label class="interruptor"><input type="checkbox" name="resumen_semanal" value="1" <?= (int)$n['resumen_semanal'] ? 'checked' : '' ?> onchange="this.form.submit()"><span></span> Recibir el resumen cada semana</label></form>
    <details class="mas-opciones" style="margin-top:.8rem">
      <summary>Ver cómo se vería hoy</summary>
      <pre class="resumen-vista"><?= e(resumen_semanal_datos($n)['texto']) ?></pre>
      <form method="post"><?= csrf_campo() ?><input type="hidden" name="accion" value="resumen_prueba"><button class="btn btn-sm btn-borde" type="submit">Enviármelo ahora</button></form>
    </details>
  </article>
  <?php else: ?>
  <article class="panel teaser"><header class="panel-head"><h2>📊 Resumen semanal</h2><i class="candado">Premium</i></header><p class="muted">Cada lunes por WhatsApp y correo: tu nota, lo mejor, lo que hay que mejorar y la estrella del equipo.</p></article>
  <?php endif; ?>
  <article class="panel plan-actual">
    <header class="panel-head"><h2>Tu plan</h2></header>
    <p class="plan-nombre"><?= e(plan_nombre($n['plan'])) ?> <span><?= quetzales(PLANES[$n['plan']]['precio']) ?>/mes</span></p>
    <?php if ($n['proximo_cobro']): ?><p class="muted">Próximo pago: <?= e(fecha_larga($n['proximo_cobro'])) ?></p><?php endif; ?>
    <?php if ($n['plan'] !== 'premium' && ($ventas = ajuste('whatsapp_ventas'))): ?>
      <a class="btn btn-sm btn-verde" target="_blank" rel="noopener" href="<?= e(whatsapp_link($ventas, 'Hola, soy de ' . $n['nombre'] . ' y quiero cambiar mi plan de Whispers.')) ?>">Cambiar de plan</a>
    <?php endif; ?>
  </article>
  </div>
</section>
<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
