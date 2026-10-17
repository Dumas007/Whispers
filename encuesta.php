<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/notificaciones.php';
require __DIR__ . '/includes/extras.php';

$token = (string)($_GET['m'] ?? '');
$mesa = q('SELECT m.*, s.nombre AS sucursal, s.google_review_url, s.activa AS sucursal_activa,
                  n.nombre AS negocio, n.logo, n.plan, n.color_principal, n.color_acento,
                  n.preguntar_personal, n.activo AS negocio_activo, n.umbral_alerta, n.umbral_resena
             FROM mesas m JOIN sucursales s ON s.id = m.sucursal_id JOIN negocios n ON n.id = m.negocio_id
            WHERE m.token = ?', [$token])->fetch();
$disponible = $mesa && (int)$mesa['activa'] && (int)$mesa['sucursal_activa'] && (int)$mesa['negocio_activo'];
$lang = $disponible && negocio_permite((int)$mesa['negocio_id'], $mesa['plan'], 'ingles') ? idioma_encuesta() : 'es';
$premium = $disponible && negocio_permite((int)$mesa['negocio_id'], $mesa['plan'], 'pregunta_especial');

// Modo tablet de mostrador: link firmado, sin límite de 10 minutos y se reinicia solo
$kiosko = $disponible && negocio_permite((int)$mesa['negocio_id'], $mesa['plan'], 'tablet') && isset($_GET['k']) && hash_equals(firma_kiosko($token), (string)$_GET['k']);
$base = 'encuesta.php?m=' . urlencode($token) . ($kiosko ? '&k=' . urlencode((string)$_GET['k']) : '');

$preguntas = $disponible ? q('SELECT id, texto, texto_en, especial FROM preguntas WHERE negocio_id = ? AND activa = 1 AND (hasta IS NULL OR hasta >= CURDATE())' . ($premium ? '' : ' AND especial = 0') . '
                               ORDER BY especial DESC, orden, id', [$mesa['negocio_id']])->fetchAll() : [];
$personal = ($disponible && (int)$mesa['preguntar_personal'] && negocio_permite((int)$mesa['negocio_id'], $mesa['plan'], 'personal'))
    ? q('SELECT id, nombre, puesto, foto FROM personal WHERE negocio_id = ? AND activo = 1 AND (sucursal_id IS NULL OR sucursal_id = ?) ORDER BY nombre',
        [$mesa['negocio_id'], $mesa['sucursal_id']])->fetchAll()
    : [];
$errores = [];
$viejo = $_POST;

if ($disponible && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $volver = $base . '&ok=1';

    if (!empty($_POST['sitio_web'])) {
        $_SESSION['gracias'] = ['promedio' => 5.0, 'premio' => null];
        redirigir($volver);
    }
    $clave = 'ultima_' . $mesa['id'];
    if (!$kiosko && !empty($_SESSION[$clave]) && time() - $_SESSION[$clave] < 600) {
        $errores[] = tx('ya_enviada', $lang);
    }
    $notas = [];
    foreach ($preguntas as $p) {
        $val = (int)($_POST['p'][$p['id']] ?? 0);
        if ($val < 1 || $val > 5) { $errores[] = tx('califica_todo', $lang); break; }
        $notas[(int)$p['id']] = $val;
    }
    if (!$preguntas) $errores[] = 'Esta encuesta no tiene preguntas activas.';

    $rec = $_POST['recomienda'] ?? '';
    $recomienda = $rec === 'si' ? 1 : ($rec === 'no' ? 0 : null);
    $comentario = trim(mb_substr((string)($_POST['comentario'] ?? ''), 0, 1000));
    $nombre     = trim(mb_substr((string)($_POST['nombre'] ?? ''), 0, 80));
    $telefono   = substr((string)preg_replace('/[^0-9+ ]/', '', (string)($_POST['telefono'] ?? '')), 0, 20);
    $pid        = (int)($_POST['personal_id'] ?? 0);
    $pid        = in_array($pid, array_map('intval', array_column($personal, 'id')), true) ? $pid : null;

    if (!$errores) {
        $promedio = round(array_sum($notas) / count($notas), 2);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            q('INSERT INTO encuestas (negocio_id, sucursal_id, mesa_id, promedio, recomendaria, comentario, nombre_cliente, telefono, ip, personal_id, idioma)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $mesa['negocio_id'], $mesa['sucursal_id'], $mesa['id'], $promedio, $recomienda,
                $comentario ?: null, $nombre ?: null, $telefono ?: null, $_SERVER['REMOTE_ADDR'] ?? null, $pid, $lang,
            ]);
            $encId = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO respuestas (encuesta_id, pregunta_id, calificacion) VALUES (?, ?, ?)');
            foreach ($notas as $qid => $val) $ins->execute([$encId, $qid, $val]);
            $pdo->commit();
        } catch (Throwable $t) {
            $pdo->rollBack();
            $errores[] = 'No pudimos guardar tu encuesta. Intentá de nuevo en un momento.';
        }

        if (!$errores) {
            $premio = null;
            try { $premio = negocio_permite((int)$mesa['negocio_id'], $mesa['plan'], 'cupones') ? emitir_cupon((int)$mesa['negocio_id'], 'premio', $encId) : null; } catch (Throwable $t) { error_log('Whispers cupón: ' . $t->getMessage()); }
            $_SESSION[$clave] = time();
            $_SESSION['gracias'] = ['promedio' => $promedio, 'premio' => $premio];
            $esBaja = $promedio <= (float)$mesa['umbral_alerta'];
            header('Location: ' . url($volver));
            if ($esBaja) {
                session_write_close();
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                } else {
                    ignore_user_abort(true);
                    header('Connection: close');
                    header('Content-Length: 0');
                    @ob_end_flush();
                    flush();
                }
                try { notificar_calificacion_baja($encId); } catch (Throwable $t) { error_log('Whispers alerta: ' . $t->getMessage()); }
                // Cupón de disculpa automático (solo si el dueño lo activó y la API de WhatsApp está conectada)
                if ($telefono && whatsapp_configurado() && negocio_permite((int)$mesa['negocio_id'], $mesa['plan'], 'cupones')) {
                    $cfgD = cupon_config((int)$mesa['negocio_id'], 'disculpa');
                    if ((int)$cfgD['activo'] && (int)$cfgD['envio_auto']) {
                        try { enviar_cupon_disculpa_api($encId); } catch (Throwable $t) { error_log('Whispers cupón: ' . $t->getMessage()); }
                    }
                }
            }
            exit;
        }
    }
}

$gracias = (isset($_GET['ok']) && isset($_SESSION['gracias'])) ? $_SESSION['gracias'] : null;
$titulo = tx('titulo', $lang) . ' — ' . ($mesa['negocio'] ?? 'Whispers');
$vars_css = $disponible && negocio_permite((int)$mesa['negocio_id'], $mesa['plan'], 'marca') ? vars_marca($mesa) : '';
$logoVisible = $disponible && $mesa['logo'] && negocio_permite((int)$mesa['negocio_id'], $mesa['plan'], 'marca');
include __DIR__ . '/includes/head.php';
$textoPreg = fn($p) => ($lang === 'en' && !empty($p['texto_en'])) ? $p['texto_en'] : $p['texto'];
?>
<body class="publico encuesta-page<?= $kiosko ? ' kiosko' : '' ?>" data-lang="<?= $lang ?>"
      data-txt='<?= e(json_encode(['1' => tx('muy_malo', $lang), '2' => tx('malo', $lang), '3' => tx('regular', $lang), '4' => tx('bueno', $lang), '5' => tx('excelente', $lang), 'falta' => tx('falta', $lang), 'enviando' => tx('enviando', $lang)], JSON_UNESCAPED_UNICODE)) ?>'>

<header class="top top-encuesta">
  <span class="marca">
    <?php if ($logoVisible): ?><img class="marca-logo" src="<?= e(url('uploads/logos/' . $mesa['logo'])) ?>" alt=""><?php endif; ?>
    <?= e($mesa['negocio'] ?? 'Whispers') ?>
  </span>
  <span class="top-der">
    <?php if ($disponible): ?>
      <?php if (negocio_permite((int)$mesa['negocio_id'], $mesa['plan'], 'ingles')): ?><a class="lang-btn" href="<?= e(url($base . ($gracias ? '&ok=1' : '') . '&lang=' . ($lang === 'en' ? 'es' : 'en'))) ?>" hreflang="<?= $lang === 'en' ? 'es' : 'en' ?>"><?= $lang === 'en' ? 'ES' : 'EN' ?></a><?php endif; ?>
      <span class="chip-mesa"><?= e($mesa['nombre'] ?: ($lang === 'en' ? 'Table ' : 'Mesa ') . $mesa['numero']) ?></span>
    <?php endif; ?>
  </span>
  <?php if ($disponible && !$gracias): ?><span class="top-progreso" id="progreso" aria-hidden="true"></span><?php endif; ?>
</header>

<main class="encuesta-wrap">
<?php if (!$disponible): ?>
  <section class="card-msg"><h1><?= tx('no_funciona', $lang) ?></h1><p><?= tx('no_funciona_t', $lang) ?></p></section>

<?php elseif ($gracias): ?>
  <?php $p = (float)$gracias['promedio']; $premio = $gracias['premio'] ?? null; ?>
  <section class="card-msg gracias">
    <div class="check" aria-hidden="true"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24"/><path d="M15 27l7 7 15-16"/></svg></div>
    <?php if ($p >= (float)$mesa['umbral_resena'] && !empty($mesa['google_review_url']) && negocio_permite((int)$mesa['negocio_id'], $mesa['plan'], 'resenas') && !$kiosko): ?>
      <h1><?= tx('gracias_bien', $lang) ?></h1>
      <p><?= tx('gracias_resena', $lang) ?></p>
      <a class="btn btn-verde" href="<?= e($mesa['google_review_url']) ?>" target="_blank" rel="noopener"><?= tx('btn_resena', $lang) ?></a>
    <?php elseif ($p <= (float)$mesa['umbral_alerta']): ?>
      <h1><?= tx('gracias_mal', $lang) ?></h1>
      <p><?= tx('gracias_mal_t', $lang) ?></p>
    <?php else: ?>
      <h1><?= tx('gracias', $lang) ?></h1>
      <p><?= tx('gracias_t', $lang) ?></p>
    <?php endif; ?>
  </section>

  <?php if ($premio): ?>
    <section class="cupon-ticket" aria-label="<?= tx('premio', $lang) ?>">
      <span class="ct-tit"><?= tx('premio', $lang) ?> 🎁</span>
      <strong class="ct-premio"><?= e($premio['titulo']) ?></strong>
      <span class="ct-sub"><?= tx('premio_cod', $lang) ?></span>
      <span class="ct-codigo"><?= e($premio['codigo']) ?></span>
      <?php if ($premio['vence_en']): ?><span class="ct-vence"><?= tx('premio_vence', $lang) ?> <?= e(date('d/m/Y', strtotime($premio['vence_en']))) ?></span><?php endif; ?>
      <?php if ($premio['condiciones']): ?><small class="ct-cond"><?= e($premio['condiciones']) ?></small><?php endif; ?>
      <?php if (!$kiosko): ?><small class="ct-cap"><?= tx('premio_cap', $lang) ?></small><?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($kiosko): ?>
    <p class="kiosko-reinicio"><?= tx('siguiente', $lang) ?> <b id="kiosko-seg"><?= $premio ? 25 : 8 ?></b> s</p>
    <script>
      (function () { var s = <?= $premio ? 25 : 8 ?>, el = document.getElementById('kiosko-seg');
        setInterval(function () { s--; if (s <= 0) location.href = <?= json_encode(url($base)) ?>; else el.textContent = s; }, 1000); })();
    </script>
  <?php endif; ?>
  <?php unset($_SESSION['gracias']); ?>

<?php else: ?>
  <div class="encuesta-intro">
    <h1><?= tx('titulo', $lang) ?></h1>
    <p><?= tx('sub', $lang) ?></p>
  </div>

  <?php if ($errores): ?>
    <div class="alerta alerta-error" role="alert"><?php foreach (array_unique($errores) as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e(url($base)) ?>" class="encuesta-form" novalidate>
    <?= csrf_campo() ?>
    <div class="trampa" aria-hidden="true"><label>Sitio web <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label></div>

    <?php foreach ($preguntas as $p): $qid = (int)$p['id']; $sel = (int)($viejo['p'][$qid] ?? 0); ?>
      <fieldset class="pregunta<?= (int)$p['especial'] ? ' especial' : '' ?>">
        <?php if ((int)$p['especial']): ?><span class="badge-especial">✨ <?= tx('pregunta_mes', $lang) ?></span><?php endif; ?>
        <legend><?= e($textoPreg($p)) ?></legend>
        <div class="estrellas">
          <?php for ($k = 5; $k >= 1; $k--): ?>
            <input type="radio" id="p<?= $qid ?>_<?= $k ?>" name="p[<?= $qid ?>]" value="<?= $k ?>" <?= $sel === $k ? 'checked' : '' ?> required>
            <label for="p<?= $qid ?>_<?= $k ?>" title="<?= $k ?>/5"><span class="sr"><?= $k ?>/5</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4L2.8 9.5l6.4-.8z"/></svg></label>
          <?php endfor; ?>
        </div>
        <span class="estrellas-txt" aria-live="polite"></span>
      </fieldset>
    <?php endforeach; ?>

    <?php if ($personal): $selP = (int)($viejo['personal_id'] ?? 0); ?>
      <fieldset class="pregunta personal-pick">
        <legend><?= tx('quien', $lang) ?> <small><?= tx('quien_sub', $lang) ?></small></legend>
        <div class="personas">
          <?php foreach ($personal as $pe): $foto = foto_personal($pe['foto']); ?>
            <input type="radio" id="pe<?= (int)$pe['id'] ?>" name="personal_id" value="<?= (int)$pe['id'] ?>" <?= $selP === (int)$pe['id'] ? 'checked' : '' ?>>
            <label for="pe<?= (int)$pe['id'] ?>" class="persona">
              <?php if ($foto): ?><img src="<?= e($foto) ?>" alt=""><?php else: ?><span class="persona-ini"><?= e(mb_strtoupper(mb_substr($pe['nombre'], 0, 1))) ?></span><?php endif; ?>
              <span><?= e($pe['nombre']) ?></span>
            </label>
          <?php endforeach; ?>
          <input type="radio" id="pe0" name="personal_id" value="0" <?= $selP === 0 && isset($viejo['personal_id']) ? 'checked' : '' ?>>
          <label for="pe0" class="persona persona-no"><span class="persona-ini">?</span><span><?= tx('no_se', $lang) ?></span></label>
        </div>
      </fieldset>
    <?php endif; ?>

    <fieldset class="pregunta">
      <legend><?= tx('recomienda', $lang) ?></legend>
      <div class="si-no">
        <input type="radio" id="rec_si" name="recomienda" value="si" <?= ($viejo['recomienda'] ?? '') === 'si' ? 'checked' : '' ?>><label for="rec_si"><span aria-hidden="true">👍</span> <?= tx('si', $lang) ?></label>
        <input type="radio" id="rec_no" name="recomienda" value="no" <?= ($viejo['recomienda'] ?? '') === 'no' ? 'checked' : '' ?>><label for="rec_no"><span aria-hidden="true">🤔</span> <?= tx('no', $lang) ?></label>
      </div>
    </fieldset>

    <section class="pregunta extra">
      <h2 class="extra-tit"><?= tx('algo_mas', $lang) ?> <small><?= tx('opcional', $lang) ?></small></h2>
      <div class="sugerencias" role="group">
        <?php foreach (tx('sug', $lang) as $sug): ?><button type="button" class="sug"><?= e($sug) ?></button><?php endforeach; ?>
      </div>
      <div class="campo-bonito">
        <label for="comentario" class="sr"><?= tx('algo_mas', $lang) ?></label>
        <textarea id="comentario" name="comentario" rows="4" maxlength="1000" placeholder="<?= e(tx('placeholder', $lang)) ?>"><?= e($viejo['comentario'] ?? '') ?></textarea>
        <span class="contador" id="contador" aria-live="polite">0/1000</span>
      </div>

      <?php if (!$kiosko): ?>
        <p class="extra-sub"><?= tx('contacto', $lang) ?></p>
        <div class="campo-doble">
          <div class="campo-icono">
            <label for="nombre" class="sr"><?= tx('nombre', $lang) ?></label>
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.5-6 8-6s8 2 8 6"/></svg>
            <input id="nombre" name="nombre" maxlength="80" value="<?= e($viejo['nombre'] ?? '') ?>" autocomplete="given-name" placeholder="<?= e(tx('nombre', $lang)) ?>">
          </div>
          <div class="campo-icono">
            <label for="telefono" class="sr"><?= tx('whatsapp', $lang) ?></label>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 3.5A11 11 0 0 0 3.3 17.3L2 22l4.8-1.3A11 11 0 0 0 20.5 3.5zM12 20.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-2.9.8.8-2.8-.2-.3A8.2 8.2 0 1 1 12 20.2z"/></svg>
            <input id="telefono" name="telefono" inputmode="tel" maxlength="20" value="<?= e($viejo['telefono'] ?? '') ?>" autocomplete="tel" placeholder="<?= e(tx('whatsapp', $lang)) ?>">
          </div>
        </div>
      <?php else: ?>
        <div class="campo-icono">
          <label for="nombre" class="sr"><?= tx('nombre', $lang) ?></label>
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.5-6 8-6s8 2 8 6"/></svg>
          <input id="nombre" name="nombre" maxlength="80" autocomplete="off" placeholder="<?= e(tx('nombre', $lang)) ?>">
        </div>
      <?php endif; ?>
    </section>

    <button class="btn btn-grande btn-enviar" type="submit"><?= tx('enviar', $lang) ?></button>
    <p class="nota-privada"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg> <?= tx('privado', $lang) ?> <?= e($mesa['negocio']) ?>.</p>
  </form>
  <?php if ($kiosko): ?>
    <script>(function () { var t; function r() { clearTimeout(t); t = setTimeout(function () { location.href = <?= json_encode(url($base)) ?>; }, 120000); }
      ['click', 'keydown', 'touchstart'].forEach(function (e) { document.addEventListener(e, r, true); }); r(); })();</script>
  <?php endif; ?>
<?php endif; ?>
</main>
<?php if (!$kiosko): ?><footer class="pie-encuesta"><?php include __DIR__ . '/includes/logo.php'; ?> Encuestas con Whispers</footer><?php endif; ?>
<script src="<?= e(url('assets/js/app.js')) ?>?v=6"></script>
</body>
</html>
