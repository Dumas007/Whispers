<?php
/** @var array $u  @var array|null $n  @var string $titulo  @var string $seccion */
$n = $n ?? null;
$pendientes = 0;
if ($n) {
    $pendientes = (int)q('SELECT COUNT(*) FROM encuestas WHERE negocio_id = ? AND promedio <= ? AND revisada = 0'
        . (fijo_sucursal($u) ? ' AND sucursal_id = ' . (int)$u['sucursal_id'] : ''),
        [$n['id'], $n['umbral_alerta']])->fetchColumn();
}
$ico = [
    'dashboard' => '<path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z"/>',
    'encuestas' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'ia'        => '<path d="M12 3l1.8 4.7L18.5 9.5 13.8 11.3 12 16l-1.8-4.7L5.5 9.5l4.7-1.8zM19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z"/>',
    'reporte'   => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M8 17v-3M12 17v-6M16 17v-4"/>',
    'sucursales'=> '<path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h3v3h-3zM18 18h3v3h-3z"/>',
    'preguntas' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17.5v.01"/>',
    'usuarios'  => '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6M17 11a3 3 0 1 0 0-6M22 21c0-3-1.5-4.8-4-5.6"/>',
    'ajustes'   => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
    'clientes'  => '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/>',
    'cuenta'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.5-6 8-6s8 2 8 6"/>',
    'cupones'   => '<path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4zM10 6v12" stroke-dasharray="2 2"/>',
    'personal'  => '<circle cx="12" cy="7" r="4"/><path d="M5 21v-1a7 7 0 0 1 14 0v1M17 3.5l1 2 2 .3-1.5 1.4.4 2.1L17 8.3"/>',
];
$navNegocio = [];
if ($n) {
    $navNegocio = [
        'dashboard'  => ['admin/dashboard.php', 'Resumen'],
        'encuestas'  => ['admin/encuestas.php', 'Encuestas'],
        'ia'         => ['admin/ia.php', 'Análisis con IA'],
        'reporte'    => ['admin/reporte.php', 'Reporte mensual'],
        'sucursales' => ['admin/sucursales.php', plan_permite($n, 'multisucursal') ? 'Sucursales y QR' : 'Mesas y QR'],
        'cupones'    => ['admin/cupones.php', 'Cupones'],
        'personal'   => ['admin/personal.php', 'Personal'],
    ];
    if (es_admin($u)) {
        $navNegocio['preguntas'] = ['admin/preguntas.php', 'Preguntas'];
        $navNegocio['usuarios']  = ['admin/usuarios.php', 'Usuarios'];
        $navNegocio['ajustes']   = ['admin/ajustes.php', 'Ajustes del negocio'];
    }
}
$navWhispers = $u['rol'] === 'super' ? [
    'clientes' => ['super/index.php', 'Clientes'],
    'globales' => ['super/ajustes.php', 'Ajustes de Whispers'],
] : [];
$icoMap = ['globales' => 'ajustes'];

$vars_css = ($n && !empty($n['colores_panel']) && plan_permite($n, 'marca')) ? vars_marca($n) : '';
$tituloPagina = $titulo . ' — ' . ($n['nombre'] ?? 'Whispers');
$tmp = $titulo; $titulo = $tituloPagina;
include __DIR__ . '/head.php';
$titulo = $tmp;
$logoUrl = ($n && $n['logo'] && plan_permite($n, 'marca')) ? url('uploads/logos/' . $n['logo']) : null;
?>
<body class="admin" <?= $n ? 'data-alertas-url="' . e(url('admin/api_alertas.php')) . '"' : '' ?>>
<a class="saltar" href="#contenido">Saltar al contenido</a>

<aside class="lateral" id="lateral">
  <div class="lateral-top">
    <?php if ($n): ?>
      <?php if ($logoUrl): ?><img class="lateral-logo" src="<?= e($logoUrl) ?>" alt=""><?php endif; ?>
      <span class="marca-admin"><?= e($n['nombre']) ?></span>
      <small>Plan <?= e(plan_nombre($n['plan'])) ?></small>
    <?php else: ?>
      <span class="marca-admin marca-whispers"><?php include __DIR__ . '/logo.php'; ?> Whispers</span>
      <small>Panel de administración</small>
    <?php endif; ?>
  </div>
  <nav aria-label="Menú principal">
    <?php if ($navWhispers): ?>
      <span class="nav-grupo">Whispers</span>
      <?php foreach ($navWhispers as $clave => [$ruta, $texto]): ?>
        <a href="<?= e(url($ruta)) ?>" class="<?= $seccion === $clave ? 'activo' : '' ?>" <?= $seccion === $clave ? 'aria-current="page"' : '' ?>>
          <svg viewBox="0 0 24 24" aria-hidden="true"><?= $ico[$icoMap[$clave] ?? $clave] ?></svg><span><?= e($texto) ?></span>
        </a>
      <?php endforeach; ?>
      <?php if ($n): ?><span class="nav-grupo">Panel de <?= e(texto_corto($n['nombre'], 22)) ?></span><?php endif; ?>
    <?php endif; ?>
    <?php foreach ($navNegocio as $clave => [$ruta, $texto]): ?>
      <a href="<?= e(url($ruta)) ?>" class="<?= $seccion === $clave ? 'activo' : '' ?>" <?= $seccion === $clave ? 'aria-current="page"' : '' ?>>
        <svg viewBox="0 0 24 24" aria-hidden="true"><?= $ico[$clave] ?></svg><span><?= e($texto) ?></span>
        <?php if ($clave === 'encuestas'): ?><b class="badge" id="badge-alertas" <?= $pendientes ? '' : 'hidden' ?>><?= $pendientes ?></b><?php endif; ?>
        <?php if (in_array($clave, ['cupones', 'personal'], true) && !plan_permite($n, $clave)): ?><i class="candado" title="Incluido en el plan Premium">Premium</i><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="lateral-pie">
    <div class="yo">
      <span class="avatar"><?= e(mb_strtoupper(mb_substr($u['nombre'], 0, 1))) ?></span>
      <span><strong><?= e($u['nombre']) ?></strong><small><?= NOMBRES_ROL[$u['rol']] ?? $u['rol'] ?></small></span>
    </div>
    <div class="pie-links">
      <a href="<?= e(url('admin/cuenta.php')) ?>">Mi cuenta</a>
      <a href="<?= e(url('logout.php')) ?>">Cerrar sesión</a>
    </div>
    <?php if ($n): ?><span class="powered"><?php include __DIR__ . '/logo.php'; ?> Con Whispers</span><?php endif; ?>
  </div>
</aside>

<div class="area">
  <?php if ($u['rol'] === 'super' && $n): ?>
    <form class="banda-super" method="post" action="<?= e(url('super/index.php')) ?>">
      <?= csrf_campo() ?><input type="hidden" name="accion" value="salir_negocio">
      <span>Estás viendo el panel de <strong><?= e($n['nombre']) ?></strong> como Whispers.</span>
      <button type="submit">Volver a clientes</button>
    </form>
  <?php endif; ?>
  <?php if ($n && $u['rol'] !== 'super' && ($pruebas = pruebas_activas((int)$n['id']))):
      $finP = $pruebas[0]['hasta']; $quedanP = (int)floor((strtotime($finP) - strtotime(date('Y-m-d'))) / 86400);
      $nombresP = array_map(fn($p) => FUNCIONES_NOMBRE[$p['funcion']] ?? $p['funcion'], $pruebas); ?>
    <div class="banda-prueba">
      <span>🎁 Estás probando gratis <strong><?= e(count($nombresP) > 3 ? 'funciones Premium' : implode(', ', $nombresP)) ?></strong> hasta el <?= e(date('d/m', strtotime($finP))) ?> (<?= $quedanP === 0 ? 'último día' : 'quedan ' . $quedanP . ' día' . ($quedanP === 1 ? '' : 's') ?>).</span>
      <?php if ($v = ajuste('whatsapp_ventas')): ?><a href="<?= e(whatsapp_link($v, 'Hola, soy de ' . $n['nombre'] . ' y quiero quedarme con ' . implode(', ', $nombresP) . '.')) ?>" target="_blank" rel="noopener">Quiero quedármelo</a><?php endif; ?>
    </div>
  <?php endif; ?>
  <header class="barra">
    <button class="menu-btn" type="button" aria-controls="lateral" aria-expanded="false" aria-label="Abrir menú">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>
    <h1><?= e($titulo) ?></h1>
    <?php if (!empty($accion_barra)) echo $accion_barra; ?>
  </header>

  <main id="contenido" class="contenido">
    <?php foreach (flashes() as $f): ?>
      <div class="alerta alerta-<?= e($f['tipo']) ?>" role="status"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
