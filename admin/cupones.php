<?php
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/includes/extras.php';
[$u, $n] = requiere_negocio(['admin', 'gerente']);
$admin = es_admin($u);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && plan_permite($n, 'cupones')) {
    csrf_verificar();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar' && $admin) {
        $tipo = ($_POST['tipo'] ?? '') === 'disculpa' ? 'disculpa' : 'premio';
        $titulo = trim(mb_substr((string)($_POST['titulo'] ?? ''), 0, 80));
        if ($titulo === '') {
            flash('error', 'Escribí qué recibe el cliente.');
        } else {
            q('INSERT INTO cupones (negocio_id, tipo, activo, titulo, condiciones, mensaje, vigencia_dias, prefijo, envio_auto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE activo = VALUES(activo), titulo = VALUES(titulo), condiciones = VALUES(condiciones), mensaje = VALUES(mensaje),
                                       vigencia_dias = VALUES(vigencia_dias), prefijo = VALUES(prefijo), envio_auto = VALUES(envio_auto)', [
                $n['id'], $tipo, isset($_POST['activo']) ? 1 : 0, $titulo,
                trim(mb_substr((string)($_POST['condiciones'] ?? ''), 0, 255)) ?: null,
                $tipo === 'disculpa' ? (trim((string)($_POST['mensaje'] ?? '')) ?: CUPON_DEFECTO['disculpa']['mensaje']) : null,
                max(0, min(365, (int)($_POST['vigencia_dias'] ?? 30))),
                strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string)($_POST['prefijo'] ?? '')), 0, 6)) ?: CUPON_DEFECTO[$tipo]['prefijo'],
                $tipo === 'disculpa' && isset($_POST['envio_auto']) ? 1 : 0,
            ]);
            flash('ok', $tipo === 'premio' ? 'Premio por responder guardado.' : 'Cupón de disculpa guardado.');
        }
    } elseif ($accion === 'canjear') {
        $codigo = strtoupper(trim((string)($_POST['codigo'] ?? '')));
        $c = q('SELECT * FROM cupones_emitidos WHERE codigo = ? AND negocio_id = ?', [$codigo, $n['id']])->fetch();
        if (!$c) {
            flash('error', "El código $codigo no existe en tu negocio.");
        } elseif ($c['canjeado_en']) {
            flash('error', "Ese código ya se usó el " . date('d/m/Y g:i a', strtotime($c['canjeado_en'])) . '.');
        } elseif ($c['vence_en'] && $c['vence_en'] < date('Y-m-d')) {
            flash('error', 'Ese código venció el ' . date('d/m/Y', strtotime($c['vence_en'])) . '.');
        } else {
            q('UPDATE cupones_emitidos SET canjeado_en = NOW(), canjeado_por = ? WHERE id = ?', [$u['id'], $c['id']]);
            flash('ok', "✓ Código válido. Aplicá: {$c['titulo']}");
        }
    }
    redirigir('admin/cupones.php');
}

$premio = cupon_config((int)$n['id'], 'premio');
$disculpa = cupon_config((int)$n['id'], 'disculpa');
$emitidos = q('SELECT ce.*, e.nombre_cliente, u.nombre canjeador FROM cupones_emitidos ce LEFT JOIN encuestas e ON e.id = ce.encuesta_id LEFT JOIN usuarios u ON u.id = ce.canjeado_por
                WHERE ce.negocio_id = ? ORDER BY ce.creado_en DESC LIMIT 40', [$n['id']])->fetchAll();
$stats = q("SELECT tipo, COUNT(*) emitidos, SUM(canjeado_en IS NOT NULL) canjeados FROM cupones_emitidos WHERE negocio_id = ? AND creado_en >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY tipo", [$n['id']])->fetchAll(PDO::FETCH_UNIQUE);

$titulo = 'Cupones';
$seccion = 'cupones';
include dirname(__DIR__) . '/includes/admin_header.php';
if (!plan_permite($n, 'cupones')) {
    $upsell_plan = 'premium'; $upsell_titulo = 'Cupones que hacen volver a tus clientes'; $upsell_texto = 'Premio por responder, cupón de disculpa por WhatsApp para el cliente molesto y canje en caja. Vos decidís qué regalar. Incluido en el plan Premium.';
    include dirname(__DIR__) . '/includes/upsell.php';
    include dirname(__DIR__) . '/includes/admin_footer.php';
    exit;
}

function form_cupon(array $c, bool $admin): void
{
    $esD = $c['tipo'] === 'disculpa'; ?>
    <form method="post" class="form-col">
      <?= csrf_campo() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="tipo" value="<?= e($c['tipo']) ?>">
      <label class="interruptor"><input type="checkbox" name="activo" value="1" <?= (int)$c['activo'] ? 'checked' : '' ?> <?= $admin ? '' : 'disabled' ?>><span></span> <?= $esD ? 'Ofrecer cupón de disculpa en encuestas con nota baja' : 'Dar un premio a cada cliente que responde' ?></label>
      <div class="campo"><label>Qué recibe el cliente</label><input name="titulo" value="<?= e($c['titulo']) ?>" maxlength="80" required <?= $admin ? '' : 'disabled' ?>></div>
      <div class="campo"><label>Condiciones <small>Opcional</small></label><input name="condiciones" value="<?= e($c['condiciones']) ?>" maxlength="255" <?= $admin ? '' : 'disabled' ?>></div>
      <div class="form-grid">
        <div class="campo"><label>Días de vigencia <small>0 = no vence</small></label><input name="vigencia_dias" type="number" min="0" max="365" value="<?= (int)$c['vigencia_dias'] ?>" <?= $admin ? '' : 'disabled' ?>></div>
        <div class="campo"><label>Inicio del código</label><input name="prefijo" value="<?= e($c['prefijo']) ?>" maxlength="6" style="text-transform:uppercase" <?= $admin ? '' : 'disabled' ?>><small class="muted">Ej.: <?= e($c['prefijo']) ?>-7KQ2M</small></div>
      </div>
      <?php if ($esD): $api = whatsapp_configurado(); ?>
        <label class="interruptor">
          <input type="checkbox" name="envio_auto" value="1" <?= (int)($c['envio_auto'] ?? 0) ? 'checked' : '' ?> <?= $admin && $api ? '' : 'disabled' ?>><span></span>
          <span><strong>Mandarlo solo, sin tocar nada</strong><small class="muted"><?= $api
              ? 'Apenas alguien califica bajo y deja su WhatsApp, le llega el cupón automáticamente.'
              : 'Disponible cuando Whispers conecte la API de WhatsApp. Mientras tanto, se envía con un toque desde la encuesta.' ?></small></span>
        </label>
        <?php if ($api): ?>
          <div class="plantilla-meta">
            <span>Mensaje que recibe el cliente (plantilla aprobada por WhatsApp):</span>
            <p><?= nl2br(e(strtr(PLANTILLA_CUPON_META, ['{{1}}' => 'María', '{{2}}' => $GLOBALS['n']['nombre'] ?? 'tu negocio', '{{3}}' => mb_strtolower($c['titulo']), '{{4}}' => $c['prefijo'] . '-7KQ2M', '{{5}}' => date('d/m/Y', strtotime('+' . max(1, (int)$c['vigencia_dias']) . ' days'))]))) ?></p>
          </div>
        <?php endif; ?>
        <details class="mas-opciones">
          <summary>Mensaje cuando se abre en WhatsApp a mano</summary>
          <textarea name="mensaje" rows="7" <?= $admin ? '' : 'disabled' ?>><?= e($c['mensaje'] ?: CUPON_DEFECTO['disculpa']['mensaje']) ?></textarea>
          <small class="muted">Se rellenan solos: {nombre}, {negocio}, {premio}, {codigo}, {vence}</small>
        </details>
      <?php endif; ?>
      <?php if ($admin): ?><button class="btn" type="submit">Guardar</button><?php endif; ?>
    </form>
<?php }
?>
<section class="canje">
  <form method="post" class="canje-form">
    <?= csrf_campo() ?><input type="hidden" name="accion" value="canjear">
    <label for="cod">¿Un cliente trae un código?</label>
    <div class="canje-fila">
      <input id="cod" name="codigo" placeholder="Ej.: GRACIAS-7KQ2M" required autocomplete="off" style="text-transform:uppercase">
      <button class="btn" type="submit">Validar y canjear</button>
    </div>
  </form>
  <div class="canje-stats">
    <div><b><?= (int)($stats['premio']['emitidos'] ?? 0) ?></b><span>premios entregados</span><small><?= (int)($stats['premio']['canjeados'] ?? 0) ?> usados</small></div>
    <div><b><?= (int)($stats['disculpa']['emitidos'] ?? 0) ?></b><span>cupones de disculpa</span><small><?= (int)($stats['disculpa']['canjeados'] ?? 0) ?> clientes regresaron</small></div>
  </div>
</section>
<p class="muted" style="margin-top:-.4rem">Últimos 30 días.</p>

<section class="grid-2">
  <article class="panel">
    <header class="panel-head"><h2>🎁 Premio por responder</h2><span class="estado <?= (int)$premio['activo'] ? 'ok' : 'gris' ?>"><?= (int)$premio['activo'] ? 'Activo' : 'Apagado' ?></span></header>
    <p class="muted" style="margin-bottom:1rem">Al terminar la encuesta, el cliente ve un código para su próxima visita. Más gente responde y además regresa.</p>
    <?php form_cupon($premio, $admin); ?>
  </article>
  <article class="panel">
    <header class="panel-head"><h2>🤝 Cupón de disculpa</h2><span class="estado <?= (int)$disculpa['activo'] ? 'ok' : 'gris' ?>"><?= (int)$disculpa['activo'] ? 'Activo' : 'Apagado' ?></span></header>
    <p class="muted" style="margin-bottom:1rem">Cuando alguien califica bajo y deja su WhatsApp, en el detalle de la encuesta te sale un botón para mandarle este cupón con un mensaje ya escrito.</p>
    <?php form_cupon($disculpa, $admin); ?>
  </article>
</section>

<article class="panel">
  <header class="panel-head"><h2>Últimos códigos</h2></header>
  <?php if (!$emitidos): ?><p class="muted">Todavía no se ha entregado ningún código.</p><?php else: ?>
  <div class="tabla-wrap">
    <table class="tabla">
      <thead><tr><th>Código</th><th>Tipo</th><th>Premio</th><th>Entregado</th><th>Estado</th></tr></thead>
      <tbody>
      <?php foreach ($emitidos as $c): $vencido = !$c['canjeado_en'] && $c['vence_en'] && $c['vence_en'] < date('Y-m-d'); ?>
        <tr>
          <td><code><?= e($c['codigo']) ?></code><?php if ($c['nombre_cliente']): ?><br><small class="muted"><?= e($c['nombre_cliente']) ?></small><?php endif; ?></td>
          <td><?= $c['tipo'] === 'premio' ? 'Premio' : 'Disculpa' ?></td>
          <td><?= e($c['titulo']) ?></td>
          <td class="nowrap"><?= e(fecha_corta($c['creado_en'])) ?><?php if ($c['encuesta_id']): ?><br><a href="<?= e(url('admin/encuesta_ver.php?id=' . $c['encuesta_id'])) ?>">Ver encuesta</a><?php endif; ?></td>
          <td><?= $c['canjeado_en'] ? '<span class="estado ok">Usado</span><br><small class="muted">' . e(fecha_corta($c['canjeado_en'])) . '</small>' : ($vencido ? '<span class="estado gris">Vencido</span>' : '<span class="estado">Sin usar</span>') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</article>
<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
