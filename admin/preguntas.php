<?php
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/includes/ia.php';
[$u, $n] = requiere_negocio(['admin']);
// Básico: las preguntas las define Whispers (el usuario super sí puede editarlas desde "Abrir panel")
$puede = plan_permite($n, 'preguntas') || $u['rol'] === 'super';
$premium = plan_permite($n, 'pregunta_especial');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    if (!$puede) { flash('error', 'Personalizar preguntas está incluido desde el plan Profesional.'); redirigir('admin/preguntas.php'); }
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $texto = trim(mb_substr((string)($_POST['texto'] ?? ''), 0, 150));
    $textoEn = trim(mb_substr((string)($_POST['texto_en'] ?? ''), 0, 150)) ?: null;
    $especial = isset($_POST['especial']) && $premium ? 1 : 0;
    $hasta = $especial && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['hasta'] ?? '') ? $_POST['hasta'] : null;
    $propia = $id ? (bool)q('SELECT 1 FROM preguntas WHERE id = ? AND negocio_id = ?', [$id, $n['id']])->fetch() : false;

    if ($accion === 'crear') {
        if ($texto === '') {
            flash('error', 'Escribí la pregunta.');
        } else {
            $orden = (int)q('SELECT COALESCE(MAX(orden), 0) + 1 FROM preguntas WHERE negocio_id = ?', [$n['id']])->fetchColumn();
            q('INSERT INTO preguntas (negocio_id, texto, texto_en, especial, hasta, orden) VALUES (?, ?, ?, ?, ?, ?)', [$n['id'], $texto, $textoEn, $especial, $hasta, $orden]);
            flash('ok', $especial ? 'Pregunta especial agregada. Sale de primera en la encuesta' . ($hasta ? ' hasta el ' . date('d/m/Y', strtotime($hasta)) : '') . '.' : 'Pregunta agregada. Ya aparece en la encuesta.');
        }
    } elseif ($propia && $accion === 'editar' && $texto !== '') {
        q('UPDATE preguntas SET texto = ?, texto_en = ? WHERE id = ?', [$texto, $textoEn, $id]);
        flash('ok', 'Pregunta actualizada.');
    } elseif ($accion === 'traducir' && !plan_permite($n, 'ingles')) {
        flash('error', 'La encuesta en inglés está incluida en el plan Premium.');
    } elseif ($accion === 'traducir') {
        $faltan = q('SELECT id, texto FROM preguntas WHERE negocio_id = ? AND (texto_en IS NULL OR texto_en = "")', [$n['id']])->fetchAll();
        if (!$faltan) {
            flash('ok', 'Todas tus preguntas ya tienen su versión en inglés.');
        } elseif (!ia_configurada()) {
            flash('error', 'La traducción automática necesita la IA activada. Podés escribir el inglés a mano en cada pregunta.');
        } else {
            try {
                $lista = [];
                foreach ($faltan as $f) $lista[(string)$f['id']] = $f['texto'];
                $txt = ia_llamar('Traducís preguntas de encuestas de satisfacción del español al inglés natural para turistas. Respondé solo con un objeto JSON {"id": "traducción"}.',
                    json_encode($lista, JSON_UNESCAPED_UNICODE), 800, true, 60);
                $ini = strpos($txt, '{'); $fin = strrpos($txt, '}');
                $tr = $ini !== false ? json_decode(substr($txt, $ini, $fin - $ini + 1), true) : null;
                $hechas = 0;
                foreach ((array)$tr as $pid => $en) {
                    if (isset($lista[(string)$pid]) && is_string($en) && trim($en) !== '') {
                        q('UPDATE preguntas SET texto_en = ? WHERE id = ? AND negocio_id = ?', [mb_substr(trim($en), 0, 150), (int)$pid, $n['id']]);
                        $hechas++;
                    }
                }
                flash('ok', "Listo: $hechas pregunta" . ($hechas === 1 ? '' : 's') . ' traducida' . ($hechas === 1 ? '' : 's') . '. Revisalas abajo.');
            } catch (RuntimeException $ex) {
                flash('error', $ex->getMessage());
            }
        }
    } elseif ($propia && $accion === 'toggle') {
        $activas = (int)q('SELECT COUNT(*) FROM preguntas WHERE negocio_id = ? AND activa = 1', [$n['id']])->fetchColumn();
        $estaActiva = (int)q('SELECT activa FROM preguntas WHERE id = ?', [$id])->fetchColumn();
        if ($estaActiva && $activas <= 1) {
            flash('error', 'La encuesta necesita al menos una pregunta visible.');
        } else {
            q('UPDATE preguntas SET activa = 1 - activa WHERE id = ?', [$id]);
            flash('ok', 'Listo, cambió la visibilidad de la pregunta.');
        }
    } elseif ($propia && ($accion === 'subir' || $accion === 'bajar')) {
        $lista = array_map('intval', q('SELECT id FROM preguntas WHERE negocio_id = ? ORDER BY orden, id', [$n['id']])->fetchAll(PDO::FETCH_COLUMN));
        $pos = array_search($id, $lista, true);
        $otro = $accion === 'subir' ? $pos - 1 : $pos + 1;
        if ($pos !== false && isset($lista[$otro])) {
            [$lista[$pos], $lista[$otro]] = [$lista[$otro], $lista[$pos]];
            foreach ($lista as $i => $pid) q('UPDATE preguntas SET orden = ? WHERE id = ?', [$i + 1, $pid]);
        }
    } elseif ($propia && $accion === 'eliminar') {
        if ((int)q('SELECT COUNT(*) FROM respuestas WHERE pregunta_id = ?', [$id])->fetchColumn() > 0) {
            flash('error', 'Esta pregunta ya tiene respuestas. Ocultala en vez de eliminarla para no perder el historial.');
        } else {
            q('DELETE FROM preguntas WHERE id = ?', [$id]);
            flash('ok', 'Pregunta eliminada.');
        }
    }
    redirigir('admin/preguntas.php');
}

$preguntas = q('SELECT p.*, (SELECT COUNT(*) FROM respuestas r WHERE r.pregunta_id = p.id) usos FROM preguntas p WHERE p.negocio_id = ? ORDER BY p.orden, p.id', [$n['id']])->fetchAll();
$activas = count(array_filter($preguntas, fn($p) => (int)$p['activa']));

$titulo = 'Preguntas de la encuesta';
$seccion = 'preguntas';
include dirname(__DIR__) . '/includes/admin_header.php';
?>
<p class="muted intro-seccion">Son las preguntas que el cliente califica con estrellas. Tenés <?= $activas ?> visibles; lo ideal son entre 4 y 6 para que la encuesta tome menos de un minuto.</p>

<section class="grid-lateral">
  <article class="panel">
    <header class="panel-head"><h2>En la encuesta</h2><?php if ($t = q('SELECT token FROM mesas WHERE negocio_id = ? AND activa = 1 LIMIT 1', [$n['id']])->fetchColumn()): ?><a href="<?= e(url('encuesta.php?m=' . $t)) ?>" target="_blank" rel="noopener">Ver cómo se ve</a><?php endif; ?></header>
    <ol class="lista-preguntas">
      <?php foreach ($preguntas as $i => $p): ?>
        <li class="<?= (int)$p['activa'] ? '' : 'inactivo' ?>">
          <?php if ($puede): ?>
            <form method="post" class="preg-edit">
              <?= csrf_campo() ?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <div class="preg-textos">
                <?php if ((int)$p['especial']): ?><span class="badge-especial">✨ Pregunta especial<?= $p['hasta'] ? ' · hasta el ' . e(date('d/m/Y', strtotime($p['hasta']))) . ($p['hasta'] < date('Y-m-d') ? ' (terminó)' : '') : '' ?></span><?php endif; ?>
                <input name="texto" value="<?= e($p['texto']) ?>" maxlength="150" aria-label="Texto de la pregunta">
                <?php if (plan_permite($n, 'ingles')): ?><input name="texto_en" value="<?= e($p['texto_en']) ?>" maxlength="150" placeholder="🇺🇸 En inglés (opcional)" aria-label="Texto en inglés" class="input-en"><?php else: ?><input type="hidden" name="texto_en" value="<?= e($p['texto_en']) ?>"><?php endif; ?>
              </div>
              <button class="btn btn-sm btn-borde" type="submit">Guardar</button>
            </form>
          <?php else: ?>
            <p class="preg-fija-txt"><?= e($p['texto']) ?></p>
          <?php endif; ?>
          <div class="preg-meta">
            <small class="muted"><?= (int)$p['usos'] ?> respuestas<?= (int)$p['activa'] ? '' : ' · Oculta' ?></small>
            <?php if ($puede): ?>
              <span class="preg-btns">
                <?php foreach (['subir' => 'Subir', 'bajar' => 'Bajar', 'toggle' => (int)$p['activa'] ? 'Ocultar' : 'Mostrar'] as $acc => $txt):
                  if (($acc === 'subir' && $i === 0) || ($acc === 'bajar' && $i === count($preguntas) - 1)) continue; ?>
                  <form method="post" class="inline"><?= csrf_campo() ?><input type="hidden" name="accion" value="<?= $acc ?>"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="link-btn" type="submit"><?= $txt ?></button></form>
                <?php endforeach; ?>
                <?php if (!(int)$p['usos']): ?><form method="post" class="inline" data-confirmar="¿Eliminar esta pregunta?"><?= csrf_campo() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="link-btn txt-mal" type="submit">Eliminar</button></form><?php endif; ?>
              </span>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
  </article>

  <?php if ($puede): ?>
    <article class="panel">
      <header class="panel-head"><h2>Agregar pregunta</h2></header>
      <form method="post" class="form-col">
        <?= csrf_campo() ?><input type="hidden" name="accion" value="crear">
        <div class="campo"><label for="t">Pregunta</label><input id="t" name="texto" maxlength="150" required placeholder="Ej.: ¿Qué tal la música y el ambiente?"><small class="muted">Se responde con 1 a 5 estrellas.</small></div>
        <?php if (plan_permite($n, 'ingles')): ?><div class="campo"><label for="te">En inglés <small>Opcional, para turistas</small></label><input id="te" name="texto_en" maxlength="150" placeholder="How was the music and atmosphere?"></div><?php endif; ?>
        <?php if ($premium): ?><label class="check-linea"><input type="checkbox" name="especial" value="1" data-muestra="hasta-campo"> Es una pregunta especial (temporal)</label>
        <div class="campo" id="hasta-campo" hidden><label for="ha">Se muestra hasta</label><input id="ha" name="hasta" type="date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+30 days')) ?>"><small class="muted">Ideal para probar un plato nuevo o una promoción. Sale de primera en la encuesta.</small></div><?php endif; ?>
        <button class="btn" type="submit">Agregar a la encuesta</button>
      </form>
    </article>
    <?php if (plan_permite($n, 'ingles')): ?>
    <article class="panel">
      <header class="panel-head"><h2>🇺🇸 Encuesta en inglés</h2></header>
      <p class="muted">Si el celular del cliente está en inglés, la encuesta sale en inglés sola. También tiene un botón EN/ES arriba.</p>
      <form method="post" style="margin-top:.8rem" data-cargando="Traduciendo…"><?= csrf_campo() ?><input type="hidden" name="accion" value="traducir"><button class="btn btn-sm btn-borde" type="submit">Traducir con IA las que faltan</button></form>
    </article>
    <?php endif; ?>
  <?php else: ?>
    <?php $upsell_plan = 'profesional'; $upsell_titulo = 'Hacé la encuesta a tu medida'; $upsell_texto = 'Con el plan Profesional cambiás, agregás y ordenás las preguntas según tu negocio.'; include dirname(__DIR__) . '/includes/upsell.php'; ?>
  <?php endif; ?>
</section>
<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
