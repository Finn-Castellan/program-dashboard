<?php
// ─────────────────────────────────────────────────────────────
//  GET  api/alerts.php                          → active alerts
//  POST api/alerts.php {"action":"create", …}   → new alert
//  POST api/alerts.php {"action":"acknowledge_all"}
//  POST api/alerts.php {"action":"acknowledge","id":5}
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setCorsHeaders();

// ── GET ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo  = getPDO();
        $stmt = $pdo->query(
            "SELECT id, program_id, type, title, description,
                    tag, time_label AS time, icon
               FROM alerts
              WHERE acknowledged = 0
              ORDER BY FIELD(type,'overdue','deadline','info'), created_at DESC"
        );
        jsonOk($stmt->fetchAll());

    } catch (PDOException $e) {
        jsonError('Database error', 500);
    }
}

// ── POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAuth();
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        jsonError('Invalid JSON body');
    }

    $action = $body['action'] ?? '';

    try {
        $pdo = getPDO();

        if ($action === 'create') {
            $VALID_TYPES = ['overdue', 'deadline', 'info'];
            $type        = trim($body['type']        ?? 'info');
            $title       = trim($body['title']       ?? '');
            $desc        = trim($body['description'] ?? '');
            $tag         = trim($body['tag']         ?? '');
            $timeLabel   = trim($body['time_label']  ?? 'Just now');
            $icon        = trim($body['icon']        ?? '🔔');
            $progId      = trim($body['program_id']  ?? '') ?: null;

            if ($title === '') jsonError('Alert title is required');
            if (!in_array($type, $VALID_TYPES, true)) jsonError('Invalid alert type');
            if (mb_strlen($title) > 200 || mb_strlen($desc) > 500) jsonError('Input too long');
            if ($progId !== null && !preg_match('/^[A-Z]{2,10}$/', $progId)) jsonError('Invalid program_id');

            $stmt = $pdo->prepare(
                'INSERT INTO alerts (program_id, type, title, description, tag, time_label, icon)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$progId, $type, $title, $desc, $tag, $timeLabel, $icon]);
            jsonOk(['success' => true, 'id' => (int) $pdo->lastInsertId()]);
        }

        if ($action === 'acknowledge_all') {
            $pdo->exec("UPDATE alerts SET acknowledged = 1 WHERE acknowledged = 0");
            jsonOk(['success' => true, 'acknowledged' => 'all']);
        }

        if ($action === 'acknowledge') {
            $id = filter_var($body['id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                jsonError('Invalid alert id');
            }
            $stmt = $pdo->prepare(
                'UPDATE alerts SET acknowledged = 1 WHERE id = ? AND acknowledged = 0'
            );
            $stmt->execute([$id]);
            jsonOk(['success' => true, 'acknowledged' => $id]);
        }

        jsonError('Unknown action');

    } catch (PDOException $e) {
        jsonError('Database error', 500);
    }
}

jsonError('Method not allowed', 405);
