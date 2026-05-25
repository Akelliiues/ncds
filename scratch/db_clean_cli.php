<?php
require_once __DIR__ . '/../config/db.php';

try {
    $pdo->beginTransaction();

    // Step 1: Standardize hoscode to 5 digits in all tables
    $tables_to_pad = [
        'target_population' => 'hoscode',
        'staging_hdc_dm' => 'hoscode',
        'staging_hdc_ht' => 'hoscode',
        'jhcis_homes' => 'hoscode',
        'vhv_users' => 'hoscode',
        'admin_users' => 'hoscode'
    ];

    $padded_counts = [];
    foreach ($tables_to_pad as $table => $col) {
        $checkCol = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        if ($checkCol->rowCount() > 0) {
            $stmt = $pdo->prepare("UPDATE `$table` SET `$col` = LPAD(TRIM(`$col`), 5, '0') WHERE `$col` IS NOT NULL AND `$col` != '' AND LENGTH(TRIM(`$col`)) < 5");
            $stmt->execute();
            $padded_counts[$table] = $stmt->rowCount();
        }
    }
    echo "Padded records: " . json_encode($padded_counts, JSON_UNESCAPED_UNICODE) . "\n";

    // Step 2: Find duplicates in target_population
    $stmtFind = $pdo->query("
        SELECT t1.cid AS masked_cid, t1.first_name AS masked_fname, t1.last_name AS masked_lname,
               t2.cid AS real_cid, t2.first_name AS real_fname, t2.last_name AS real_lname,
               t1.hoscode, t1.pid
        FROM target_population t1
        JOIN target_population t2 ON LPAD(t1.hoscode, 5, '0') = LPAD(t2.hoscode, 5, '0') AND t1.pid = t2.pid
        WHERE t1.cid LIKE '%*%' AND t2.cid NOT LIKE '%*%'
    ");
    $duplicates = $stmtFind->fetchAll();
    echo "Duplicates found: " . count($duplicates) . "\n";

    $merged_tasks = 0;
    $merged_dpac = 0;
    $deleted_masked_targets = 0;

    // Prepared statements for merging
    $stmtGetAssign = $pdo->prepare("SELECT * FROM task_assignments WHERE target_cid = ?");
    $stmtDeleteAssign = $pdo->prepare("DELETE FROM task_assignments WHERE assignment_id = ?");
    $stmtUpdateAssignCid = $pdo->prepare("UPDATE task_assignments SET target_cid = ? WHERE assignment_id = ?");
    
    $stmtGetDpac = $pdo->prepare("SELECT * FROM dpac_enrollments WHERE cid = ?");
    $stmtDeleteDpac = $pdo->prepare("DELETE FROM dpac_enrollments WHERE enrollment_id = ?");
    $stmtUpdateDpacCid = $pdo->prepare("UPDATE dpac_enrollments SET cid = ? WHERE enrollment_id = ?");

    $stmtDeleteTarget = $pdo->prepare("DELETE FROM target_population WHERE cid = ?");

    // Temporarily disable foreign keys to allow merging and cleanup
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    foreach ($duplicates as $dup) {
        $masked_cid = $dup['masked_cid'];
        $real_cid = $dup['real_cid'];

        // 1. Merge task assignments
        $stmtGetAssign->execute([$masked_cid]);
        $masked_assigns = $stmtGetAssign->fetchAll();

        $stmtGetAssign->execute([$real_cid]);
        $real_assigns = $stmtGetAssign->fetchAll();

        $real_by_year = [];
        foreach ($real_assigns as $ra) {
            $real_by_year[$ra['budget_year']] = $ra;
        }

        foreach ($masked_assigns as $ma) {
            $year = $ma['budget_year'];
            if (isset($real_by_year[$year])) {
                $ra = $real_by_year[$year];
                if ($ma['assignment_status'] === 'completed' && $ra['assignment_status'] !== 'completed') {
                    // Move screening results to the real assignment
                    $moveScreening = $pdo->prepare("UPDATE screening_results SET assignment_id = ? WHERE assignment_id = ?");
                    $moveScreening->execute([$ma['assignment_id'], $ra['assignment_id']]);
                    
                    $stmtDeleteAssign->execute([$ra['assignment_id']]);
                    $stmtUpdateAssignCid->execute([$real_cid, $ma['assignment_id']]);
                } else {
                    // Delete the masked assignment
                    $checkScreening = $pdo->prepare("SELECT COUNT(*) FROM screening_results WHERE assignment_id = ?");
                    $checkScreening->execute([$ma['assignment_id']]);
                    if ($checkScreening->fetchColumn() > 0) {
                        $moveScreening = $pdo->prepare("UPDATE screening_results SET assignment_id = ? WHERE assignment_id = ?");
                        $moveScreening->execute([$ra['assignment_id'], $ma['assignment_id']]);
                    }
                    $stmtDeleteAssign->execute([$ma['assignment_id']]);
                }
            } else {
                $stmtUpdateAssignCid->execute([$real_cid, $ma['assignment_id']]);
            }
            $merged_tasks++;
        }

        // 2. Merge DPAC enrollments
        $stmtGetDpac->execute([$masked_cid]);
        $masked_dpac_list = $stmtGetDpac->fetchAll();

        $stmtGetDpac->execute([$real_cid]);
        $real_dpac_list = $stmtGetDpac->fetchAll();

        $real_dpac_by_year = [];
        foreach ($real_dpac_list as $rd) {
            $real_dpac_by_year[$rd['budget_year']] = $rd;
        }

        foreach ($masked_dpac_list as $md) {
            $year = $md['budget_year'];
            if (isset($real_dpac_by_year[$year])) {
                $stmtDeleteDpac->execute([$md['enrollment_id']]);
            } else {
                $stmtUpdateDpacCid->execute([$real_cid, $md['enrollment_id']]);
            }
            $merged_dpac++;
        }

        // 3. Delete the masked target record
        $stmtDeleteTarget->execute([$masked_cid]);
        $deleted_masked_targets++;
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    $pdo->commit();
    echo "Cleanup completed successfully!\n";
    echo "Merged tasks: $merged_tasks\n";
    echo "Merged DPAC: $merged_dpac\n";
    echo "Deleted masked targets: $deleted_masked_targets\n";

} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
