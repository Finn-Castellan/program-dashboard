<?php
// ─────────────────────────────────────────────────────────────
//  GET  api/activities.php?program_id=GIP  → list activities
//  GET  api/activities.php                 → all activities
//  POST api/activities.php                 → create activity
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setCorsHeaders();

$VALID_TYPES = [
    'Workshops','Coaching','Gate Reviews','Testing',
    'Advisory','Demo Days','Investor Eng.','Pilots',
];
$VALID_STATUSES = ['on-track','caution','behind'];

// ── GET ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo = getPDO();

        if (!empty($_GET['program_id'])) {
            $pid  = trim($_GET['program_id']);
            $stmt = $pdo->prepare(
                'SELECT id, program_id, name, type, activity_date, status,
                        responsible_person, notes, created_at
                   FROM activities
                  WHERE program_id = ?
                  ORDER BY activity_date DESC, created_at DESC
                  LIMIT 50'
            );
            $stmt->execute([$pid]);
        } else {
            $stmt = $pdo->query(
                'SELECT id, program_id, name, type, activity_date, status,
                        responsible_person, notes, created_at
                   FROM activities
                  ORDER BY activity_date DESC, created_at DESC
                  LIMIT 200'
            );
        }

        $activities = [];
        foreach ($stmt->fetchAll() as $row) {
            $activities[] = [
                'id'       => (int) $row['id'],
                'progId'   => $row['program_id'],
                'name'     => $row['name'],
                'type'     => $row['type'],
                'date'     => $row['activity_date'],
                'status'   => $row['status'],
                'person'   => $row['responsible_person'] ?? 'Unassigned',
                'notes'    => $row['notes'] ?? '',
                'isNew'    => true,
            ];
        }

        jsonOk($activities);

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

    // Validate required fields
    $progId = trim($body['program_id'] ?? '');
    $name   = trim($body['name']       ?? '');
    $type   = trim($body['type']       ?? '');
    $date   = trim($body['date']       ?? '');
    $status = trim($body['status']     ?? 'on-track');
    $person = trim($body['person']     ?? 'Unassigned');
    $notes  = trim($body['notes']      ?? '');

    if ($name === '') {
        jsonError('Activity name is required');
    }
    if (!preg_match('/^[A-Z]{2,10}$/', $progId)) {
        jsonError('Invalid program_id');
    }
    if (!in_array($type, $VALID_TYPES, true)) {
        jsonError('Invalid activity type');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate(
        (int)substr($date,5,2), (int)substr($date,8,2), (int)substr($date,0,4)
    )) {
        jsonError('Invalid date format (expected YYYY-MM-DD)');
    }
    if (!in_array($status, $VALID_STATUSES, true)) {
        jsonError('Invalid status');
    }
    if (mb_strlen($name) > 200 || mb_strlen($person) > 100) {
        jsonError('Input too long');
    }

    try {
        $pdo = getPDO();

        // Verify program exists
        $chk = $pdo->prepare('SELECT id FROM programs WHERE id = ? LIMIT 1');
        $chk->execute([$progId]);
        if (!$chk->fetch()) {
            jsonError('Program not found', 404);
        }

        // Insert activity
        $ins = $pdo->prepare(
            'INSERT INTO activities
               (program_id, name, type, activity_date, status, responsible_person, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$progId, $name, $type, $date, $status, $person ?: null, $notes ?: null]);
        $newId = (int) $pdo->lastInsertId();

        // Increment program counters
        $upd = $pdo->prepare(
            'UPDATE programs
                SET today_count    = today_count + 1,
                    total_count    = total_count + 1,
                    completion_pct = LEAST(100, completion_pct + 1),
                    updated_at     = CURRENT_TIMESTAMP
              WHERE id = ?'
        );
        $upd->execute([$progId]);

        // Update type_counts_json for the matching activity type
        $typeIdx = array_search($type, $VALID_TYPES, true);
        if ($typeIdx !== false) {
            $pdo->prepare(
                "UPDATE programs
                    SET type_counts_json = JSON_SET(
                          type_counts_json,
                          CONCAT('$[', ?, ']'),
                          CAST(JSON_EXTRACT(type_counts_json, CONCAT('$[', ?, ']')) AS UNSIGNED) + 1
                        )
                  WHERE id = ?"
            )->execute([$typeIdx, $typeIdx, $progId]);
        }

        jsonOk(['success' => true, 'id' => $newId]);

    } catch (PDOException $e) {
        jsonError('Database error', 500);
    }
}

jsonError('Method not allowed', 405);
