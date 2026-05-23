<?php
// ─────────────────────────────────────────────────────────────
//  GET api/snapshots.php?program_id=GIP   → history for one program
//  GET api/snapshots.php?program_id=GIP&limit=90 (days, default 90)
//  GET api/snapshots.php                  → last 200 rows across all programs
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setCorsHeaders();
requireAuth();   // history is only for authenticated managers

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

try {
    $pdo   = getPDO();
    $limit = max(1, min(500, (int) ($_GET['limit'] ?? 90)));

    if (!empty($_GET['program_id'])) {
        $pid = trim($_GET['program_id']);
        if (!preg_match('/^[A-Z]{2,10}$/', $pid)) jsonError('Invalid program_id');

        $stmt = $pdo->prepare(
            "SELECT id, program_id, changed_by, status, budget_used,
                    today_count, total_count, completion_pct,
                    kpis_json, changed_fields, snapshot_at
               FROM program_snapshots
              WHERE program_id = ?
              ORDER BY snapshot_at DESC
              LIMIT $limit"
        );
        $stmt->execute([$pid]);
    } else {
        $stmt = $pdo->query(
            "SELECT id, program_id, changed_by, status, budget_used,
                    today_count, total_count, completion_pct,
                    kpis_json, changed_fields, snapshot_at
               FROM program_snapshots
              ORDER BY snapshot_at DESC
              LIMIT 200"
        );
    }

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'id'            => (int) $row['id'],
            'programId'     => $row['program_id'],
            'changedBy'     => $row['changed_by'],
            'status'        => $row['status'],
            'budgetUsed'    => (int) $row['budget_used'],
            'todayCount'    => (int) $row['today_count'],
            'totalCount'    => (int) $row['total_count'],
            'completionPct' => (int) $row['completion_pct'],
            'kpis'          => json_decode($row['kpis_json'] ?? 'null'),
            'changedFields' => $row['changed_fields'],
            'snapshotAt'    => $row['snapshot_at'],
        ];
    }

    jsonOk($rows);

} catch (PDOException $e) {
    jsonError('Database error', 500);
}
