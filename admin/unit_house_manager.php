<?php
// admin/unit_house_manager.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

// Check if super admin
$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$is_super_admin = (!isset($admin_hoscode) || empty($admin_hoscode)) && (isset($_SESSION['admin_username']) && $_SESSION['admin_username'] !== 'adminsso');

if (!$is_super_admin) {
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';
$active_tab = $_GET['tab'] ?? 'units';

// Handle Form Submissions (CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $redirect_url = 'unit_house_manager.php?tab=' . urlencode($active_tab);

    try {
        // --- 1. Health Units CRUD ---
        if ($action === 'add_unit') {
            $hoscode = trim($_POST['hoscode'] ?? '');
            $hosname = trim($_POST['hosname'] ?? '');
            if (empty($hoscode) || empty($hosname)) {
                throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน");
            }
            $stmt = $pdo->prepare("SELECT hoscode FROM health_units WHERE hoscode = ?");
            $stmt->execute([$hoscode]);
            if ($stmt->fetch()) {
                throw new Exception("รหัสหน่วยบริการนี้มีอยู่แล้วในระบบ");
            }
            $stmt = $pdo->prepare("INSERT INTO health_units (hoscode, hosname) VALUES (?, ?)");
            $stmt->execute([$hoscode, $hosname]);
            $redirect_url .= '&status=success&msg=' . urlencode("เพิ่มหน่วยบริการ '$hosname' สำเร็จ");
            header("Location: $redirect_url");
            exit();
        } elseif ($action === 'edit_unit') {
            $hoscode = trim($_POST['hoscode'] ?? '');
            $hosname = trim($_POST['hosname'] ?? '');
            if (empty($hoscode) || empty($hosname)) {
                throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน");
            }
            $stmt = $pdo->prepare("UPDATE health_units SET hosname = ? WHERE hoscode = ?");
            $stmt->execute([$hosname, $hoscode]);
            $redirect_url .= '&status=success&msg=' . urlencode("แก้ไขหน่วยบริการเรียบร้อยแล้ว");
            header("Location: $redirect_url");
            exit();
        } elseif ($action === 'delete_unit') {
            $hoscode = trim($_POST['hoscode'] ?? '');
            if (empty($hoscode)) {
                throw new Exception("ไม่พบรหัสหน่วยบริการ");
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM villages WHERE hoscode = ?");
            $stmt->execute([$hoscode]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("ไม่สามารถลบได้เนื่องจากมีหมู่บ้านเชื่อมโยงกับหน่วยบริการนี้อยู่");
            }
            $stmt = $pdo->prepare("DELETE FROM health_units WHERE hoscode = ?");
            $stmt->execute([$hoscode]);
            $redirect_url .= '&status=success&msg=' . urlencode("ลบหน่วยบริการเรียบร้อยแล้ว");
            header("Location: $redirect_url");
            exit();
        }

        // --- 2. Sub-districts CRUD ---
        elseif ($action === 'add_sub_district') {
            $code = trim($_POST['sub_district_code'] ?? '');
            $name = trim($_POST['sub_district_name'] ?? '');
            if (empty($code) || empty($name)) {
                throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน");
            }
            $stmt = $pdo->prepare("SELECT sub_district_code FROM sub_districts WHERE sub_district_code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetch()) {
                throw new Exception("รหัสตำบลนี้มีอยู่แล้วในระบบ");
            }
            $stmt = $pdo->prepare("INSERT INTO sub_districts (sub_district_code, sub_district_name) VALUES (?, ?)");
            $stmt->execute([$code, $name]);
            $redirect_url .= '&status=success&msg=' . urlencode("เพิ่มตำบล '$name' สำเร็จ");
            header("Location: $redirect_url");
            exit();
        } elseif ($action === 'edit_sub_district') {
            $code = trim($_POST['sub_district_code'] ?? '');
            $name = trim($_POST['sub_district_name'] ?? '');
            if (empty($code) || empty($name)) {
                throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน");
            }
            $stmt = $pdo->prepare("UPDATE sub_districts SET sub_district_name = ? WHERE sub_district_code = ?");
            $stmt->execute([$name, $code]);
            $redirect_url .= '&status=success&msg=' . urlencode("แก้ไขตำบลเรียบร้อยแล้ว");
            header("Location: $redirect_url");
            exit();
        } elseif ($action === 'delete_sub_district') {
            $code = trim($_POST['sub_district_code'] ?? '');
            if (empty($code)) {
                throw new Exception("ไม่พบรหัสตำบล");
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM villages WHERE sub_district_code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("ไม่สามารถลบได้เนื่องจากมีหมู่บ้านเชื่อมโยงกับตำบลนี้อยู่");
            }
            $stmt = $pdo->prepare("DELETE FROM sub_districts WHERE sub_district_code = ?");
            $stmt->execute([$code]);
            $redirect_url .= '&status=success&msg=' . urlencode("ลบตำบลเรียบร้อยแล้ว");
            header("Location: $redirect_url");
            exit();
        }

        // --- 3. Villages CRUD ---
        elseif ($action === 'add_village') {
            $sub_district_code = trim($_POST['sub_district_code'] ?? '');
            $moo = intval($_POST['moo'] ?? 0);
            $village_name = trim($_POST['village_name'] ?? '');
            $hoscode = trim($_POST['hoscode'] ?? '');
            
            if (empty($sub_district_code) || $moo <= 0 || empty($village_name) || empty($hoscode)) {
                throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน");
            }
            
            $vhid_code = $sub_district_code . sprintf("%02d", $moo);
            
            $stmt = $pdo->prepare("SELECT vhid_code FROM villages WHERE vhid_code = ?");
            $stmt->execute([$vhid_code]);
            if ($stmt->fetch()) {
                throw new Exception("หมู่บ้านรหัส '$vhid_code' (หมู่ที่ $moo ในตำบลนี้) มีอยู่แล้วในระบบ");
            }
            
            $stmt = $pdo->prepare("INSERT INTO villages (vhid_code, sub_district_code, moo, village_name, hoscode) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$vhid_code, $sub_district_code, $moo, $village_name, $hoscode]);
            $redirect_url .= '&status=success&msg=' . urlencode("เพิ่มหมู่บ้าน '$village_name' สำเร็จ");
            header("Location: $redirect_url");
            exit();
        } elseif ($action === 'edit_village') {
            $vhid_code = trim($_POST['vhid_code'] ?? '');
            $sub_district_code = trim($_POST['sub_district_code'] ?? '');
            $moo = intval($_POST['moo'] ?? 0);
            $village_name = trim($_POST['village_name'] ?? '');
            $hoscode = trim($_POST['hoscode'] ?? '');
            
            if (empty($vhid_code) || empty($sub_district_code) || $moo <= 0 || empty($village_name) || empty($hoscode)) {
                throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน");
            }
            
            $new_vhid_code = $sub_district_code . sprintf("%02d", $moo);
            
            if ($new_vhid_code !== $vhid_code) {
                $stmt = $pdo->prepare("SELECT vhid_code FROM villages WHERE vhid_code = ?");
                $stmt->execute([$new_vhid_code]);
                if ($stmt->fetch()) {
                    throw new Exception("ไม่สามารถเปลี่ยนเป็นหมู่ที่ $moo ได้ เนื่องจากมีรหัสหมู่บ้าน '$new_vhid_code' อยู่ในระบบแล้ว");
                }
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM jhcis_homes WHERE vhid_code = ?");
                $stmt->execute([$vhid_code]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("ไม่สามารถเปลี่ยนตำบลหรือหมู่ที่ได้ เนื่องจากมีบ้านเชื่อมโยงกับรหัสหมู่บ้านนี้อยู่");
                }
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM target_population WHERE vhid_code = ?");
                $stmt->execute([$vhid_code]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("ไม่สามารถเปลี่ยนตำบลหรือหมู่ที่ได้ เนื่องจากมีประชากรเชื่อมโยงกับรหัสหมู่บ้านนี้อยู่");
                }
                
                $stmt = $pdo->prepare("UPDATE villages SET vhid_code = ?, sub_district_code = ?, moo = ?, village_name = ?, hoscode = ? WHERE vhid_code = ?");
                $stmt->execute([$new_vhid_code, $sub_district_code, $moo, $village_name, $hoscode, $vhid_code]);
            } else {
                $stmt = $pdo->prepare("UPDATE villages SET village_name = ?, hoscode = ? WHERE vhid_code = ?");
                $stmt->execute([$village_name, $hoscode, $vhid_code]);
            }
            $redirect_url .= '&status=success&msg=' . urlencode("แก้ไขหมู่บ้านเรียบร้อยแล้ว");
            header("Location: $redirect_url");
            exit();
        } elseif ($action === 'delete_village') {
            $vhid_code = trim($_POST['vhid_code'] ?? '');
            if (empty($vhid_code)) {
                throw new Exception("ไม่พบรหัสหมู่บ้าน");
            }
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM jhcis_homes WHERE vhid_code = ?");
            $stmt->execute([$vhid_code]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("ไม่สามารถลบหมู่บ้านได้ เนื่องจากมีบ้านเชื่อมโยงกับหมู่บ้านนี้อยู่");
            }
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM target_population WHERE vhid_code = ?");
            $stmt->execute([$vhid_code]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("ไม่สามารถลบหมู่บ้านได้ เนื่องจากมีประชากรเป้าหมายเชื่อมโยงกับหมู่บ้านนี้อยู่");
            }
            
            $stmt = $pdo->prepare("DELETE FROM villages WHERE vhid_code = ?");
            $stmt->execute([$vhid_code]);
            $redirect_url .= '&status=success&msg=' . urlencode("ลบหมู่บ้านเรียบร้อยแล้ว");
            header("Location: $redirect_url");
            exit();
        }

        // --- 4. Houses CRUD ---
        elseif ($action === 'add_home') {
            $hoscode = trim($_POST['hoscode'] ?? '');
            $hid = trim($_POST['hid'] ?? '');
            $house_no = trim($_POST['house_no'] ?? '');
            $vhid_code = trim($_POST['vhid_code'] ?? '');
            $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
            $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
            
            if (empty($hoscode) || empty($hid) || empty($house_no) || empty($vhid_code)) {
                throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน");
            }
            
            $stmt = $pdo->prepare("SELECT hid FROM jhcis_homes WHERE hoscode = ? AND hid = ?");
            $stmt->execute([$hoscode, $hid]);
            if ($stmt->fetch()) {
                throw new Exception("รหัสบ้าน (HID) '$hid' มีอยู่แล้วภายใต้หน่วยบริการนี้");
            }
            
            $stmt = $pdo->prepare("INSERT INTO jhcis_homes (hoscode, hid, house_no, vhid_code, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$hoscode, $hid, $house_no, $vhid_code, $latitude, $longitude]);
            
            $redirect_url .= '&status=success&msg=' . urlencode("เพิ่มข้อมูลบ้านเลขที่ '$house_no' สำเร็จ");
            header("Location: $redirect_url");
            exit();
        } elseif ($action === 'edit_home') {
            $hoscode = trim($_POST['hoscode'] ?? '');
            $hid = trim($_POST['hid'] ?? '');
            $house_no = trim($_POST['house_no'] ?? '');
            $vhid_code = trim($_POST['vhid_code'] ?? '');
            $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
            $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
            
            $orig_hoscode = trim($_POST['orig_hoscode'] ?? '');
            $orig_hid = trim($_POST['orig_hid'] ?? '');
            
            if (empty($hoscode) || empty($hid) || empty($house_no) || empty($vhid_code)) {
                throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน");
            }
            
            if ($hoscode !== $orig_hoscode || $hid !== $orig_hid) {
                $stmt = $pdo->prepare("SELECT hid FROM jhcis_homes WHERE hoscode = ? AND hid = ?");
                $stmt->execute([$hoscode, $hid]);
                if ($stmt->fetch()) {
                    throw new Exception("ไม่สามารถแก้ไขได้เนื่องจากรหัสบ้านใหม่นี้มีอยู่แล้วในระบบ");
                }
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM target_population WHERE hoscode = ? AND hid = ?");
                $stmt->execute([$orig_hoscode, $orig_hid]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("ไม่สามารถแก้ไขรหัสบ้าน/หน่วยบริการได้ เนื่องจากบ้านหลังนี้มีประชากรเป้าหมายอยู่");
                }
                
                $stmt = $pdo->prepare("UPDATE jhcis_homes SET hoscode = ?, hid = ?, house_no = ?, vhid_code = ?, latitude = ?, longitude = ? WHERE hoscode = ? AND hid = ?");
                $stmt->execute([$hoscode, $hid, $house_no, $vhid_code, $latitude, $longitude, $orig_hoscode, $orig_hid]);
            } else {
                $stmt = $pdo->prepare("UPDATE jhcis_homes SET house_no = ?, vhid_code = ?, latitude = ?, longitude = ? WHERE hoscode = ? AND hid = ?");
                $stmt->execute([$house_no, $vhid_code, $latitude, $longitude, $hoscode, $hid]);
            }
            $redirect_url .= '&status=success&msg=' . urlencode("แก้ไขข้อมูลบ้านเรียบร้อยแล้ว");
            header("Location: $redirect_url");
            exit();
        } elseif ($action === 'delete_home') {
            $hoscode = trim($_POST['hoscode'] ?? '');
            $hid = trim($_POST['hid'] ?? '');
            
            if (empty($hoscode) || empty($hid)) {
                throw new Exception("ระบุรหัสบ้านไม่ครบถ้วน");
            }
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM target_population WHERE hoscode = ? AND hid = ?");
            $stmt->execute([$hoscode, $hid]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("ไม่สามารถลบบ้านหลังนี้ได้ เนื่องจากมีประชากรเป้าหมายอาศัยอยู่");
            }
            
            $stmt = $pdo->prepare("DELETE FROM jhcis_homes WHERE hoscode = ? AND hid = ?");
            $stmt->execute([$hoscode, $hid]);
            $redirect_url .= '&status=success&msg=' . urlencode("ลบข้อมูลบ้านเรียบร้อยแล้ว");
            header("Location: $redirect_url");
            exit();
        }
    } catch (Exception $e) {
        $redirect_url .= '&status=error&msg=' . urlencode($e->getMessage());
        header("Location: $redirect_url");
        exit();
    }
}

// Get notification parameters
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $message = $_GET['msg'] ?? '';
    } else {
        $error = $_GET['msg'] ?? '';
    }
}

// Fetch lists
$health_units = $pdo->query("SELECT * FROM health_units ORDER BY hoscode ASC")->fetchAll();
$sub_districts = $pdo->query("SELECT * FROM sub_districts ORDER BY sub_district_code ASC")->fetchAll();

// Fetch Villages with joins
$villages = $pdo->query("
    SELECT v.*, s.sub_district_name, h.hosname
    FROM villages v
    LEFT JOIN sub_districts s ON v.sub_district_code = s.sub_district_code
    LEFT JOIN health_units h ON v.hoscode = h.hoscode
    ORDER BY v.sub_district_code ASC, v.moo ASC
")->fetchAll();

// Dynamic village map for Cascading Dropdowns
$hoscodeVillagesMap = [];
foreach ($villages as $v) {
    $hc = $v['hoscode'];
    if (!$hc) continue;
    if (!isset($hoscodeVillagesMap[$hc])) {
        $hoscodeVillagesMap[$hc] = [];
    }
    $hoscodeVillagesMap[$hc][] = [
        'vhid_code' => $v['vhid_code'],
        'village_name' => $v['village_name'],
        'moo' => intval($v['moo'])
    ];
}

// Fetch Paginated Houses
$house_page = max(1, intval($_GET['house_page'] ?? 1));
$house_limit = 20;
$house_offset = ($house_page - 1) * $house_limit;

$house_where = [];
$house_params = [];

$house_filter_hoscode = $_GET['house_hoscode'] ?? '';
$house_filter_vhid = $_GET['house_vhid'] ?? '';
$house_search = trim($_GET['house_search'] ?? '');

// Expression to resolve raw vhid_code: standardizes 7 to 8 digits, maps single digit moo to 8-digit village code based on hoscode, and falls back to target_population
$raw_vhid_sql = "COALESCE(
    NULLIF(
        CASE 
            WHEN h.vhid_code REGEXP '^[0-9]+$' AND CAST(h.vhid_code AS UNSIGNED) > 0 AND CAST(h.vhid_code AS UNSIGNED) < 100 THEN
                CONCAT(
                    CASE 
                        WHEN CAST(h.hoscode AS UNSIGNED) IN (10957, 3751) THEN '341801'
                        WHEN CAST(h.hoscode AS UNSIGNED) = 3752 THEN '341802'
                        WHEN CAST(h.hoscode AS UNSIGNED) = 3753 THEN '341803'
                        WHEN CAST(h.hoscode AS UNSIGNED) = 3754 THEN '341804'
                        WHEN CAST(h.hoscode AS UNSIGNED) IN (3755, 3756) THEN '341805'
                        WHEN CAST(h.hoscode AS UNSIGNED) = 3757 THEN '341806'
                        ELSE '341801'
                    END,
                    LPAD(h.vhid_code, 2, '0')
                )
            WHEN LENGTH(h.vhid_code) = 7 THEN 
                CONCAT(SUBSTRING(h.vhid_code, 1, 6), '0', SUBSTRING(h.vhid_code, 7, 1))
            WHEN LENGTH(h.vhid_code) = 8 THEN 
                h.vhid_code
            ELSE 
                NULL
        END, 
        ''
    ),
    (SELECT 
        CASE 
            WHEN LENGTH(t.vhid_code) = 7 THEN CONCAT(SUBSTRING(t.vhid_code, 1, 6), '0', SUBSTRING(t.vhid_code, 7, 1))
            WHEN LENGTH(t.vhid_code) = 8 THEN t.vhid_code
            ELSE CONCAT(t.sub_district_code, LPAD(t.moo, 2, '0'))
        END
     FROM target_population t
     WHERE CAST(t.hoscode AS UNSIGNED) = CAST(h.hoscode AS UNSIGNED) 
       AND CAST(t.hid AS UNSIGNED) = CAST(h.hid AS UNSIGNED)
       AND ((t.vhid_code IS NOT NULL AND t.vhid_code != '') 
            OR (t.sub_district_code IS NOT NULL AND t.sub_district_code != '' AND t.moo IS NOT NULL AND t.moo != ''))
     LIMIT 1),
    (SELECT 
        CONCAT(
            CASE 
                WHEN CAST(h.hoscode AS UNSIGNED) IN (10957, 3751) THEN '341801'
                WHEN CAST(h.hoscode AS UNSIGNED) = 3752 THEN '341802'
                WHEN CAST(h.hoscode AS UNSIGNED) = 3753 THEN '341803'
                WHEN CAST(h.hoscode AS UNSIGNED) = 3754 THEN '341804'
                WHEN CAST(h.hoscode AS UNSIGNED) IN (3755, 3756) THEN '341805'
                WHEN CAST(h.hoscode AS UNSIGNED) = 3757 THEN '341806'
                ELSE '341801'
            END,
            LPAD(t.moo, 2, '0')
        )
     FROM target_population t
     WHERE CAST(t.hoscode AS UNSIGNED) = CAST(h.hoscode AS UNSIGNED)
       AND CAST(t.hid AS UNSIGNED) = CAST(h.hid AS UNSIGNED)
       AND t.moo IS NOT NULL AND t.moo != ''
     LIMIT 1)
)";

// Final resolved vhid_code: replaces the district code prefix '3420' (from live database) with '3418' (from portal villages configuration)
$resolved_vhid_sql = "CASE WHEN $raw_vhid_sql LIKE '3420%' THEN CONCAT('3418', SUBSTRING($raw_vhid_sql, 5)) ELSE $raw_vhid_sql END";

if (!empty($house_filter_hoscode)) {
    $house_where[] = "CAST(h.hoscode AS UNSIGNED) = CAST(? AS UNSIGNED)";
    $house_params[] = $house_filter_hoscode;
}
if (!empty($house_filter_vhid)) {
    $house_where[] = "$resolved_vhid_sql = ?";
    $house_params[] = $house_filter_vhid;
}
if (!empty($house_search)) {
    $house_where[] = "(h.house_no = ? OR h.hid = ?)";
    $house_params[] = $house_search;
    $house_params[] = $house_search;
}

$house_where_sql = "";
if (!empty($house_where)) {
    $house_where_sql = "WHERE " . implode(" AND ", $house_where);
}

// Count total houses
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM jhcis_homes h $house_where_sql");
$count_stmt->execute($house_params);
$total_houses = $count_stmt->fetchColumn();
$total_house_pages = ceil($total_houses / $house_limit);

// Query paginated houses
$house_sql = "
    SELECT h.*, u.hosname, v.village_name, v.moo,
           $resolved_vhid_sql AS resolved_vhid_code
    FROM jhcis_homes h
    LEFT JOIN health_units u ON CAST(h.hoscode AS UNSIGNED) = CAST(u.hoscode AS UNSIGNED)
    LEFT JOIN villages v ON $resolved_vhid_sql = v.vhid_code
    $house_where_sql
    ORDER BY h.hoscode ASC, resolved_vhid_code ASC, CAST(h.house_no AS UNSIGNED) ASC, h.house_no ASC
    LIMIT $house_limit OFFSET $house_offset
";
$house_stmt = $pdo->prepare($house_sql);
$house_stmt->execute($house_params);
$houses_list = $house_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการหน่วยบริการ & บ้าน - SSOTansum NCD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background-color: var(--bg-main); }
        
        .tabs {
            display: flex;
            background-color: var(--bg-card);
            border-radius: 16px;
            padding: 6px;
            margin-bottom: 25px;
            box-shadow: var(--neumorph-inset);
            gap: 8px;
            width: fit-content;
            flex-wrap: wrap;
        }

        .tab {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 14.5px;
            font-weight: 800;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 12px;
            transition: all var(--transition-speed);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .tab:hover {
            color: var(--text-primary);
        }

        .tab.active {
            background-color: #0d2c54 !important; /* Force Navy Blue */
            color: #ffffff !important;
            box-shadow: inset 3px 3px 6px rgba(0, 0, 0, 0.4), inset -3px -3px 6px rgba(255, 255, 255, 0.1) !important;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .header-action {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(13, 44, 84, 0.45);
            backdrop-filter: blur(5px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-container {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--neumorph-flat);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }

        .modal-header-premium {
            padding: 20px 24px;
            border-bottom: 2px solid var(--bg-darker);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-main);
            border-radius: 20px 20px 0 0;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-close-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }

        .modal-close-btn:hover {
            color: var(--color-red);
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .pagination {
            display: flex;
            gap: 6px;
            justify-content: center;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .page-link {
            padding: 8px 14px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 8px;
            font-size: 13.5px;
            transition: all 0.2s;
            box-shadow: var(--neumorph-flat);
            font-weight: bold;
        }

        .page-link:hover {
            border-color: var(--color-primary);
            background: var(--bg-darker);
            transform: translateY(-1px);
        }

        .page-link.active {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
            box-shadow: var(--neumorph-inset);
        }
        
        .action-icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            border-radius: 8px;
            color: var(--text-secondary);
            transition: all var(--transition-speed);
        }
        .action-icon-btn:hover {
            background-color: var(--bg-darker);
        }
        .action-icon-btn.edit:hover {
            color: var(--color-primary);
        }
        .action-icon-btn.delete:hover {
            color: var(--color-red);
        }
    </style>
</head>
<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h2 style="color: var(--color-accent); margin-top: 0; margin-bottom: 8px;">🏢 จัดการโครงสร้างหน่วยบริการ & บ้าน</h2>
        <p style="color: var(--text-secondary); margin-bottom: 25px;">หน้าจอควบคุมและจัดการข้อมูล ตำบล หมู่บ้าน รพ.สต. และข้อมูลบ้าน (jhcis_homes)</p>

        <?php if ($message): ?>
            <div style="background-color: rgba(16, 185, 129, 0.15); border: 1px solid var(--color-green); color: var(--color-green); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold;">
                ✅ <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background-color: rgba(239, 68, 68, 0.15); border: 1px solid var(--color-red); color: var(--color-red); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold;">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Tab Controls -->
        <div class="tabs">
            <button onclick="switchTab('units')" id="tab-units" class="tab <?= $active_tab == 'units' ? 'active' : '' ?>">
                🏥 หน่วยบริการ (รพ.สต.)
            </button>
            <button onclick="switchTab('subdistricts')" id="tab-subdistricts" class="tab <?= $active_tab == 'subdistricts' ? 'active' : '' ?>">
                🗺️ ตำบล
            </button>
            <button onclick="switchTab('villages')" id="tab-villages" class="tab <?= $active_tab == 'villages' ? 'active' : '' ?>">
                🏡 หมู่บ้าน
            </button>
            <button onclick="switchTab('houses')" id="tab-houses" class="tab <?= $active_tab == 'houses' ? 'active' : '' ?>">
                🏠 บ้าน (jhcis_homes)
            </button>
        </div>

        <!-- ==================== TAB 1: UNITS ==================== -->
        <div id="content-units" class="tab-content <?= $active_tab == 'units' ? 'active' : '' ?>">
            <div class="card-dark">
                <div class="header-action">
                    <h3 style="margin: 0; color: var(--color-accent);">รายชื่อหน่วยบริการคัดกรอง (<?= count($health_units) ?> แห่ง)</h3>
                    <button onclick="openModal('modal-add-unit')" class="btn-primary" style="margin: 0; border-radius: 20px;">
                        + เพิ่มหน่วยบริการ
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 200px;">รหัส HOSCODE</th>
                                <th>ชื่อหน่วยบริการ</th>
                                <th style="width: 120px; text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($health_units as $hu): ?>
                                <tr>
                                    <td><strong style="color: var(--text-primary);"><?= htmlspecialchars($hu['hoscode']) ?></strong></td>
                                    <td><?= htmlspecialchars($hu['hosname']) ?></td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <button onclick="openEditUnitModal('<?= htmlspecialchars($hu['hoscode']) ?>', '<?= htmlspecialchars($hu['hosname']) ?>')" class="action-icon-btn edit" title="แก้ไข">
                                                ✏️
                                            </button>
                                            <form method="POST" onsubmit="return confirm('ยืนยันที่จะลบหน่วยบริการนี้?')" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_unit">
                                                <input type="hidden" name="hoscode" value="<?= htmlspecialchars($hu['hoscode']) ?>">
                                                <button type="submit" class="action-icon-btn delete" title="ลบ">
                                                    🗑️
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== TAB 2: SUBDISTRICTS ==================== -->
        <div id="content-subdistricts" class="tab-content <?= $active_tab == 'subdistricts' ? 'active' : '' ?>">
            <div class="card-dark">
                <div class="header-action">
                    <h3 style="margin: 0; color: var(--color-accent);">รายชื่อตำบลในเขตพื้นที่ (<?= count($sub_districts) ?> ตำบล)</h3>
                    <button onclick="openModal('modal-add-subdistrict')" class="btn-primary" style="margin: 0; border-radius: 20px;">
                        + เพิ่มตำบล
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 200px;">รหัสตำบล (6 หลัก)</th>
                                <th>ชื่อตำบล</th>
                                <th style="width: 120px; text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sub_districts as $sd): ?>
                                <tr>
                                    <td><strong style="color: var(--text-primary);"><?= htmlspecialchars($sd['sub_district_code']) ?></strong></td>
                                    <td>ตำบล<?= htmlspecialchars($sd['sub_district_name']) ?></td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <button onclick="openEditSubDistrictModal('<?= htmlspecialchars($sd['sub_district_code']) ?>', '<?= htmlspecialchars($sd['sub_district_name']) ?>')" class="action-icon-btn edit" title="แก้ไข">
                                                ✏️
                                            </button>
                                            <form method="POST" onsubmit="return confirm('ยืนยันที่จะลบตำบลนี้?')" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_sub_district">
                                                <input type="hidden" name="sub_district_code" value="<?= htmlspecialchars($sd['sub_district_code']) ?>">
                                                <button type="submit" class="action-icon-btn delete" title="ลบ">
                                                    🗑️
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== TAB 3: VILLAGES ==================== -->
        <div id="content-villages" class="tab-content <?= $active_tab == 'villages' ? 'active' : '' ?>">
            <div class="card-dark">
                <div class="header-action">
                    <h3 style="margin: 0; color: var(--color-accent);">รายชื่อหมู่บ้าน (<?= count($villages) ?> หมู่บ้าน)</h3>
                    <button onclick="openModal('modal-add-village')" class="btn-primary" style="margin: 0; border-radius: 20px;">
                        + เพิ่มหมู่บ้าน
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 180px;">รหัสหมู่บ้าน (VHID)</th>
                                <th style="width: 120px;">ตำบล</th>
                                <th style="width: 80px; text-align: center;">หมู่ที่</th>
                                <th>ชื่อหมู่บ้าน</th>
                                <th>รพ.สต. ที่รับผิดชอบ</th>
                                <th style="width: 120px; text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($villages as $vl): ?>
                                <tr>
                                    <td><strong style="color: var(--text-primary);"><?= htmlspecialchars($vl['vhid_code']) ?></strong></td>
                                    <td>ตำบล<?= htmlspecialchars($vl['sub_district_name']) ?></td>
                                    <td style="text-align: center;">หมู่ <?= htmlspecialchars($vl['moo']) ?></td>
                                    <td style="font-weight: bold; color: var(--color-primary);"><?= htmlspecialchars($vl['village_name']) ?></td>
                                    <td><?= htmlspecialchars($vl['hosname'] ?? $vl['hoscode']) ?></td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <button onclick="openEditVillageModal('<?= htmlspecialchars($vl['vhid_code']) ?>', '<?= htmlspecialchars($vl['sub_district_code']) ?>', <?= $vl['moo'] ?>, '<?= htmlspecialchars($vl['village_name']) ?>', '<?= htmlspecialchars($vl['hoscode']) ?>')" class="action-icon-btn edit" title="แก้ไข">
                                                ✏️
                                            </button>
                                            <form method="POST" onsubmit="return confirm('ยืนยันที่จะลบหมู่บ้านนี้?')" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_village">
                                                <input type="hidden" name="vhid_code" value="<?= htmlspecialchars($vl['vhid_code']) ?>">
                                                <button type="submit" class="action-icon-btn delete" title="ลบ">
                                                    🗑️
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== TAB 4: HOUSES ==================== -->
        <div id="content-houses" class="tab-content <?= $active_tab == 'houses' ? 'active' : '' ?>">
            <!-- Filters -->
            <div class="card-dark" style="margin-bottom: 25px;">
                <form method="GET" action="unit_house_manager.php" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <input type="hidden" name="tab" value="houses">
                    
                    <div style="flex: 1; min-width: 200px;">
                        <label class="form-label" style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">รพ.สต. ที่ดูแล</label>
                        <select name="house_hoscode" id="filter_house_hoscode" class="form-select" onchange="onFilterHoscodeChange()">
                            <option value="">-- ทั้งหมด --</option>
                            <?php foreach ($health_units as $hu): ?>
                                <option value="<?= htmlspecialchars($hu['hoscode']) ?>" <?= $house_filter_hoscode == $hu['hoscode'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($hu['hosname']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="flex: 1; min-width: 200px;">
                        <label class="form-label" style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">หมู่บ้าน</label>
                        <select name="house_vhid" id="filter_house_vhid" class="form-select">
                            <option value="">-- ทั้งหมด --</option>
                            <!-- Dynamic loaded by JS -->
                        </select>
                    </div>

                    <div style="flex: 1; min-width: 200px;">
                        <label class="form-label" style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">เลขที่บ้าน / รหัสบ้าน (HID)</label>
                        <input type="text" name="house_search" class="form-input-text" placeholder="ระบุเลขที่บ้านหรือ HID..." value="<?= htmlspecialchars($house_search) ?>" style="margin-bottom: 0; height: 40px; box-shadow: var(--neumorph-inset);">
                    </div>

                    <div>
                        <button type="submit" class="btn-primary" style="height: 42px; padding: 0 20px; border-radius: var(--border-radius); font-weight: bold; cursor: pointer; border: none; background: var(--color-accent); color: white;">
                            ค้นหา
                        </button>
                        <a href="unit_house_manager.php?tab=houses" class="btn-primary" style="height: 42px; padding: 0 15px; border-radius: var(--border-radius); font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; background: rgba(13, 44, 84, 0.1); color: var(--text-primary); border: 1px solid var(--border-color); box-sizing: border-box; margin-left: 5px;">
                            ล้างค่า
                        </a>
                    </div>
                </form>
            </div>

            <div class="card-dark">
                <div class="header-action">
                    <div>
                        <h3 style="margin: 0; color: var(--color-accent);">รายชื่อบ้านพักในฐานข้อมูล (พบ <?= number_format($total_houses) ?> หลัง)</h3>
                        <span style="font-size: 12.5px; color: var(--text-muted);">หน้า <?= $house_page ?> / <?= max(1, $total_house_pages) ?></span>
                    </div>
                    <button onclick="openModal('modal-add-home')" class="btn-primary" style="margin: 0; border-radius: 20px;">
                        + เพิ่มบ้านแมนนวล
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 160px;">รหัสบ้าน (HID)</th>
                                <th style="width: 140px;">บ้านเลขที่</th>
                                <th>หมู่บ้าน</th>
                                <th>รพ.สต. ที่ดูแล</th>
                                <th style="width: 250px;">พิกัด GPS (Latitude, Longitude)</th>
                                <th style="width: 120px; text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($houses_list)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 25px; color: var(--text-muted);">ไม่พบข้อมูลบ้านตามตัวกรองที่เลือก</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($houses_list as $hm): ?>
                                    <tr>
                                        <td><strong style="color: var(--text-primary);"><?= htmlspecialchars($hm['hid']) ?></strong></td>
                                        <td style="font-weight: bold; color: var(--color-primary);"><?= htmlspecialchars($hm['house_no']) ?></td>
                                        <td><?= htmlspecialchars($hm['village_name'] ?: get_village_only_name($hm['resolved_vhid_code'] ?? $hm['vhid_code'] ?? '', $hm['moo'] ?: intval(substr($hm['resolved_vhid_code'] ?? $hm['vhid_code'] ?? '', 6, 2)))) ?></td>
                                        <td><?= htmlspecialchars($hm['hosname'] ?? $hm['hoscode']) ?></td>
                                        <td>
                                            <?php if ($hm['latitude'] && $hm['longitude']): ?>
                                                <span style="color: var(--color-green); font-weight: bold;">🛰️ <?= htmlspecialchars($hm['latitude']) ?>, <?= htmlspecialchars($hm['longitude']) ?></span>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">- ไม่ระบุพิกัด -</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; gap: 8px; justify-content: center;">
                                                <button onclick="openEditHomeModal('<?= htmlspecialchars($hm['hoscode']) ?>', '<?= htmlspecialchars($hm['hid']) ?>', '<?= htmlspecialchars($hm['house_no']) ?>', '<?= htmlspecialchars($hm['vhid_code']) ?>', '<?= htmlspecialchars($hm['latitude'] ?? '') ?>', '<?= htmlspecialchars($hm['longitude'] ?? '') ?>')" class="action-icon-btn edit" title="แก้ไข">
                                                    ✏️
                                                </button>
                                                <form method="POST" onsubmit="return confirm('ยืนยันที่จะลบข้อมูลบ้านหลังนี้?')" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_home">
                                                    <input type="hidden" name="hoscode" value="<?= htmlspecialchars($hm['hoscode']) ?>">
                                                    <input type="hidden" name="hid" value="<?= htmlspecialchars($hm['hid']) ?>">
                                                    <button type="submit" class="action-icon-btn delete" title="ลบ">
                                                        🗑️
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Houses Pagination -->
                <?php if ($total_house_pages > 1): ?>
                    <div class="pagination">
                        <?php
                        $startPage = max(1, $house_page - 3);
                        $endPage = min($total_house_pages, $house_page + 3);
                        
                        $pg_params = $_GET;
                        
                        if ($startPage > 1) {
                            $pg_params['house_page'] = 1;
                            echo '<a href="?' . http_build_query($pg_params) . '" class="page-link">1</a>';
                            if ($startPage > 2) echo '<span style="padding: 6px; color: var(--text-secondary);">...</span>';
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            $active = ($i == $house_page) ? 'active' : '';
                            $pg_params['house_page'] = $i;
                            echo '<a href="?' . http_build_query($pg_params) . '" class="page-link ' . $active . '">' . $i . '</a>';
                        }
                        
                        if ($endPage < $total_house_pages) {
                            if ($endPage < $total_house_pages - 1) echo '<span style="padding: 6px; color: var(--text-secondary);">...</span>';
                            $pg_params['house_page'] = $total_house_pages;
                            echo '<a href="?' . http_build_query($pg_params) . '" class="page-link">' . $total_house_pages . '</a>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==================== MODALS ==================== -->
    
    <!-- 1. Add Health Unit Modal -->
    <div id="modal-add-unit" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header-premium">
                <h3 style="margin:0; color:var(--color-accent);">🏥 เพิ่มหน่วยบริการคัดกรองใหม่</h3>
                <button onclick="closeModal('modal-add-unit')" class="modal-close-btn">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_unit">
                <div class="modal-body">
                    <div class="form-group">
                        <label>รหัส HOSCODE (5 หลัก)</label>
                        <input type="text" name="hoscode" class="form-input-text" maxlength="10" placeholder="ระบุรหัสหน่วยบริการ..." required style="box-shadow:var(--neumorph-inset);">
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>ชื่อหน่วยบริการ</label>
                        <input type="text" name="hosname" class="form-input-text" placeholder="ระบุชื่อหน่วยบริการ/รพ.สต..." required style="box-shadow:var(--neumorph-inset);">
                    </div>
                    <div style="display:flex; gap:12px; margin-top:24px;">
                        <button type="button" onclick="closeModal('modal-add-unit')" class="btn-giant btn-giant-secondary" style="flex:1; margin:0;">ยกเลิก</button>
                        <button type="submit" class="btn-giant btn-giant-primary" style="flex:1; margin:0;">บันทึก</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Health Unit Modal -->
    <div id="modal-edit-unit" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header-premium">
                <h3 style="margin:0; color:var(--color-accent);">🏥 แก้ไขหน่วยบริการคัดกรอง</h3>
                <button onclick="closeModal('modal-edit-unit')" class="modal-close-btn">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_unit">
                <div class="modal-body">
                    <div class="form-group">
                        <label>รหัส HOSCODE (อ่านอย่างเดียว)</label>
                        <input type="text" id="edit_unit_hoscode" name="hoscode" class="form-input-text" readonly style="background-color: var(--bg-darker); cursor: not-allowed; box-shadow:var(--neumorph-inset);">
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>ชื่อหน่วยบริการ</label>
                        <input type="text" id="edit_unit_hosname" name="hosname" class="form-input-text" required style="box-shadow:var(--neumorph-inset);">
                    </div>
                    <div style="display:flex; gap:12px; margin-top:24px;">
                        <button type="button" onclick="closeModal('modal-edit-unit')" class="btn-giant btn-giant-secondary" style="flex:1; margin:0;">ยกเลิก</button>
                        <button type="submit" class="btn-giant btn-giant-primary" style="flex:1; margin:0;">บันทึก</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. Add Sub-district Modal -->
    <div id="modal-add-subdistrict" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header-premium">
                <h3 style="margin:0; color:var(--color-accent);">🗺️ เพิ่มตำบลใหม่</h3>
                <button onclick="closeModal('modal-add-subdistrict')" class="modal-close-btn">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_sub_district">
                <div class="modal-body">
                    <div class="form-group">
                        <label>รหัสตำบล (6 หลัก)</label>
                        <input type="text" name="sub_district_code" class="form-input-text" maxlength="10" placeholder="ระบุรหัสตำบล 6 หลัก เช่น 341801..." required style="box-shadow:var(--neumorph-inset);">
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>ชื่อตำบล</label>
                        <input type="text" name="sub_district_name" class="form-input-text" placeholder="ระบุชื่อตำบล..." required style="box-shadow:var(--neumorph-inset);">
                    </div>
                    <div style="display:flex; gap:12px; margin-top:24px;">
                        <button type="button" onclick="closeModal('modal-add-subdistrict')" class="btn-giant btn-giant-secondary" style="flex:1; margin:0;">ยกเลิก</button>
                        <button type="submit" class="btn-giant btn-giant-primary" style="flex:1; margin:0;">บันทึก</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Sub-district Modal -->
    <div id="modal-edit-subdistrict" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header-premium">
                <h3 style="margin:0; color:var(--color-accent);">🗺️ แก้ไขตำบล</h3>
                <button onclick="closeModal('modal-edit-subdistrict')" class="modal-close-btn">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_sub_district">
                <div class="modal-body">
                    <div class="form-group">
                        <label>รหัสตำบล (อ่านอย่างเดียว)</label>
                        <input type="text" id="edit_subdistrict_code" name="sub_district_code" class="form-input-text" readonly style="background-color: var(--bg-darker); cursor: not-allowed; box-shadow:var(--neumorph-inset);">
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>ชื่อตำบล</label>
                        <input type="text" id="edit_subdistrict_name" name="sub_district_name" class="form-input-text" required style="box-shadow:var(--neumorph-inset);">
                    </div>
                    <div style="display:flex; gap:12px; margin-top:24px;">
                        <button type="button" onclick="closeModal('modal-edit-subdistrict')" class="btn-giant btn-giant-secondary" style="flex:1; margin:0;">ยกเลิก</button>
                        <button type="submit" class="btn-giant btn-giant-primary" style="flex:1; margin:0;">บันทึก</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. Add Village Modal -->
    <div id="modal-add-village" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header-premium">
                <h3 style="margin:0; color:var(--color-accent);">🏡 เพิ่มหมู่บ้านใหม่</h3>
                <button onclick="closeModal('modal-add-village')" class="modal-close-btn">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_village">
                <div class="modal-body">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>ตำบล</label>
                            <select name="sub_district_code" class="form-select" required>
                                <option value="">-- เลือกตำบล --</option>
                                <?php foreach ($sub_districts as $sd): ?>
                                    <option value="<?= htmlspecialchars($sd['sub_district_code']) ?>">ตำบล<?= htmlspecialchars($sd['sub_district_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>หมู่ที่</label>
                            <input type="number" name="moo" class="form-input-text" placeholder="หมู่ที่..." min="1" max="99" required style="box-shadow:var(--neumorph-inset);">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>ชื่อหมู่บ้าน</label>
                        <input type="text" name="village_name" class="form-input-text" placeholder="ระบุชื่อหมู่บ้าน เช่น บ้านแก่งกบ..." required style="box-shadow:var(--neumorph-inset);">
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>รพ.สต. ที่รับผิดชอบ</label>
                        <select name="hoscode" class="form-select" required>
                            <option value="">-- เลือกหน่วยบริการ --</option>
                            <?php foreach ($health_units as $hu): ?>
                                <option value="<?= htmlspecialchars($hu['hoscode']) ?>"><?= htmlspecialchars($hu['hosname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:flex; gap:12px; margin-top:24px;">
                        <button type="button" onclick="closeModal('modal-add-village')" class="btn-giant btn-giant-secondary" style="flex:1; margin:0;">ยกเลิก</button>
                        <button type="submit" class="btn-giant btn-giant-primary" style="flex:1; margin:0;">บันทึก</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Village Modal -->
    <div id="modal-edit-village" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header-premium">
                <h3 style="margin:0; color:var(--color-accent);">🏡 แก้ไขหมู่บ้าน</h3>
                <button onclick="closeModal('modal-edit-village')" class="modal-close-btn">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_village">
                <input type="hidden" id="edit_vhid_code" name="vhid_code">
                <div class="modal-body">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>ตำบล</label>
                            <select id="edit_village_subdistrict" name="sub_district_code" class="form-select" required>
                                <option value="">-- เลือกตำบล --</option>
                                <?php foreach ($sub_districts as $sd): ?>
                                    <option value="<?= htmlspecialchars($sd['sub_district_code']) ?>">ตำบล<?= htmlspecialchars($sd['sub_district_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>หมู่ที่</label>
                            <input type="number" id="edit_village_moo" name="moo" class="form-input-text" min="1" max="99" required style="box-shadow:var(--neumorph-inset);">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>ชื่อหมู่บ้าน</label>
                        <input type="text" id="edit_village_name" name="village_name" class="form-input-text" required style="box-shadow:var(--neumorph-inset);">
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>รพ.สต. ที่รับผิดชอบ</label>
                        <select id="edit_village_hoscode" name="hoscode" class="form-select" required>
                            <option value="">-- เลือกหน่วยบริการ --</option>
                            <?php foreach ($health_units as $hu): ?>
                                <option value="<?= htmlspecialchars($hu['hoscode']) ?>"><?= htmlspecialchars($hu['hosname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:flex; gap:12px; margin-top:24px;">
                        <button type="button" onclick="closeModal('modal-edit-village')" class="btn-giant btn-giant-secondary" style="flex:1; margin:0;">ยกเลิก</button>
                        <button type="submit" class="btn-giant btn-giant-primary" style="flex:1; margin:0;">บันทึก</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. Add House Modal -->
    <div id="modal-add-home" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header-premium">
                <h3 style="margin:0; color:var(--color-accent);">🏠 เพิ่มบ้านพักแบบแมนนวล</h3>
                <button onclick="closeModal('modal-add-home')" class="modal-close-btn">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_home">
                <div class="modal-body">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>รพ.สต. ที่ดูแล</label>
                            <select id="add_home_hoscode" name="hoscode" class="form-select" onchange="onAddHomeHoscodeChange()" required>
                                <option value="">-- เลือก รพ.สต. --</option>
                                <?php foreach ($health_units as $hu): ?>
                                    <option value="<?= htmlspecialchars($hu['hoscode']) ?>"><?= htmlspecialchars($hu['hosname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>หมู่บ้าน</label>
                            <select id="add_home_vhid" name="vhid_code" class="form-select" required>
                                <option value="">-- เลือกหมู่บ้าน --</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid-2" style="margin-top: 15px;">
                        <div class="form-group">
                            <label>รหัสบ้าน (HID - 15 หลัก)</label>
                            <input type="text" name="hid" class="form-input-text" maxlength="15" placeholder="ระบุรหัสบ้าน HID..." required style="box-shadow:var(--neumorph-inset);">
                        </div>
                        <div class="form-group">
                            <label>บ้านเลขที่</label>
                            <input type="text" name="house_no" class="form-input-text" placeholder="บ้านเลขที่..." required style="box-shadow:var(--neumorph-inset);">
                        </div>
                    </div>
                    <div class="form-grid-2" style="margin-top: 15px;">
                        <div class="form-group">
                            <label>Latitude (ละติจูด)</label>
                            <input type="number" step="0.0000001" name="latitude" class="form-input-text" placeholder="15.XXXXXXX..." style="box-shadow:var(--neumorph-inset);">
                        </div>
                        <div class="form-group">
                            <label>Longitude (ลองจิจูด)</label>
                            <input type="number" step="0.0000001" name="longitude" class="form-input-text" placeholder="104.XXXXXXX..." style="box-shadow:var(--neumorph-inset);">
                        </div>
                    </div>
                    <div style="display:flex; gap:12px; margin-top:24px;">
                        <button type="button" onclick="closeModal('modal-add-home')" class="btn-giant btn-giant-secondary" style="flex:1; margin:0;">ยกเลิก</button>
                        <button type="submit" class="btn-giant btn-giant-primary" style="flex:1; margin:0;">บันทึก</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit House Modal -->
    <div id="modal-edit-home" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header-premium">
                <h3 style="margin:0; color:var(--color-accent);">🏠 แก้ไขข้อมูลบ้านพัก</h3>
                <button onclick="closeModal('modal-edit-home')" class="modal-close-btn">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_home">
                <input type="hidden" id="edit_home_orig_hoscode" name="orig_hoscode">
                <input type="hidden" id="edit_home_orig_hid" name="orig_hid">
                <div class="modal-body">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>รพ.สต. ที่ดูแล</label>
                            <select id="edit_home_hoscode" name="hoscode" class="form-select" onchange="onEditHomeHoscodeChange()" required>
                                <option value="">-- เลือก รพ.สต. --</option>
                                <?php foreach ($health_units as $hu): ?>
                                    <option value="<?= htmlspecialchars($hu['hoscode']) ?>"><?= htmlspecialchars($hu['hosname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>หมู่บ้าน</label>
                            <select id="edit_home_vhid" name="vhid_code" class="form-select" required>
                                <option value="">-- เลือกหมู่บ้าน --</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid-2" style="margin-top: 15px;">
                        <div class="form-group">
                            <label>รหัสบ้าน (HID - 15 หลัก)</label>
                            <input type="text" id="edit_home_hid" name="hid" class="form-input-text" maxlength="15" required style="box-shadow:var(--neumorph-inset);">
                        </div>
                        <div class="form-group">
                            <label>บ้านเลขที่</label>
                            <input type="text" id="edit_home_house_no" name="house_no" class="form-input-text" required style="box-shadow:var(--neumorph-inset);">
                        </div>
                    </div>
                    <div class="form-grid-2" style="margin-top: 15px;">
                        <div class="form-group">
                            <label>Latitude (ละติจูด)</label>
                            <input type="number" step="0.0000001" id="edit_home_latitude" name="latitude" class="form-input-text" style="box-shadow:var(--neumorph-inset);">
                        </div>
                        <div class="form-group">
                            <label>Longitude (ลองจิจูด)</label>
                            <input type="number" step="0.0000001" id="edit_home_longitude" name="longitude" class="form-input-text" style="box-shadow:var(--neumorph-inset);">
                        </div>
                    </div>
                    <div style="display:flex; gap:12px; margin-top:24px;">
                        <button type="button" onclick="closeModal('modal-edit-home')" class="btn-giant btn-giant-secondary" style="flex:1; margin:0;">ยกเลิก</button>
                        <button type="submit" class="btn-giant btn-giant-primary" style="flex:1; margin:0;">บันทึก</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== JAVASCRIPT ==================== -->
    <script>
        const hoscodeVillagesMap = <?= json_encode($hoscodeVillagesMap, JSON_UNESCAPED_UNICODE) ?>;
        
        // Active tab setting
        let activeTab = '<?= htmlspecialchars($active_tab) ?>';

        function switchTab(tabName) {
            activeTab = tabName;
            
            // Toggle active classes on tab buttons
            document.querySelectorAll('.tab').forEach(btn => btn.classList.remove('active'));
            const activeTabBtn = document.getElementById('tab-' + tabName);
            if (activeTabBtn) activeTabBtn.classList.add('active');

            // Toggle active classes on content panels
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            const activeContent = document.getElementById('content-' + tabName);
            if (activeContent) activeContent.classList.add('active');

            // Update URL query parameter without full reload if possible (but we might need it for pagination filters)
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }

        // Modal triggers
        function openModal(id) {
            const modal = document.getElementById(id);
            if (modal) modal.style.display = 'flex';
        }

        function closeModal(id) {
            const modal = document.getElementById(id);
            if (modal) modal.style.display = 'none';
        }

        // Edit Unit
        function openEditUnitModal(hoscode, hosname) {
            document.getElementById('edit_unit_hoscode').value = hoscode;
            document.getElementById('edit_unit_hosname').value = hosname;
            openModal('modal-edit-unit');
        }

        // Edit Subdistrict
        function openEditSubDistrictModal(code, name) {
            document.getElementById('edit_subdistrict_code').value = code;
            document.getElementById('edit_subdistrict_name').value = name;
            openModal('modal-edit-subdistrict');
        }

        // Edit Village
        function openEditVillageModal(vhid, subdistrict, moo, name, hoscode) {
            document.getElementById('edit_vhid_code').value = vhid;
            document.getElementById('edit_village_subdistrict').value = subdistrict;
            document.getElementById('edit_village_moo').value = moo;
            document.getElementById('edit_village_name').value = name;
            document.getElementById('edit_village_hoscode').value = hoscode;
            openModal('modal-edit-village');
        }

        // Edit Home
        function openEditHomeModal(hoscode, hid, houseNo, vhid, latitude, longitude) {
            document.getElementById('edit_home_orig_hoscode').value = hoscode;
            document.getElementById('edit_home_orig_hid').value = hid;
            document.getElementById('edit_home_hoscode').value = hoscode;
            document.getElementById('edit_home_hid').value = hid;
            document.getElementById('edit_home_house_no').value = houseNo;
            document.getElementById('edit_home_latitude').value = latitude;
            document.getElementById('edit_home_longitude').value = longitude;
            
            // Populates villages select in modal and select current
            populateVillagesSelect('edit_home_hoscode', 'edit_home_vhid', vhid);
            
            openModal('modal-edit-home');
        }

        // Dynamic Cascading Dropdowns for Villages
        function populateVillagesSelect(hoscodeSelectId, vhidSelectId, selectedVhid = '') {
            const hoscode = document.getElementById(hoscodeSelectId).value;
            const vhidSelect = document.getElementById(vhidSelectId);
            
            // Set dynamic first option text based on whether it is a search filter
            const firstOptionText = vhidSelectId.includes('filter') ? '-- ทั้งหมด --' : '-- เลือกหมู่บ้าน --';
            vhidSelect.innerHTML = `<option value="">${firstOptionText}</option>`;
            
            if (hoscode && hoscodeVillagesMap[hoscode]) {
                hoscodeVillagesMap[hoscode].forEach(v => {
                    const option = document.createElement('option');
                    option.value = v.vhid_code;
                    option.textContent = `หมู่ที่ ${v.moo} ${v.village_name}`;
                    if (v.vhid_code === selectedVhid) {
                        option.selected = true;
                    }
                    vhidSelect.appendChild(option);
                });
            }
        }

        // Handlers for dynamic dropdown changes
        function onFilterHoscodeChange() {
            populateVillagesSelect('filter_house_hoscode', 'filter_house_vhid', '<?= htmlspecialchars($house_filter_vhid) ?>');
        }
        function onAddHomeHoscodeChange() {
            populateVillagesSelect('add_home_hoscode', 'add_home_vhid');
        }
        function onEditHomeHoscodeChange() {
            populateVillagesSelect('edit_home_hoscode', 'edit_home_vhid');
        }

        // Document ready hooks
        window.addEventListener('DOMContentLoaded', () => {
            // Setup active tab on load
            switchTab(activeTab);

            // Populate filters if values already exist
            const initialFilterHoscode = '<?= htmlspecialchars($house_filter_hoscode) ?>';
            if (initialFilterHoscode) {
                onFilterHoscodeChange();
            }
        });
    </script>
</body>
</html>
