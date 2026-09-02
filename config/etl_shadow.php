<?php

/**
 * Shadow ETL for NCDs Portal.
 *
 * This module reads the staging snapshots and writes proposals only to the
 * NCDs Portal review tables. It never connects to or changes JHCIS.
 */

function ncdShadowJson(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function ncdShadowDecode(?string $value): array
{
    if ($value === null || $value === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function ncdShadowHoscode($value): string
{
    $value = trim((string)$value);
    return $value === '' ? '' : str_pad($value, 5, '0', STR_PAD_LEFT);
}

function ncdShadowPid($value): string
{
    $value = ltrim(trim((string)$value), '0');
    return $value === '' ? '0' : $value;
}

function ncdShadowValidCid($value): bool
{
    $value = trim((string)$value);
    if (!preg_match('/^[0-9]{13}$/', $value)) {
        return false;
    }
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += ((int)$value[$i]) * (13 - $i);
    }
    return ((11 - ($sum % 11)) % 10) === (int)$value[12];
}

function ncdShadowUsableText($value): bool
{
    $value = trim((string)$value);
    if ($value === '' || strpos($value, '*') !== false) {
        return false;
    }
    return !in_array($value, ['ไม่ทราบ', 'ไม่ทราบชื่อ', 'ไม่ทราบประวัติ', 'Unknown'], true);
}

function ncdShadowIdentityKey(array $row): string
{
    $hoscode = ncdShadowHoscode($row['hoscode'] ?? '');
    $pid = ncdShadowPid($row['pid'] ?? '');
    if ($hoscode !== '' && $pid !== '0') {
        return $hoscode . '|' . $pid;
    }
    return 'CID|' . trim((string)($row['cid'] ?? ''));
}

function ncdShadowRiskFlag($risk): int
{
    return in_array(trim((string)$risk), ['1', '2'], true) ? 1 : 0;
}

function ncdShadowHealthOrigin($dmRisk, $htRisk): string
{
    $dmRisk = trim((string)$dmRisk);
    $htRisk = trim((string)$htRisk);
    if ($dmRisk === '2' || $htRisk === '2') return 'HIGH_RISK';
    if ($dmRisk === '1' && $htRisk === '1') return 'BOTH';
    if ($dmRisk === '1') return 'DM_ONLY';
    if ($htRisk === '1') return 'HT_ONLY';
    return 'NORMAL';
}

function ncdShadowPersonSnapshot(array $source, array $person = []): array
{
    $vhid = trim((string)($person['vhid_code'] ?? $source['check_vhid'] ?? ''));
    $moo = null;
    $subDistrict = null;
    if (strlen($vhid) === 8 && ctype_digit($vhid)) {
        $moo = (int)substr($vhid, 6, 2);
        $subDistrict = substr($vhid, 0, 6);
    }

    $houseNo = trim((string)($person['house_no'] ?? ''));
    if ($houseNo === '') {
        $addr = trim((string)($source['addr'] ?? ''));
        if (preg_match('/^(\d+(?:\/\d+)?)/', $addr, $match)) {
            $houseNo = $match[1];
        }
    }

    return [
        'cid' => trim((string)($person['cid'] ?? $source['cid'] ?? '')),
        'hid' => trim((string)($person['hid'] ?? $source['hid'] ?? '')),
        'pid' => ncdShadowPid($person['pid'] ?? $source['pid'] ?? ''),
        'first_name' => trim((string)($person['first_name'] ?? $source['name'] ?? '')),
        'last_name' => trim((string)($person['last_name'] ?? $source['lname'] ?? '')),
        'sex' => trim((string)($person['sex'] ?? $source['sex'] ?? '')),
        'birth' => trim((string)($person['birth'] ?? $source['birth'] ?? '')),
        'house_no' => $houseNo,
        'moo' => $moo,
        'sub_district_code' => $subDistrict,
        'vhid_code' => $vhid,
        'hoscode' => ncdShadowHoscode($person['hoscode'] ?? $source['hoscode'] ?? ''),
    ];
}

function ncdShadowTargetSnapshot(array $row): array
{
    $fields = [
        'cid', 'hid', 'pid', 'first_name', 'last_name', 'sex', 'birth',
        'house_no', 'moo', 'sub_district_code', 'vhid_code', 'hoscode',
        'need_screen_dm', 'need_screen_ht', 'health_status_origin', 'is_manual'
    ];
    $snapshot = [];
    foreach ($fields as $field) {
        $snapshot[$field] = $row[$field] ?? null;
    }
    return $snapshot;
}

function ncdShadowFillableChanges(array $before, array $source): array
{
    $changes = [];
    foreach (['hid', 'pid', 'first_name', 'last_name', 'sex', 'birth', 'house_no', 'moo', 'sub_district_code', 'vhid_code'] as $field) {
        $current = trim((string)($before[$field] ?? ''));
        $incoming = $source[$field] ?? null;
        // A masked value is intentional protected data, not an empty field.
        $currentMissing = $current === '' || in_array($current, ['0', '000000000000000', '1970-01-01'], true);
        $incomingUsable = $field === 'moo'
            ? ((int)$incoming > 0)
            : ncdShadowUsableText($incoming);
        if ($currentMissing && $incomingUsable) {
            $changes[$field] = $incoming;
        }
    }
    return $changes;
}

function ncdShadowNewTargetErrors(array $source): array
{
    $errors = [];
    if (!ncdShadowValidCid($source['cid'] ?? '')) $errors[] = 'เลขประจำตัวประชาชน';
    if (ncdShadowHoscode($source['hoscode'] ?? '') === '') $errors[] = 'หน่วยบริการ';
    if (!ncdShadowUsableText($source['hid'] ?? '')) $errors[] = 'รหัสบ้าน';
    if (!ncdShadowUsableText($source['first_name'] ?? '')) $errors[] = 'ชื่อ';
    if (!ncdShadowUsableText($source['last_name'] ?? '')) $errors[] = 'นามสกุล';
    if (!in_array((string)($source['sex'] ?? ''), ['1', '2'], true)) $errors[] = 'เพศ';
    if ((int)($source['moo'] ?? 0) <= 0) $errors[] = 'หมู่';
    if (!preg_match('/^[0-9]{8}$/', (string)($source['vhid_code'] ?? ''))) $errors[] = 'รหัสหมู่บ้าน';
    return $errors;
}

function ncdShadowInsertItem(PDO $pdo, int $runId, array $item): void
{
    $stmt = $pdo->prepare("INSERT INTO etl_review_items
        (run_id, hoscode, source_cid, target_cid, match_key, item_type,
         before_data, after_data, change_summary, review_status, apply_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $runId,
        $item['hoscode'] ?: null,
        $item['source_cid'] ?: null,
        $item['target_cid'] ?: null,
        $item['match_key'],
        $item['item_type'],
        ncdShadowJson($item['before']),
        ncdShadowJson($item['after']),
        $item['summary'],
        $item['review_status'] ?? 'pending',
        $item['apply_status'] ?? 'not_applied',
    ]);
}

function ncdShadowGenerateReview(PDO $pdo, string $createdBy): int
{
    $dmRows = $pdo->query("SELECT * FROM staging_hdc_dm ORDER BY staging_id")->fetchAll(PDO::FETCH_ASSOC);
    $htRows = $pdo->query("SELECT * FROM staging_hdc_ht ORDER BY staging_id")->fetchAll(PDO::FETCH_ASSOC);
    $personRows = $pdo->query("SELECT * FROM staging_jhcis_person ORDER BY staging_id")->fetchAll(PDO::FETCH_ASSOC);

    if (!$dmRows && !$htRows && !$personRows) {
        throw new RuntimeException('ยังไม่มีข้อมูลในพื้นที่พักสำหรับตรวจสอบ');
    }

    // Reuse an unfinished review when its exact staging snapshot is unchanged.
    // This makes refresh/repeated clicks idempotent and avoids review-data trash.
    $snapshotHash = hash('sha256', ncdShadowJson([
        'dm' => $dmRows,
        'ht' => $htRows,
        'person' => $personRows,
    ]));
    $existingStmt = $pdo->prepare("SELECT run_id FROM etl_review_runs
        WHERE snapshot_hash=? AND status='reviewing' ORDER BY run_id DESC LIMIT 1");
    $existingStmt->execute([$snapshotHash]);
    $existingRunId = (int)$existingStmt->fetchColumn();
    if ($existingRunId > 0) {
        return $existingRunId;
    }

    $sources = [];
    foreach ($dmRows as $row) {
        $key = ncdShadowIdentityKey($row);
        $sources[$key]['base'] = $row;
        $sources[$key]['dm'] = $row;
    }
    foreach ($htRows as $row) {
        $key = ncdShadowIdentityKey($row);
        if (!isset($sources[$key]['base'])) {
            $sources[$key]['base'] = $row;
        }
        $sources[$key]['ht'] = $row;
    }

    $sourceKeysByCid = [];
    foreach ($sources as $key => $bundle) {
        $cid = trim((string)($bundle['base']['cid'] ?? ''));
        if ($cid !== '') $sourceKeysByCid[$cid][] = $key;
    }

    $personsByHosPid = [];
    $personsByCid = [];
    foreach ($personRows as $row) {
        $personsByHosPid[ncdShadowIdentityKey($row)] = $row;
        $cid = trim((string)($row['cid'] ?? ''));
        if ($cid !== '') {
            $personsByCid[$cid] = $row;
        }
    }

    $targets = $pdo->query("SELECT cid, hid, pid, first_name, last_name, sex, birth, house_no, moo,
        sub_district_code, vhid_code, hoscode, need_screen_dm, need_screen_ht,
        health_status_origin, is_manual FROM target_population")->fetchAll(PDO::FETCH_ASSOC);
    $targetsByCid = [];
    $targetsByHosPid = [];
    foreach ($targets as $target) {
        $targetsByCid[trim((string)$target['cid'])] = $target;
        $targetsByHosPid[ncdShadowIdentityKey($target)] = $target;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO etl_review_runs (status, snapshot_hash, created_by) VALUES ('reviewing', ?, ?)")
            ->execute([$snapshotHash, $createdBy]);
        $runId = (int)$pdo->lastInsertId();

        $counts = ['total' => count($sources), 'proposed' => 0, 'unchanged' => 0, 'transfer' => 0, 'conflict' => 0];
        $hoscodes = [];

        foreach ($sources as $key => $bundle) {
            $base = $bundle['base'];
            $sourceCid = trim((string)($base['cid'] ?? ''));
            $sourceHoscode = ncdShadowHoscode($base['hoscode'] ?? '');
            if ($sourceHoscode !== '') {
                $hoscodes[$sourceHoscode] = true;
            }
            $person = $personsByHosPid[$key] ?? ($personsByCid[$sourceCid] ?? []);
            $source = ncdShadowPersonSnapshot($base, $person);
            $source['need_screen_dm'] = isset($bundle['dm']) ? ncdShadowRiskFlag($bundle['dm']['risk'] ?? null) : 0;
            $source['need_screen_ht'] = isset($bundle['ht']) ? ncdShadowRiskFlag($bundle['ht']['risk'] ?? null) : 0;
            $source['dm_risk'] = $bundle['dm']['risk'] ?? null;
            $source['ht_risk'] = $bundle['ht']['risk'] ?? null;
            $source['health_status_origin'] = ncdShadowHealthOrigin($source['dm_risk'], $source['ht_risk']);

            $identityIssue = '';
            if (count($sourceKeysByCid[$sourceCid] ?? []) > 1) {
                $identityIssue = 'เลขประจำตัวเดียวกันปรากฏมากกว่าหนึ่งหน่วยบริการในข้อมูลนำเข้า';
            } elseif ($person && ncdShadowHoscode($person['hoscode'] ?? '') !== ''
                && ncdShadowHoscode($person['hoscode']) !== $sourceHoscode) {
                $identityIssue = 'ข้อมูลบุคคลและข้อมูลความเสี่ยงระบุหน่วยบริการไม่ตรงกัน';
            }

            $target = null;
            if (ncdShadowValidCid($source['cid']) && isset($targetsByCid[$source['cid']])) {
                $target = $targetsByCid[$source['cid']];
            } elseif (isset($targetsByHosPid[$key])) {
                $target = $targetsByHosPid[$key];
            }

            $before = $target ? ncdShadowTargetSnapshot($target) : [];
            $targetCid = $target['cid'] ?? '';

            if ($target && ncdShadowValidCid($source['cid']) && trim((string)$targetCid) !== $source['cid']) {
                $identityIssue = 'จับคู่ด้วยรหัสภายในได้ แต่เลขประจำตัวประชาชนไม่ตรงกับข้อมูลเดิม';
            }
            if ($target && !ncdShadowValidCid($targetCid)) {
                $identityIssue = 'ข้อมูลเดิมใช้รหัสปกปิดหรือรหัสชั่วคราว จึงไม่รวมระเบียนอัตโนมัติ';
            }

            if ($identityIssue !== '') {
                ncdShadowInsertItem($pdo, $runId, [
                    'hoscode' => $sourceHoscode,
                    'source_cid' => $source['cid'],
                    'target_cid' => $targetCid,
                    'match_key' => $key,
                    'item_type' => 'conflict',
                    'before' => $before,
                    'after' => $source,
                    'summary' => $identityIssue . ' จึงไม่นำเข้าสู่ระบบจริง',
                    'review_status' => 'needs_review',
                    'apply_status' => 'blocked',
                ]);
                $counts['conflict']++;
                continue;
            }

            if ($target && ncdShadowHoscode($target['hoscode']) !== '' && $sourceHoscode !== ''
                && ncdShadowHoscode($target['hoscode']) !== $sourceHoscode) {
                ncdShadowInsertItem($pdo, $runId, [
                    'hoscode' => $sourceHoscode,
                    'source_cid' => $source['cid'],
                    'target_cid' => $targetCid,
                    'match_key' => $key,
                    'item_type' => 'unit_transfer',
                    'before' => $before,
                    'after' => $source,
                    'summary' => 'พบหน่วยบริการต่างจากข้อมูลเดิม ใช้เพื่อเฝ้าดูเท่านั้น',
                    'review_status' => 'observed',
                    'apply_status' => 'blocked',
                ]);
                $counts['transfer']++;
                continue;
            }

            if (!$target && !ncdShadowValidCid($source['cid'])) {
                ncdShadowInsertItem($pdo, $runId, [
                    'hoscode' => $sourceHoscode,
                    'source_cid' => $source['cid'],
                    'target_cid' => '',
                    'match_key' => $key,
                    'item_type' => 'conflict',
                    'before' => [],
                    'after' => $source,
                    'summary' => 'ยังจับคู่บุคคลไม่ได้อย่างแน่นอน จึงไม่นำเข้าสู่ระบบจริง',
                    'review_status' => 'needs_review',
                    'apply_status' => 'blocked',
                ]);
                $counts['conflict']++;
                continue;
            }

            $addsRisk = $source['need_screen_dm'] === 1 || $source['need_screen_ht'] === 1;
            $newDm = $addsRisk && (!$target || ((int)$target['need_screen_dm'] === 0 && $source['need_screen_dm'] === 1));
            $newHt = $addsRisk && (!$target || ((int)$target['need_screen_ht'] === 0 && $source['need_screen_ht'] === 1));
            $supportChanges = $target ? ncdShadowFillableChanges($before, $source) : [];

            if (!$target && $addsRisk && ($missing = ncdShadowNewTargetErrors($source))) {
                ncdShadowInsertItem($pdo, $runId, [
                    'hoscode' => $sourceHoscode,
                    'source_cid' => $source['cid'],
                    'target_cid' => '',
                    'match_key' => $key,
                    'item_type' => 'conflict',
                    'before' => [],
                    'after' => $source,
                    'summary' => 'ข้อมูลยังไม่ครบ: ' . implode(', ', $missing) . ' จึงไม่นำเข้าสู่ระบบจริง',
                    'review_status' => 'needs_review',
                    'apply_status' => 'blocked',
                ]);
                $counts['conflict']++;
                continue;
            }

            if ($newDm || $newHt) {
                $after = $target ? $before : $source;
                $after = array_merge($after, $supportChanges);
                $after['cid'] = $targetCid ?: $source['cid'];
                $after['hoscode'] = $target ? ncdShadowHoscode($target['hoscode']) : $sourceHoscode;
                $after['need_screen_dm'] = max((int)($before['need_screen_dm'] ?? 0), $source['need_screen_dm']);
                $after['need_screen_ht'] = max((int)($before['need_screen_ht'] ?? 0), $source['need_screen_ht']);
                if (empty($before['health_status_origin']) || $before['health_status_origin'] === 'NORMAL') {
                    $after['health_status_origin'] = $source['health_status_origin'];
                }
                $riskNames = [];
                if ($newDm) $riskNames[] = 'DM';
                if ($newHt) $riskNames[] = 'HT';
                ncdShadowInsertItem($pdo, $runId, [
                    'hoscode' => $sourceHoscode,
                    'source_cid' => $source['cid'],
                    'target_cid' => $targetCid,
                    'match_key' => $key,
                    'item_type' => 'new_risk',
                    'before' => $before,
                    'after' => $after,
                    'summary' => 'เสนอเพิ่มกลุ่มเสี่ยง ' . implode(' และ ', $riskNames),
                ]);
                $counts['proposed']++;
                continue;
            }

            if ($target && $supportChanges) {
                ncdShadowInsertItem($pdo, $runId, [
                    'hoscode' => $sourceHoscode,
                    'source_cid' => $source['cid'],
                    'target_cid' => $targetCid,
                    'match_key' => $key,
                    'item_type' => 'support_update',
                    'before' => $before,
                    'after' => array_merge($before, $supportChanges),
                    'summary' => 'เสนอเติมข้อมูลประกอบที่ยังว่าง โดยไม่เปลี่ยนผลงานเดิม',
                ]);
                $counts['proposed']++;
                continue;
            }

            $counts['unchanged']++;
        }

        $stmt = $pdo->prepare("UPDATE etl_review_runs SET source_hoscodes=?, total_source=?, proposed_count=?,
            unchanged_count=?, transfer_count=?, conflict_count=? WHERE run_id=?");
        $stmt->execute([
            ncdShadowJson(array_keys($hoscodes)), $counts['total'], $counts['proposed'],
            $counts['unchanged'], $counts['transfer'], $counts['conflict'], $runId
        ]);
        $pdo->commit();
        return $runId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function ncdShadowApplyRun(PDO $pdo, int $runId, string $appliedBy): array
{
    $result = ['applied' => 0, 'failed' => 0];
    $runStmt = $pdo->prepare("SELECT status FROM etl_review_runs WHERE run_id=? FOR UPDATE");

    $pdo->beginTransaction();
    try {
        $runStmt->execute([$runId]);
        if ($runStmt->fetchColumn() !== 'reviewing') {
            throw new RuntimeException('รอบตรวจสอบนี้สิ้นสุดแล้วหรือไม่พบข้อมูล');
        }

    $itemsStmt = $pdo->prepare("SELECT * FROM etl_review_items
        WHERE run_id=? AND review_status='approved' AND apply_status='not_applied'
          AND item_type IN ('new_risk','support_update') ORDER BY item_id");
    $itemsStmt->execute([$runId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            try {
                $pdo->exec('SAVEPOINT shadow_etl_item');
                $after = ncdShadowDecode($item['after_data']);
                $cid = trim((string)($item['target_cid'] ?: ($after['cid'] ?? '')));
                $hoscode = ncdShadowHoscode($item['hoscode']);
                if (!ncdShadowValidCid($cid) || $hoscode === '') {
                    throw new RuntimeException('รหัสบุคคลหรือหน่วยบริการไม่สมบูรณ์');
                }

                $currentStmt = $pdo->prepare("SELECT * FROM target_population WHERE cid=? FOR UPDATE");
                $currentStmt->execute([$cid]);
                $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

                if ($current && ncdShadowHoscode($current['hoscode']) !== $hoscode) {
                    throw new RuntimeException('หน่วยบริการในระบบจริงเปลี่ยนหลังสร้างข้อเสนอ');
                }

                if (!$current) {
                    $insert = $pdo->prepare("INSERT INTO target_population
                        (cid,hid,pid,first_name,last_name,sex,birth,house_no,moo,sub_district_code,vhid_code,hoscode,
                         need_screen_dm,need_screen_ht,health_status_origin,is_manual)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)");
                    $insert->execute([
                        $cid, $after['hid'], $after['pid'] ?: null,
                        $after['first_name'], $after['last_name'],
                        $after['sex'], $after['birth'] ?: null, $after['house_no'] ?: null,
                        $after['moo'], $after['sub_district_code'] ?: null,
                        $after['vhid_code'], $hoscode,
                        (int)($after['need_screen_dm'] ?? 0), (int)($after['need_screen_ht'] ?? 0),
                        $after['health_status_origin'] ?? 'NORMAL'
                    ]);
                } else {
                    $update = $pdo->prepare("UPDATE target_population SET
                        hid=CASE WHEN hid IS NULL OR hid='' OR hid='000000000000000' THEN ? ELSE hid END,
                        pid=CASE WHEN pid IS NULL OR pid='' THEN ? ELSE pid END,
                        first_name=CASE WHEN first_name IS NULL OR first_name='' THEN ? ELSE first_name END,
                        last_name=CASE WHEN last_name IS NULL OR last_name='' THEN ? ELSE last_name END,
                        sex=CASE WHEN sex IS NULL OR sex='' THEN ? ELSE sex END,
                        birth=CASE WHEN birth IS NULL OR birth='1970-01-01' THEN ? ELSE birth END,
                        house_no=CASE WHEN house_no IS NULL OR house_no='' THEN ? ELSE house_no END,
                        moo=CASE WHEN moo IS NULL OR moo=0 THEN ? ELSE moo END,
                        sub_district_code=CASE WHEN sub_district_code IS NULL OR sub_district_code='' THEN ? ELSE sub_district_code END,
                        vhid_code=CASE WHEN vhid_code IS NULL OR vhid_code='' THEN ? ELSE vhid_code END,
                        need_screen_dm=CASE WHEN ?=1 THEN 1 ELSE need_screen_dm END,
                        need_screen_ht=CASE WHEN ?=1 THEN 1 ELSE need_screen_ht END,
                        health_status_origin=CASE WHEN health_status_origin IS NULL OR health_status_origin='' OR health_status_origin='NORMAL' THEN ? ELSE health_status_origin END,
                        updated_at=NOW() WHERE cid=?");
                    $update->execute([
                        $after['hid'] ?? null, $after['pid'] ?? null,
                        $after['first_name'] ?? null, $after['last_name'] ?? null,
                        $after['sex'] ?? null, $after['birth'] ?? null,
                        $after['house_no'] ?? null, $after['moo'] ?? null,
                        $after['sub_district_code'] ?? null, $after['vhid_code'] ?? null,
                        (int)($after['need_screen_dm'] ?? 0), (int)($after['need_screen_ht'] ?? 0),
                        $after['health_status_origin'] ?? 'NORMAL', $cid
                    ]);
                }

                $pdo->prepare("UPDATE etl_review_items SET apply_status='applied', applied_by=?, applied_at=NOW(), error_message=NULL WHERE item_id=?")
                    ->execute([$appliedBy, $item['item_id']]);
                $result['applied']++;
            } catch (Throwable $itemError) {
                $pdo->exec('ROLLBACK TO SAVEPOINT shadow_etl_item');
                $pdo->prepare("UPDATE etl_review_items SET apply_status='failed', error_message=? WHERE item_id=?")
                    ->execute([$itemError->getMessage(), $item['item_id']]);
                $result['failed']++;
            }
        }

        $status = $result['failed'] > 0 ? 'applied_with_errors' : 'applied';
        $pdo->prepare("UPDATE etl_review_runs SET status=?, applied_by=?, applied_at=NOW() WHERE run_id=?")
            ->execute([$status, $appliedBy, $runId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return $result;
}
