<?php
declare(strict_types=1);

/**
 * HOFARS bootstrap: config, database, session, security + view helpers.
 * Every page starts with: require __DIR__ . '/includes/bootstrap.php';
 */

define('APP_ROOT', dirname(__DIR__));

/* ---------------------------------------------------------------- config */

function load_env(string $file): void
{
    if (!is_file($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value, " \t\"'");
    }
}

function env(string $key, ?string $default = null): ?string
{
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    $value = getenv($key);
    return $value === false ? $default : $value;
}

load_env(APP_ROOT . '/.env');
date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Kuala_Lumpur'));

$debug = env('APP_DEBUG', 'false') === 'true';
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');

set_exception_handler(function (Throwable $e) use ($debug): void {
    error_log((string) $e);
    http_response_code(500);
    echo $debug
        ? '<pre>' . htmlspecialchars((string) $e) . '</pre>'
        : 'Something went wrong. Please try again later.';
});

/* -------------------------------------------------------------- database */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    if (env('DB_DRIVER', 'mysql') === 'sqlite') {
        // Only used for quick local smoke tests.
        $pdo = new PDO('sqlite:' . env('DB_NAME', APP_ROOT . '/hofars.sqlite'), null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('DB_HOST', '127.0.0.1'),
        env('DB_PORT', '3306'),
        env('DB_NAME', 'hofars_db')
    );
    $options[PDO::ATTR_EMULATE_PREPARES] = false;
    $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), $options);
    return $pdo;
}

/** Prepared-statement shortcut. */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/* --------------------------------------------------------------- session */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_name('hofars_session');
    session_start();
}

/* ------------------------------------------------------------- constants */

const ROLES = ['student', 'technician', 'admin'];
const REPORT_STATUSES = ['Pending', 'Assigned', 'In Progress', 'Under Review', 'Resolved', 'Cancelled'];

/* ---------------------------------------------------------- view helpers */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function report_code(int $id): string
{
    return 'HF-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

function status_class(string $status): string
{
    return 'status-' . strtolower(str_replace(' ', '-', $status));
}

function status_badge(string $status): string
{
    return '<span class="status-badge ' . e(status_class($status)) . '">' . e($status) . '</span>';
}

function fmt_datetime(?string $value): string
{
    return $value ? date('d M Y, H:i', strtotime($value)) : '-';
}

function fmt_date(?string $value): string
{
    return $value ? date('d M Y', strtotime($value)) : '-';
}

function initial(string $name): string
{
    return strtoupper(mb_substr(trim($name) ?: '?', 0, 1));
}

/* ---------------------------------------------------------------- security */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every POST handler. */
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

function current_user(bool $refresh = false): ?array
{
    static $user = false;
    if ($refresh) {
        $user = false;
    }
    if ($user !== false) {
        return $user;
    }
    $user = null;
    if (!empty($_SESSION['uid'])) {
        $user = q(
            'SELECT id, username, name, email, role, matric_number, avatar FROM users WHERE id = ?',
            [$_SESSION['uid']]
        )->fetch() ?: null;
        if ($user === null) {
            unset($_SESSION['uid']);
        }
    }
    return $user;
}

function home_for(string $role): string
{
    return match ($role) {
        'admin'      => 'admin_hofars.php',
        'technician' => 'technician_hofars.php',
        default      => 'student_hofars.php',
    };
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('index.php');
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        redirect(home_for($user['role']));
    }
    return $user;
}

function login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    unset($_SESSION['login_fails'], $_SESSION['login_lock_until']);
}

function password_problem(string $password): ?string
{
    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password must be at least 8 characters with 1 uppercase letter and 1 number.';
    }
    return null;
}

/* ----------------------------------------------------------------- uploads */

const IMAGE_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];
const VIDEO_TYPES = [
    'video/mp4'       => 'mp4',
    'video/quicktime' => 'mov',
    'video/x-msvideo' => 'avi',
];

function ensure_upload_dirs(): void
{
    $base = APP_ROOT . '/uploads';
    foreach ([$base, "$base/reports", "$base/updates", "$base/avatars"] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    // Uploads are never served directly; they go through download.php (which checks access).
    $htaccess = $base . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
    }
}

function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than the server allows.',
        UPLOAD_ERR_PARTIAL                        => 'The file was only partly uploaded. Please try again.',
        default                                   => 'The file could not be uploaded.',
    };
}

/**
 * Validates (real MIME type, size) and stores one uploaded file under a random name.
 * @return array{stored:string, original:string, mime:string}
 * @throws RuntimeException with a user-friendly message
 */
function store_upload(array $file, string $subdir, array $allowed, int $maxBytes): array
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message((int) $error));
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Each file must be ' . round($maxBytes / 1048576) . ' MB or smaller.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Unsupported file type. Allowed: ' . implode(', ', array_values($allowed)) . '.');
    }
    if (str_starts_with($mime, 'image/') && @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('That image file looks damaged.');
    }
    ensure_upload_dirs();
    $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], APP_ROOT . "/uploads/$subdir/$stored")) {
        throw new RuntimeException('Could not save the uploaded file.');
    }
    return [
        'stored'   => $stored,
        'original' => mb_substr(basename((string) $file['name']), 0, 150),
        'mime'     => $mime,
    ];
}

/** Turns PHP's $_FILES['x'] (multiple) into a list of single-file arrays, skipping empty slots. */
function normalize_files(?array $files): array
{
    if (!$files || !is_array($files['name'])) {
        return ($files && ($files['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) ? [$files] : [];
    }
    $out = [];
    foreach ($files['name'] as $i => $name) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name'     => $name,
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];
    }
    return $out;
}

function delete_upload(string $subdir, ?string $stored): void
{
    if ($stored && preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,4}$/', $stored)) {
        @unlink(APP_ROOT . "/uploads/$subdir/$stored");
    }
}

function avatar_url(?array $user): ?string
{
    return ($user && !empty($user['avatar'])) ? 'download.php?type=avatar&id=' . (int) $user['id'] : null;
}

/* ----------------------------------------------------------- report domain */

function add_history(int $reportId, string $status, string $note, ?int $actorId): void
{
    q(
        'INSERT INTO report_history (report_id, status, note, actor_id, created_at) VALUES (?, ?, ?, ?, ?)',
        [$reportId, $status, $note, $actorId, now()]
    );
}

/** Can this user see this report? Returns the report row (joined) or null. */
function find_report_for(array $user, int $id): ?array
{
    $report = q(
        'SELECT r.*, f.name AS facility, t.name AS technician_name
           FROM reports r
           JOIN facilities f ON f.id = r.facility_id
           LEFT JOIN users t ON t.id = r.technician_id
          WHERE r.id = ?',
        [$id]
    )->fetch();
    if (!$report) {
        return null;
    }
    $allowed = match ($user['role']) {
        'admin'      => true,
        'technician' => (int) $report['technician_id'] === (int) $user['id'],
        default      => (int) $report['user_id'] === (int) $user['id'],
    };
    return $allowed ? $report : null;
}
