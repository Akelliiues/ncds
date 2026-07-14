<?php
// admin/target_manager.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
// Self-healing normalization: check and normalize hoscodes and PIDs if a new import occurred
try {
    $checkUnnormalized = $pdo->query("
        SELECT 1 FROM target_population 
        WHERE LENGTH(hoscode) < 5 
           OR pid LIKE '0%' 
           OR (cid LIKE '0%' AND (pid IS NULL OR pid = '' OR pid = '0'))
        LIMIT 1
    ");
    if ($checkUnnormalized->fetch()) {
        $pdo->beginTransaction();
        $pdo->exec("UPDATE target_population SET hoscode = LPAD(hoscode, 5, '0') WHERE LENGTH(hoscode) < 5");
        $pdo->exec("UPDATE staging_hdc_dm SET hoscode = LPAD(hoscode, 5, '0') WHERE LENGTH(hoscode) < 5");
        $pdo->exec("UPDATE staging_hdc_ht SET hoscode = LPAD(hoscode, 5, '0') WHERE LENGTH(hoscode) < 5");
        
        $pdo->exec("
            UPDATE target_population 
            SET pid = TRIM(LEADING '0' FROM SUBSTRING(cid, 6)) 
            WHERE cid LIKE '0%' 
              AND (pid IS NULL OR pid = '' OR pid = '0')
              AND LENGTH(cid) >= 10
        ");
        
        $pdo->exec("UPDATE target_population SET pid = TRIM(LEADING '0' FROM pid) WHERE pid LIKE '0%'");
        $pdo->exec("UPDATE staging_hdc_dm SET pid = TRIM(LEADING '0' FROM pid) WHERE pid LIKE '0%'");
        $pdo->exec("UPDATE staging_hdc_ht SET pid = TRIM(LEADING '0' FROM pid) WHERE pid LIKE '0%'");
        $pdo->commit();
    }
} catch (\Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// Self-healing merge: merge any newly imported masked target duplicates with unmasked JHCIS records
try {
    $dupesQuery = $pdo->query("
        SELECT 
            t1.cid AS masked_cid, t1.pid AS masked_pid, t1.hoscode AS masked_hos,
            t1.need_screen_dm AS masked_dm, t1.need_screen_ht AS masked_ht, t1.health_status_origin AS masked_status,
            t2.cid AS real_cid, t2.pid AS real_pid, t2.first_name AS real_first, t2.last_name AS real_last
        FROM target_population t1
        JOIN target_population t2 
          ON t1.hoscode = t2.hoscode 
         AND t1.pid = t2.pid
        WHERE (
            t1.cid LIKE '%*%' 
            OR t1.first_name LIKE '%*%' 
            OR t1.cid LIKE '0%' 
            OR t1.cid = CONCAT(t1.hoscode, LPAD(t1.pid, 8, '0'))
            OR t1.cid = CONCAT(t1.hoscode, t1.pid)
          )
          AND (
            t2.cid NOT LIKE '%*%' 
            AND t2.first_name NOT LIKE '%*%' 
            AND t2.cid NOT LIKE '0%' 
            AND t2.cid <> CONCAT(t2.hoscode, LPAD(t2.pid, 8, '0'))
            AND t2.cid <> CONCAT(t2.hoscode, t2.pid)
          )
          AND t1.cid <> t2.cid
          AND t1.pid IS NOT NULL AND t1.pid != ''
    ");
    $dupes = $dupesQuery->fetchAll();
    
    if (!empty($dupes)) {
        $pdo->beginTransaction();
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        
        $stmtUpdateReal = $pdo->prepare("
            UPDATE target_population 
            SET 
                need_screen_dm = CASE WHEN ? = 1 THEN 1 ELSE need_screen_dm END,
                need_screen_ht = CASE WHEN ? = 1 THEN 1 ELSE need_screen_ht END,
                health_status_origin = CASE WHEN health_status_origin = 'NORMAL' OR health_status_origin = '' OR health_status_origin IS NULL THEN ? ELSE health_status_origin END,
                updated_at = NOW()
            WHERE cid = ?
        ");
        
        $stmtGetAssign = $pdo->prepare("SELECT * FROM task_assignments WHERE target_cid = ?");
        $stmtDeleteAssign = $pdo->prepare("DELETE FROM task_assignments WHERE assignment_id = ?");
        $stmtUpdateAssignCid = $pdo->prepare("UPDATE task_assignments SET target_cid = ? WHERE assignment_id = ?");
        
        $stmtGetDpac = $pdo->prepare("SELECT * FROM dpac_enrollments WHERE cid = ?");
        $stmtDeleteDpac = $pdo->prepare("DELETE FROM dpac_enrollments WHERE enrollment_id = ?");
        $stmtUpdateDpacCid = $pdo->prepare("UPDATE dpac_enrollments SET cid = ? WHERE enrollment_id = ?");
        
        $stmtUpdateStagingDM = $pdo->prepare("UPDATE staging_hdc_dm SET pid = ?, cid = ?, name = ?, lname = ? WHERE hoscode = ? AND pid = ?");
        $stmtUpdateStagingHT = $pdo->prepare("UPDATE staging_hdc_ht SET pid = ?, cid = ?, name = ?, lname = ? WHERE hoscode = ? AND pid = ?");
        $stmtUpdateStagingDMCid = $pdo->prepare("UPDATE staging_hdc_dm SET pid = ?, cid = ?, name = ?, lname = ? WHERE cid = ?");
        $stmtUpdateStagingHTCid = $pdo->prepare("UPDATE staging_hdc_ht SET pid = ?, cid = ?, name = ?, lname = ? WHERE cid = ?");
        
        $stmtDeleteTarget = $pdo->prepare("DELETE FROM target_population WHERE cid = ?");
        
        foreach ($dupes as $dup) {
            $mCid = $dup['masked_cid'];
            $rCid = $dup['real_cid'];
            
            $stmtUpdateReal->execute([$dup['masked_dm'], $dup['masked_ht'], $dup['masked_status'], $rCid]);
            
            $stmtGetAssign->execute([$mCid]);
            $mAssigns = $stmtGetAssign->fetchAll();
            
            $stmtGetAssign->execute([$rCid]);
            $rAssigns = $stmtGetAssign->fetchAll();
            
            $rByYear = [];
            foreach ($rAssigns as $ra) {
                $rByYear[$ra['budget_year']] = $ra;
            }
            
            foreach ($mAssigns as $ma) {
                $year = $ma['budget_year'];
                if (isset($rByYear[$year])) {
                    $ra = $rByYear[$year];
                    $checkScreen = $pdo->prepare("SELECT COUNT(*) FROM screening_results WHERE assignment_id = ?");
                    $checkScreen->execute([$ma['assignment_id']]);
                    $hasScreening = $checkScreen->fetchColumn() > 0;
                    
                    if ($hasScreening) {
                        $moveScreen = $pdo->prepare("UPDATE screening_results SET assignment_id = ? WHERE assignment_id = ?");
                        $moveScreen->execute([$ra['assignment_id'], $ma['assignment_id']]);
                    }
                    $stmtDeleteAssign->execute([$ma['assignment_id']]);
                } else {
                    $stmtUpdateAssignCid->execute([$rCid, $ma['assignment_id']]);
                }
            }
            
            $stmtGetDpac->execute([$mCid]);
            $mDpac = $stmtGetDpac->fetchAll();
            
            $stmtGetDpac->execute([$rCid]);
            $rDpac = $stmtGetDpac->fetchAll();
            
            $rDpacByYear = [];
            foreach ($rDpac as $rd) {
                $rDpacByYear[$rd['budget_year']] = $rd;
            }
            
            foreach ($mDpac as $md) {
                $year = $md['budget_year'];
                if (isset($rDpacByYear[$year])) {
                    $moveFollowups = $pdo->prepare("UPDATE dpac_followups SET enrollment_id = ? WHERE enrollment_id = ?");
                    $moveFollowups->execute([$rDpacByYear[$year]['enrollment_id'], $md['enrollment_id']]);
                    $stmtDeleteDpac->execute([$md['enrollment_id']]);
                } else {
                    $stmtUpdateDpacCid->execute([$rCid, $md['enrollment_id']]);
                }
            }
            
            // Sync staging tables
            $stmtUpdateStagingDM->execute([$dup['real_pid'], $dup['real_cid'], $dup['real_first'], $dup['real_last'], $dup['masked_hos'], $dup['masked_pid']]);
            $stmtUpdateStagingHT->execute([$dup['real_pid'], $dup['real_cid'], $dup['real_first'], $dup['real_last'], $dup['masked_hos'], $dup['masked_pid']]);
            $stmtUpdateStagingDMCid->execute([$dup['real_pid'], $dup['real_cid'], $dup['real_first'], $dup['real_last'], $mCid]);
            $stmtUpdateStagingHTCid->execute([$dup['real_pid'], $dup['real_cid'], $dup['real_first'], $dup['real_last'], $mCid]);
            
            $stmtDeleteTarget->execute([$mCid]);
        }
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        $pdo->commit();
    }
} catch (\Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// Self-healing fuzzy merge based on birth, sex, and fuzzy name matching (ignoring asterisks)
try {
    $hasMasked = $pdo->query("
        SELECT 1 FROM target_population 
        WHERE (cid LIKE '%*%' OR first_name LIKE '%*%' OR cid LIKE '0%') 
        LIMIT 1
    ")->fetchColumn();

    if ($hasMasked) {
        $fuzzyDupesQuery = $pdo->query("
            SELECT 
                t1.cid AS masked_cid, t1.pid AS masked_pid, t1.hoscode AS masked_hos,
                t1.need_screen_dm AS masked_dm, t1.need_screen_ht AS masked_ht, t1.health_status_origin AS masked_status,
                t2.cid AS real_cid, t2.pid AS real_pid, t2.first_name AS real_first, t2.last_name AS real_last
            FROM target_population t1
            JOIN target_population t2 
              ON t1.hoscode = t2.hoscode 
             AND t1.birth = t2.birth 
             AND t1.sex = t2.sex
            WHERE (
                t1.cid LIKE '%*%' 
                OR t1.first_name LIKE '%*%' 
                OR t1.cid LIKE '0%' 
                OR t1.cid = CONCAT(t1.hoscode, LPAD(t1.pid, 8, '0'))
                OR t1.cid = CONCAT(t1.hoscode, t1.pid)
              )
              AND (
                t2.cid NOT LIKE '%*%' 
                AND t2.first_name NOT LIKE '%*%' 
                AND t2.cid NOT LIKE '0%' 
                AND t2.cid <> CONCAT(t2.hoscode, LPAD(t2.pid, 8, '0'))
                AND t2.cid <> CONCAT(t2.hoscode, t2.pid)
              )
              AND t1.cid <> t2.cid
              AND REPLACE(t1.first_name, '*', '') = SUBSTRING(t2.first_name, 1, LENGTH(REPLACE(t1.first_name, '*', '')))
              AND REPLACE(t1.last_name, '*', '') = SUBSTRING(t2.last_name, 1, LENGTH(REPLACE(t1.last_name, '*', '')))
        ");
        $fuzzyDupes = $fuzzyDupesQuery->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($fuzzyDupes)) {
            $pdo->beginTransaction();
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            
            $stmtUpdateReal = $pdo->prepare("
                UPDATE target_population 
                SET 
                    need_screen_dm = CASE WHEN ? = 1 THEN 1 ELSE need_screen_dm END,
                    need_screen_ht = CASE WHEN ? = 1 THEN 1 ELSE need_screen_ht END,
                    health_status_origin = CASE WHEN health_status_origin = 'NORMAL' OR health_status_origin = '' OR health_status_origin IS NULL THEN ? ELSE health_status_origin END,
                    updated_at = NOW()
                WHERE cid = ?
            ");
            
            $stmtGetAssign = $pdo->prepare("SELECT * FROM task_assignments WHERE target_cid = ?");
            $stmtDeleteAssign = $pdo->prepare("DELETE FROM task_assignments WHERE assignment_id = ?");
            $stmtUpdateAssignCid = $pdo->prepare("UPDATE task_assignments SET target_cid = ? WHERE assignment_id = ?");
            
            $stmtGetDpac = $pdo->prepare("SELECT * FROM dpac_enrollments WHERE cid = ?");
            $stmtDeleteDpac = $pdo->prepare("DELETE FROM dpac_enrollments WHERE enrollment_id = ?");
            $stmtUpdateDpacCid = $pdo->prepare("UPDATE dpac_enrollments SET cid = ? WHERE enrollment_id = ?");
            
            $stmtUpdateStagingDM = $pdo->prepare("UPDATE staging_hdc_dm SET pid = ?, cid = ?, name = ?, lname = ? WHERE hoscode = ? AND pid = ?");
            $stmtUpdateStagingHT = $pdo->prepare("UPDATE staging_hdc_ht SET pid = ?, cid = ?, name = ?, lname = ? WHERE hoscode = ? AND pid = ?");
            $stmtUpdateStagingDMCid = $pdo->prepare("UPDATE staging_hdc_dm SET pid = ?, cid = ?, name = ?, lname = ? WHERE cid = ?");
            $stmtUpdateStagingHTCid = $pdo->prepare("UPDATE staging_hdc_ht SET pid = ?, cid = ?, name = ?, lname = ? WHERE cid = ?");
            
            $stmtDeleteTarget = $pdo->prepare("DELETE FROM target_population WHERE cid = ?");
            
            foreach ($fuzzyDupes as $dup) {
                $mCid = $dup['masked_cid'];
                $rCid = $dup['real_cid'];
                
                $stmtUpdateReal->execute([$dup['masked_dm'], $dup['masked_ht'], $dup['masked_status'], $rCid]);
                
                $stmtGetAssign->execute([$mCid]);
                $mAssigns = $stmtGetAssign->fetchAll();
                
                $stmtGetAssign->execute([$rCid]);
                $rAssigns = $stmtGetAssign->fetchAll();
                
                $rByYear = [];
                foreach ($rAssigns as $ra) {
                    $rByYear[$ra['budget_year']] = $ra;
                }
                
                foreach ($mAssigns as $ma) {
                    $year = $ma['budget_year'];
                    if (isset($rByYear[$year])) {
                        $ra = $rByYear[$year];
                        $checkScreen = $pdo->prepare("SELECT COUNT(*) FROM screening_results WHERE assignment_id = ?");
                        $checkScreen->execute([$ma['assignment_id']]);
                        $hasScreening = $checkScreen->fetchColumn() > 0;
                        
                        if ($hasScreening) {
                            $moveScreen = $pdo->prepare("UPDATE screening_results SET assignment_id = ? WHERE assignment_id = ?");
                            $moveScreen->execute([$ra['assignment_id'], $ma['assignment_id']]);
                        }
                        $stmtDeleteAssign->execute([$ma['assignment_id']]);
                    } else {
                        $stmtUpdateAssignCid->execute([$rCid, $ma['assignment_id']]);
                    }
                }
                
                $stmtGetDpac->execute([$mCid]);
                $mDpac = $stmtGetDpac->fetchAll();
                
                $stmtGetDpac->execute([$rCid]);
                $rDpac = $stmtGetDpac->fetchAll();
                
                $rDpacByYear = [];
                foreach ($rDpac as $rd) {
                    $rDpacByYear[$rd['budget_year']] = $rd;
                }
                
                foreach ($mDpac as $md) {
                    $year = $md['budget_year'];
                    if (isset($rDpacByYear[$year])) {
                        $moveFollowups = $pdo->prepare("UPDATE dpac_followups SET enrollment_id = ? WHERE enrollment_id = ?");
                        $moveFollowups->execute([$rDpacByYear[$year]['enrollment_id'], $md['enrollment_id']]);
                        $stmtDeleteDpac->execute([$md['enrollment_id']]);
                    } else {
                        $stmtUpdateDpacCid->execute([$rCid, $md['enrollment_id']]);
                    }
                }
                
                // Sync staging tables
                $stmtUpdateStagingDM->execute([$dup['real_pid'], $dup['real_cid'], $dup['real_first'], $dup['real_last'], $dup['masked_hos'], $dup['masked_pid']]);
                $stmtUpdateStagingHT->execute([$dup['real_pid'], $dup['real_cid'], $dup['real_first'], $dup['real_last'], $dup['masked_hos'], $dup['masked_pid']]);
                $stmtUpdateStagingDMCid->execute([$dup['real_pid'], $dup['real_cid'], $dup['real_first'], $dup['real_last'], $mCid]);
                $stmtUpdateStagingHTCid->execute([$dup['real_pid'], $dup['real_cid'], $dup['real_first'], $dup['real_last'], $mCid]);
                
                $stmtDeleteTarget->execute([$mCid]);
            }
            
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            $pdo->commit();
        }
    }
} catch (\Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

$jsData = [];
$subsList = [];
try {
    $subsList = $pdo->query("SELECT * FROM sub_districts ORDER BY sub_district_code ASC")->fetchAll();
    foreach ($subsList as $sub) {
        $subCode = $sub['sub_district_code'];
        $subName = $sub['sub_district_name'];

        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT hoscode) FROM villages WHERE sub_district_code = ? AND hoscode IS NOT NULL AND hoscode != ''");
        $stmt->execute([$subCode]);
        $distinctHoscodes = $stmt->fetchColumn();

        $hasSubUnits = ($distinctHoscodes > 1);

        if ($hasSubUnits) {
            $jsData[$subCode] = [
                'name' => $subName,
                'hasSubUnits' => true,
                'subUnits' => []
            ];

            $stmt = $pdo->prepare("SELECT DISTINCT v.hoscode, h.hosname FROM villages v JOIN health_units h ON v.hoscode = h.hoscode WHERE v.sub_district_code = ?");
            $stmt->execute([$subCode]);
            $subUnits = $stmt->fetchAll();

            foreach ($subUnits as $su) {
                $hc = $su['hoscode'];
                $hcName = $su['hosname'];

                $vStmt = $pdo->prepare("SELECT moo, village_name FROM villages WHERE sub_district_code = ? AND hoscode = ? ORDER BY moo ASC");
                $vStmt->execute([$subCode, $hc]);
                $vills = $vStmt->fetchAll();

                $villList = [];
                foreach ($vills as $v) {
                    $villList[] = [
                        'moo' => intval($v['moo']),
                        'name' => $v['village_name']
                    ];
                }

                $jsData[$subCode]['subUnits'][$hc] = [
                    'name' => $hcName,
                    'villages' => $villList
                ];
            }
        } else {
            $stmt = $pdo->prepare("SELECT DISTINCT hoscode FROM villages WHERE sub_district_code = ? LIMIT 1");
            $stmt->execute([$subCode]);
            $hc = $stmt->fetchColumn();

            $vStmt = $pdo->prepare("SELECT moo, village_name FROM villages WHERE sub_district_code = ? ORDER BY moo ASC");
            $vStmt->execute([$subCode]);
            $vills = $vStmt->fetchAll();

            $villList = [];
            foreach ($vills as $v) {
                $villList[] = [
                    'moo' => intval($v['moo']),
                    'name' => $v['village_name']
                ];
            }

            $jsData[$subCode] = [
                'name' => $subName,
                'hasSubUnits' => false,
                'hoscode' => $hc ?: '',
                'villages' => $villList
            ];
        }
    }
} catch (\Exception $e) {
    // Fail silently
}

// Handle API requests
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'get_targets') {
        header('Content-Type: application/json');
        $hoscode = $_GET['hoscode'] ?? '';
        $moo = $_GET['moo'] ?? '';
        $status = $_GET['status'] ?? 'all';
        $search = trim($_GET['search'] ?? '');
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = max(10, min(200, intval($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        if (!$hoscode) {
            echo json_encode(['data' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'totalPages' => 0]);
            exit;
        }

        try {
            $pdo->exec("ALTER TABLE target_population ADD COLUMN prefix VARCHAR(50) NULL AFTER pid");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE target_population ADD COLUMN is_manual TINYINT(1) DEFAULT 0 AFTER need_screen_ht");
        } catch (Exception $e) {
        }

        $hoscodes = get_query_hoscodes($hoscode);
        $inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
        
        $isAllMoo = ($moo === 'all' || $moo === '');

        $vhid_code = '';
        if (!$isAllMoo) {
            try {
                $inPlaceholdersV = implode(',', array_fill(0, count($hoscodes), '?'));
                $stmtV = $pdo->prepare("SELECT vhid_code FROM villages WHERE hoscode IN ($inPlaceholdersV) AND moo = ? LIMIT 1");
                $stmtV->execute(array_merge($hoscodes, [intval($moo)]));
                $vhid_code = $stmtV->fetchColumn();
            } catch (Exception $e) {
                $vhid_code = '';
            }
        }

        if ($isAllMoo) {
            $params = array_merge(
                $hoscodes, // dm ใน ส่วนที่ 1
                $hoscodes, // ht ใน ส่วนที่ 1
                $hoscodes, // target_population ใน ส่วนที่ 1
                $hoscodes, // dm ใน ส่วนที่ 2
                $hoscodes  // ht ใน ส่วนที่ 2
            );
            $mooCond1 = "";
            $mooCond2 = "";
            $mooCond3 = "";
        } else {
            $moo_int = intval($moo);
            if (!empty($vhid_code)) {
                $params = array_merge(
                    $hoscodes, // dm ใน ส่วนที่ 1
                    $hoscodes, // ht ใน ส่วนที่ 1
                    $hoscodes, // target_population ใน ส่วนที่ 1
                    [$moo_int], // moo_int ใน ส่วนที่ 1
                    $hoscodes, // dm ใน ส่วนที่ 2
                    [$vhid_code], // check_vhid ใน ส่วนที่ 2 dm
                    $hoscodes, // ht ใน ส่วนที่ 2
                    [$vhid_code]  // check_vhid ใน ส่วนที่ 2 ht
                );
                $mooCond1 = " AND t.moo = ?";
                $mooCond2 = " AND dm.check_vhid = ?";
                $mooCond3 = " AND ht.check_vhid = ?";
            } else {
                $moo_str = sprintf('%02d', $moo_int);
                $params = array_merge(
                    $hoscodes, // dm ใน ส่วนที่ 1
                    $hoscodes, // ht ใน ส่วนที่ 1
                    $hoscodes, // target_population ใน ส่วนที่ 1
                    [$moo_int], // moo_int ใน ส่วนที่ 1
                    $hoscodes, // dm ใน ส่วนที่ 2
                    [$moo_str], // moo_str ใน ส่วนที่ 2 dm
                    $hoscodes, // ht ใน ส่วนที่ 2
                    [$moo_str]  // moo_str ใน ส่วนที่ 2 ht
                );
                $mooCond1 = " AND t.moo = ?";
                $mooCond2 = " AND RIGHT(dm.check_vhid, 2) = ?";
                $mooCond3 = " AND RIGHT(ht.check_vhid, 2) = ?";
            }
        }

        $sql = "
        SELECT * FROM (
            -- ส่วนที่ 1: ดึงประชากรทั้งหมดจาก target_population ของหมู่ที่เลือก และ LEFT JOIN ข้อมูลผลแล็บจาก staging (ถ้ามี)
            SELECT 
                t.cid,
                t.pid,
                t.hoscode,
                t.prefix,
                t.first_name,
                t.last_name,
                t.birth,
                t.house_no,
                t.moo,
                t.sub_district_code,
                t.vhid_code,
                TIMESTAMPDIFF(YEAR, t.birth, CURDATE()) as age,
                t.need_screen_dm,
                t.need_screen_ht,
                t.health_status_origin,
                t.is_manual,
                h.bslevel, h.bstest, h.sbp, h.dbp,
                (SELECT 1 FROM dpac_enrollments dp WHERE dp.cid = t.cid AND dp.budget_year = 2026 AND dp.status = 'active' LIMIT 1) as is_dpac
            FROM target_population t
            LEFT JOIN (
                SELECT 
                    cid, pid, hoscode,
                    MAX(bslevel) as bslevel, MAX(bstest) as bstest, MAX(sbp) as sbp, MAX(dbp) as dbp
                FROM (
                    SELECT 
                        dm.cid, dm.pid, dm.hoscode, dm.bslevel, dm.bstest, NULL as sbp, NULL as dbp
                    FROM staging_hdc_dm dm
                    WHERE dm.hoscode IN ($inPlaceholders)
                    
                    UNION ALL
                    
                    SELECT 
                        ht.cid, ht.pid, ht.hoscode, NULL as bslevel, NULL as bstest, ht.sbp, ht.dbp
                    FROM staging_hdc_ht ht
                    WHERE ht.hoscode IN ($inPlaceholders)
                ) sub_staging
                GROUP BY hoscode, pid
            ) h ON t.hoscode = h.hoscode AND t.pid = h.pid
            WHERE t.hoscode IN ($inPlaceholders) $mooCond1
            
            UNION ALL
            
            -- ส่วนที่ 2: ดึงรายชื่อประชากรใน staging ของหมู่ที่เลือก แต่ยังไม่ได้เพิ่ม/ไม่มีชื่อใน target_population
            SELECT 
                h.cid,
                h.pid,
                h.hoscode,
                NULL as prefix,
                h.first_name,
                h.last_name,
                h.birth,
                h.house_no,
                CASE 
                    WHEN LENGTH(h.check_vhid) = 8 THEN CAST(SUBSTRING(h.check_vhid, 7, 2) AS UNSIGNED)
                    ELSE 1
                END as moo,
                CASE 
                    WHEN LENGTH(h.check_vhid) = 8 THEN SUBSTRING(h.check_vhid, 1, 6)
                    ELSE '341801'
                END as sub_district_code,
                h.check_vhid as vhid_code,
                TIMESTAMPDIFF(YEAR, h.birth, CURDATE()) as age,
                0 as need_screen_dm,
                0 as need_screen_ht,
                h.health_status_origin,
                0 as is_manual,
                h.bslevel, h.bstest, h.sbp, h.dbp,
                (SELECT 1 FROM dpac_enrollments dp WHERE dp.cid = h.cid AND dp.budget_year = 2026 AND dp.status = 'active' LIMIT 1) as is_dpac
            FROM (
                SELECT 
                    cid, pid, hoscode, name as first_name, lname as last_name, birth, addr as house_no, check_vhid,
                    MAX(bslevel) as bslevel, MAX(bstest) as bstest, MAX(sbp) as sbp, MAX(dbp) as dbp,
                    MAX(health_status_origin) as health_status_origin
                FROM (
                    SELECT 
                        dm.cid, dm.pid, dm.hoscode, dm.name, dm.lname, dm.birth, dm.addr, dm.check_vhid,
                        dm.bslevel, dm.bstest, NULL as sbp, NULL as dbp,
                        CASE 
                            WHEN dm.risk = '2' THEN 'HIGH_RISK'
                            WHEN dm.risk = '1' THEN 'DM_ONLY'
                            WHEN dm.risk = '3' THEN 'SUSPECT'
                            ELSE 'NORMAL'
                        END as health_status_origin
                    FROM staging_hdc_dm dm
                    WHERE dm.hoscode IN ($inPlaceholders) $mooCond2
                    
                    UNION ALL
                    
                    SELECT 
                        ht.cid, ht.pid, ht.hoscode, ht.name, ht.lname, ht.birth, ht.addr, ht.check_vhid,
                        NULL as bslevel, NULL as bstest, ht.sbp, ht.dbp,
                        CASE 
                            WHEN ht.risk = '2' THEN 'HIGH_RISK'
                            WHEN ht.risk = '1' THEN 'HT_ONLY'
                            WHEN ht.risk = '3' THEN 'SUSPECT'
                            ELSE 'NORMAL'
                        END as health_status_origin
                    FROM staging_hdc_ht ht
                    WHERE ht.hoscode IN ($inPlaceholders) $mooCond3
                ) sub_staging
                GROUP BY hoscode, pid
            ) h
            LEFT JOIN target_population t ON t.hoscode = h.hoscode AND t.pid = h.pid
            WHERE t.cid IS NULL
        ) main_result
        ";

        if ($search === '') {
            $sql .= " WHERE age >= 35";
        } else {
            $sql .= " WHERE 1=1";
        }

        if ($status === 'target') {
            $sql .= " AND (need_screen_dm = 1 OR need_screen_ht = 1 OR is_manual = 1)";
        } elseif ($status === 'non_target') {
            $sql .= " AND (need_screen_dm = 0 AND need_screen_ht = 0 AND is_manual = 0)";
        }

        if ($search !== '') {
            $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR cid LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        // Count total records before pagination
        $countSql = "SELECT COUNT(*) FROM ($sql) count_tbl";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = (int) $countStmt->fetchColumn();
        $totalPages = max(1, ceil($totalRecords / $limit));

        $sql .= " ORDER BY CAST(house_no AS UNSIGNED) ASC, house_no ASC";
        $sql .= " LIMIT $limit OFFSET $offset";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode([
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $totalRecords,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] == 'toggle_target_disease') {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $cid = $data['cid'] ?? '';
        $disease = $data['disease'] ?? ''; // 'DM' or 'HT'
        $status = isset($data['status']) ? intval($data['status']) : 0;

        if (!$cid || !in_array($disease, ['DM', 'HT'])) {
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
            exit;
        }

        try {
            $field = $disease === 'DM' ? 'need_screen_dm' : 'need_screen_ht';
            
            // Check if exists
            $stmt = $pdo->prepare("SELECT cid, pid, hoscode, need_screen_dm, need_screen_ht FROM target_population WHERE cid = ?");
            $stmt->execute([$cid]);
            $exists = $stmt->fetch();

            $req_tambon = $data['tambon'] ?? '';
            $req_moo = $data['moo'] ?? '';
            $req_hoscode = $data['hoscode'] ?? '';

            if ($exists) {
                // Update
                if ($req_tambon && $req_moo && $req_hoscode) {
                    $moo_str = str_pad($req_moo, 2, '0', STR_PAD_LEFT);
                    $vhid_code = $req_tambon . $moo_str;
                    $stmtUpd = $pdo->prepare("UPDATE target_population SET $field = ?, moo = ?, sub_district_code = ?, vhid_code = ?, hoscode = ?, updated_at = NOW() WHERE cid = ?");
                    $stmtUpd->execute([$status, $req_moo, $req_tambon, $vhid_code, $req_hoscode, $cid]);
                } else {
                    $stmtUpd = $pdo->prepare("UPDATE target_population SET $field = ?, updated_at = NOW() WHERE cid = ?");
                    $stmtUpd->execute([$status, $cid]);
                }
                echo json_encode(['status' => 'success']);
            } else {
                // Insert from staging HDC
                $dm = null;
                $ht = null;
                
                $stmtDM = $pdo->prepare("SELECT * FROM staging_hdc_dm WHERE cid = ? LIMIT 1");
                $stmtDM->execute([$cid]);
                $dm = $stmtDM->fetch();
                
                $stmtHT = $pdo->prepare("SELECT * FROM staging_hdc_ht WHERE cid = ? LIMIT 1");
                $stmtHT->execute([$cid]);
                $ht = $stmtHT->fetch();
                
                $r = $dm ?: $ht;
                if (!$r) {
                    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลประชากรนี้ในระบบ HDC Staging']);
                    exit;
                }

                $need_dm = $disease === 'DM' ? $status : 0;
                $need_ht = $disease === 'HT' ? $status : 0;
                
                if ($dm && $ht) $origin = 'BOTH';
                else if ($dm) $origin = 'DM_ONLY';
                else if ($ht) $origin = 'HT_ONLY';
                else $origin = 'NORMAL';

                $insert_hoscode = $req_hoscode ?: $r['hoscode'] ?: '';

                if ($req_tambon && $req_moo) {
                    $tambon = $req_tambon;
                    $moo = intval($req_moo);
                    $moo_str = str_pad($req_moo, 2, '0', STR_PAD_LEFT);
                    $vhid_code = $req_tambon . $moo_str;
                } else {
                    $vhid_code = $r['check_vhid'] ?? '';
                    if (strlen($vhid_code) === 8) {
                        $tambon = substr($vhid_code, 0, 6);
                        $moo = intval(substr($vhid_code, 6, 2));
                    } else {
                        $vhid_code = '34180101';
                        $tambon = '341801';
                        $moo = 1;
                    }
                }
                
                $insert_cid = $r['cid'];
                if (strpos($insert_cid, '*') !== false && !empty($r['hoscode']) && !empty($r['pid'])) {
                    $insert_cid = str_pad($r['hoscode'], 5, '0', STR_PAD_LEFT) . str_pad($r['pid'], 8, '0', STR_PAD_LEFT);
                }

                $insert = $pdo->prepare("INSERT INTO target_population 
                    (cid, pid, first_name, last_name, sex, birth, house_no, moo, sub_district_code, vhid_code, hoscode, health_status_origin, need_screen_dm, need_screen_ht)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insert->execute([
                    $insert_cid, $r['pid'], $r['name'], $r['lname'], $r['sex'], $r['birth'], $r['addr'], $moo, $tambon, $vhid_code, $insert_hoscode, $origin, $need_dm, $need_ht
                ]);

                echo json_encode(['status' => 'success']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] == 'enroll_dpac_single') {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $cid = $data['cid'] ?? '';
        $risk_type = $data['risk_type'] ?? 'DM'; // 'DM' or 'HT'
        $budget_year = 2026;

        if (!$cid) {
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
            exit;
        }

        try {
            // Check if exists in target_population
            $stmtCheckTarget = $pdo->prepare("SELECT cid FROM target_population WHERE cid = ?");
            $stmtCheckTarget->execute([$cid]);
            
            $req_tambon = $data['tambon'] ?? '';
            $req_moo = $data['moo'] ?? '';
            $req_hoscode = $data['hoscode'] ?? '';

            if ($stmtCheckTarget->rowCount() == 0) {
                // Insert from staging HDC
                $dm = null;
                $ht = null;
                $stmtDM = $pdo->prepare("SELECT * FROM staging_hdc_dm WHERE cid = ? LIMIT 1");
                $stmtDM->execute([$cid]);
                $dm = $stmtDM->fetch();
                
                $stmtHT = $pdo->prepare("SELECT * FROM staging_hdc_ht WHERE cid = ? LIMIT 1");
                $stmtHT->execute([$cid]);
                $ht = $stmtHT->fetch();
                
                $r = $dm ?: $ht;
                if ($r) {
                    if ($dm && $ht) $origin = 'BOTH';
                    else if ($dm) $origin = 'DM_ONLY';
                    else if ($ht) $origin = 'HT_ONLY';
                    else $origin = 'NORMAL';
                    
                    if ($req_tambon && $req_moo) {
                        $tambon = $req_tambon;
                        $moo = intval($req_moo);
                        $moo_str = str_pad($req_moo, 2, '0', STR_PAD_LEFT);
                        $vhid_code = $req_tambon . $moo_str;
                    } else {
                        $vhid_code = $r['check_vhid'] ?? '';
                        if (strlen($vhid_code) === 8) {
                            $tambon = substr($vhid_code, 0, 6);
                            $moo = intval(substr($vhid_code, 6, 2));
                        } else {
                            $vhid_code = '34180101';
                            $tambon = '341801';
                            $moo = 1;
                        }
                    }
                    
                    $insert_cid = $r['cid'];
                    if (strpos($insert_cid, '*') !== false && !empty($r['hoscode']) && !empty($r['pid'])) {
                        $insert_cid = str_pad($r['hoscode'], 5, '0', STR_PAD_LEFT) . str_pad($r['pid'], 8, '0', STR_PAD_LEFT);
                    }
                    
                    $need_dm = $risk_type === 'DM' ? 1 : 0;
                    $need_ht = $risk_type === 'HT' ? 1 : 0;

                    $insert_hoscode = $req_hoscode ?: $r['hoscode'] ?: '';

                    $insert = $pdo->prepare("INSERT INTO target_population 
                        (cid, pid, first_name, last_name, sex, birth, house_no, moo, sub_district_code, vhid_code, hoscode, health_status_origin, need_screen_dm, need_screen_ht)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $insert->execute([
                        $insert_cid, $r['pid'], $r['name'], $r['lname'], $r['sex'], $r['birth'], $r['addr'], $moo, $tambon, $vhid_code, $insert_hoscode, $origin, $need_dm, $need_ht
                    ]);
                }
            } else {
                // Make sure correct target flag is set
                $field = $risk_type === 'DM' ? 'need_screen_dm' : 'need_screen_ht';
                
                if ($req_tambon && $req_moo && $req_hoscode) {
                    $moo_str = str_pad($req_moo, 2, '0', STR_PAD_LEFT);
                    $vhid_code = $req_tambon . $moo_str;
                    $updTarget = $pdo->prepare("UPDATE target_population SET $field = 1, moo = ?, sub_district_code = ?, vhid_code = ?, hoscode = ? WHERE cid = ?");
                    $updTarget->execute([$req_moo, $req_tambon, $vhid_code, $req_hoscode, $cid]);
                } else {
                    $updTarget = $pdo->prepare("UPDATE target_population SET $field = 1 WHERE cid = ?");
                    $updTarget->execute([$cid]);
                }
            }

            // Check if already enrolled in DPAC
            $stmtCheck = $pdo->prepare("SELECT enrollment_id FROM dpac_enrollments WHERE cid = ? AND budget_year = ? AND status = 'active'");
            $stmtCheck->execute([$cid, $budget_year]);
            if ($stmtCheck->rowCount() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'บุคคลนี้ลงทะเบียนในโครงการ DPAC ปีงบนี้อยู่แล้ว']);
                exit;
            }

            // Insert
            $stmtInsert = $pdo->prepare("INSERT INTO dpac_enrollments (cid, budget_year, risk_type, status) VALUES (?, ?, ?, 'active')");
            $stmtInsert->execute([$cid, $budget_year, $risk_type]);

            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] == 'add_manual') {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        $cid = $data['cid'] ?? '';
        $old_cid = $data['old_cid'] ?? '';
        $prefix = $data['prefix'] ?? '';
        $fname = $data['fname'] ?? '';
        $lname = $data['lname'] ?? '';
        $birth_formatted = $data['birth_formatted'] ?? date('Y-m-d');
        $house_no = $data['house_no'] ?? '';
        $dm = $data['need_dm'] ? 1 : 0;
        $ht = $data['need_ht'] ? 1 : 0;
        $tambon = $data['tambon'] ?? '';
        $moo = $data['moo'] ?? '';
        $hoscode = $data['hoscode'] ?? '';

        $birth = $birth_formatted;
        $hid = '';
        $pid = '';
        $sex = '1';

        $moo_str = str_pad($moo, 2, '0', STR_PAD_LEFT);
        $vhid_code = $tambon . $moo_str;

        try {
            try {
                $pdo->exec("ALTER TABLE target_population ADD COLUMN prefix VARCHAR(50) NULL AFTER pid");
            } catch (Exception $e) {
            }
            try {
                $pdo->exec("ALTER TABLE target_population ADD COLUMN is_manual TINYINT(1) DEFAULT 0 AFTER need_screen_ht");
            } catch (Exception $e) {
            }

            if ($dm == 1 && $ht == 1) {
                $origin = 'BOTH';
            } elseif ($dm == 1) {
                $origin = 'DM_ONLY';
            } elseif ($ht == 1) {
                $origin = 'HT_ONLY';
            } else {
                $origin = 'NORMAL';
            }

            if (!empty($old_cid) && $old_cid !== $cid) {
                // Changing CID: verify new CID doesn't already exist
                $check = $pdo->prepare("SELECT cid FROM target_population WHERE cid = ?");
                $check->execute([$cid]);
                if ($check->rowCount() > 0) {
                    echo json_encode(['status' => 'error', 'message' => 'เลขบัตรประชาชนใหม่มีอยู่ในระบบแล้ว ไม่สามารถแก้ไขเป็นเลขนี้ได้']);
                    exit;
                }

                // Retrieve existing JHCIS details (pid/hid/sex) to preserve them
                $findOld = $pdo->prepare("SELECT hid, pid, sex FROM target_population WHERE cid = ?");
                $findOld->execute([$old_cid]);
                $oldRow = $findOld->fetch();
                if ($oldRow) {
                    $hid = $oldRow['hid'];
                    $pid = $oldRow['pid'];
                    $sex = $oldRow['sex'];
                }

                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                try {
                    // Update task assignments target_cid
                    $stmtUpdateAssignCid = $pdo->prepare("UPDATE task_assignments SET target_cid = ? WHERE target_cid = ?");
                    $stmtUpdateAssignCid->execute([$cid, $old_cid]);

                    // Update DPAC enrollments cid
                    $stmtUpdateDpacCid = $pdo->prepare("UPDATE dpac_enrollments SET cid = ? WHERE cid = ?");
                    $stmtUpdateDpacCid->execute([$cid, $old_cid]);

                    // Update target_population record (updates the Primary Key)
                    $stmt = $pdo->prepare("UPDATE target_population SET cid = ?, prefix = ?, first_name = ?, last_name = ?, birth = ?, house_no = ?, moo = ?, sub_district_code = ?, vhid_code = ?, hoscode = ?, need_screen_dm = ?, need_screen_ht = ?, health_status_origin = ?, is_manual = 1, updated_at = NOW() WHERE cid = ?");
                    $stmt->execute([$cid, $prefix, $fname, $lname, $birth, $house_no, $moo_str, $tambon, $vhid_code, $hoscode, $dm, $ht, $origin, $old_cid]);
                    
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                } catch (Exception $ex) {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                    throw $ex;
                }
            } else {
                $check = $pdo->prepare("SELECT cid, hid, pid, sex FROM target_population WHERE cid = ?");
                $check->execute([$cid]);
                $existing = $check->fetch();
                if ($existing) {
                    $hid = $existing['hid'];
                    $pid = $existing['pid'];
                    $sex = $existing['sex'];
                    
                    $stmt = $pdo->prepare("UPDATE target_population SET prefix = ?, first_name = ?, last_name = ?, birth = ?, house_no = ?, moo = ?, sub_district_code = ?, vhid_code = ?, hoscode = ?, need_screen_dm = ?, need_screen_ht = ?, health_status_origin = ?, is_manual = 1, updated_at = NOW() WHERE cid = ?");
                    $stmt->execute([$prefix, $fname, $lname, $birth, $house_no, $moo_str, $tambon, $vhid_code, $hoscode, $dm, $ht, $origin, $cid]);
                } else {
                    $sql = "INSERT INTO target_population 
                            (cid, hid, pid, prefix, first_name, last_name, sex, birth, house_no, moo, sub_district_code, vhid_code, hoscode, health_status_origin, need_screen_dm, need_screen_ht, is_manual) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $cid,
                        $hid,
                        $pid,
                        $prefix,
                        $fname,
                        $lname,
                        $sex,
                        $birth,
                        $house_no,
                        $moo_str,
                        $tambon,
                        $vhid_code,
                        $hoscode,
                        $origin,
                        $dm,
                        $ht
                    ]);
                }
            }
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }


}

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการกลุ่มเป้าหมาย (Target Manager) - SSOTansum NCD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background-color: var(--bg-main);
        }

        .filter-card {
            background-color: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--neumorph-flat);
            margin-bottom: 20px;
        }

        .list-card {
            background-color: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--neumorph-inset);
            height: 600px;
            display: flex;
            flex-direction: column;
        }

        .list-body {
            flex: 1;
            overflow-y: auto;
            margin-top: 10px;
            padding-right: 5px;
        }

        .item-row {
            background-color: var(--bg-main);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 10px;
            box-shadow: var(--neumorph-flat);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all var(--transition-speed);
        }

        .item-info h4 {
            margin: 0 0 4px 0;
            color: var(--text-primary);
            font-size: 16px;
        }

        .item-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 13px;
        }

        .target-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--color-accent);
        }

        .badge {
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-dm {
            background: rgba(244, 63, 94, 0.2);
            color: #f43f5e;
            border: 1px solid #f43f5e;
        }

        .badge-ht {
            background: rgba(56, 189, 248, 0.2);
            color: #38bdf8;
            border: 1px solid #38bdf8;
        }

        .badge-none {
            background: rgba(156, 163, 175, 0.2);
            color: #9ca3af;
            border: 1px solid #9ca3af;
        }
        /* Pagination Controls */
        .pagination-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            padding: 16px 0 8px;
            flex-wrap: wrap;
        }
        .pagination-bar button {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-weight: 700;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all var(--transition-speed);
            min-width: 36px;
        }
        .pagination-bar button:hover:not(:disabled) {
            color: var(--color-accent);
            border-color: var(--color-accent);
            transform: translateY(-1px);
        }
        .pagination-bar button.active {
            background: var(--color-primary);
            color: #fff;
            border-color: var(--color-primary);
            box-shadow: var(--neumorph-inset);
        }
        .pagination-bar button:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .pagination-info {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
            margin-left: 12px;
        }
    </style>
</head>

<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h2
            style="color: var(--color-accent); margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px;">
            ระบบจัดการกลุ่มเป้าหมายคัดกรอง (Target Manager)
        </h2>

        <!-- Step 1: Filters -->
        <div class="filter-card">
            <h4 style="margin-top: 0; margin-bottom: 16px; color: var(--text-primary);">ตัวกรองพื้นที่และกลุ่มประชากร
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div>
                    <label class="form-label">ตำบล</label>
                    <select id="tambon" class="form-select" onchange="onTambonChange()">
                        <option value="">-- เลือกตำบล --</option>
                        <?php foreach ($subsList as $sub): ?>
                            <option value="<?= htmlspecialchars($sub['sub_district_code']) ?>"><?= htmlspecialchars($sub['sub_district_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="hoscode_container" style="display: none;">
                    <label class="form-label">หน่วยบริการ (รพ.สต.)</label>
                    <select id="hoscode" class="form-select" onchange="onHoscodeChange()">
                        <option value="">-- เลือกหน่วยบริการ --</option>
                    </select>
                </div>
                <div id="moo_container">
                    <label class="form-label">หมู่บ้าน</label>
                    <select id="moo" class="form-select" onchange="fetchData(1)">
                        <option value="">-- เลือกพื้นที่ก่อน --</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">สถานะกลุ่มเป้าหมาย</label>
                    <select id="status_filter" class="form-select" onchange="fetchData(1)">
                        <option value="all">ทั้งหมด</option>
                        <option value="target">เป็นกลุ่มเป้าหมายแล้ว</option>
                        <option value="non_target">ยังไม่ถูกตั้งเป็นเป้าหมาย</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">ค้นหารายชื่อ / CID</label>
                    <input type="text" id="search_input" class="form-control" placeholder="พิมพ์ชื่อ, นามสกุล หรือ CID" oninput="onSearchInput()">
                </div>
            </div>
        </div>



        <!-- Target List -->
        <div class="list-card" style="height: auto; min-height: 500px;">
            <div
                style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: var(--text-primary);">รายชื่อประชากร <span id="target-count"
                        style="font-size: 14px; color: var(--text-muted); font-weight: normal;">(พบ 0 ราย)</span></h3>
                <button onclick="showManualAddModal()" class="btn-giant btn-giant-primary" title="เพิ่มประชากรใหม่"
                    style="margin: 0; padding: 0; display: inline-flex; align-items: center; justify-content: center; width: 44px !important; height: 44px !important; border-radius: 50% !important; min-width: 44px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg>
                </button>
            </div>

            <div class="list-body" id="target-list" style="margin-top: 20px;">
                <div style="text-align: center; color: var(--text-muted); padding: 40px;">กรุณาเลือกพื้นที่</div>
            </div>
            <div id="pagination-container"></div>
        </div>
    </div>

    <!-- Script Definitions -->
    <script>
        // Data logic from register.php (Copied from assignment.php)
        const tambonData = <?= json_encode($jsData, JSON_UNESCAPED_UNICODE) ?>;

        function onTambonChange() {
            const tCode = document.getElementById('tambon').value;
            const hContainer = document.getElementById('hoscode_container');
            const hSelect = document.getElementById('hoscode');
            const mSelect = document.getElementById('moo');

            hSelect.innerHTML = '<option value="">-- เลือกหน่วยบริการ --</option>';
            mSelect.innerHTML = '<option value="">-- เลือกพื้นที่ก่อน --</option>';
            hContainer.style.display = 'none';

            if (!tCode) { fetchData(1); return; }

            const tInfo = tambonData[tCode];
            if (tInfo.hasSubUnits) {
                hContainer.style.display = 'block';
                for (let hc in tInfo.subUnits) {
                    hSelect.innerHTML += `<option value="${hc}">${tInfo.subUnits[hc].name}</option>`;
                }
            } else {
                populateMoo(tInfo.villages);
                fetchData(1);
            }
        }

        function onHoscodeChange() {
            const tCode = document.getElementById('tambon').value;
            const hCode = document.getElementById('hoscode').value;
            if (tCode && hCode && tambonData[tCode].hasSubUnits) {
                populateMoo(tambonData[tCode].subUnits[hCode].villages);
                fetchData(1);
            } else {
                document.getElementById('moo').innerHTML = '<option value="">-- เลือกหน่วยบริการก่อน --</option>';
                fetchData(1);
            }
        }

        function populateMoo(villages) {
            const mSelect = document.getElementById('moo');
            mSelect.innerHTML = '<option value="all" selected>ทุกหมู่บ้าน</option>';
            villages.forEach(v => {
                mSelect.innerHTML += `<option value="${v.moo}">หมู่ที่ ${v.moo} ${v.name}</option>`;
            });
        }

        let currentTargets = [];
        let currentPage = 1;
        let totalPages = 1;
        let totalRecords = 0;
        const pageLimit = 50;

        let searchTimeout = null;
        function onSearchInput() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                fetchData(1);
            }, 300);
        }

        function fetchData(page = currentPage) {
            currentPage = page;
            const tambon = document.getElementById('tambon').value;
            const moo = document.getElementById('moo').value;
            const status = document.getElementById('status_filter').value;
            const search = document.getElementById('search_input') ? document.getElementById('search_input').value.trim() : '';
            let hoscode = '';

            if (!tambon || !moo) {
                document.getElementById('target-list').innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">กรุณาเลือกหมู่บ้าน</div>';
                document.getElementById('target-count').innerText = '(พบ 0 ราย)';
                document.getElementById('pagination-container').innerHTML = '';
                return;
            }

            if (tambonData[tambon].hasSubUnits) {
                hoscode = document.getElementById('hoscode').value;
            } else {
                hoscode = tambonData[tambon].hoscode;
            }

            document.getElementById('target-list').innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">กำลังโหลด...</div>';
            document.getElementById('pagination-container').innerHTML = '';

            fetch(`target_manager.php?action=get_targets&hoscode=${hoscode}&moo=${moo}&status=${status}&search=${encodeURIComponent(search)}&page=${currentPage}&limit=${pageLimit}`)
                .then(r => r.json())
                .then(resp => {
                    const tPages = resp.totalPages || 1;
                    if (currentPage > tPages && tPages > 0) {
                        fetchData(tPages);
                        return;
                    }
                    currentTargets = resp.data || [];
                    totalRecords = resp.total || 0;
                    totalPages = tPages;
                    currentPage = resp.page || 1;
                    renderTargets();
                    renderPagination();
                });
        }

        function renderTargets() {
            const list = document.getElementById('target-list');
            document.getElementById('target-count').innerText = `(พบ ${totalRecords} ราย — หน้า ${currentPage}/${totalPages})`;

            if (currentTargets.length === 0) {
                list.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">ไม่พบประชากรเป้าหมายในพื้นที่นี้</div>';
                return;
            }

            let html = '';
            currentTargets.forEach(t => {
                let badgeDM = `
                    <button onclick="toggleSingleTarget('${t.cid}', 'DM', ${t.need_screen_dm})" 
                            class="badge" 
                            style="border: 1px solid ${t.need_screen_dm == 1 ? '#f43f5e' : '#cbd5e1'}; font-weight: bold; cursor: pointer; padding: 4px 8px; border-radius: 8px; transition: all 0.2s;
                                   background: ${t.need_screen_dm == 1 ? 'rgba(244, 63, 94, 0.15)' : 'transparent'};
                                   color: ${t.need_screen_dm == 1 ? '#e11d48' : 'var(--text-muted)'};
                                   opacity: ${t.need_screen_dm == 1 ? '1' : '0.8'}">
                        ${t.need_screen_dm == 1 ? '🔴 เป็นเป้าหมาย DM' : '⚪ ยังไม่เป็นเป้าหมาย DM'}
                    </button>
                `;
                let badgeHT = `
                    <button onclick="toggleSingleTarget('${t.cid}', 'HT', ${t.need_screen_ht})" 
                            class="badge" 
                            style="border: 1px solid ${t.need_screen_ht == 1 ? '#0284c7' : '#cbd5e1'}; font-weight: bold; cursor: pointer; padding: 4px 8px; border-radius: 8px; transition: all 0.2s;
                                   background: ${t.need_screen_ht == 1 ? 'rgba(2, 132, 199, 0.15)' : 'transparent'};
                                   color: ${t.need_screen_ht == 1 ? '#0284c7' : 'var(--text-muted)'};
                                   opacity: ${t.need_screen_ht == 1 ? '1' : '0.8'}">
                        ${t.need_screen_ht == 1 ? '🔵 เป็นเป้าหมาย HT' : '⚪ ยังไม่เป็นเป้าหมาย HT'}
                    </button>
                `;

                let dpacBadge = '';
                if (t.is_dpac == 1) {
                    dpacBadge = `<span class="badge" style="background: rgba(16, 185, 129, 0.15); color: var(--color-green); border: 1px solid var(--color-green); font-weight: bold;">เข้าร่วม DPAC แล้ว</span>`;
                } else {
                    let suggested = '';
                    if (t.need_screen_dm == 1) suggested = 'DM';
                    else if (t.need_screen_ht == 1) suggested = 'HT';
                    dpacBadge = `
                        <button onclick="enrollSingleDpac('${t.cid}', '${suggested}')" 
                                class="btn-primary" 
                                style="padding: 4px 10px; font-size: 12px; font-weight: bold; background: var(--color-green); border: none; border-radius: 6px; cursor: pointer; color: white; display: inline-flex; align-items: center; gap: 4px; height: 26px; line-height: 1;">
                            + DPAC
                        </button>
                    `;
                }

                // HDC info strings
                let fbsInfo = t.bslevel ? ` | FBS: <strong style="color: var(--color-yellow);">${t.bslevel}</strong> mg/dL` : '';
                let bpInfo = (t.sbp && t.dbp) ? ` | BP: <strong style="color: var(--color-yellow);">${t.sbp}/${t.dbp}</strong> mmHg` : '';

                let originText = t.health_status_origin;
                if (originText === 'HT_ONLY') originText = 'เฉพาะความดัน';
                else if (originText === 'DM_ONLY') originText = 'เฉพาะเบาหวาน';
                else if (originText === 'BOTH') originText = 'ทั้งเบาหวานและความดัน';
                else if (originText === 'HIGH_RISK') originText = 'กลุ่มเสี่ยงสูง';
                else if (originText === 'NORMAL') originText = 'ปกติ';
                else if (originText === 'MANUAL') originText = 'แมนนวล (ข้อมูลเก่า)';
                else if (!originText) originText = 'ไม่ระบุ';

                if (t.is_manual == 1) originText += ' (แมนนวล)';

                html += `
                    <div class="item-row">
                        <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                            <div class="item-info" style="flex: 1;">
                                <h4>${t.first_name} ${t.last_name} <span style="font-size: 13px; font-weight: normal; color: var(--text-muted); margin-left: 8px;">(CID: ${t.cid})</span></h4>
                                <p>บ้านเลขที่: ${t.house_no} | อายุ: ${t.age || '-'} ปี | กลุ่ม HDC: ${originText}${fbsInfo}${bpInfo}</p>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            ${badgeDM}
                            ${badgeHT}
                            ${dpacBadge}
                            <button onclick="editTarget('${t.cid}')" title="แก้ไขข้อมูล" style="background: none; border: none; color: var(--color-accent); cursor: pointer; padding: 4px; margin-left: 5px; display: inline-flex; align-items: center;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                `;
            });
            list.innerHTML = html;
        }

        function renderPagination() {
            const container = document.getElementById('pagination-container');
            if (totalPages <= 1) { container.innerHTML = ''; return; }

            let html = '<div class="pagination-bar">';

            // Previous button
            html += `<button ${currentPage <= 1 ? 'disabled' : ''} onclick="goToPage(${currentPage - 1})" title="หน้าก่อน">◀</button>`;

            // Page number buttons with ellipsis
            const maxVisible = 7;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
            let endPage = Math.min(totalPages, startPage + maxVisible - 1);
            if (endPage - startPage < maxVisible - 1) {
                startPage = Math.max(1, endPage - maxVisible + 1);
            }

            if (startPage > 1) {
                html += `<button onclick="goToPage(1)">1</button>`;
                if (startPage > 2) html += `<span style="color: var(--text-muted); padding: 0 4px;">…</span>`;
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `<button class="${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) html += `<span style="color: var(--text-muted); padding: 0 4px;">…</span>`;
                html += `<button onclick="goToPage(${totalPages})">${totalPages}</button>`;
            }

            // Next button
            html += `<button ${currentPage >= totalPages ? 'disabled' : ''} onclick="goToPage(${currentPage + 1})" title="หน้าถัดไป">▶</button>`;

            html += `<span class="pagination-info">แสดง ${((currentPage-1)*pageLimit)+1}–${Math.min(currentPage*pageLimit, totalRecords)} จาก ${totalRecords} ราย</span>`;
            html += '</div>';
            container.innerHTML = html;
        }

        function goToPage(page) {
            if (page < 1 || page > totalPages) return;
            fetchData(page);
        }



        function toggleSingleTarget(cid, disease, currentStatus) {
            const newStatus = currentStatus === 1 ? 0 : 1;
            const tambon = document.getElementById('tambon').value;
            const moo = document.getElementById('moo').value;
            let hoscode = '';
            if (tambonData[tambon].hasSubUnits) {
                hoscode = document.getElementById('hoscode').value;
            } else {
                hoscode = tambonData[tambon].hoscode;
            }

            fetch(`target_manager.php?action=toggle_target_disease`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    cid: cid, 
                    disease: disease, 
                    status: newStatus,
                    tambon: tambon,
                    moo: moo,
                    hoscode: hoscode
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    fetchData();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                }
            })
            .catch(err => alert('เกิดข้อผิดพลาดในการเชื่อมต่อ'));
        }

        function enrollSingleDpac(cid, suggestedType) {
            let type = suggestedType;
            if (!type) {
                type = prompt("กรุณาระบุประเภทความเสี่ยงโครงการ DPAC (DM หรือ HT):", "DM");
                if (!type) return;
                type = type.toUpperCase();
                if (type !== 'DM' && type !== 'HT') {
                    alert("ระบุไม่ถูกต้อง กรุณาระบุ DM หรือ HT");
                    return;
                }
            }

            const tambon = document.getElementById('tambon').value;
            const moo = document.getElementById('moo').value;
            let hoscode = '';
            if (tambonData[tambon].hasSubUnits) {
                hoscode = document.getElementById('hoscode').value;
            } else {
                hoscode = tambonData[tambon].hoscode;
            }

            fetch(`target_manager.php?action=enroll_dpac_single`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    cid: cid, 
                    risk_type: type,
                    tambon: tambon,
                    moo: moo,
                    hoscode: hoscode
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('ส่งรายชื่อเข้าโครงการ DPAC สำเร็จ!');
                    fetchData();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                }
            })
            .catch(err => alert('เกิดข้อผิดพลาดในการเชื่อมต่อ'));
        }

        // Sub-admin automatic scoping
        const loggedAdminHoscode = "<?= $admin_hoscode ?: '' ?>";
        window.addEventListener('DOMContentLoaded', () => {
            initModalTambonSelect();
            if (loggedAdminHoscode) {
                let targetTambon = "";
                let targetSubUnit = "";

                // Find matching tambon for the logged in hoscode
                for (let t in tambonData) {
                    if (tambonData[t].hasSubUnits) {
                        if (tambonData[t].subUnits[loggedAdminHoscode]) {
                            targetTambon = t;
                            targetSubUnit = loggedAdminHoscode;
                            break;
                        }
                    } else {
                        if (tambonData[t].hoscode === loggedAdminHoscode) {
                            targetTambon = t;
                            break;
                        }
                    }
                }

                if (targetTambon) {
                    const tSelect = document.getElementById('tambon');
                    tSelect.value = targetTambon;
                    tSelect.style.pointerEvents = 'none';
                    tSelect.style.backgroundColor = 'var(--bg-darker)';
                    onTambonChange();

                    if (targetSubUnit) {
                        const hSelect = document.getElementById('hoscode');
                        hSelect.value = targetSubUnit;
                        hSelect.style.pointerEvents = 'none';
                        hSelect.style.backgroundColor = 'var(--bg-darker)';
                        onHoscodeChange();
                    }
                }
            }
        });

        function initModalTambonSelect() {
            const mTambon = document.getElementById('modal_tambon');
            mTambon.innerHTML = '<option value="">-- เลือกตำบล --</option>';
            for (let tCode in tambonData) {
                mTambon.innerHTML += `<option value="${tCode}">${tambonData[tCode].name}</option>`;
            }
        }

        function onModalTambonChange() {
            const tCode = document.getElementById('modal_tambon').value;
            const hContainer = document.getElementById('modal_hoscode_container');
            const hSelect = document.getElementById('modal_hoscode');
            const mSelect = document.getElementById('modal_moo');

            hSelect.innerHTML = '<option value="">-- เลือก รพ.สต. --</option>';
            mSelect.innerHTML = '<option value="">-- เลือกหมู่บ้าน --</option>';
            hContainer.style.display = 'none';

            if (!tCode) return;

            const tInfo = tambonData[tCode];
            if (tInfo.hasSubUnits) {
                hContainer.style.display = 'block';
                for (let hc in tInfo.subUnits) {
                    hSelect.innerHTML += `<option value="${hc}">${tInfo.subUnits[hc].name}</option>`;
                }
            } else {
                populateModalMoo(tInfo.villages);
            }
        }

        function onModalHoscodeChange() {
            const tCode = document.getElementById('modal_tambon').value;
            const hCode = document.getElementById('modal_hoscode').value;
            if (tCode && hCode && tambonData[tCode].hasSubUnits) {
                populateModalMoo(tambonData[tCode].subUnits[hCode].villages);
            } else {
                document.getElementById('modal_moo').innerHTML = '<option value="">-- เลือกหมู่บ้าน --</option>';
            }
        }

        function populateModalMoo(villages) {
            const mSelect = document.getElementById('modal_moo');
            mSelect.innerHTML = '<option value="">-- เลือกหมู่บ้าน --</option>';
            villages.forEach(v => {
                mSelect.innerHTML += `<option value="${v.moo}">หมู่ที่ ${v.moo} ${v.name}</option>`;
            });
        }

        function lockModalAreaSelectors(lock) {
            const tSelect = document.getElementById('modal_tambon');
            const hSelect = document.getElementById('modal_hoscode');
            if (lock) {
                tSelect.disabled = true;
                tSelect.style.pointerEvents = 'none';
                tSelect.style.backgroundColor = 'var(--bg-darker)';
                tSelect.style.color = 'var(--text-primary)';
                if (hSelect) {
                    hSelect.disabled = true;
                    hSelect.style.pointerEvents = 'none';
                    hSelect.style.backgroundColor = 'var(--bg-darker)';
                    hSelect.style.color = 'var(--text-primary)';
                }
            } else {
                tSelect.disabled = false;
                tSelect.style.pointerEvents = 'auto';
                tSelect.style.backgroundColor = '';
                tSelect.style.color = '';
                if (hSelect) {
                    hSelect.disabled = false;
                    hSelect.style.pointerEvents = 'auto';
                    hSelect.style.backgroundColor = '';
                    hSelect.style.color = '';
                }
            }
        }

        function setupModalArea(tambonVal, hoscodeVal, mooVal) {
            const tSelect = document.getElementById('modal_tambon');
            const hSelect = document.getElementById('modal_hoscode');
            const mSelect = document.getElementById('modal_moo');

            // If sub-admin is logged in, force tambon and hoscode to their assigned area
            if (loggedAdminHoscode) {
                let targetTambon = "";
                for (let t in tambonData) {
                    if (tambonData[t].hasSubUnits) {
                        if (tambonData[t].subUnits[loggedAdminHoscode]) {
                            targetTambon = t;
                            break;
                        }
                    } else {
                        if (tambonData[t].hoscode === loggedAdminHoscode) {
                            targetTambon = t;
                            break;
                        }
                    }
                }
                tambonVal = targetTambon;
                hoscodeVal = loggedAdminHoscode;
            }

            // 1. Set Tambon
            tSelect.value = tambonVal || '';
            onModalTambonChange();

            // 2. Set Hoscode if applicable
            if (hoscodeVal && tambonVal && tambonData[tambonVal] && tambonData[tambonVal].hasSubUnits) {
                hSelect.value = hoscodeVal;
                onModalHoscodeChange();
            }

            // 3. Set Moo
            if (mooVal !== undefined && mooVal !== null && mooVal !== '') {
                mSelect.value = parseInt(mooVal, 10);
            }
        }

        let modalMode = 'add';
        let editCid = '';

        function showManualAddModal() {
            const tambon = document.getElementById('tambon').value;
            let moo = document.getElementById('moo').value;
            if (!tambon || !moo) {
                alert('กรุณาเลือกพื้นที่ (ตำบลและหมู่บ้าน) ก่อนเพิ่มข้อมูลแมนนวล');
                return;
            }
            if (moo === 'all') {
                moo = '';
            }

            modalMode = 'add';
            document.getElementById('modal-title').innerText = '+ เพิ่มประชากรเป้าหมายแบบแมนนวล';
            document.getElementById('manual_cid').disabled = false;

            document.getElementById('manual_cid').value = '';
            document.getElementById('manual_prefix').value = '';
            document.getElementById('manual_fname').value = '';
            document.getElementById('manual_lname').value = '';
            document.getElementById('manual_birth_date').value = '';
            document.getElementById('manual_house_no').value = '';
            document.getElementById('manual_dm').checked = true;
            document.getElementById('manual_ht').checked = true;
            document.getElementById('cid-error').style.display = 'none';
            document.getElementById('manual_cid').style.borderColor = 'var(--border-color)';

            let hoscode = '';
            if (tambonData[tambon].hasSubUnits) {
                hoscode = document.getElementById('hoscode').value;
            } else {
                hoscode = tambonData[tambon].hoscode;
            }
            setupModalArea(tambon, hoscode, moo);

            if (loggedAdminHoscode) {
                lockModalAreaSelectors(true);
            } else {
                lockModalAreaSelectors(false);
            }

            document.getElementById('manual-add-modal').style.display = 'flex';
        }

        function editTarget(cid) {
            const t = currentTargets.find(x => x.cid === cid);
            if (!t) return;

            modalMode = 'edit';
            editCid = cid;
            document.getElementById('modal-title').innerText = 'แก้ไขข้อมูลประชากร';
            document.getElementById('manual_cid').value = t.cid;
            document.getElementById('cid-error').style.display = 'none';
            document.getElementById('manual_cid').style.borderColor = 'var(--border-color)';
            formatThaiID(document.getElementById('manual_cid'));
            
            // Enable editing CID if it is currently masked (contains *) or is a pseudo-CID (fails Mod 11 check)
            if (t.cid.indexOf('*') !== -1 || !isValidThaiID(t.cid.replace(/[^0-9]/g, ''))) {
                document.getElementById('manual_cid').disabled = false;
                document.getElementById('manual_cid').placeholder = "ระบุเลข 13 หลักจริงแทนข้อมูลชั่วคราว/ปกปิด";
            } else {
                document.getElementById('manual_cid').disabled = true;
                document.getElementById('manual_cid').placeholder = "X-XXXX-XXXXX-XX-X";
            }

            document.getElementById('manual_prefix').value = t.prefix || '';
            document.getElementById('manual_fname').value = t.first_name || '';
            document.getElementById('manual_lname').value = t.last_name || '';

            if (t.birth) {
                const parts = t.birth.split('-');
                if (parts.length === 3) {
                    const yearBE = parseInt(parts[0]) + 543;
                    document.getElementById('manual_birth_date').value = `${parts[2]}/${parts[1]}/${yearBE}`;
                }
            } else {
                document.getElementById('manual_birth_date').value = '';
            }

            document.getElementById('manual_house_no').value = t.house_no || '';
            document.getElementById('manual_dm').checked = t.need_screen_dm == 1;
            document.getElementById('manual_ht').checked = t.need_screen_ht == 1;

            setupModalArea(t.sub_district_code, t.hoscode, t.moo);

            if (loggedAdminHoscode) {
                lockModalAreaSelectors(true);
            } else {
                lockModalAreaSelectors(false);
            }

            document.getElementById('manual-add-modal').style.display = 'flex';
        }

        function closeManualAddModal() {
            document.getElementById('manual-add-modal').style.display = 'none';
        }

        function submitManualAdd() {
            const tambon = document.getElementById('modal_tambon').value;
            const moo = document.getElementById('modal_moo').value;
            if (!tambon) { alert('กรุณาเลือกตำบล'); return; }
            if (!moo) { alert('กรุณาเลือกหมู่บ้าน'); return; }

            let hoscode = '';
            if (tambonData[tambon].hasSubUnits) {
                hoscode = document.getElementById('modal_hoscode').value;
                if (!hoscode) { alert('กรุณาเลือกหน่วยบริการ'); return; }
            } else {
                hoscode = tambonData[tambon].hoscode;
            }

            const rawBirthDate = document.getElementById('manual_birth_date').value.trim();
            if (!rawBirthDate) { alert('กรุณาระบุ วัน/เดือน/ปีเกิด (พ.ศ.)'); return; }

            const parts = rawBirthDate.split('/');
            if (parts.length !== 3) {
                alert('กรุณาระบุรูปแบบ วัน/เดือน/ปีเกิด ให้ถูกต้อง เช่น 25/10/2530');
                return;
            }
            const day = parts[0].padStart(2, '0');
            const month = parts[1].padStart(2, '0');
            const yearBE = parseInt(parts[2]);
            if (isNaN(yearBE) || yearBE < 2400 || yearBE > 2600) {
                alert('กรุณาระบุปี พ.ศ. ให้ถูกต้อง');
                return;
            }
            const yearCE = yearBE - 543;

            const rawCid = document.getElementById('manual_cid').value.replace(/[^0-9*]/g, '');
            if (modalMode === 'add' || (modalMode === 'edit' && rawCid !== editCid)) {
                if (rawCid.indexOf('*') === -1 && !isValidThaiID(rawCid)) {
                    alert('เลขบัตรประชาชนไม่ถูกต้องตามหลักเกณฑ์ กรุณาตรวจสอบ');
                    return;
                }
            }

            const prefix = document.getElementById('manual_prefix').value;
            const data = {
                old_cid: modalMode === 'edit' ? editCid : '',
                cid: rawCid,
                prefix: prefix,
                fname: document.getElementById('manual_fname').value.trim(),
                lname: document.getElementById('manual_lname').value.trim(),
                birth_formatted: `${yearCE}-${month}-${day}`,
                house_no: document.getElementById('manual_house_no').value.trim(),
                need_dm: document.getElementById('manual_dm').checked ? 1 : 0,
                need_ht: document.getElementById('manual_ht').checked ? 1 : 0,
                tambon: tambon,
                moo: moo,
                hoscode: hoscode
            };

            if (!data.cid || data.cid.length !== 13) { alert('กรุณาระบุเลขบัตรประชาชน 13 หลัก'); return; }
            if (!data.fname || !data.lname) { alert('กรุณาระบุชื่อและนามสกุล'); return; }

            fetch('target_manager.php?action=add_manual', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        if (modalMode === 'edit') {
                            alert('การแก้ไขสำเร็จ');
                        } else {
                            alert('เพิ่มประชากรกลุ่มเป้าหมายสำเร็จ!');
                        }
                        closeManualAddModal();
                        document.getElementById('manual_cid').value = '';
                        document.getElementById('manual_fname').value = '';
                        document.getElementById('manual_lname').value = '';
                        document.getElementById('manual_birth_date').value = '';
                        document.getElementById('manual_house_no').value = '';
                        fetchData();
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + (res.message || 'ไม่สามารถบันทึกได้'));
                    }
                })
                .catch(err => alert('เกิดข้อผิดพลาดในการเชื่อมต่อ'));
        }

        function formatThaiID(input) {
            let val = input.value.replace(/[^0-9*]/g, '');
            if (val.length > 13) val = val.substring(0, 13);

            let formatted = '';
            if (val.length > 0) formatted += val.substring(0, 1);
            if (val.length > 1) formatted += '-' + val.substring(1, 5);
            if (val.length > 5) formatted += '-' + val.substring(5, 10);
            if (val.length > 10) formatted += '-' + val.substring(10, 12);
            if (val.length > 12) formatted += '-' + val.substring(12, 13);

            input.value = formatted;

            const errDiv = document.getElementById('cid-error');
            if (errDiv) {
                if (val.length === 13) {
                    if (val.indexOf('*') !== -1) {
                        errDiv.style.display = 'none';
                        input.style.borderColor = 'var(--border-color)';
                    } else if (!isValidThaiID(val)) {
                        errDiv.style.display = 'block';
                        input.style.borderColor = '#ff4d4f';
                    } else {
                        errDiv.style.display = 'none';
                        input.style.borderColor = 'var(--border-color)';
                    }
                } else {
                    errDiv.style.display = 'none';
                    input.style.borderColor = 'var(--border-color)';
                }
            }
        }

        function isValidThaiID(id) {
            if (id.length !== 13) return false;
            let sum = 0;
            for (let i = 0; i < 12; i++) {
                sum += parseInt(id.charAt(i)) * (13 - i);
            }
            const mod = sum % 11;
            const checkDigit = (11 - mod) % 10;
            return checkDigit === parseInt(id.charAt(12));
        }
    </script>

    <!-- Manual Add Modal -->
    <div id="manual-add-modal" class="modal-overlay"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
        <div class="modal-content card-dark" style="max-width: 500px; width: 100%; padding: 24px; position: relative;">
            <h3 id="modal-title"
                style="margin-top: 0; color: var(--color-accent); border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                + เพิ่มประชากรเป้าหมายแบบแมนนวล</h3>
            <div style="margin-top: 16px;">
                <label class="form-label"
                    style="color: var(--text-secondary); display: block; margin-bottom: 4px;">เลขบัตรประชาชน (13
                    หลัก)</label>
                <input type="text" id="manual_cid" class="form-control" maxlength="17" placeholder="X-XXXX-XXXXX-XX-X"
                    style="width: 100%; box-sizing: border-box;" oninput="formatThaiID(this)">
                <div id="cid-error" style="color: #ff4d4f; font-size: 12px; margin-top: 4px; display: none;">
                    เลขบัตรประชาชนไม่ถูกต้องตามหลักเกณฑ์ Mod 11</div>
            </div>
            <div style="display: flex; gap: 12px; margin-top: 12px;">
                <div style="width: 120px; flex-shrink: 0;">
                    <label class="form-label"
                        style="color: var(--text-secondary); display: block; margin-bottom: 4px;">คำนำหน้า</label>
                    <select id="manual_prefix" class="form-control" style="width: 100%; box-sizing: border-box;">
                        <option value="นาย">นาย</option>
                        <option value="นาง">นาง</option>
                        <option value="นางสาว">นางสาว</option>
                        <option value="">(ไม่ระบุ)</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label class="form-label"
                        style="color: var(--text-secondary); display: block; margin-bottom: 4px;">ชื่อ</label>
                    <input type="text" id="manual_fname" class="form-control" placeholder="ชื่อจริง"
                        style="width: 100%; box-sizing: border-box;">
                </div>
                <div style="flex: 1;">
                    <label class="form-label"
                        style="color: var(--text-secondary); display: block; margin-bottom: 4px;">นามสกุล</label>
                    <input type="text" id="manual_lname" class="form-control" placeholder="นามสกุล"
                        style="width: 100%; box-sizing: border-box;">
                </div>
            </div>
            <div style="display: flex; gap: 12px; margin-top: 12px;">
                <div style="flex: 1;">
                    <label class="form-label"
                        style="color: var(--text-secondary); display: block; margin-bottom: 4px;">วัน/เดือน/ปีเกิด
                        (พ.ศ.)</label>
                    <input type="text" id="manual_birth_date" class="form-control" placeholder="เช่น 25/10/2530"
                        style="width: 100%; box-sizing: border-box;"
                        oninput="this.value = this.value.replace(/\D/g, '').substring(0,8).replace(/^(\d{2})(\d{1,2})?(\d{1,4})?$/, function(_, d, m, y) { return d + (m ? '/' + m : '') + (y ? '/' + y : ''); })">
                </div>
                <div style="flex: 1;">
                    <label class="form-label"
                        style="color: var(--text-secondary); display: block; margin-bottom: 4px;">บ้านเลขที่</label>
                    <input type="text" id="manual_house_no" class="form-control" placeholder="บ้านเลขที่"
                        style="width: 100%; box-sizing: border-box;">
                </div>
            </div>
            <div style="display: flex; gap: 12px; margin-top: 12px;">
                <div style="flex: 1;">
                    <label class="form-label"
                        style="color: var(--text-secondary); display: block; margin-bottom: 4px;">ตำบล</label>
                    <select id="modal_tambon" class="form-control" style="width: 100%; box-sizing: border-box;" onchange="onModalTambonChange()">
                        <option value="">-- เลือกตำบล --</option>
                    </select>
                </div>
                <div id="modal_hoscode_container" style="flex: 1; display: none;">
                    <label class="form-label"
                        style="color: var(--text-secondary); display: block; margin-bottom: 4px;">รพ.สต. (หน่วยบริการ)</label>
                    <select id="modal_hoscode" class="form-control" style="width: 100%; box-sizing: border-box;" onchange="onModalHoscodeChange()">
                        <option value="">-- เลือก รพ.สต. --</option>
                    </select>
                </div>
                <div id="modal_moo_container" style="flex: 1;">
                    <label class="form-label"
                        style="color: var(--text-secondary); display: block; margin-bottom: 4px;">หมู่บ้าน</label>
                    <select id="modal_moo" class="form-control" style="width: 100%; box-sizing: border-box;">
                        <option value="">-- เลือกหมู่บ้าน --</option>
                    </select>
                </div>
            </div>
            <div style="margin-top: 16px;">
                <label class="form-label"
                    style="color: var(--text-secondary); display: block; margin-bottom: 4px;">ต้องการตรวจ</label>
                <div style="display: flex; gap: 16px; margin-top: 8px;">
                    <label style="color: var(--text-primary); cursor: pointer;"><input type="checkbox" id="manual_dm"
                            checked style="accent-color: var(--color-accent);"> เบาหวาน (DM)</label>
                    <label style="color: var(--text-primary); cursor: pointer;"><input type="checkbox" id="manual_ht"
                            checked style="accent-color: var(--color-accent);"> ความดัน (HT)</label>
                </div>
            </div>
            <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
                <button onclick="closeManualAddModal()" class="btn-giant"
                    style="background: var(--bg-main); color: var(--text-secondary); box-shadow: var(--neumorph-flat);">ยกเลิก</button>
                <button onclick="submitManualAdd()" class="btn-giant btn-giant-primary">บันทึกข้อมูล</button>
            </div>
        </div>
    </div>
</body>

</html>