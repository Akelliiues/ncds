<?php
// api/kpi_cache.php - High-Speed Materialized KPI Aggregator & Background Cache Rebuilder
// Supports CLI, Cron Trigger, or Authorized Admin Request

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cache.php';

header('Content-Type: application/json; charset=utf-8');

$isCli = (php_sapi_name() === 'cli');
$isAdmin = isset($_SESSION['admin_user']) || isset($_SESSION['admin_role']);
$cronKey = isset($_GET['cron_key']) ? trim((string)$_GET['cron_key']) : '';
$validCronKey = 'ncd_kpi_cron_' . date('Ymd'); // e.g. ncd_kpi_cron_20260826

if (!$isCli && !$isAdmin && $cronKey !== $validCronKey && $cronKey !== 'ncd_kpi_rebuild_secret') {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access. Admin session, CLI, or valid cron key required.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$startTime = microtime(true);
$budgetYear = isset($_GET['budget_year']) && ctype_digit((string)$_GET['budget_year']) 
    ? (int)$_GET['budget_year'] : (int)date('Y') + 543; // Default current Thai budget year

// 8 Official Health Units
$healthUnits = [
    '10957' => ['name' => 'โรงพยาบาลตาลสุม', 'tambon' => '342001'],
    '03751' => ['name' => 'รพ.สต.ดอนพันชาด', 'tambon' => '342001'],
    '03752' => ['name' => 'รพ.สต.บ้านสำโรง', 'tambon' => '342002'],
    '03753' => ['name' => 'รพ.สต.บ้านจิกเทิง', 'tambon' => '342003'],
    '03754' => ['name' => 'รพ.สต.บ้านหนองกุงใหญ่', 'tambon' => '342004'],
    '03755' => ['name' => 'รพ.สต.นาคาย', 'tambon' => '342005'],
    '03756' => ['name' => 'รพ.สต.คำหนามแท่ง', 'tambon' => '342005'],
    '03757' => ['name' => 'รพ.สต.คำหว้า', 'tambon' => '342006']
];

$tambons = [
    '342001' => 'ตำบลตาลสุม',
    '342002' => 'ตำบลสำโรง',
    '342003' => 'ตำบลจิกเทิง',
    '342004' => 'ตำบลหนองกุง',
    '342005' => 'ตำบลนาคาย',
    '342006' => 'ตำบลคำหว้า'
];

$rebuiltUnits = 0;
$rebuiltTambons = 0;

try {
    foreach ([0, 1] as $isSandbox) {
        // 1. Calculate per Health Unit
        foreach ($healthUnits as $hCode => $hData) {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT p.cid) as total_target,
                    COUNT(DISTINCT CASE WHEN s.round_number = 1 THEN s.target_cid END) as r1_done,
                    COUNT(DISTINCT CASE WHEN s.round_number = 2 THEN s.target_cid END) as r2_done,
                    COUNT(DISTINCT CASE WHEN s.round_number >= 3 THEN s.target_cid END) as r3_done
                FROM target_population p
                LEFT JOIN task_assignments a ON p.cid = a.target_cid 
                    AND a.budget_year = ? AND a.is_sandbox = ?
                LEFT JOIN screening_results s ON a.assignment_id = s.assignment_id 
                    AND s.is_sandbox = ?
                WHERE p.hoscode = ?
            ");
            $stmt->execute([$budgetYear, $isSandbox, $isSandbox, $hCode]);
            $unitRow = $stmt->fetch() ?: [];

            $totalTarget = (int)($unitRow['total_target'] ?? 0);
            $r1Done = (int)($unitRow['r1_done'] ?? 0);
            $r2Done = (int)($unitRow['r2_done'] ?? 0);
            $r3Done = (int)($unitRow['r3_done'] ?? 0);

            // DPAC calculation
            $dpacStmt = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT e.enrollment_id) as dpac_total,
                    COUNT(DISTINCT CASE WHEN f.status = 'completed' THEN f.followup_id END) as dpac_done
                FROM dpac_enrollments e
                LEFT JOIN dpac_followups f ON e.enrollment_id = f.enrollment_id AND f.is_sandbox = ?
                JOIN target_population p ON e.cid = p.cid
                WHERE e.budget_year = ? AND e.is_sandbox = ? AND p.hoscode = ?
            ");
            $dpacStmt->execute([$isSandbox, $budgetYear, $isSandbox, $hCode]);
            $dpacRow = $dpacStmt->fetch() ?: [];

            $dpacTotal = (int)($dpacRow['dpac_total'] ?? 0);
            $dpacDone = (int)($dpacRow['dpac_done'] ?? 0);

            $cacheKey = "unit_{$hCode}_{$budgetYear}_sb{$isSandbox}";
            $payloadJson = json_encode([
                'unit_code' => $hCode,
                'unit_name' => $hData['name'],
                'tambon_code' => $hData['tambon'],
                'total_target' => $totalTarget,
                'r1_done' => $r1Done,
                'r2_done' => $r2Done,
                'r3_done' => $r3Done,
                'dpac_total' => $dpacTotal,
                'dpac_done' => $dpacDone,
                'cached_at' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE);

            $insStmt = $pdo->prepare("
                INSERT INTO kpi_summary_cache 
                    (cache_key, budget_year, hoscode, sub_district_code, is_sandbox, total_target, r1_done, r2_done, r3_done, dpac_total, dpac_done, payload_json, updated_at)
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    total_target = VALUES(total_target),
                    r1_done = VALUES(r1_done),
                    r2_done = VALUES(r2_done),
                    r3_done = VALUES(r3_done),
                    dpac_total = VALUES(dpac_total),
                    dpac_done = VALUES(dpac_done),
                    payload_json = VALUES(payload_json),
                    updated_at = NOW()
            ");
            $insStmt->execute([
                $cacheKey, $budgetYear, $hCode, $hData['tambon'], $isSandbox,
                $totalTarget, $r1Done, $r2Done, $r3Done, $dpacTotal, $dpacDone, $payloadJson
            ]);

            // Save in NcdCache (5 minutes TTL)
            NcdCache::set($cacheKey, json_decode($payloadJson, true), 300);
            $rebuiltUnits++;
        }

        // 2. Calculate District Overall Total
        $distStmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT p.cid) as total_target,
                COUNT(DISTINCT CASE WHEN s.round_number = 1 THEN s.target_cid END) as r1_done,
                COUNT(DISTINCT CASE WHEN s.round_number = 2 THEN s.target_cid END) as r2_done,
                COUNT(DISTINCT CASE WHEN s.round_number >= 3 THEN s.target_cid END) as r3_done
            FROM target_population p
            LEFT JOIN task_assignments a ON p.cid = a.target_cid 
                AND a.budget_year = ? AND a.is_sandbox = ?
            LEFT JOIN screening_results s ON a.assignment_id = s.assignment_id 
                AND s.is_sandbox = ?
        ");
        $distStmt->execute([$budgetYear, $isSandbox, $isSandbox]);
        $distRow = $distStmt->fetch() ?: [];

        $distKey = "district_total_{$budgetYear}_sb{$isSandbox}";
        $distPayload = json_encode([
            'district' => 'อำเภอตาลสุม',
            'total_target' => (int)($distRow['total_target'] ?? 0),
            'r1_done' => (int)($distRow['r1_done'] ?? 0),
            'r2_done' => (int)($distRow['r2_done'] ?? 0),
            'r3_done' => (int)($distRow['r3_done'] ?? 0),
            'cached_at' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);

        $pdo->prepare("
            INSERT INTO kpi_summary_cache 
                (cache_key, budget_year, hoscode, sub_district_code, is_sandbox, total_target, r1_done, r2_done, r3_done, payload_json, updated_at)
            VALUES 
                (?, ?, 'ALL', 'ALL', ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                total_target = VALUES(total_target),
                r1_done = VALUES(r1_done),
                r2_done = VALUES(r2_done),
                r3_done = VALUES(r3_done),
                payload_json = VALUES(payload_json),
                updated_at = NOW()
        ")->execute([
            $distKey, $budgetYear, $isSandbox,
            (int)($distRow['total_target'] ?? 0),
            (int)($distRow['r1_done'] ?? 0),
            (int)($distRow['r2_done'] ?? 0),
            (int)($distRow['r3_done'] ?? 0),
            $distPayload
        ]);

        NcdCache::set($distKey, json_decode($distPayload, true), 300);
    }

    $durationMs = round((microtime(true) - $startTime) * 1000, 2);

    echo json_encode([
        'status' => 'success',
        'message' => 'Materialized KPI Summary Cache rebuilt successfully.',
        'budget_year' => $budgetYear,
        'rebuilt_health_units' => $rebuiltUnits,
        'duration_ms' => $durationMs,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to rebuild KPI cache: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
