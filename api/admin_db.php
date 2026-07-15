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
} else {
    echo json_encode(['status' => 'error', 'message' => 'คำสั่งไม่ถูกต้อง']);
    exit();
}
