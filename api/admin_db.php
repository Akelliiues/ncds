<?php
// api/admin_db.php
require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

// Super Admin check: 'admin_logged_in' is true and 'admin_hoscode' is null
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || $_SESSION['admin_hoscode'] !== null) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ในการดำเนินการนี้']);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'clear_hoscode') {
    $hoscode = $_POST['hoscode'] ?? '';
    if (empty($hoscode)) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่ได้ระบุ รพ.สต.']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Target population table uses 'cid' as primary key.
        // Child tables: task_assignments (target_cid), screening_results (assignment_id), vhv_rewards (screening_id)
        
        // 1. Delete vhv_rewards where screening_id belongs to assignments of this hoscode
        $pdo->prepare("
            DELETE FROM vhv_rewards WHERE screening_id IN (
                SELECT s.screening_id 
                FROM screening_results s
                JOIN task_assignments a ON s.assignment_id = a.assignment_id
                JOIN target_population p ON a.target_cid = p.cid
                WHERE p.hoscode = ?
            )
        ")->execute([$hoscode]);

        // 2. Delete screening_results
        $pdo->prepare("
            DELETE FROM screening_results WHERE assignment_id IN (
                SELECT a.assignment_id
                FROM task_assignments a
                JOIN target_population p ON a.target_cid = p.cid
                WHERE p.hoscode = ?
            )
        ")->execute([$hoscode]);

        // 3. Delete task_assignments
        $pdo->prepare("
            DELETE FROM task_assignments WHERE target_cid IN (
                SELECT cid FROM target_population WHERE hoscode = ?
            )
        ")->execute([$hoscode]);

        // 4. Delete dpac_followups
        $pdo->prepare("
            DELETE FROM dpac_followups WHERE enrollment_id IN (
                SELECT e.enrollment_id 
                FROM dpac_enrollments e
                JOIN target_population p ON e.cid = p.cid
                WHERE p.hoscode = ?
            )
        ")->execute([$hoscode]);

        // 5. Delete dpac_enrollments
        $pdo->prepare("
            DELETE FROM dpac_enrollments WHERE cid IN (
                SELECT cid FROM target_population WHERE hoscode = ?
            )
        ")->execute([$hoscode]);

        // 6. Finally, delete target_population
        $stmt = $pdo->prepare("DELETE FROM target_population WHERE hoscode = ?");
        $stmt->execute([$hoscode]);
        $deletedRows = $stmt->rowCount();

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'ล้างข้อมูลสำเร็จแล้ว',
            'deleted_count' => $deletedRows
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
        ]);
    }
    exit();
} elseif ($action === 'delete_individual_record') {
    $cid = $_POST['cid'] ?? '';
    if (empty($cid)) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่ได้ระบุ CID']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Delete vhv_rewards
        $pdo->prepare("
            DELETE FROM vhv_rewards WHERE screening_id IN (
                SELECT s.screening_id 
                FROM screening_results s
                JOIN task_assignments a ON s.assignment_id = a.assignment_id
                WHERE a.target_cid = ?
            )
        ")->execute([$cid]);

        // 2. Delete screening_results
        $pdo->prepare("
            DELETE FROM screening_results WHERE assignment_id IN (
                SELECT assignment_id FROM task_assignments WHERE target_cid = ?
            )
        ")->execute([$cid]);

        // 3. Delete task_assignments
        $pdo->prepare("DELETE FROM task_assignments WHERE target_cid = ?")->execute([$cid]);

        // 4. Delete dpac_followups
        $pdo->prepare("
            DELETE FROM dpac_followups WHERE enrollment_id IN (
                SELECT enrollment_id FROM dpac_enrollments WHERE cid = ?
            )
        ")->execute([$cid]);

        // 5. Delete dpac_enrollments
        $pdo->prepare("DELETE FROM dpac_enrollments WHERE cid = ?")->execute([$cid]);

        // 6. Delete target_population
        $stmt = $pdo->prepare("DELETE FROM target_population WHERE cid = ?");
        $stmt->execute([$cid]);
        $deletedRows = $stmt->rowCount();

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'ลบข้อมูลรายบุคคลสำเร็จ',
            'deleted_count' => $deletedRows
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
        ]);
    }
    exit();
} elseif ($action === 'clear_mock_data') {
    try {
        $pdo->beginTransaction();
        
        $mockCids = ['1234567890111', '1234567890112', '1234567890113', '1234567890114'];
        $mockCidsPlaceholders = implode(',', array_fill(0, count($mockCids), '?'));
        
        // 1. Delete vhv_rewards of mock cids
        $stmtRewards = $pdo->prepare("
            DELETE FROM vhv_rewards WHERE screening_id IN (
                SELECT s.screening_id 
                FROM screening_results s
                JOIN task_assignments a ON s.assignment_id = a.assignment_id
                WHERE a.target_cid IN ($mockCidsPlaceholders)
            )
        ");
        $stmtRewards->execute($mockCids);
        
        // 2. Delete screening_results
        $stmtScreen = $pdo->prepare("
            DELETE FROM screening_results WHERE assignment_id IN (
                SELECT assignment_id FROM task_assignments WHERE target_cid IN ($mockCidsPlaceholders)
            )
        ");
        $stmtScreen->execute($mockCids);
        
        // 3. Delete task_assignments
        $stmtAssign = $pdo->prepare("DELETE FROM task_assignments WHERE target_cid IN ($mockCidsPlaceholders)");
        $stmtAssign->execute($mockCids);
        
        // 4. Delete dpac_followups
        $stmtFollowup = $pdo->prepare("
            DELETE FROM dpac_followups WHERE enrollment_id IN (
                SELECT enrollment_id FROM dpac_enrollments WHERE cid IN ($mockCidsPlaceholders)
            )
        ");
        $stmtFollowup->execute($mockCids);
        
        // 5. Delete dpac_enrollments
        $stmtDpac = $pdo->prepare("DELETE FROM dpac_enrollments WHERE cid IN ($mockCidsPlaceholders)");
        $stmtDpac->execute($mockCids);
        
        // 6. Delete target_population (mock records)
        $stmtTarget = $pdo->prepare("DELETE FROM target_population WHERE cid IN ($mockCidsPlaceholders)");
        $stmtTarget->execute($mockCids);
        $deletedTargets = $stmtTarget->rowCount();
        
        // 7. Delete staging_hdc_dm / ht mock records
        $stmtStgDm = $pdo->prepare("DELETE FROM staging_hdc_dm WHERE cid IN ($mockCidsPlaceholders)");
        $stmtStgDm->execute($mockCids);
        $stmtStgHt = $pdo->prepare("DELETE FROM staging_hdc_ht WHERE cid IN ($mockCidsPlaceholders)");
        $stmtStgHt->execute($mockCids);
        
        // 8. Delete mock VHV users
        $mockVhvs = ['1001', '1002', '1003'];
        $mockVhvsPlaceholders = implode(',', array_fill(0, count($mockVhvs), '?'));
        $stmtVhv = $pdo->prepare("DELETE FROM vhv_users WHERE vhv_id IN ($mockVhvsPlaceholders)");
        $stmtVhv->execute($mockVhvs);
        $deletedVhvs = $stmtVhv->rowCount();
        
        $pdo->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'ล้างข้อมูลจำลองและบัญชีทดสอบเรียบร้อยแล้ว',
            'deleted_targets' => $deletedTargets,
            'deleted_vhvs' => $deletedVhvs
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาดในการล้างข้อมูลจำลอง: ' . $e->getMessage()
        ]);
    }
    exit();
} elseif ($action === 'cleanup_duplicate_rewards') {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->query("
            DELETE r1 FROM vhv_rewards r1
            INNER JOIN vhv_rewards r2 
                ON r1.assignment_id = r2.assignment_id 
                AND r1.reward_id < r2.reward_id
            WHERE r1.assignment_id IS NOT NULL
        ");
        
        $affectedRows = $stmt->rowCount();
        $pdo->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'ทำความสะอาดฐานข้อมูลสำเร็จ! ล้างแต้มคัดกรองที่ซ้ำซ้อนออกทั้งหมด ' . number_format($affectedRows) . ' รายการเรียบร้อยแล้ว',
            'affected' => $affectedRows
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
        ]);
    }
    exit();
} elseif ($action === 'cleanup_orphaned_data') {
    try {
        $pdo->beginTransaction();
        
        // 1. Delete rewards where screening_id is not null but screening_results is deleted
        $deletedScreeningRewards = $pdo->exec("
            DELETE r FROM vhv_rewards r
            LEFT JOIN screening_results s ON r.screening_id = s.screening_id
            WHERE r.screening_id IS NOT NULL AND s.screening_id IS NULL
        ");
        
        // 2. Delete rewards where followup_id is not null but dpac_followups is deleted
        $deletedFollowupRewards = $pdo->exec("
            DELETE r FROM vhv_rewards r
            LEFT JOIN dpac_followups f ON r.followup_id = f.followup_id
            WHERE r.followup_id IS NOT NULL AND f.followup_id IS NULL
        ");
        
        // 3. Delete rewards where assignment_id is not null but task_assignments is deleted
        $deletedAssignRewards = $pdo->exec("
            DELETE r FROM vhv_rewards r
            LEFT JOIN task_assignments a ON r.assignment_id = a.assignment_id
            WHERE r.assignment_id IS NOT NULL AND a.assignment_id IS NULL
        ");
        
        // 4. Delete duplicate survey rewards for each VHV
        $stmtSurvey = $pdo->query("
            SELECT vhv_id, GROUP_CONCAT(reward_id ORDER BY reward_id ASC) as ids
            FROM vhv_rewards 
            WHERE screening_id IS NULL AND followup_id IS NULL AND assignment_id IS NULL AND points_earned = 5.00
            GROUP BY vhv_id
            HAVING COUNT(*) > 1
        ");
        $surveyDups = $stmtSurvey->fetchAll(PDO::FETCH_ASSOC);
        $deletedDupSurveys = 0;
        foreach ($surveyDups as $dup) {
            $ids = explode(',', $dup['ids']);
            array_shift($ids); // Keep the first reward_id, remove it from list to delete
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $delStmt = $pdo->prepare("DELETE FROM vhv_rewards WHERE reward_id IN ($placeholders)");
                $delStmt->execute($ids);
                $deletedDupSurveys += $delStmt->rowCount();
            }
        }
        
        // 5. Delete invalid 1.00 points with all IDs NULL
        $deletedInvalid1Points = $pdo->exec("
            DELETE FROM vhv_rewards 
            WHERE points_earned = 1.00 
              AND screening_id IS NULL 
              AND followup_id IS NULL 
              AND assignment_id IS NULL
        ");
        
        $totalCleaned = $deletedScreeningRewards + $deletedFollowupRewards + $deletedAssignRewards + $deletedDupSurveys + $deletedInvalid1Points;
        $pdo->commit();
        
        $details = "ทำความสะอาดข้อมูลขยะสำเร็จทั้งหมด " . number_format($totalCleaned) . " รายการ:\n"
                 . "- ลบแต้มคัดกรองที่ไม่มีใบผลการตรวจ: " . number_format($deletedScreeningRewards) . " รายการ\n"
                 . "- ลบแต้มติดตาม DPAC ที่ไม่มีใบงาน: " . number_format($deletedFollowupRewards) . " รายการ\n"
                 . "- ลบแต้มคัดกรองที่ไม่มีงานมอบหมาย: " . number_format($deletedAssignRewards) . " รายการ\n"
                 . "- ลบแต้มทำแบบประเมินซ้ำซ้อน: " . number_format($deletedDupSurveys) . " รายการ\n"
                 . "- ลบแต้มลอยที่ไม่มีแหล่งอ้างอิง: " . number_format($deletedInvalid1Points) . " รายการ";
                 
        echo json_encode([
            'status' => 'success',
            'message' => $details,
            'affected' => $totalCleaned
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
        ]);
    }
    exit();
} elseif ($action === 'resolve_all_duplicates') {
    try {
        $pdo->beginTransaction();
        
        // Find pairs of duplicates between mock and real CID
        $stmt = $pdo->query("
            SELECT 
                t1.cid AS mock_cid, 
                t2.cid AS real_cid, 
                t1.hoscode, 
                t1.pid
            FROM target_population t1
            JOIN target_population t2 
              ON t1.hoscode = t2.hoscode 
             AND t1.pid = t2.pid
            WHERE (
                t1.cid LIKE '%*%' 
                OR t1.cid LIKE '0%' 
                OR t1.cid = CONCAT(LPAD(t1.hoscode, 5, '0'), LPAD(t1.pid, 8, '0'))
            )
            AND t2.cid NOT LIKE '%*%'
            AND t2.cid NOT LIKE '0%'
            AND t2.cid <> CONCAT(LPAD(t2.hoscode, 5, '0'), LPAD(t2.pid, 8, '0'))
        ");
        $pairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $mergedCount = 0;

        if (!empty($pairs)) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            
            $stmtUpdateAssign = $pdo->prepare("UPDATE task_assignments SET target_cid = ? WHERE target_cid = ?");
            $stmtUpdateDpac = $pdo->prepare("UPDATE dpac_enrollments SET cid = ? WHERE cid = ?");
            $stmtMergeMock = $pdo->prepare("
                UPDATE target_population t_real
                JOIN target_population t_mock ON t_mock.cid = ?
                SET 
                    t_real.need_screen_dm = CASE WHEN t_mock.need_screen_dm = 1 THEN 1 ELSE t_real.need_screen_dm END,
                    t_real.need_screen_ht = CASE WHEN t_mock.need_screen_ht = 1 THEN 1 ELSE t_real.need_screen_ht END,
                    t_real.health_status_origin = CASE WHEN t_real.health_status_origin = 'NORMAL' OR t_real.health_status_origin = '' OR t_real.health_status_origin IS NULL THEN t_mock.health_status_origin ELSE t_real.health_status_origin END
                WHERE t_real.cid = ?
            ");
            $stmtDeleteMock = $pdo->prepare("DELETE FROM target_population WHERE cid = ?");

            foreach ($pairs as $match) {
                $stmtMergeMock->execute([$match['mock_cid'], $match['real_cid']]);
                $stmtUpdateAssign->execute([$match['real_cid'], $match['mock_cid']]);
                $stmtUpdateDpac->execute([$match['real_cid'], $match['mock_cid']]);
                $stmtDeleteMock->execute([$match['mock_cid']]);
                $mergedCount++;
            }
            
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        }

        $pdo->commit();
        echo json_encode([
            'status' => 'success',
            'message' => "กู้คืนและควบรวมข้อมูลสำเร็จ!\nทำการย้ายประวัติการตรวจและควบรวมกลุ่มเป้าหมายที่ซ้ำซ้อนเรียบร้อยแล้วทั้งหมด " . number_format($mergedCount) . " รายการ",
            'affected' => $mergedCount
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
        ]);
    }
    exit();
} elseif ($action === 'cleanup_sandbox_data') {
    try {
        $pdo->beginTransaction();
        
        // 1. Delete sandboxed records (is_sandbox = 1)
        $deletedRewards = $pdo->exec("DELETE FROM vhv_rewards WHERE is_sandbox = 1");
        $deletedScreenings = $pdo->exec("DELETE FROM screening_results WHERE is_sandbox = 1");
        $deletedTasks = $pdo->exec("DELETE FROM task_assignments WHERE is_sandbox = 1");
        $deletedDpacFollowups = $pdo->exec("DELETE FROM dpac_followups WHERE is_sandbox = 1");

        // 2. Restore production task assignments touched in sandbox
        $restoredTasksStmt = $pdo->query("
            UPDATE task_assignments 
            SET assignment_status = 'pending', 
                is_sandbox_completed = 0 
            WHERE is_sandbox_completed = 1
        ");
        $restoredTasks = $restoredTasksStmt->rowCount();

        // 3. Restore production DPAC followups touched in sandbox
        $restoredDpacStmt = $pdo->query("
            UPDATE dpac_followups 
            SET status = 'pending', 
                completed_at = NULL, 
                weight = NULL, 
                height = NULL, 
                waist = NULL, 
                fbs = NULL, 
                bp_sys = NULL, 
                bp_dia = NULL, 
                health_risk_level = NULL, 
                advice_given = NULL, 
                skip_count = 0, 
                skipped_reason = NULL, 
                is_sandbox_completed = 0 
            WHERE is_sandbox_completed = 1
        ");
        $restoredDpac = $restoredDpacStmt->rowCount();

        // 4. Delete mismatched village assignments (where VHV village != resident village) ONLY if they are pending (not completed/skipped)
        $deletedMismatchedTasks = $pdo->exec("
            DELETE a FROM task_assignments a
            JOIN target_population p ON a.target_cid = p.cid
            JOIN vhv_users v ON a.vhv_id = v.vhv_id
            WHERE p.vhid_code != v.vhid_code
              AND p.moo != 0
              AND p.house_no NOT LIKE '%นอกเขต%'
              AND a.assignment_status = 'pending'
        ");

        $resetMismatchedDpac = $pdo->exec("
            UPDATE dpac_enrollments e
            JOIN target_population p ON e.cid = p.cid
            JOIN vhv_users v ON e.assigned_vhv_id = v.vhv_id
            SET e.assigned_vhv_id = NULL
            WHERE p.vhid_code != v.vhid_code
              AND p.moo != 0
              AND p.house_no NOT LIKE '%นอกเขต%'
              AND EXISTS (
                  SELECT 1 FROM dpac_followups f 
                  WHERE f.enrollment_id = e.enrollment_id 
                    AND f.vhv_id = e.assigned_vhv_id 
                    AND f.status = 'pending'
              )
        ");

        $deletedMismatchedDpacFollowups = $pdo->exec("
            DELETE f FROM dpac_followups f
            JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
            JOIN target_population p ON e.cid = p.cid
            JOIN vhv_users v ON f.vhv_id = v.vhv_id
            WHERE p.vhid_code != v.vhid_code
              AND p.moo != 0
              AND p.house_no NOT LIKE '%นอกเขต%'
              AND f.status = 'pending'
        ");

        $pdo->commit();
        
        $totalDeleted = $deletedRewards + $deletedScreenings + $deletedTasks + $deletedDpacFollowups + $deletedMismatchedTasks + $deletedMismatchedDpacFollowups;
        $totalRestored = $restoredTasks + $restoredDpac;
        
        $details = "ล้างงานค้างและข้อมูลจำลองจากโหมดทดสอบสำเร็จ:\n"
                 . "- ลบข้อมูลทดสอบ (is_sandbox = 1) ทั้งหมด: " . number_format($totalDeleted - $deletedMismatchedTasks - $deletedMismatchedDpacFollowups) . " รายการ\n"
                 . "  (แต้ม: $deletedRewards, ผลตรวจ: $deletedScreenings, งานมอบหมาย: $deletedTasks, ติดตาม DPAC: $deletedDpacFollowups)\n"
                 . "- คืนค่าใบงานและติดตามของจริงให้คัดกรองต่อได้: " . number_format($totalRestored) . " รายการ\n"
                 . "  (งานมอบหมายจริง: $restoredTasks, ติดตาม DPAC จริง: $restoredDpac)\n"
                 . "- ตรวจพบและล้างงานมอบหมายข้ามหมู่บ้านที่ผิดพลาด: " . number_format($deletedMismatchedTasks + $deletedMismatchedDpacFollowups) . " รายการ\n"
                 . "  (NCD ข้ามหมู่บ้าน: $deletedMismatchedTasks, DPAC ข้ามหมู่บ้าน: $deletedMismatchedDpacFollowups, ปรับคืนสิทธิ์ DPAC: $resetMismatchedDpac)";
                 
        echo json_encode([
            'status' => 'success',
            'message' => $details,
            'affected' => $totalDeleted + $totalRestored
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
        ]);
    }
    exit();
} else {
    echo json_encode(['status' => 'error', 'message' => 'คำสั่งไม่ถูกต้อง']);
    exit();
}
