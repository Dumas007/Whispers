<?php
/**
 * Consultas compartidas por el resumen y el reporte mensual.
 * Todo se filtra por negocio; opcionalmente por sucursal y mesa.
 */
function metricas(int $negocioId, string $desde, string $hasta, ?int $sucursalId = null, ?int $mesaId = null, float $umbral = 3.0): array
{
    $w = 'e.negocio_id = ? AND e.creado_en >= ? AND e.creado_en < ?';
    $p = [$negocioId, $desde, $hasta];
    if ($sucursalId) { $w .= ' AND e.sucursal_id = ?'; $p[] = $sucursalId; }
    if ($mesaId)     { $w .= ' AND e.mesa_id = ?';     $p[] = $mesaId; }
    $u = sprintf('%.2F', $umbral);

    $kpi = q("SELECT COUNT(*) total, AVG(e.promedio) prom, AVG(e.recomendaria) * 100 rec,
                     SUM(CASE WHEN e.promedio <= $u THEN 1 ELSE 0 END) alertas,
                     SUM(CASE WHEN e.promedio <= $u AND e.revisada = 1 THEN 1 ELSE 0 END) atendidas,
                     SUM(CASE WHEN e.comentario IS NOT NULL AND e.comentario <> '' THEN 1 ELSE 0 END) con_comentario
                FROM encuestas e WHERE $w", $p)->fetch();

    $porPregunta = q("SELECT p.id, p.texto, AVG(r.calificacion) prom, COUNT(*) n
                        FROM respuestas r
                        JOIN encuestas e ON e.id = r.encuesta_id
                        JOIN preguntas p ON p.id = r.pregunta_id
                       WHERE $w GROUP BY p.id, p.texto, p.orden ORDER BY p.orden, p.id", $p)->fetchAll();

    $porMesa = q("SELECT m.id, m.numero, m.nombre, s.nombre sucursal, COUNT(e.id) total, AVG(e.promedio) prom,
                         SUM(CASE WHEN e.promedio <= $u THEN 1 ELSE 0 END) alertas
                    FROM encuestas e JOIN mesas m ON m.id = e.mesa_id JOIN sucursales s ON s.id = e.sucursal_id
                   WHERE $w GROUP BY m.id, m.numero, m.nombre, s.nombre ORDER BY s.nombre, m.numero", $p)->fetchAll();

    $porSucursal = q("SELECT s.id, s.nombre, COUNT(e.id) total, AVG(e.promedio) prom, AVG(e.recomendaria) * 100 rec,
                             SUM(CASE WHEN e.promedio <= $u THEN 1 ELSE 0 END) alertas
                        FROM encuestas e JOIN sucursales s ON s.id = e.sucursal_id
                       WHERE $w GROUP BY s.id, s.nombre ORDER BY prom DESC", $p)->fetchAll();

    $serie = [];
    foreach (q("SELECT DATE(e.creado_en) d, COUNT(*) n, AVG(e.promedio) pr FROM encuestas e WHERE $w GROUP BY DATE(e.creado_en)", $p) as $r) {
        $serie[$r['d']] = $r;
    }

    return [
        'total'       => (int)$kpi['total'],
        'prom'        => $kpi['prom'] !== null ? (float)$kpi['prom'] : null,
        'rec'         => $kpi['rec'] !== null ? (float)$kpi['rec'] : null,
        'alertas'     => (int)$kpi['alertas'],
        'atendidas'   => (int)$kpi['atendidas'],
        'comentarios' => (int)$kpi['con_comentario'],
        'porPregunta' => $porPregunta,
        'porMesa'     => $porMesa,
        'porSucursal' => $porSucursal,
        'serie'       => $serie,
        'where'       => $w,
        'params'      => $p,
    ];
}

function comentarios_periodo(array $m, string $orden = 'e.creado_en DESC', int $limite = 6): array
{
    $limite = max(1, min(500, $limite));
    return q("SELECT e.id, e.comentario, e.promedio, e.creado_en, e.revisada, m.numero, m.nombre mesa, s.nombre sucursal
                FROM encuestas e JOIN mesas m ON m.id = e.mesa_id JOIN sucursales s ON s.id = e.sucursal_id
               WHERE {$m['where']} AND e.comentario IS NOT NULL AND e.comentario <> ''
            ORDER BY $orden LIMIT $limite", $m['params'])->fetchAll();
}
