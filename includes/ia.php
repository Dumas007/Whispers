<?php
/**
 * Análisis de comentarios con IA.
 * Funciona con OpenAI (ChatGPT) o con Anthropic (Claude): se elige en Whispers → Ajustes de Whispers.
 */
require_once __DIR__ . '/notificaciones.php';

const IA_PROVEEDORES = [
    'openai'    => ['nombre' => 'OpenAI (ChatGPT)', 'llave' => 'openai_key',    'modelo' => 'modelo_openai', 'defecto' => 'gpt-4o-mini'],
    'anthropic' => ['nombre' => 'Anthropic (Claude)', 'llave' => 'anthropic_key', 'modelo' => 'modelo_ia',     'defecto' => 'claude-haiku-5-5'],
];

/** Proveedor activo. Si no se eligió, usa el que tenga llave guardada (primero OpenAI). */
function ia_proveedor(): string
{
    $p = ajuste('ia_proveedor');
    if (isset(IA_PROVEEDORES[$p])) return $p;
    return ajuste('openai_key') === '' && ajuste('anthropic_key') !== '' ? 'anthropic' : 'openai';
}

function ia_modelo(?string $prov = null): string
{
    $c = IA_PROVEEDORES[$prov ?? ia_proveedor()];
    return ajuste($c['modelo'], $c['defecto']);
}

function ia_configurada(): bool
{
    return ajuste(IA_PROVEEDORES[ia_proveedor()]['llave']) !== '';
}

/**
 * Manda un mensaje al proveedor activo y devuelve el texto de la respuesta.
 * @throws RuntimeException
 */
function ia_llamar(string $sistema, string $mensaje, int $maxTokens = 2000, bool $json = true, int $timeout = 90): string
{
    $prov = ia_proveedor();
    $llave = ajuste(IA_PROVEEDORES[$prov]['llave']);
    if ($llave === '') {
        throw new RuntimeException('Falta la llave de ' . IA_PROVEEDORES[$prov]['nombre'] . '. Configurala en Ajustes de Whispers.');
    }
    $modelo = ia_modelo($prov);

    if ($prov === 'openai') {
        $body = [
            'model'                 => $modelo,
            'max_completion_tokens' => $maxTokens,
            'messages'              => [
                ['role' => 'system', 'content' => $sistema],
                ['role' => 'user', 'content' => $mensaje],
            ],
        ];
        if ($json) $body['response_format'] = ['type' => 'json_object'];
        $r = http_json('https://api.openai.com/v1/chat/completions', ['Authorization: Bearer ' . $llave], $body, $timeout);
    } else {
        $r = http_json('https://api.anthropic.com/v1/messages', ['x-api-key: ' . $llave, 'anthropic-version: 2023-06-01'], [
            'model'      => $modelo,
            'max_tokens' => $maxTokens,
            'system'     => $sistema,
            'messages'   => [['role' => 'user', 'content' => $mensaje]],
        ], $timeout);
    }

    if ($r['code'] < 200 || $r['code'] >= 300) {
        $msg = json_decode($r['body'], true)['error']['message'] ?? ($r['error'] ?: 'HTTP ' . $r['code']);
        throw new RuntimeException('La IA no respondió: ' . $msg);
    }
    $data = json_decode($r['body'], true);
    $texto = '';
    if ($prov === 'openai') {
        $texto = (string)($data['choices'][0]['message']['content'] ?? '');
    } else {
        foreach ($data['content'] ?? [] as $b) {
            if (($b['type'] ?? '') === 'text') $texto .= $b['text'];
        }
    }
    if (trim($texto) === '') {
        throw new RuntimeException('La IA respondió vacío. Probá de nuevo o revisá el modelo configurado.');
    }
    return $texto;
}

/**
 * @param array $comentarios filas con comentario, promedio, creado_en, mesa, sucursal
 * @param array $porPregunta filas con texto, prom
 * @return array resultado estructurado
 * @throws RuntimeException
 */
function ia_analizar_comentarios(string $negocio, string $rubro, string $periodo, array $comentarios, array $porPregunta): array
{
    if (!ia_configurada()) {
        throw new RuntimeException('El análisis con IA no está activado. Whispers lo configura en Ajustes de Whispers.');
    }
    if (count($comentarios) < 3) {
        throw new RuntimeException('Se necesitan al menos 3 comentarios en el período para que el análisis tenga sentido.');
    }

    $lineas = [];
    foreach (array_slice($comentarios, 0, 300) as $c) {
        $lineas[] = sprintf('- [%s · %s · nota %s] %s', date('j/n', strtotime($c['creado_en'])), $c['mesa'] ?: $c['sucursal'],
            number_format((float)$c['promedio'], 1), texto_corto(str_replace("\n", ' ', $c['comentario']), 400));
    }
    $notas = [];
    foreach ($porPregunta as $p) {
        $notas[] = '- ' . $p['texto'] . ': ' . number_format((float)$p['prom'], 2) . ' de 5';
    }

    $sistema = 'Sos analista de experiencia del cliente para negocios en Guatemala. Leés encuestas reales y das conclusiones concretas, '
        . 'útiles para el dueño o gerente, en español de Guatemala (voseo), sin tecnicismos. No inventes datos: todo debe salir de los comentarios. '
        . 'Respondé ÚNICAMENTE con un objeto JSON válido, sin texto antes ni después y sin bloques de código.';

    $instr = "Negocio: $negocio" . ($rubro ? " ($rubro)" : '') . "\nPeríodo: $periodo\n\nPromedios por pregunta:\n" . implode("\n", $notas)
        . "\n\nComentarios de clientes (" . count($lineas) . "):\n" . implode("\n", $lineas)
        . "\n\nDevolvé este JSON:\n"
        . '{"resumen":"2 o 3 oraciones con lo más importante del período",'
        . '"tono_general":"positivo|mixto|negativo",'
        . '"temas":[{"tema":"nombre corto","menciones":0,"tono":"positivo|negativo|mixto","ejemplo":"cita textual breve de un comentario"}],'
        . '"fortalezas":["lo que los clientes valoran, frase corta"],'
        . '"a_mejorar":["problema concreto que se repite, frase corta"],'
        . '"acciones":[{"accion":"qué hacer esta semana, concreto","por_que":"en qué comentarios se basa"}]}'
        . "\nMáximo 6 temas ordenados por menciones, 4 fortalezas, 4 a_mejorar y 4 acciones.";

    $modelo = ia_modelo();
    $texto = ia_llamar($sistema, $instr, 2000, true, 90);
    $texto = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($texto)));
    $ini = strpos($texto, '{');
    $fin = strrpos($texto, '}');
    $json = ($ini !== false && $fin !== false) ? json_decode(substr($texto, $ini, $fin - $ini + 1), true) : null;
    if (!is_array($json) || empty($json['resumen'])) {
        throw new RuntimeException('La IA devolvió un formato inesperado. Probá de nuevo.');
    }
    $json['_modelo'] = $modelo;
    return $json;
}

/** Dibuja un análisis guardado. */
function ia_html(array $r): string
{
    $tonos = ['positivo' => 'ok', 'negativo' => 'mal', 'mixto' => 'medio'];
    ob_start(); ?>
    <div class="ia-res">
      <p class="ia-resumen"><?= e($r['resumen'] ?? '') ?></p>
      <?php if (!empty($r['temas'])): ?>
        <h3>De qué hablan tus clientes</h3>
        <ul class="ia-temas">
          <?php foreach ($r['temas'] as $t): ?>
            <li class="tono-<?= $tonos[$t['tono'] ?? 'mixto'] ?? 'medio' ?>">
              <span class="ia-tema-top"><strong><?= e($t['tema'] ?? '') ?></strong><small><?= (int)($t['menciones'] ?? 0) ?> menciones</small></span>
              <?php if (!empty($t['ejemplo'])): ?><q><?= e($t['ejemplo']) ?></q><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <div class="ia-cols">
        <?php if (!empty($r['fortalezas'])): ?><div><h3>Lo que valoran</h3><ul class="ia-lista ok"><?php foreach ($r['fortalezas'] as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php if (!empty($r['a_mejorar'])): ?><div><h3>Lo que se repite como problema</h3><ul class="ia-lista mal"><?php foreach ($r['a_mejorar'] as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      </div>
      <?php if (!empty($r['acciones'])): ?>
        <h3>Qué hacer esta semana</h3>
        <ol class="ia-acciones">
          <?php foreach ($r['acciones'] as $a): ?><li><strong><?= e(is_array($a) ? ($a['accion'] ?? '') : $a) ?></strong><?php if (is_array($a) && !empty($a['por_que'])): ?><span><?= e($a['por_que']) ?></span><?php endif; ?></li><?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </div>
    <?php return (string)ob_get_clean();
}
