<?php
// ─────────────────────────────────────────────────────────────
//  Database connection — edit DB_USER / DB_PASS to match your
//  MySQL credentials (default XAMPP: root / empty password)
// ─────────────────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'u895763689_activity_db');
define('DB_USER',    'u895763689_activity_db');
define('DB_PASS',    '(Miic@2017#)');
define('DB_CHARSET', 'utf8mb4');

// ─────────────────────────────────────────────────────────────
//  Admin credentials
//  Default login: admin / miic2026!
//  To change the password, replace ADMIN_PASS_HASH with:
//    php -r "echo password_hash('yourNewPassword', PASSWORD_BCRYPT);"
// ─────────────────────────────────────────────────────────────
define('ADMIN_USER',      'admin');
define('ADMIN_PASS_HASH', '$2y$10$2PkA8llUkgGKOurMSRZddOCI2jIUYzKATQ2nmyx91LufRS6rxg1RG');

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// Common response helpers
function jsonOk(mixed $data): never {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $code = 400): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message]);
    exit;
}

// CORS for local development (localhost only)
function setCorsHeaders(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (preg_match('#^https?://localhost(:\d+)?$#', $origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    }
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Credentials: true');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ─────────────────────────────────────────────────────────────
//  Session / auth helpers
// ─────────────────────────────────────────────────────────────
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => false,   // set true when running over HTTPS
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function isAuthenticated(): bool {
    startSecureSession();
    return !empty($_SESSION['admin_user']);
}

function requireAuth(): void {
    if (!isAuthenticated()) {
        jsonError('Unauthorised', 401);
    }
}
