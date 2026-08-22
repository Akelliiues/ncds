<?php
// api/get_health_progress.php
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/demo_data.php';

$cid = trim($_GET['cid'] ?? ($_POST['cid'] ?? ''));

if (DemoDataProvider::isDemoMode()) {
    echo json_encode([
        'status' => 'success',
        'cid' => $cid,
        'has_previous' => true,
        'previous' => [
            'bp_sys' => 148,
            'bp_dia' => 92,
            'fbs' => 118,
            'weight' => 68.5,
            'height' => 160,
            'bmi' => 26.8,
            'waist' => 88,
            'sleep_quality' => 'restless',
            'screen_date' => date('Y-m-d', strtotime('-60 days')),
            'source' => 'screening_r1'
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

require_once __DIR__ . '/../config/db.php';

if (empty($cid)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสบัตรประชาชน'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Look up most recent screening results for this CID
    $stmt = $pdo->prepare("
        SELECT sr.bp_sys, sr.bp_dia, sr.fbs, sr.weight, sr.height, sr.bmi, sr.waist,
               sr.sleep_quality, sr.screen_date, sr.care_level, sr.round_number
        FROM screening_results sr
        JOIN task_assignments ta ON sr.assignment_id = ta.assignment_id
        WHERE ta.target_cid = ?
        ORDER BY sr.screen_date DESC, sr.screening_id DESC
        LIMIT 2
    ");
    $stmt->execute([$cid]);
    $screenings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Also look up DPAC followups if any
    $stmtDpac = $pdo->prepare("
        SELECT df.bp_sys, df.bp_dia, df.fbs, df.weight, df.height, df.waist,
               df.sleep_quality, df.completed_at AS screen_date, df.round_number, df.care_level
        FROM dpac_followups df
        JOIN dpac_enrollments de ON df.enrollment_id = de.enrollment_id
        WHERE de.cid = ? AND df.status = 'completed'
        ORDER BY df.completed_at DESC, df.followup_id DESC
        LIMIT 2
    ");
    $stmtDpac->execute([$cid]);
    $dpacs = $stmtDpac->fetchAll(PDO::FETCH_ASSOC);

    // Combine and find previous record
    $allRecords = array_merge($screenings, $dpacs);
    usort($allRecords, function($a, $b) {
        return strtotime($b['screen_date'] ?? '1970-01-01') - strtotime($a['screen_date'] ?? '1970-01-01');
    });

    if (!empty($allRecords)) {
        $prev = $allRecords[0];
        echo json_encode([
            'status' => 'success',
            'cid' => $cid,
            'has_previous' => true,
            'previous' => $prev
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'status' => 'success',
            'cid' => $cid,
            'has_previous' => false,
            'previous' => null
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (\Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
