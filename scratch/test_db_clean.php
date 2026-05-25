<?php
require_once __DIR__ . '/../config/db.php';

try {
    $pdo->beginTransaction();

    // Clean test data first
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("DELETE FROM task_assignments WHERE target_cid IN ('334200013****', '3342000130123')");
    $pdo->exec("DELETE FROM target_population WHERE cid IN ('334200013****', '3342000130123')");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // Insert Record A (masked)
    $stmtInsert = $pdo->prepare("INSERT INTO target_population (cid, first_name, last_name, hoscode, pid, sex, birth) VALUES (?, ?, ?, ?, ?, '1', '1985-05-12')");
    $stmtInsert->execute(['334200013****', 'กษ***', 'แก้***', '3754', '123']); // note: unpadded hoscode

    // Insert Record B (real)
    $stmtInsert->execute(['3342000130123', 'กฤษณะ', 'แก้วดี', '03754', '123']); // note: padded hoscode

    // Insert task assignment for Record A (masked, completed)
    $stmtAssign = $pdo->prepare("INSERT INTO task_assignments (target_cid, vhv_id, budget_year, assignment_status) VALUES (?, '1002', 2026, ?)");
    $stmtAssign->execute(['334200013****', 'completed']);
    $maskedAssignId = $pdo->lastInsertId();

    // Insert mock screening result for Record A's assignment
    $pdo->prepare("INSERT INTO screening_results (assignment_id, sys_bp1, dia_bp1, dtx_value) VALUES (?, 120, 80, 110)")->execute([$maskedAssignId]);

    // Insert task assignment for Record B (real, pending)
    $stmtAssign->execute(['3342000130123', 'pending']);
    $realAssignId = $pdo->lastInsertId();

    $pdo->commit();
    echo "Seed test duplicates successful.\n";

    // Run clean script
    echo "Running clean script...\n";
    include __DIR__ . '/db_clean_cli.php';

    // Verify
    $stmtCheckTarget = $pdo->prepare("SELECT * FROM target_population WHERE cid = ?");
    
    // Record A should be deleted
    $stmtCheckTarget->execute(['334200013****']);
    $recA = $stmtCheckTarget->fetch();
    if ($recA) {
        echo "FAIL: Masked target still exists.\n";
    } else {
        echo "SUCCESS: Masked target deleted.\n";
    }

    // Record B should exist and hoscode should be padded to 03754
    $stmtCheckTarget->execute(['3342000130123']);
    $recB = $stmtCheckTarget->fetch();
    if ($recB && $recB['hoscode'] === '03754') {
        echo "SUCCESS: Real target exists with padded hoscode.\n";
    } else {
        echo "FAIL: Real target missing or hoscode not padded.\n";
    }

    // Check task assignments
    $stmtCheckAssign = $pdo->prepare("SELECT * FROM task_assignments WHERE target_cid = ?");
    $stmtCheckAssign->execute(['3342000130123']);
    $assigns = $stmtCheckAssign->fetchAll();
    
    if (count($assigns) === 1) {
        $a = $assigns[0];
        if ($a['assignment_status'] === 'completed') {
            echo "SUCCESS: Task assignment merged and status is completed.\n";
            // Check screening results
            $stmtCheckScreening = $pdo->prepare("SELECT * FROM screening_results WHERE assignment_id = ?");
            $stmtCheckScreening->execute([$a['assignment_id']]);
            $screening = $stmtCheckScreening->fetch();
            if ($screening && $screening['sys_bp1'] == 120) {
                echo "SUCCESS: Screening results moved successfully.\n";
            } else {
                echo "FAIL: Screening results not found or incorrect.\n";
            }
        } else {
            echo "FAIL: Task assignment status is not completed.\n";
        }
    } else {
        echo "FAIL: Expected 1 merged task assignment, found " . count($assigns) . ".\n";
    }

    // Clean up test data
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("DELETE FROM task_assignments WHERE target_cid IN ('334200013****', '3342000130123')");
    $pdo->exec("DELETE FROM target_population WHERE cid IN ('334200013****', '3342000130123')");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Test database cleaned up.\n";

} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
