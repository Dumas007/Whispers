<?php
/** @var string $upsell_plan  @var string $upsell_titulo  @var string $upsell_texto  @var array $n */
$ventas = ajuste('whatsapp_ventas');
?>
<section class="upsell">
  <span class="plan-tag">Plan <?= e(plan_nombre($upsell_plan)) ?></span>
  <h2><?= e($upsell_titulo) ?></h2>
  <p><?= e($upsell_texto) ?></p>
  <?php if ($ventas): ?>
    <a class="btn btn-verde" target="_blank" rel="noopener" href="<?= e(whatsapp_link($ventas, 'Hola, soy de ' . $n['nombre'] . ' y quiero pasarme al plan ' . plan_nombre($upsell_plan) . ' de Whispers.')) ?>">Quiero el plan <?= e(plan_nombre($upsell_plan)) ?></a>
  <?php endif; ?>
</section>
