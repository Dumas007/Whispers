<?php
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/includes/extras.php';
[$u, $n] = requiere_negocio();

$id = (int)($_GET['id'] ?? 0);
$enc = q('SELECT e.*, m.numero, m.nombre mesa, s.nombre sucursal, pe.nombre atendio FROM encuestas e JOIN mesas m ON m.id = e.mesa_id JOIN sucursales s ON s.id = e.sucursal_id LEFT JOIN personal pe ON pe.id = e.personal_id WHERE e.id = ? AND e.negocio_id = ?', [$id, $n['id']])->fetch();
if (!$enc || !puede_ver_sucursal($u, (int)$enc['sucursal_id'])) {
    flash('error', 'Esa encuesta no existe o no tenés acceso a ella.');
    redirigir('admin/encuestas.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    if (($_POST['accion'] ?? '') === 'cupon_api' && $enc['telefono']) {
        [$ok, $det, $cup] = enviar_cupon_disculpa_api($id);
        flash($ok ? 'ok' : 'error', $ok ? '✓ Cupón ' . ($cup['codigo'] ?? '') . ' enviado por WhatsApp. ' . ($det !== 'Enviado' ? $det : '') : 'No se pudo enviar: ' . $det);
        redirigir('admin/encuesta_ver.php?id=' . $id);
    }
    if (($_POST['accion'] ?? '') === 'cupon_disculpa' && $enc['telefono'] && plan_permite($n, 'cupones')) {
        $cfg = cupon_config((int)$n['id'], 'disculpa');
        $cup = emitir_cupon((int)$n['id'], 'disculpa', $id);
        $msg = mensaje_cupon($cfg['mensaje'] ?: CUPON_DEFECTO['disculpa']['mensaje'], [
            'nombre' => $enc['nombre_cliente'] ? strtok($enc['nombre_cliente'], ' ') : '', 'negocio' => $n['nombre'], 'codigo' => $cup['codigo'],
            'premio' => $cup['titulo'], 'vence' => $cup['vence_en'] ? date('d/m/Y', strtotime($cup['vence_en'])) : 'sin fecha de vencimiento',
        ]);
        $msg = preg_replace('/Hola\s+,/u', 'Hola,', $msg);
        $nota = trim(($enc['nota_interna'] ?? '') . "\n" . 'Cupón de disculpa ' . $cup['codigo'] . ' enviado por ' . $u['nombre'] . ', ' . date('j/n g:i a'));
        q('UPDATE encuestas SET nota_interna = ? WHERE id = ?', [$nota, $id]);
        header('Location: ' . whatsapp_link($enc['telefono'], $msg));
        exit;
    }
    if (($_POST['accion'] ?? '') === 'eliminar' && es_admin($u)) {
        q('DELETE FROM encuestas WHERE id = ? AND negocio_id = ?', [$id, $n['id']]);
        flash('ok', 'Encuesta eliminada.');
        redirigir('admin/encuestas.php');
    }
    $nota = trim(mb_substr((string)($_POST['nota_interna'] ?? ''), 0, 2000));
    $revisada = isset($_POST['revisada']) ? 1 : 0;
    $marca = $nota !== '' && $nota !== (string)$enc['nota_interna'] ? $nota . "\n— " . $u['nombre'] . ', ' . date('j/n g:i a') : $nota;
    q('UPDATE encuestas SET nota_interna = ?, revisada = ? WHERE id = ? AND negocio_id = ?', [$marca ?: null, $revisada, $id, $n['id']]);
    flash('ok', $revisada ? 'Encuesta marcada como revisada.' : 'Seguimiento guardado.');
    redirigir('admin/encuesta_ver.php?id=' . $id);
}

$respuestas = q('SELECT p.texto, r.calificacion FROM respuestas r JOIN preguntas p ON p.id = r.pregunta_id WHERE r.encuesta_id = ? ORDER BY p.orden, p.id', [$id])->fetchAll();
$log = q('SELECT * FROM alertas_log WHERE encuesta_id = ? ORDER BY id', [$id])->fetchAll();
$cupones = q('SELECT * FROM cupones_emitidos WHERE encuesta_id = ? ORDER BY id', [$id])->fetchAll();
$cfgDisculpa = cupon_config((int)$n['id'], 'disculpa');
$pe = (float)$enc['promedio'];
$titulo = ($enc['mesa'] ?: 'Mesa ' . $enc['numero']) . ' · ' . fecha_corta($enc['creado_en']);
$seccion = 'encuestas';
include dirname(__DIR__) . '/includes/admin_header.php';
?>
<a class="volver" href="<?= e(url('admin/encuestas.php')) ?>">Volver a encuestas</a>

<section class="detalle">
  <article class="panel">
    <header class="detalle-head">
      <span class="detalle-nota nota-<?= clase_nota($pe, $n['umbral_alerta']) ?>"><?= nota($pe) ?></span>
      <div>
        <h2><?= texto_nota($pe, $n['umbral_alerta']) ?></h2>
        <p class="muted"><?= e($enc['sucursal']) ?> · <?= e($enc['mesa'] ?: 'Mesa ' . $enc['numero']) ?> · <?= e(fecha_larga($enc['creado_en'])) ?>, <?= date('g:i a', strtotime($enc['creado_en'])) ?></p>
      </div>
    </header>
    <ul class="resp-lista">
      <?php foreach ($respuestas as $r): $c = (int)$r['calificacion']; ?>
        <li><span><?= e($r['texto']) ?></span><span class="mini-estrellas" aria-label="<?= $c ?> de 5"><?= str_repeat('★', $c) ?><span class="apagadas"><?= str_repeat('★', 5 - $c) ?></span></span></li>
      <?php endforeach; ?>
      <?php if ($enc['atendio']): ?><li><span>Lo atendió</span><strong><?= e($enc['atendio']) ?></strong></li><?php endif; ?>
      <li><span>¿Nos recomendaría?</span><strong><?= $enc['recomendaria'] === null ? 'No respondió' : ((int)$enc['recomendaria'] ? 'Sí' : 'Todavía no') ?></strong></li>
    </ul>
    <?php if ($enc['comentario']): ?>
      <blockquote class="cita"><?= nl2br(e($enc['comentario'])) ?></blockquote>
    <?php endif; ?>
    <?php if ($enc['nombre_cliente'] || $enc['telefono']): ?>
      <div class="contacto">
        <span><?= e($enc['nombre_cliente'] ?: 'Cliente') ?><?= $enc['telefono'] ? ' · ' . e($enc['telefono']) : '' ?></span>
        <?php if ($enc['telefono'] && (float)$enc['promedio'] <= $n['umbral_alerta'] && plan_permite($n, 'cupones') && (int)$cfgDisculpa['activo']): ?>
          <span class="cupon-acciones">
            <?php if (whatsapp_configurado()): ?>
              <?php $yaEnviado = q("SELECT creado_en FROM alertas_log WHERE encuesta_id = ? AND canal = 'cupon' AND ok = 1 LIMIT 1", [$id])->fetchColumn(); ?>
              <?php if ($yaEnviado): ?>
                <span class="estado ok">✓ Cupón enviado <?= e(fecha_corta($yaEnviado)) ?></span>
              <?php else: ?>
                <form method="post" class="inline" data-confirmar="¿Enviarle el cupón de disculpa por WhatsApp a <?= e($enc['telefono']) ?>?"><?= csrf_campo() ?><input type="hidden" name="accion" value="cupon_api">
                  <button class="btn btn-sm btn-verde" type="submit">🤝 Enviar cupón de disculpa</button></form>
              <?php endif; ?>
              <form method="post" target="_blank" class="inline"><?= csrf_campo() ?><input type="hidden" name="accion" value="cupon_disculpa">
                <button class="link-btn" type="submit">o abrir en WhatsApp</button></form>
            <?php else: ?>
              <form method="post" target="_blank" class="inline"><?= csrf_campo() ?><input type="hidden" name="accion" value="cupon_disculpa">
                <button class="btn btn-sm btn-verde" type="submit">🤝 Enviar cupón de disculpa</button></form>
            <?php endif; ?>
          </span>
        <?php elseif ($enc['telefono']): ?>
          <a class="btn btn-sm btn-verde" target="_blank" rel="noopener" href="<?= e(whatsapp_link($enc['telefono'], 'Hola' . ($enc['nombre_cliente'] ? ' ' . $enc['nombre_cliente'] : '') . ', te escribimos de ' . $n['nombre'] . '. Gracias por tu comentario, queremos saber más para mejorar.')) ?>">Escribirle por WhatsApp</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($cupones): ?>
      <div class="log-alertas">
        <h3>Cupones de esta encuesta</h3>
        <?php foreach ($cupones as $c): ?>
          <p><code><?= e($c['codigo']) ?></code> · <?= $c['tipo'] === 'premio' ? 'Premio por responder' : 'Disculpa' ?> · <?= e($c['titulo']) ?> · <?= $c['canjeado_en'] ? '<span class="estado ok">Usado</span>' : '<span class="estado">Sin usar</span>' ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($log): ?>
      <div class="log-alertas">
        <h3>Avisos enviados</h3>
        <?php foreach ($log as $l): ?>
          <p><span class="estado <?= (int)$l['ok'] ? 'ok' : 'mal' ?>"><?= (int)$l['ok'] ? 'Enviado' : 'Falló' ?></span> <?= e(ucfirst($l['canal'])) ?> a <?= e($l['destino']) ?><?= (int)$l['ok'] ? '' : ' — ' . e(texto_corto((string)$l['detalle'], 140)) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>

  <article class="panel">
    <header class="panel-head"><h2>Seguimiento interno</h2></header>
    <form method="post" class="form-col">
      <?= csrf_campo() ?>
      <div class="campo">
        <label for="nota_interna">Qué se hizo con esta encuesta</label>
        <textarea id="nota_interna" name="nota_interna" rows="6" placeholder="Ej.: Se habló con el mesero y se le ofreció postre de cortesía en su próxima visita."><?= e($enc['nota_interna'] ?? '') ?></textarea>
        <small class="muted">Solo lo ve tu equipo.</small>
      </div>
      <label class="check-linea"><input type="checkbox" name="revisada" value="1" <?= (int)$enc['revisada'] ? 'checked' : '' ?>> Marcar como revisada</label>
      <button class="btn" type="submit">Guardar seguimiento</button>
    </form>
    <?php if (es_admin($u)): ?>
      <form method="post" class="eliminar-enc" data-confirmar="¿Eliminar esta encuesta? Se borra también de los reportes.">
        <?= csrf_campo() ?><input type="hidden" name="accion" value="eliminar">
        <button class="link-btn txt-mal" type="submit">Eliminar encuesta</button>
      </form>
    <?php endif; ?>
  </article>
</section>
<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
