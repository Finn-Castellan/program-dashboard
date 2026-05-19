<?php
// ─────────────────────────────────────────────────────────────
//  GET  api/auto_alerts.php
//  Scans the database and auto-generates alerts for:
//    1. Activities past their date with status = 'behind'
//    2. Activities due within 7 days with status != 'on-track'
//    3. Programs with status = 'behind'
//    4. Programs with budget_used >= 85%
//    5. Programs with completion_pct < 50 (not already 'behind')
//  Deduplicates by title — will not insert if an unacknowledged
//  alert with the same title already exists.
//  Returns { created, skipped, alerts[] }
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

try {
    $pdo = getPDO();
    $created = 0;
    $skipped = 0;

    /**
     * Insert an alert only if no unacknowledged alert with the same title exists.
     */
    $insertIfNew = function (
        ?string $progId,
        string  $type,
        string  $title,
        string  $desc,
        string  $tag,
        string  $timeLabel,
        string  $icon
    ) use ($pdo, &$created, &$skipped): void {
        $check = $pdo->prepare(
            'SELECT id FROM alerts WHERE title = ? AND acknowledged = 0 LIMIT 1'
        );
        $check->execute([$title]);
        if ($check->fetch()) {
            $skipped++;
            return;
        }
        $ins = $pdo->prepare(
            'INSERT INTO alerts (program_id, type, title, description, tag, time_label, icon)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$progId, $type, $title, $desc, $tag, $timeLabel, $icon]);
        $created++;
    };

    // ── 1. Overdue activities (past activity_date + status = 'behind') ───
    $stmt = $pdo->query(
        "SELECT a.id, a.program_id, a.name, a.activity_date, a.responsible_person,
                p.abbr
           FROM activities a
           JOIN programs p ON p.id = a.program_id
          WHERE a.activity_date < CURDATE()
            AND a.status = 'behind'
          ORDER BY a.activity_date ASC
          LIMIT 20"
    );
    foreach ($stmt->fetchAll() as $act) {
        $daysAgo  = (int) (new DateTime('today'))->diff(new DateTime($act['activity_date']))->days;
        $dueFmt   = date('M j, Y', strtotime($act['activity_date']));
        $person   = $act['responsible_person'] ?? 'Unassigned';
        $title    = "{$act['abbr']}: '{$act['name']}' is overdue";
        $insertIfNew(
            $act['program_id'],
            'overdue',
            $title,
            "Activity was due {$dueFmt} and is marked behind. Responsible: {$person}.",
            $act['abbr'],
            $daysAgo . ' day' . ($daysAgo !== 1 ? 's' : '') . ' overdue',
            '🔴'
        );
    }

    // ── 2. Upcoming deadlines (due in 1–7 days, not on-track) ───────────
    $stmt = $pdo->prepare(
        "SELECT a.id, a.program_id, a.name, a.activity_date, a.status,
                p.abbr
           FROM activities a
           JOIN programs p ON p.id = a.program_id
          WHERE a.activity_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND a.status != 'on-track'
          ORDER BY a.activity_date ASC
          LIMIT 20"
    );
    $stmt->execute();
    foreach ($stmt->fetchAll() as $act) {
        $dueDate = date('M j, Y', strtotime($act['activity_date']));
        $title   = "{$act['abbr']}: '{$act['name']}' due soon";
        $insertIfNew(
            $act['program_id'],
            'deadline',
            $title,
            "Activity is due {$dueDate} and currently has status: {$act['status']}. Attention required.",
            $act['abbr'],
            "Due {$dueDate}",
            '🟡'
        );
    }

    // ── 3. Programs marked 'behind' ──────────────────────────────────────
    $stmt = $pdo->query(
        "SELECT id, abbr, name, completion_pct
           FROM programs
          WHERE status = 'behind'"
    );
    foreach ($stmt->fetchAll() as $prog) {
        $title = "{$prog['abbr']} is falling behind schedule";
        $insertIfNew(
            $prog['id'],
            'overdue',
            $title,
            "{$prog['name']} is marked 'behind'. Completion at {$prog['completion_pct']}%. Immediate review required.",
            $prog['abbr'],
            'Needs attention',
            '🔴'
        );
    }

    // ── 4. High budget utilisation (≥ 85%) ──────────────────────────────
    $stmt = $pdo->query(
        "SELECT id, abbr, name, budget_used
           FROM programs
          WHERE budget_used >= 85"
    );
    foreach ($stmt->fetchAll() as $prog) {
        $title = "{$prog['abbr']} budget at {$prog['budget_used']}%";
        $insertIfNew(
            $prog['id'],
            'deadline',
            $title,
            "{$prog['name']} has used {$prog['budget_used']}% of its allocated budget. Review expenditure and request reallocation if needed.",
            $prog['abbr'],
            'Budget warning',
            '🟡'
        );
    }

    // ── 5. Low completion but not yet flagged as 'behind' ────────────────
    $stmt = $pdo->query(
        "SELECT id, abbr, name, completion_pct
           FROM programs
          WHERE completion_pct < 50
            AND status != 'behind'"
    );
    foreach ($stmt->fetchAll() as $prog) {
        $title = "{$prog['abbr']} completion below 50%";
        $insertIfNew(
            $prog['id'],
            'info',
            $title,
            "{$prog['name']} is at {$prog['completion_pct']}% completion. Consider reviewing milestones and adjusting plans.",
            $prog['abbr'],
            'Low completion',
            '🔵'
        );
    }

    // Return the full fresh (unacknowledged) alerts list
    $fresh = $pdo->query(
        "SELECT id, program_id, type, title, description,
                tag, time_label AS time, icon
           FROM alerts
          WHERE acknowledged = 0
          ORDER BY FIELD(type,'overdue','deadline','info'), created_at DESC"
    )->fetchAll();

    jsonOk([
        'created' => $created,
        'skipped' => $skipped,
        'alerts'  => $fresh,
    ]);

} catch (PDOException $e) {
    jsonError('Database error', 500);
}
