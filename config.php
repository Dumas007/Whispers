<?php
declare(strict_types=1);

define('APP_DIR', __DIR__);
date_default_timezone_set('America/Guatemala');

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}
define('INSTALADO', defined('DB_NAME'));

if (!defined('BASE_URL')) {
    $doc = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
    $app = realpath(__DIR__) ?: __DIR__;
    $rel = ($doc !== '' && strpos($app, $doc) === 0) ? substr($app, strlen($doc)) : '';
    define('BASE_URL', rtrim(str_replace('\\', '/', $rel), '/'));
}

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL === '' ? '/' : BASE_URL . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('whispers_sess');
    session_start();
}

if (!INSTALADO && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php' && PHP_SAPI !== 'cli') {
    header('Location: ' . BASE_URL . '/install.php');
    exit;
}

/* =========================================================
   Planes
   ========================================================= */
const PLANES = [
    'basico'      => ['nombre' => 'Básico',      'precio' => 450,  'sucursales' => 1],
    'profesional' => ['nombre' => 'Profesional', 'precio' => 850,  'sucursales' => 1],
    'premium'     => ['nombre' => 'Premium',     'precio' => 1500, 'sucursales' => 50],
];
/*
 * Qué incluye cada plan.
 *  Básico:      encuesta con las preguntas que define Whispers, resumen, reporte mensual,
 *               alertas en panel, correo y WhatsApp, 1 análisis con IA al mes.
 *  Profesional: + el dueño cambia sus preguntas, IA sin límite mensual, invitación a reseñar en Google.
 *  Premium:     todo lo demás: cupones, logo y colores, personal, inglés, modo tablet,
 *               pregunta especial, resumen semanal, día y turno, comparación con el rubro, varias sucursales.
 */
const FUNCIONES_PLAN = [
    'preguntas'        => ['profesional', 'premium'],
    'alertas_whatsapp' => ['basico', 'profesional', 'premium'],
    'ia_ilimitada'     => ['profesional', 'premium'],
    'resenas'          => ['profesional', 'premium'],
    'cupones'          => ['premium'],
    'marca'            => ['premium'],
    'personal'         => ['premium'],
    'ingles'           => ['premium'],
    'tablet'           => ['premium'],
    'pregunta_especial'=> ['premium'],
    'resumen_semanal'  => ['premium'],
    'analitica'        => ['premium'],
    'multisucursal'    => ['premium'],
];
const PLAN_MINIMO = ['preguntas' => 'profesional', 'ia_ilimitada' => 'profesional', 'resenas' => 'profesional'];

/** Funciones que Whispers puede regalar o dar a prueba a un cliente, con su nombre para mostrar. */
const FUNCIONES_NOMBRE = [
    'preguntas'         => 'Editar sus preguntas',
    'ia_ilimitada'      => 'IA sin límite',
    'resenas'           => 'Invitación a reseñar en Google',
    'cupones'           => 'Cupones',
    'marca'             => 'Logo y colores',
    'personal'          => 'Personal y ranking',
    'ingles'            => 'Encuesta en inglés',
    'tablet'            => 'Modo tablet',
    'pregunta_especial' => 'Pregunta especial',
    'resumen_semanal'   => 'Resumen semanal',
    'analitica'         => 'Día y turno + comparación con el rubro',
    'multisucursal'     => 'Varias sucursales',
];

/** Plan más barato que incluye una función. */
function plan_para(string $funcion): string
{
    return PLAN_MINIMO[$funcion] ?? 'premium';
}
const PRECIO_IMPLEMENTACION = 1200;
const PREGUNTAS_BASE_EN = [
    '¿Qué tal estuvo la comida?'          => 'How was the food?',
    '¿Cómo te atendió el personal?'       => 'How was the service from our staff?',
    '¿Qué tal el tiempo de espera?'       => 'How was the waiting time?',
    '¿Cómo encontraste la limpieza?'      => 'How clean was the place?',
    '¿El precio va con lo que recibiste?' => 'Was it good value for the price?',
];
const PREGUNTAS_BASE = [
    '¿Qué tal estuvo la comida?',
    '¿Cómo te atendió el personal?',
    '¿Qué tal el tiempo de espera?',
    '¿Cómo encontraste la limpieza?',
    '¿El precio va con lo que recibiste?',
];

function plan_permite(array $negocio, string $funcion): bool
{
    return negocio_permite((int)($negocio['negocio_id'] ?? $negocio['id'] ?? 0), (string)$negocio['plan'], $funcion);
}

/** ¿El negocio tiene esta función? Por su plan o porque Whispers se la dio (de prueba o permanente). */
function negocio_permite(int $negocioId, string $plan, string $funcion): bool
{
    if (in_array($plan, FUNCIONES_PLAN[$funcion] ?? [], true)) return true;
    return in_array($funcion, funciones_extra($negocioId), true);
}

/** Funciones regaladas vigentes de un negocio (se guardan en memoria durante la petición). */
function funciones_extra(int $negocioId, bool $refrescar = false): array
{
    static $cache = [];
    if (!$negocioId) return [];
    if ($refrescar || !isset($cache[$negocioId])) {
        try {
            $cache[$negocioId] = q('SELECT funcion FROM negocio_funciones WHERE negocio_id = ? AND (hasta IS NULL OR hasta >= CURDATE())', [$negocioId])->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $t) {
            $cache[$negocioId] = []; // por si la tabla aún no existe
        }
    }
    return $cache[$negocioId];
}

/** Funciones de prueba vigentes (con fecha de fin), para avisarle al cliente. */
function pruebas_activas(int $negocioId): array
{
    try {
        return q('SELECT funcion, hasta FROM negocio_funciones WHERE negocio_id = ? AND hasta IS NOT NULL AND hasta >= CURDATE() ORDER BY hasta', [$negocioId])->fetchAll();
    } catch (Throwable $t) {
        return [];
    }
}

function plan_nombre(string $plan): string
{
    return PLANES[$plan]['nombre'] ?? $plan;
}

/* =========================================================
   Base de datos
   ========================================================= */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET time_zone = '-06:00'");
        } catch (PDOException $ex) {
            http_response_code(500);
            exit('No se pudo conectar a la base de datos. Revisá config.local.php.');
        }
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/* ---------- Ajustes globales (tabla ajustes) ---------- */
function ajuste(string $clave, string $defecto = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT clave, valor FROM ajustes') as $r) {
            $cache[$r['clave']] = (string)$r['valor'];
        }
    }
    return ($cache[$clave] ?? '') !== '' ? $cache[$clave] : $defecto;
}

function ajuste_guardar(string $clave, string $valor): void
{
    q('INSERT INTO ajustes (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)', [$clave, $valor]);
}

/* =========================================================
   Utilidades
   ========================================================= */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function url_absoluta(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . url($path);
}

function redirigir(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verificar(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('La sesión expiró. Regresá a la página anterior y volvé a intentar.');
    }
}

function flash(string $tipo, string $msg): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function token_mesa(): string
{
    return bin2hex(random_bytes(8));
}

function texto_corto(string $s, int $max): string
{
    $s = trim($s);
    return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
}

function fecha_corta(string $f): string
{
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $t = strtotime($f);
    return date('j', $t) . ' ' . $meses[(int)date('n', $t) - 1] . ', ' . date('g:i a', $t);
}

function fecha_larga(string $f): string
{
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $t = strtotime($f);
    return date('j', $t) . ' de ' . $meses[(int)date('n', $t) - 1] . ' de ' . date('Y', $t);
}

function nombre_mes(string $ym): string
{
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $t = strtotime($ym . '-01');
    return ucfirst($meses[(int)date('n', $t) - 1]) . ' ' . date('Y', $t);
}

function quetzales(float $n): string
{
    return 'Q' . number_format($n, 0, '.', ',');
}

function whatsapp_link(string $numero, string $mensaje = ''): string
{
    $n = preg_replace('/\D/', '', $numero);
    if (strlen($n) === 8) {
        $n = '502' . $n;
    }
    return 'https://wa.me/' . $n . ($mensaje !== '' ? '?text=' . rawurlencode($mensaje) : '');
}

/* ---------- Notas ---------- */
function clase_nota(?float $n, float $umbral = 3.0): string
{
    if ($n === null) return 'vacio';
    if ($n >= 4) return 'ok';
    if ($n > $umbral) return 'medio';
    return 'mal';
}

function texto_nota(?float $n, float $umbral = 3.0): string
{
    if ($n === null) return 'Sin datos';
    if ($n >= 4.5) return 'Excelente';
    if ($n >= 4) return 'Muy bien';
    if ($n > $umbral) return 'Regular';
    return 'Necesita atención';
}

function nota(?float $n): string
{
    return $n === null ? '—' : number_format($n, 1);
}

/* =========================================================
   Sesión, roles y negocio activo
   ========================================================= */
function usuario(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function requiere_login(array $roles = []): array
{
    $s = usuario();
    if (!$s) {
        redirigir('login.php');
    }
    $u = q('SELECT u.id, u.negocio_id, u.sucursal_id, u.nombre, u.email, u.rol, u.activo, n.activo AS negocio_activo
              FROM usuarios u LEFT JOIN negocios n ON n.id = u.negocio_id WHERE u.id = ?', [$s['id']])->fetch();
    if (!$u || !(int)$u['activo'] || !isset(NOMBRES_ROL[$u['rol']]) || ($u['rol'] !== 'super' && !(int)$u['negocio_activo'])) {
        $_SESSION = [];
        session_destroy();
        session_start();
        flash('error', 'Tu acceso está desactivado. Comunicate con Whispers.');
        redirigir('login.php');
    }
    $_SESSION['usuario'] = ['id' => (int)$u['id'], 'nombre' => $u['nombre'], 'rol' => $u['rol']];
    if ($roles && !in_array($u['rol'], $roles, true)) {
        flash('error', 'Tu usuario no tiene permiso para entrar a esa sección.');
        redirigir(inicio_rol($u['rol']));
    }
    return $u;
}

/** Devuelve [usuario, negocio]. El super entra al negocio que eligió en "Abrir panel". */
function requiere_negocio(array $roles = []): array
{
    $u = requiere_login();
    $nid = $u['rol'] === 'super' ? (int)($_SESSION['ver_negocio'] ?? 0) : (int)$u['negocio_id'];
    if (!$nid) {
        redirigir('super/index.php');
    }
    $n = q('SELECT * FROM negocios WHERE id = ?', [$nid])->fetch();
    if (!$n) {
        unset($_SESSION['ver_negocio']);
        redirigir($u['rol'] === 'super' ? 'super/index.php' : 'logout.php');
    }
    $n['umbral_alerta'] = (float)$n['umbral_alerta'];
    $n['umbral_resena'] = (float)$n['umbral_resena'];

    if ($roles && $u['rol'] !== 'super' && !in_array($u['rol'], $roles, true)) {
        flash('error', 'Esa sección no está disponible para tu usuario.');
        redirigir(inicio_rol($u['rol']));
    }
    return [$u, $n];
}

function es_admin(array $u): bool
{
    return in_array($u['rol'], ['super', 'admin'], true);
}

/** Usuario que solo puede ver su sucursal (gerente con sucursal asignada). */
function fijo_sucursal(array $u): bool
{
    return !es_admin($u) && !empty($u['sucursal_id']);
}

/** Página de inicio según el rol. */
function inicio_rol(string $rol): string
{
    switch ($rol) {
        case 'super':  return empty($_SESSION['ver_negocio']) ? 'super/index.php' : 'admin/dashboard.php';
        default:       return 'admin/dashboard.php';
    }
}

const NOMBRES_ROL = ['super' => 'Whispers', 'admin' => 'Administrador', 'gerente' => 'Gerente'];

function ejecutar_sql_archivo(PDO $pdo, string $archivo): void
{
    foreach (array_filter(array_map('trim', explode(';', (string)file_get_contents($archivo)))) as $sql) {
        $pdo->exec($sql);
    }
}

/** Sucursales que este usuario puede ver dentro del negocio. */
function sucursales_visibles(array $u, array $n, bool $soloActivas = false): array
{
    $sql = 'SELECT * FROM sucursales WHERE negocio_id = ?' . ($soloActivas ? ' AND activa = 1' : '');
    $p = [$n['id']];
    if (fijo_sucursal($u)) {
        $sql .= ' AND id = ?';
        $p[] = $u['sucursal_id'];
    }
    return q($sql . ' ORDER BY id', $p)->fetchAll();
}

/* =========================================================
   Alta de negocios (lo usa el instalador y el panel de Whispers)
   ========================================================= */
function crear_negocio(array $d): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('INSERT INTO negocios (nombre, rubro, plan, proximo_cobro, alerta_email) VALUES (?, ?, ?, ?, ?)', [
            $d['nombre'], $d['rubro'] ?: null, $d['plan'],
            $d['proximo_cobro'] ?? date('Y-m-d', strtotime('+1 month')), $d['alerta_email'] ?? null,
        ]);
        $nid = (int)$pdo->lastInsertId();
        q('INSERT INTO sucursales (negocio_id, nombre, direccion) VALUES (?, ?, ?)', [$nid, $d['sucursal'] ?: 'Sucursal principal', $d['direccion'] ?? null]);
        $sid = (int)$pdo->lastInsertId();
        $mesas = max(1, min(100, (int)($d['mesas'] ?? 2)));
        for ($i = 1; $i <= $mesas; $i++) {
            q('INSERT INTO mesas (negocio_id, sucursal_id, numero, nombre, token) VALUES (?, ?, ?, ?, ?)', [$nid, $sid, $i, 'Mesa ' . $i, token_mesa()]);
        }
        foreach (PREGUNTAS_BASE as $i => $t) {
            q('INSERT INTO preguntas (negocio_id, texto, texto_en, orden) VALUES (?, ?, ?, ?)', [$nid, $t, PREGUNTAS_BASE_EN[$t] ?? null, $i + 1]);
        }
        if (!empty($d['admin_email'])) {
            q("INSERT INTO usuarios (negocio_id, nombre, email, password_hash, rol) VALUES (?, ?, ?, ?, 'admin')", [
                $nid, $d['admin_nombre'], strtolower($d['admin_email']), password_hash($d['admin_password'], PASSWORD_DEFAULT),
            ]);
        }
        $pdo->commit();
        return $nid;
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }
}

/** Llena un negocio con encuestas de ejemplo (para demos con prospectos). */
function sembrar_demo(int $nid, int $cantidad = 60): void
{
    $mesas = q('SELECT id, sucursal_id FROM mesas WHERE negocio_id = ?', [$nid])->fetchAll();
    $preg  = q('SELECT id FROM preguntas WHERE negocio_id = ? ORDER BY orden', [$nid])->fetchAll(PDO::FETCH_COLUMN);
    if (!$mesas || !$preg) return;

    $buenos = ['Todo muy rico, el pollo en su punto.', 'La mesera súper amable, volvemos seguro.', 'Excelente servicio, gracias!',
        'Rico el café y rápido.', 'Ambiente tranquilo, buena atención.', 'Muy recomendado.', 'Las porciones están perfectas.',
        'Nos atendieron rapidísimo aunque estaba lleno.', 'El postre de la casa buenísimo.', 'Muy limpio todo.', '', '', '', ''];
    $malos  = ['Tardaron bastante con las bebidas.', 'Los baños no estaban limpios.', 'La comida llegó fría.',
        'Nos olvidaron la orden de papas.', 'Muy caro para lo que sirven.', 'Tardaron 40 minutos con el plato fuerte.',
        'El mesero no regresó a la mesa.', 'La música demasiado alta.', ''];
    $nombres = ['Andrea', 'Luis', 'Carlos', 'María José', 'Pedro', 'Sofía', 'Diego', 'Ana Lucía', '', '', '', '', ''];

    $pers = q('SELECT id FROM personal WHERE negocio_id = ?', [$nid])->fetchAll(PDO::FETCH_COLUMN);
    if (!$pers) {
        foreach ([['Andrea', 'Mesera'], ['Luis', 'Mesero'], ['Carla', 'Mesera']] as [$pn, $pp]) {
            q('INSERT INTO personal (negocio_id, nombre, puesto) VALUES (?, ?, ?)', [$nid, $pn, $pp]);
        }
        $pers = q('SELECT id FROM personal WHERE negocio_id = ?', [$nid])->fetchAll(PDO::FETCH_COLUMN);
        q("UPDATE negocios SET preguntar_personal = 1 WHERE id = ?", [$nid]);
    }
    $insE = db()->prepare('INSERT INTO encuestas (negocio_id, sucursal_id, mesa_id, promedio, recomendaria, comentario, nombre_cliente, revisada, creado_en, personal_id) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $insR = db()->prepare('INSERT INTO respuestas (encuesta_id, pregunta_id, calificacion) VALUES (?,?,?)');
    for ($i = 0; $i < $cantidad; $i++) {
        $m = $mesas[array_rand($mesas)];
        $malo = mt_rand(1, 100) <= 18;
        $notas = [];
        foreach ($preg as $p) {
            $notas[$p] = $malo ? [1, 2, 2, 3, 4][mt_rand(0, 4)] : [3, 4, 4, 5, 5, 5][mt_rand(0, 5)];
        }
        $prom = round(array_sum($notas) / count($notas), 2);
        $com = $malo ? $malos[array_rand($malos)] : $buenos[array_rand($buenos)];
        $nom = $nombres[array_rand($nombres)];
        $dias = mt_rand(0, 59);
        $fecha = date('Y-m-d H:i:s', strtotime("-$dias days") - mt_rand(0, 9) * 3600);
        if (strtotime($fecha) > time()) $fecha = date('Y-m-d H:i:s');
        $pid = mt_rand(1, 100) <= 75 ? $pers[array_rand($pers)] : null;
        $insE->execute([$nid, $m['sucursal_id'], $m['id'], $prom, $malo ? 0 : 1, $com ?: null, $nom ?: null, ($malo && $dias > 3) ? 1 : 0, $fecha, $pid]);
        $eid = (int)db()->lastInsertId();
        foreach ($notas as $p => $v) {
            $insR->execute([$eid, $p, $v]);
        }
    }
}

/** Sucursal elegida en filtros (?s=). Un gerente asignado a una sucursal siempre queda fijo en la suya. */
function filtro_sucursal(array $u, array $sucVisibles): ?int
{
    if (fijo_sucursal($u)) {
        return (int)$u['sucursal_id'];
    }
    $s = (int)($_GET['s'] ?? 0);
    return in_array($s, array_map('intval', array_column($sucVisibles, 'id')), true) ? $s : null;
}

/** Verifica que una encuesta/mesa/sucursal pertenezca al negocio (y a la sucursal del gerente). */
function puede_ver_sucursal(array $u, int $sucursalId): bool
{
    return !(fijo_sucursal($u) && (int)$u['sucursal_id'] !== $sucursalId);
}

/* =========================================================
   Colores de marca de cada negocio
   ========================================================= */
function color_valido($c): ?string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', (string)$c) ? strtoupper((string)$c) : null;
}

function luminancia(string $hex): float
{
    $c = [];
    foreach ([1, 3, 5] as $i) {
        $v = hexdec(substr($hex, $i, 2)) / 255;
        $c[] = $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
    }
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

function contraste(string $a, string $b): float
{
    $la = luminancia($a); $lb = luminancia($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/** Texto legible (blanco o navy) sobre un color de fondo. */
function texto_sobre(string $fondo): string
{
    return contraste($fondo, '#FFFFFF') >= contraste($fondo, '#141A3D') ? '#FFFFFF' : '#141A3D';
}

/** Variables CSS con los colores del negocio. Vacío si usa los de Whispers. */
function vars_marca(array $n): string
{
    $p = color_valido($n['color_principal'] ?? null);
    $a = color_valido($n['color_acento'] ?? null);
    if (!$p && !$a) return '';
    $p = $p ?: '#141A3D';
    $a = $a ?: '#9D8CFF';
    $tp = texto_sobre($p);
    $oscuro = $tp === '#FFFFFF';
    $v = [
        '--marca' => $p,
        '--marca-txt' => $tp,
        '--marca-suave' => $oscuro ? 'rgba(255,255,255,.74)' : 'rgba(20,26,61,.7)',
        '--marca-hover' => $oscuro ? 'rgba(255,255,255,.1)' : 'rgba(20,26,61,.08)',
        '--boton' => $p,
        '--boton-txt' => $tp,
        '--acento' => $a,
        '--acento-txt' => texto_sobre($a),
        '--estrella' => $a,
        '--acento-sobre-marca' => contraste($a, $p) >= 2.2 ? $a : $tp,
    ];
    $out = '';
    foreach ($v as $k => $val) $out .= $k . ':' . $val . ';';
    return $out;
}

/* =========================================================
   Actualizaciones de base de datos para instalaciones anteriores
   ========================================================= */
const DB_VERSION = 8;
const MAX_CONTACTOS_ALERTA = 5;

function migrar(): void
{
    try {
        $v = (int)(db()->query("SELECT valor FROM ajustes WHERE clave = 'db_version'")->fetchColumn() ?: 1);
        if ($v >= DB_VERSION) return;
        $cols = db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'negocios'")->fetchAll(PDO::FETCH_COLUMN);
        $nuevas = [
            'color_principal' => 'VARCHAR(7) NULL',
            'color_acento'    => 'VARCHAR(7) NULL',
            'colores_panel'   => 'TINYINT(1) NOT NULL DEFAULT 0',
        ];
        foreach ($nuevas as $col => $def) {
            if (!in_array($col, $cols, true)) db()->exec("ALTER TABLE negocios ADD COLUMN $col $def");
        }
        if ($v < 3) {
            // Varios números y correos para alertas, separados por coma
            db()->exec('ALTER TABLE negocios MODIFY alerta_whatsapp VARCHAR(255) NULL, MODIFY alerta_email VARCHAR(800) NULL');
        }
        if ($v < 5) {
            // Cupones, personal, inglés, pregunta especial, resumen semanal
            $agregar = function (string $tabla, array $cols) {
                $hay = db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tabla'")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($cols as $c => $def) if (!in_array($c, $hay, true)) db()->exec("ALTER TABLE $tabla ADD COLUMN $c $def");
            };
            $agregar('negocios', [
                'preguntar_personal' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'resumen_semanal' => 'TINYINT(1) NOT NULL DEFAULT 1',
            ]);
            $agregar('preguntas', ['texto_en' => 'VARCHAR(150) NULL', 'especial' => 'TINYINT(1) NOT NULL DEFAULT 0', 'hasta' => 'DATE NULL']);
            $agregar('encuestas', ['personal_id' => 'INT NULL', 'idioma' => "CHAR(2) NOT NULL DEFAULT 'es'"]);
            ejecutar_sql_archivo(db(), __DIR__ . '/includes/schema_v5.sql');
            foreach (PREGUNTAS_BASE_EN as $es => $en) {
                db()->prepare('UPDATE preguntas SET texto_en = ? WHERE texto = ? AND texto_en IS NULL')->execute([$en, $es]);
            }
        }
        if ($v < 6) {
            // Se retiró el módulo de comandas: los usuarios mesero/cocina quedan desactivados
            db()->exec("UPDATE usuarios SET activo = 0 WHERE rol NOT IN ('super','admin','gerente')");
        }
        if ($v < 7) {
            // Envío automático del cupón de disculpa por la API de WhatsApp
            $hay = db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cupones'")->fetchAll(PDO::FETCH_COLUMN);
            if ($hay && !in_array('envio_auto', $hay, true)) db()->exec('ALTER TABLE cupones ADD COLUMN envio_auto TINYINT(1) NOT NULL DEFAULT 0');
        }
        if ($v < 8) {
            ejecutar_sql_archivo(db(), __DIR__ . '/includes/schema_v5.sql');
        }
        if (ajuste('app_secret') === '') ajuste_guardar('app_secret', bin2hex(random_bytes(24)));
        ajuste_guardar('db_version', (string)DB_VERSION);
    } catch (Throwable $t) {
        error_log('Whispers migración: ' . $t->getMessage());
    }
}

if (INSTALADO && PHP_SAPI !== 'cli') {
    migrar();
}

/** Convierte "a, b, c" (o un arreglo) en una lista limpia, sin repetidos. */
function lista_contactos($valor): array
{
    $partes = is_array($valor) ? $valor : preg_split('/[,;\n]+/', (string)$valor);
    $out = [];
    foreach ($partes as $p) {
        $p = trim((string)$p);
        if ($p !== '' && !in_array($p, $out, true)) $out[] = $p;
    }
    return $out;
}
