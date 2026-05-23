<?php
// ─────────────────────────────────────────────────────────────
//  GET   api/programs.php              → all programs
//  PATCH api/programs.php              → update one program
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setCorsHeaders();

// ── PATCH ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    requireAuth();
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) jsonError('Invalid JSON body');

    $id = trim($body['id'] ?? '');
    if (!preg_match('/^[A-Z]{2,10}$/', $id)) jsonError('Invalid program id');

    $VALID_STATUSES = ['on-track', 'caution', 'behind'];

    $fields = [];
    $params = [];

    if (isset($body['status'])) {
        if (!in_array($body['status'], $VALID_STATUSES, true)) jsonError('Invalid status');
        $fields[] = 'status = ?';
        $params[] = $body['status'];
    }
    if (isset($body['budget_used'])) {
        $v = filter_var($body['budget_used'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>100]]);
        if ($v === false) jsonError('budget_used must be 0–100');
        $fields[] = 'budget_used = ?'; $params[] = $v;
    }
    if (isset($body['today_count'])) {
        $v = filter_var($body['today_count'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
        if ($v === false) jsonError('today_count must be a non-negative integer');
        $fields[] = 'today_count = ?'; $params[] = $v;
    }
    if (isset($body['total_count'])) {
        $v = filter_var($body['total_count'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
        if ($v === false) jsonError('total_count must be a non-negative integer');
        $fields[] = 'total_count = ?'; $params[] = $v;
    }
    if (isset($body['completion_pct'])) {
        $v = filter_var($body['completion_pct'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>100]]);
        if ($v === false) jsonError('completion_pct must be 0–100');
        $fields[] = 'completion_pct = ?'; $params[] = $v;
    }
    if (isset($body['kpis']) && is_array($body['kpis'])) {
        $fields[] = 'kpis_json = ?'; $params[] = json_encode($body['kpis']);
    }
    if (isset($body['trend']) && is_array($body['trend'])) {
        $fields[] = 'trend_json = ?'; $params[] = json_encode(array_values($body['trend']));
    }

    if (empty($fields)) jsonError('No updatable fields provided');

    $fields[]  = 'updated_at = CURRENT_TIMESTAMP';
    $params[]  = $id;

    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare(
            'UPDATE programs SET ' . implode(', ', $fields) . ' WHERE id = ?'
        );
        $stmt->execute($params);
        if ($stmt->rowCount() === 0) jsonError('Program not found', 404);

        // ── Write audit snapshot ─────────────────────────────
        $snap = $pdo->prepare(
            'SELECT status, budget_used, today_count, total_count, completion_pct, kpis_json
               FROM programs WHERE id = ?'
        );
        $snap->execute([$id]);
        $cur = $snap->fetch();

        $changedFields = implode(',', array_map(
            fn($f) => trim(explode(' ', $f)[0]),   // strip "= ?" to get just the column name
            array_filter($fields, fn($f) => $f !== 'updated_at = CURRENT_TIMESTAMP')
        ));

        $ins = $pdo->prepare(
            'INSERT INTO program_snapshots
               (program_id, changed_by, status, budget_used, today_count,
                total_count, completion_pct, kpis_json, changed_fields)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $id,
            $_SESSION['admin_user'] ?? 'admin',
            $cur['status'],
            (int) $cur['budget_used'],
            (int) $cur['today_count'],
            (int) $cur['total_count'],
            (int) $cur['completion_pct'],
            $cur['kpis_json'],
            $changedFields,
        ]);

        jsonOk(['success' => true]);
    } catch (PDOException $e) {
        jsonError('Database error', 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

// Activity type enum order — must match the activities table ENUM definition
// and the bar/doughnut chart label order in the front-end.
const TYPE_ORDER = [
    'Workshops', 'Coaching', 'Gate Reviews', 'Testing',
    'Advisory',  'Demo Days', 'Investor Eng.', 'Pilots'
];

try {
    $pdo = getPDO();

    // ── 1. Fetch all programs ────────────────────────────────
    $stmt = $pdo->query(
        "SELECT * FROM programs
         ORDER BY FIELD(id,'GIP','TES','VBP','PCTP','SAP','IRP','GMP','IDIA')"
    );
    $rows = $stmt->fetchAll();

    // ── 2. Live activity-type counts (one query, all programs) ─
    $cntStmt = $pdo->query(
        "SELECT program_id, type, COUNT(*) AS cnt
         FROM activities
         GROUP BY program_id, type"
    );
    // $liveCounts[program_id][type] = count
    $liveCounts = [];
    foreach ($cntStmt->fetchAll() as $r) {
        $liveCounts[$r['program_id']][$r['type']] = (int) $r['cnt'];
    }

    // ── 3. Build response ────────────────────────────────────
    $programs = [];
    foreach ($rows as $row) {
        $pid = $row['id'];

        if (!empty($liveCounts[$pid])) {
            // Build 8-element array from real activity records
            $byType = [];
            foreach (TYPE_ORDER as $t) {
                $byType[] = $liveCounts[$pid][$t] ?? 0;
            }
            // Doughnut chart uses first 5 types
            $dist = array_slice($byType, 0, 5);
        } else {
            // No real activities logged yet — fall back to stored seed JSON
            $byType = json_decode($row['type_counts_json'] ?? '[]') ?: [];
            $dist   = json_decode($row['distribution_json']  ?? '[]') ?: [];
        }

        $programs[] = [
            'id'          => $pid,
            'name'        => $row['name'],
            'abbr'        => $row['abbr'],
            'stage'       => $row['stage'],
            'icon'        => $row['icon'],
            'color'       => $row['color'],
            'desc'        => $row['description'] ?? '',
            'status'      => $row['status'],
            'budget_used' => (int) $row['budget_used'],
            'metrics'     => [
                'today'      => (int) $row['today_count'],
                'total'      => (int) $row['total_count'],
                'completion' => (int) $row['completion_pct'],
            ],
            'trend'              => json_decode($row['trend_json'] ?? '[]'),
            'activities_by_type' => $byType,
            'distribution'       => $dist,
            'kpis'               => json_decode($row['kpis_json'] ?? '{}', true) ?? (object)[],
        ];
    }

    jsonOk($programs);

} catch (PDOException $e) {
    jsonError('Database error', 500);
}
