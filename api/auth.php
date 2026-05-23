<?php
// ─────────────────────────────────────────────────────────────
//  GET    api/auth.php  → { authenticated: bool, user: string|null }
//  POST   api/auth.php  → login  { username, password }
//  DELETE api/auth.php  → logout
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setCorsHeaders();
startSecureSession();

// ── GET — session check ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonOk([
        'authenticated' => isAuthenticated(),
        'user'          => $_SESSION['admin_user'] ?? null,
    ]);
}

// ── POST — login ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true);
    $username = trim($body['username'] ?? '');
    $password = $body['password']      ?? '';

    // Constant-time username comparison to avoid timing attacks
    $userMatch = hash_equals(ADMIN_USER, $username);
    $passMatch = password_verify($password, ADMIN_PASS_HASH);

    if ($userMatch && $passMatch) {
        session_regenerate_id(true);
        $_SESSION['admin_user'] = $username;
        jsonOk(['success' => true, 'user' => $username]);
    }

    // Intentionally vague error — do not reveal which field was wrong
    jsonError('Invalid credentials', 401);
}

// ── DELETE — logout ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $_SESSION = [];
    session_destroy();
    jsonOk(['success' => true]);
}

jsonError('Method not allowed', 405);
