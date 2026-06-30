<?php
require_once 'config/db.php';
$hoscode = '10702'; // a typical health unit code
$moo = '01';

$hoscodes = get_query_hoscodes($hoscode);
$inPlaceholders = implode(',', array_fill(0, count($hoscodes), '?'));
$params = array_merge($hoscodes, $hoscodes, $hoscodes, $hoscodes, $hoscodes);

echo "Running original query...\n";
$start = microtime(true);

$sql_original = "
SELECT COUNT(*) FROM (
    SELECT 
        COALESCE(NULLIF(tp_real_pcu.cid, ''), NULLIF(tp_real_cid.cid, ''), t.cid) as cid,
        t.pid,
        t.hoscode,
        COALESCE(NULLIF(tp_real_pcu.first_name, ''), NULLIF(tp_real_cid.first_name, ''), NULLIF(t.first_name, ''), 'ไม่ทราบชื่อ') as first_name,
        COALESCE(NULLIF(tp_real_pcu.last_name, ''), NULLIF(tp_real_cid.last_name, ''), NULLIF(t.last_name, ''), 'ไม่ทราบประวัติ') as last_name,
        t.birth, t.house_no, TIMESTAMPDIFF(YEAR, t.birth, CURDATE()) as age,
        t.need_screen_dm, t.need_screen_ht, t.health_status_origin, t.is_manual
    FROM target_population t
    LEFT JOIN target_population tp_real_pcu ON (
        tp_real_pcu.hoscode = t.hoscode
        AND tp_real_pcu.pid = t.pid
        AND tp_real_pcu.cid NOT LIKE '0%'
        AND tp_real_pcu.cid NOT LIKE '%*%'
        AND tp_real_pcu.first_name NOT IN ('ไม่ทราบชื่อ','ไม่ทราบ','Unknown','')
    )
    LEFT JOIN target_population tp_real_cid ON (
        t.cid LIKE '%*%'
        AND tp_real_cid.cid NOT LIKE '0%'
        AND tp_real_cid.cid NOT LIKE '%*%'
        AND tp_real_cid.cid LIKE REPLACE(t.cid, '*', '%')
        AND tp_real_cid.birth = t.birth
        AND tp_real_cid.sex = t.sex
        AND tp_real_cid.first_name LIKE REPLACE(t.first_name, '*', '%')
        AND tp_real_cid.last_name LIKE REPLACE(t.last_name, '*', '%')
    )
    LEFT JOIN (
        SELECT cid, pid, hoscode, MAX(bslevel) as bslevel, MAX(bstest) as bstest, MAX(sbp) as sbp, MAX(dbp) as dbp
        FROM (
            SELECT dm.cid, dm.pid, dm.hoscode, dm.bslevel, dm.bstest, NULL as sbp, NULL as dbp FROM staging_hdc_dm dm WHERE dm.hoscode IN ($inPlaceholders)
            UNION ALL
            SELECT ht.cid, ht.pid, ht.hoscode, NULL as bslevel, NULL as bstest, ht.sbp, ht.dbp FROM staging_hdc_ht ht WHERE ht.hoscode IN ($inPlaceholders)
        ) sub_staging GROUP BY hoscode, pid
    ) h ON ((t.cid = h.cid AND h.cid NOT LIKE '%*%') OR (t.hoscode = h.hoscode AND t.pid = h.pid))
    WHERE t.hoscode IN ($inPlaceholders)
) count_tbl
";

try {
    $stmt = $pdo->prepare($sql_original);
    $stmt->execute($params);
    $cnt = $stmt->fetchColumn();
    $end = microtime(true);
    echo "Original query time: " . round($end - $start, 4) . " seconds. Total records: " . $cnt . "\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Running optimized query (no self-joins, simple coalesce)...\n";
$start = microtime(true);

$sql_opt = "
SELECT COUNT(*) FROM (
    SELECT 
        t.cid, t.pid, t.hoscode, t.first_name, t.last_name, t.birth, t.house_no,
        TIMESTAMPDIFF(YEAR, t.birth, CURDATE()) as age,
        t.need_screen_dm, t.need_screen_ht, t.health_status_origin, t.is_manual
    FROM target_population t
    WHERE t.hoscode IN ($inPlaceholders)
) count_tbl
";

try {
    $stmt = $pdo->prepare($sql_opt);
    $stmt->execute($hoscodes);
    $cnt = $stmt->fetchColumn();
    $end = microtime(true);
    echo "Optimized query time: " . round($end - $start, 4) . " seconds. Total records: " . $cnt . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>