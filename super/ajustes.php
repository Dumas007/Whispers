<?php
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/includes/ia.php';
require dirname(__DIR__) . '/includes/extras.php';
$u = requiere_login(['super']);

$campos = [
    'whatsapp_ventas' => 'texto', 'email_remitente' => 'texto',
    'ia_proveedor' => 'texto', 'openai_key' => 'secreto', 'modelo_openai' => 'texto',
    'anthropic_key' => 'secreto', 'modelo_ia' => 'texto',
    'wa_token' => 'secreto', 'wa_phone_id' => 'texto', 'wa_plantilla' => 'texto', 'wa_plantilla_resumen' => 'texto', 'wa_plantilla_cupon' => 'texto', 'wa_idioma' => 'texto', 'wa_version' => 'texto',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    if (($_POST['accion'] ?? '') === 'probar_ia') {
        try {
            $resp = ia_llamar('Respondés en una sola palabra.', 'Respondé solo: listo', 20, false, 30);
            flash('ok', 'La conexión con ' . IA_PROVEEDORES[ia_proveedor()]['nombre'] . ' funciona (modelo ' . ia_modelo() . ').');
        } catch (Throwable $t) {
            flash('error', $t->getMessage());
        }
        redirigir('super/ajustes.php');
    }
    foreach ($campos as $k => $tipo) {
        if (!array_key_exists($k, $_POST)) continue;
        $v = trim((string)$_POST[$k]);
        if ($tipo === 'secreto' && $v === '') continue; // vacío = no cambiar
        if ($k === 'whatsapp_ventas') $v = preg_replace('/\D/', '', $v);
        if ($k === 'ia_proveedor' && !isset(IA_PROVEEDORES[$v])) continue;
        ajuste_guardar($k, $v);
    }
    foreach (['openai_key', 'anthropic_key', 'wa_token'] as $k) if (!empty($_POST['borrar_' . $k])) ajuste_guardar($k, '');
    flash('ok', 'Ajustes de Whispers guardados.');
    redirigir('super/ajustes.php');
}

if (ajuste('url_publica') !== url_absoluta('')) ajuste_guardar('url_publica', url_absoluta(''));
$urlCron = url_absoluta('cron/resumen_semanal.php?clave=' . substr(hash_hmac('sha256', 'cron-resumen', app_secret()), 0, 24));

function oculto(string $v): string { return $v === '' ? 'No configurada' : 'Guardada · termina en ' . substr($v, -4); }

$n = null;
$titulo = 'Ajustes de Whispers';
$seccion = 'globales';
include dirname(__DIR__) . '/includes/admin_header.php';
?>
<form method="post" class="ajustes-globales">
  <?= csrf_campo() ?>
  <article class="panel">
    <header class="panel-head"><h2>Tu negocio</h2></header>
    <div class="form-grid">
      <div class="campo"><label for="wv">WhatsApp de ventas</label><input id="wv" name="whatsapp_ventas" value="<?= e(ajuste('whatsapp_ventas')) ?>" placeholder="50255551234"><small class="muted">Lo usan los botones de la página principal y los avisos de “cambiar de plan”.</small></div>
      <div class="campo"><label for="er">Correo remitente de alertas</label><input id="er" name="email_remitente" type="email" value="<?= e(ajuste('email_remitente')) ?>" placeholder="alertas@tudominio.com"><small class="muted">Usá un correo de tu propio dominio para que no caiga en spam.</small></div>
    </div>
  </article>

  <article class="panel">
    <header class="panel-head"><h2>Análisis con IA</h2><span class="estado <?= ia_configurada() ? 'ok' : 'gris' ?>"><?= ia_configurada() ? 'Activo con ' . e(IA_PROVEEDORES[ia_proveedor()]['nombre']) : 'Sin configurar' ?></span></header>
    <div class="campo" style="max-width:360px;margin-bottom:1rem">
      <label for="ip">Qué IA usar</label>
      <select id="ip" name="ia_proveedor">
        <?php foreach (IA_PROVEEDORES as $k => $pv): ?><option value="<?= $k ?>" <?= ia_proveedor() === $k ? 'selected' : '' ?>><?= e($pv['nombre']) ?></option><?php endforeach; ?>
      </select>
      <small class="muted">Solo se usa la que elijás aquí. Podés tener las dos llaves guardadas y cambiar cuando querás.</small>
    </div>

    <h3 class="form-tit">OpenAI (ChatGPT)</h3>
    <p class="muted">Creá tu llave en platform.openai.com → API keys. El costo se cobra a tu cuenta de OpenAI según el uso.</p>
    <div class="form-grid">
      <div class="campo"><label for="ok">Llave de la API</label><input id="ok" name="openai_key" type="password" autocomplete="off" placeholder="sk-…"><small class="muted"><?= e(oculto(ajuste('openai_key'))) ?>. Dejalo vacío para no cambiarla.</small></div>
      <div class="campo"><label for="mo">Modelo</label><input id="mo" name="modelo_openai" value="<?= e(ajuste('modelo_openai', 'gpt-4o-mini')) ?>"><small class="muted">gpt-4o-mini es económico y suficiente. Podés poner cualquier modelo de chat que tengás disponible en tu cuenta.</small></div>
    </div>
    <?php if (ajuste('openai_key')): ?><label class="check-linea"><input type="checkbox" name="borrar_openai_key" value="1"> Borrar la llave de OpenAI</label><?php endif; ?>

    <h3 class="form-tit" style="margin-top:1.4rem">Anthropic (Claude)</h3>
    <p class="muted">Opcional. Creá tu llave en console.anthropic.com → API Keys.</p>
    <div class="form-grid">
      <div class="campo"><label for="ak">Llave de la API</label><input id="ak" name="anthropic_key" type="password" autocomplete="off" placeholder="sk-ant-…"><small class="muted"><?= e(oculto(ajuste('anthropic_key'))) ?>. Dejalo vacío para no cambiarla.</small></div>
      <div class="campo"><label for="mi">Modelo</label><input id="mi" name="modelo_ia" value="<?= e(ajuste('modelo_ia', 'claude-haiku-5-5')) ?>"></div>
    </div>
    <?php if (ajuste('anthropic_key')): ?><label class="check-linea"><input type="checkbox" name="borrar_anthropic_key" value="1"> Borrar la llave de Anthropic</label><?php endif; ?>
  </article>

  <article class="panel">
    <header class="panel-head"><h2>Alertas por WhatsApp (API de Meta)</h2><span class="estado <?= whatsapp_configurado() ? 'ok' : 'gris' ?>"><?= whatsapp_configurado() ? 'Activo' : 'Sin configurar' ?></span></header>
    <p class="muted">Sale de Meta for Developers → tu app → WhatsApp → Configuración de la API. La plantilla debe estar aprobada y tener 4 variables: {{1}} negocio y sucursal, {{2}} mesa, {{3}} nota, {{4}} comentario.</p>
    <div class="form-grid">
      <div class="campo"><label for="wt">Token de acceso</label><input id="wt" name="wa_token" type="password" autocomplete="off"><small class="muted"><?= e(oculto(ajuste('wa_token'))) ?>. Usá un token permanente de usuario del sistema.</small></div>
      <div class="campo"><label for="wp">Identificador del número (Phone number ID)</label><input id="wp" name="wa_phone_id" value="<?= e(ajuste('wa_phone_id')) ?>"></div>
      <div class="campo"><label for="wpl">Nombre de la plantilla</label><input id="wpl" name="wa_plantilla" value="<?= e(ajuste('wa_plantilla', 'alerta_calificacion')) ?>"></div>
      <div class="campo"><label for="wpr">Plantilla del resumen semanal</label><input id="wpr" name="wa_plantilla_resumen" value="<?= e(ajuste('wa_plantilla_resumen', 'resumen_semanal')) ?>"><small class="muted">5 variables: {{1}} negocio, {{2}} nota, {{3}} encuestas, {{4}} lo mejor, {{5}} a mejorar.</small></div>
      <div class="campo campo-full"><label for="wpc">Plantilla del cupón de disculpa</label><input id="wpc" name="wa_plantilla_cupon" value="<?= e(ajuste('wa_plantilla_cupon', 'cupon_disculpa')) ?>">
        <small class="muted">Categoría Marketing, 5 variables. Texto sugerido para Meta: <?= e(str_replace("\n", ' ', PLANTILLA_CUPON_META)) ?></small></div>
      <div class="campo"><label for="wi">Idioma de la plantilla</label><input id="wi" name="wa_idioma" value="<?= e(ajuste('wa_idioma', 'es')) ?>"><small class="muted">Tal como lo aprobó Meta: es, es_MX…</small></div>
      <div class="campo"><label for="wvv">Versión de la API</label><input id="wvv" name="wa_version" value="<?= e(ajuste('wa_version', 'v21.0')) ?>"></div>
    </div>
    <?php if (ajuste('wa_token')): ?><label class="check-linea"><input type="checkbox" name="borrar_wa_token" value="1"> Borrar el token guardado</label><?php endif; ?>
  </article>

  <article class="panel">
    <header class="panel-head"><h2>Resumen semanal automático</h2></header>
    <p class="muted">Programá esta tarea una vez por semana (lunes 8:00 a. m.). En cPanel: <strong>Cron Jobs</strong> con el comando <code>php <?= e(APP_DIR) ?>/cron/resumen_semanal.php</code>. Si tu hosting no tiene cron, usá cron-job.org con este link:</p>
    <div class="copiar" style="margin-top:.6rem"><input readonly value="<?= e($urlCron) ?>"><button class="btn btn-sm btn-borde" type="button" data-copiar="<?= e($urlCron) ?>">Copiar</button></div>
  </article>

  <div class="fila-botones"><button class="btn" type="submit">Guardar ajustes</button></div>
</form>
<?php if (ia_configurada()): ?>
  <form method="post" class="fila-botones"><?= csrf_campo() ?><input type="hidden" name="accion" value="probar_ia"><button class="btn btn-sm btn-borde" type="submit">Probar conexión con la IA</button></form>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
