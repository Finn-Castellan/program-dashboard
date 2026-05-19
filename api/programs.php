<?php
// ─────────────────────────────────────────────────────────────
//  GET   api/programs.php              → all programs
//  PATCH api/programs.php              → update one program
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setCorsHeaders();

// ── PATCH ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
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
        jsonOk(['success' => true]);
    } catch (PDOException $e) {
        jsonError('Database error', 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

try {
    $pdo  = getPDO();
    $stmt = $pdo->query(
        "SELECT * FROM programs
         ORDER BY FIELD(id,'GIP','TES','VBP','PCTP','SAP','IRP','GMP','IDIA')"
    );
    $rows = $stmt->fetchAll();

    $programs = [];
    foreach ($rows as $row) {
        $programs[] = [
            'id'          => $row['id'],
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
            'trend'               => json_decode($row['trend_json']       ?? '[]'),
            'activities_by_type'  => json_decode($row['type_counts_json'] ?? '[]'),
            'distribution'        => json_decode($row['distribution_json'] ?? '[]'),
            'kpis'                => json_decode($row['kpis_json']         ?? '{}', true) ?? (object)[],
        ];
    }

    jsonOk($programs);

} catch (PDOException $e) {
    jsonError('Database error', 500);
}
