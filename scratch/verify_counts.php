<?php
// scratch/verify_counts.php
require_once __DIR__ . '/../config/db.php';

echo "=== Verifying Target Counts in target_population ===\n";

$total = $pdo->query("SELECT COUNT(*) FROM target_population")->fetchColumn();
echo "Total target_population: $total\n";

$dm_only = $pdo->query("SELECT COUNT(*) FROM target_population WHERE need_screen_dm = 1 AND need_screen_ht = 0")->fetchColumn();
echo "DM target only (need_screen_dm=1, need_screen_ht=0): $dm_only\n";

$ht_only = $pdo->query("SELECT COUNT(*) FROM target_population WHERE need_screen_dm = 0 AND need_screen_ht = 1")->fetchColumn();
echo "HT target only (need_screen_dm=0, need_screen_ht=1): $ht_only\n";

$both = $pdo->query("SELECT COUNT(*) FROM target_population WHERE need_screen_dm = 1 AND need_screen_ht = 1")->fetchColumn();
echo "Both DM & HT target (need_screen_dm=1, need_screen_ht=1): $both\n";

$total_targets = $pdo->query("SELECT COUNT(*) FROM target_population WHERE need_screen_dm = 1 OR need_screen_ht = 1")->fetchColumn();
echo "Total screen targets (DM=1 or HT=1): $total_targets\n";

echo "\n=== Verifying Staging HDC Counts ===\n";

$staging_dm = $pdo->query("SELECT COUNT(*) FROM staging_hdc_dm")->fetchColumn();
echo "Total staging_hdc_dm rows: $staging_dm\n";

$staging_ht = $pdo->query("SELECT COUNT(*) FROM staging_hdc_ht")->fetchColumn();
echo "Total staging_hdc_ht rows: $staging_ht\n";

$staging_both = $pdo->query("SELECT COUNT(DISTINCT dm.cid) FROM staging_hdc_dm dm JOIN staging_hdc_ht ht ON dm.cid = ht.cid")->fetchColumn();
echo "Common CIDs in both DM & HT staging tables: $staging_both\n";

$dist_dm = $pdo->query("SELECT COUNT(DISTINCT cid) FROM staging_hdc_dm")->fetchColumn();
echo "Unique CIDs in staging_hdc_dm: $dist_dm\n";

$dist_ht = $pdo->query("SELECT COUNT(DISTINCT cid) FROM staging_hdc_ht")->fetchColumn();
echo "Unique CIDs in staging_hdc_ht: $dist_ht\n";

$union_cids = $pdo->query("SELECT COUNT(DISTINCT cid) FROM (SELECT cid FROM staging_hdc_dm UNION SELECT cid FROM staging_hdc_ht) u")->fetchColumn();
echo "Unique CIDs union of DM & HT staging: $union_cids\n";
?>
