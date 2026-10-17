<?php
require dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$s = usuario();
if (!$s) { http_response_code(401); echo '{"error":"sin sesión"}'; exit; }

$u = q('SELECT id, negocio_id, sucursal_id, rol FROM usuarios WHERE id = ? AND activo = 1', [$s['id']])->fetch();
$nid = $u ? ($u['rol'] === 'super' ? (int)($_SESSION['ver_negocio'] ?? 0) : (int)$u['negocio_id']) : 0;
session_write_close();
if (!$nid) { echo '{"pendientes":0,"ultima":null}'; exit; }

$umbral = (float)q('SELECT umbral_alerta FROM negocios WHERE id = ?', [$nid])->fetchColumn();
$extra = (fijo_sucursal($u)) ? ' AND e.sucursal_id = ' . (int)$u['sucursal_id'] : '';
$r = q("SELECT COUNT(*) pendientes, MAX(e.id) ultima_id FROM encuestas e WHERE e.negocio_id = ? AND e.promedio <= ? AND e.revisada = 0$extra", [$nid, $umbral])->fetch();
$ultima = null;
if ($r['ultima_id']) {
    $x = q('SELECT e.id, e.promedio, m.numero, m.nombre FROM encuestas e JOIN mesas m ON m.id = e.mesa_id WHERE e.id = ?', [$r['ultima_id']])->fetch();
    $ultima = ['id' => (int)$x['id'], 'mesa' => $x['nombre'] ?: 'Mesa ' . $x['numero'], 'promedio' => (float)$x['promedio'], 'url' => url('admin/encuesta_ver.php?id=' . $x['id'])];
}
echo json_encode(['pendientes' => (int)$r['pendientes'], 'ultima' => $ultima], JSON_UNESCAPED_UNICODE);
