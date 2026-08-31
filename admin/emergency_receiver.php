<?php
// admin/emergency_receiver.php - NCDs Red Alert Desktop Station (ศูนย์รับสัญญาณวิกฤตฉุกเฉินประจำ รพ.สต.)
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$admin_username = strtolower($_SESSION['admin_username'] ?? '');
$is_visitor = !empty($_SESSION['is_visitor']) || !empty($_SESSION['is_executive']);
$is_main_admin = !empty($_SESSION['admin_logged_in']) && !$is_visitor && ($admin_username === 'admin' || empty($admin_hoscode) || $admin_hoscode === '00325');
$is_super_admin = !empty($_SESSION['is_super_admin']);
$hc_names = function_exists('get_health_units') ? get_health_units() : [];

$selected_hoscode = $_GET['hoscode'] ?? $admin_hoscode ?? 'ALL';

// 1. Query all Sub-districts in District (ตรวจจับตำบลทั้งหมดในเขตอำเภอ)
$sub_districts = [];
try {
    $sub_districts = $pdo->query("SELECT * FROM sub_districts ORDER BY sub_district_code ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

// 2. Query all Villages with Sub-district and Health Unit info (ดึงรายชื่อหมู่บ้านที่ตรงตามตำบล)
$villages_data = [];
try {
    $villages_data = $pdo->query("
        SELECT v.vhid_code, v.sub_district_code, s.sub_district_name, v.hoscode, h.hosname, CAST(v.moo AS UNSIGNED) as moo, v.village_name
        FROM villages v
        LEFT JOIN sub_districts s ON v.sub_district_code = s.sub_district_code
        LEFT JOIN health_units h ON v.hoscode = h.hoscode
        ORDER BY v.sub_district_code ASC, CAST(v.moo AS UNSIGNED) ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <script>
        (function() {
            window.name = "ncd_red_alert_station_tab";
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
            // Suppress automatic dev modal popups on emergency dispatch screen so it only opens on click
            localStorage.setItem('ncd_dev_modal_last_shown_v2', new Date().toDateString());
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔰 NCDs Red Alert Station - ศูนย์รับสัญญาณวิกฤตฉุกเฉิน</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --emergency-red: #DC2626;
            --emergency-dark-red: #991B1B;
        }

        body {
            margin: 0;
            padding: 0;
            background: var(--bg-main, #eef2f7);
            color: var(--text-primary, #0d2c54);
            font-family: var(--font-base, 'Sarabun', sans-serif);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .station-header {
            background: var(--bg-card, #ffffff);
            border-bottom: 1px solid var(--border-color, rgba(0,0,0,0.06));
            padding: 10px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            box-sizing: border-box;
            z-index: 10000;
        }

        .station-header-spacer {
            flex: 0 0 var(--station-header-height, 86px);
            height: var(--station-header-height, 86px);
            pointer-events: none;
        }

        .header-left-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-right-tools {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .station-action-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            background: var(--bg-darker, #f1f5f9);
            padding: 5px 8px;
            border-radius: 16px;
            border: 1px solid var(--border-color, #CBD5E1);
            box-shadow: var(--neumorph-inset);
        }

        .station-select-ctrl {
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border-color, #CBD5E1);
            padding: 7px 12px;
            border-radius: 11px;
            font-size: 12.5px;
            font-weight: 700;
            outline: none;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s ease;
        }

        .station-select-ctrl:focus {
            border-color: #2563EB;
        }

        .btn-simulate-sos,
        .btn-download-app,
        .btn-referral-board {
            background: var(--bg-card, #ffffff) !important;
            color: var(--text-primary, #0d2c54) !important;
            border: 1px solid var(--border-color, #CBD5E1) !important;
            box-shadow: var(--neumorph-flat) !important;
        }

        .btn-simulate-sos:hover,
        .btn-download-app:hover,
        .btn-referral-board:hover {
            background: var(--bg-darker, #f1f5f9) !important;
            transform: translateY(-2px);
        }

        .header-divider {
            width: 1px;
            height: 28px;
            background: var(--border-color, #CBD5E1);
            margin: 0 2px;
        }

        .theme-switch-btn {
            width: 38px;
            height: 38px;
            padding: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-card);
            border: 1px solid var(--border-color, #CBD5E1);
            color: var(--text-primary);
            box-shadow: var(--neumorph-flat);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .theme-switch-btn:hover {
            transform: translateY(-1px) rotate(15deg);
            border-color: #2563EB;
            color: #2563EB;
        }

        .dev-info-subtle-btn {
            width: 34px;
            height: 34px;
            padding: 0;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: 1px solid transparent;
            color: var(--text-muted, #64748B);
            opacity: 0.6;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .dev-info-subtle-btn:hover {
            opacity: 1;
            background: var(--bg-card);
            border-color: var(--border-color, #CBD5E1);
            color: #2563EB;
            transform: translateY(-1px);
            box-shadow: var(--neumorph-flat);
        }

        .disc-red-subtle {
            background: var(--bg-card, #ffffff) !important;
            color: #DC2626 !important;
            border-color: var(--border-color, #CBD5E1) !important;
            box-shadow: var(--neumorph-flat) !important;
        }
        .disc-green-subtle {
            background: var(--bg-card, #ffffff) !important;
            color: #059669 !important;
            border-color: var(--border-color, #CBD5E1) !important;
            box-shadow: var(--neumorph-flat) !important;
        }
        .disc-blue-subtle {
            background: var(--bg-card, #ffffff) !important;
            color: #2563EB !important;
            border-color: var(--border-color, #CBD5E1) !important;
            box-shadow: var(--neumorph-flat) !important;
        }

        .station-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .pulsing-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #10B981;
            box-shadow: 0 0 12px #10B981;
            animation: pulse-green 1.8s infinite ease-in-out;
            flex-shrink: 0;
        }

        .pulsing-dot.active-crisis {
            background: #DC2626;
            box-shadow: 0 0 20px #DC2626;
            animation: pulse-red 0.8s infinite ease-in-out;
        }

        @keyframes pulse-green {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.3); opacity: 0.6; }
        }

        @keyframes pulse-red {
            0%, 100% { transform: scale(1); opacity: 1; filter: brightness(1.3); }
            50% { transform: scale(1.5); opacity: 0.7; filter: brightness(0.9); }
        }

        @keyframes emergencyBeaconPulse {
            0% {
                transform: scale(1);
                box-shadow: inset 2px 2px 4px rgba(255,255,255,0.7), 0 0 0 0 rgba(255, 255, 255, 0.75), 0 6px 18px rgba(0,0,0,0.3);
            }
            50% {
                transform: scale(1.08);
                box-shadow: inset 2px 2px 4px rgba(255,255,255,0.9), 0 0 0 12px rgba(255, 255, 255, 0), 0 10px 25px rgba(220,38,38,0.6);
            }
            100% {
                transform: scale(1);
                box-shadow: inset 2px 2px 4px rgba(255,255,255,0.7), 0 0 0 0 rgba(255, 255, 255, 0), 0 6px 18px rgba(0,0,0,0.3);
            }
        }

        .station-container {
            flex: 1;
            padding: 18px clamp(12px, 1.5vw, 28px);
            max-width: none;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
        }

        /* Compact Non-intrusive Live Status Bar */
        .status-hero-bar {
            background: var(--bg-card, #ffffff);
            border: 1.5px solid var(--border-color, rgba(0,0,0,0.06));
            border-radius: 16px;
            padding: 8px 16px;
            box-shadow: var(--neumorph-flat);
            margin-bottom: 16px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .station-header > .status-hero-bar {
            flex: 0 0 100%;
            width: 100%;
            margin: 0;
            box-sizing: border-box;
        }

        .status-bar-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .status-bar-text-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .status-bar-title {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--text-primary);
        }

        .status-bar-subtitle {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .status-bar-right {
            display: flex;
            align-items: center;
        }

        .status-ping-capsule {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            background: #DC2626;
            color: #FFFFFF;
            padding: 3px 12px;
            border-radius: 50px;
            box-shadow: 0 3px 9px rgba(220, 38, 38, 0.24);
            border: 1px solid #DC2626;
            white-space: nowrap;
        }

        .status-ping-capsule span,
        .status-ping-capsule #last-ping-time {
            color: #FFFFFF !important;
        }

        .status-hero-bar.alerting {
            background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%) !important;
            border-color: #EF4444 !important;
            color: #FFFFFF !important;
            box-shadow: 0 8px 24px rgba(220, 38, 38, 0.4);
        }

        .status-hero-bar.alerting .status-bar-title {
            color: #FFFFFF !important;
            font-size: 14px;
        }

        .status-hero-bar.alerting .status-bar-subtitle {
            color: rgba(255, 255, 255, 0.92) !important;
        }

        .status-hero-bar.alerting .status-ping-capsule {
            background: #DC2626 !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
            color: #FFFFFF !important;
        }

        .status-hero-bar.alerting #last-ping-time {
            color: #FFFFFF !important;
        }

        .alert-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 22px;
            align-items: stretch;
        }

        .alert-item-card {
            background: var(--bg-card, #ffffff);
            border: 1.5px solid var(--border-color, rgba(0,0,0,0.06));
            border-radius: 24px;
            padding: 20px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: var(--neumorph-flat);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            box-sizing: border-box;
            cursor: pointer;
        }

        .alert-item-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 32px rgba(0,0,0,0.09);
        }

        /* 3 Distinct Status Card Themes */
        .alert-item-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            z-index: 2;
        }

        /* 1. Pending (ยังไม่รับเรื่อง - แดงด่วนที่สุด) */
        .alert-item-card.pending {
            border-color: rgba(220, 38, 38, 0.65);
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.06) 0%, var(--bg-card) 100%);
            box-shadow: 0 10px 28px rgba(220, 38, 38, 0.22), var(--neumorph-flat);
        }
        .alert-item-card.pending::before {
            background: linear-gradient(90deg, #DC2626, #EF4444, #F87171);
        }

        /* 2. Acknowledged (รับเรื่องแล้ว/กำลังดูแล - เหลือง/ส้ม) */
        .alert-item-card.acknowledged {
            border-color: rgba(245, 158, 11, 0.55);
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.05) 0%, var(--bg-card) 100%);
            box-shadow: var(--neumorph-flat);
        }
        .alert-item-card.acknowledged::before {
            background: linear-gradient(90deg, #D97706, #F59E0B, #FBBF24);
        }

        /* 3. Referred Hospital (ส่งต่อ รพ. แล้ว - เขียวสำเร็จ) */
        .alert-item-card.referred_hospital {
            border-color: rgba(16, 185, 129, 0.55);
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, var(--bg-card) 100%);
            box-shadow: var(--neumorph-flat);
        }
        .alert-item-card.referred_hospital::before {
            background: linear-gradient(90deg, #059669, #10B981, #34D399);
        }

        /* Standardized Card Sub-Blocks for Exact Equal Height */
        .card-header-block {
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1.5px dashed var(--border-color, rgba(0,0,0,0.08));
        }

        .card-top-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
        }

        .case-id-badge {
            font-size: 11.5px;
            color: var(--text-muted);
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        .card-meta-right {
            display: flex;
            align-items: center;
            gap: 6px;
            justify-content: flex-end;
            flex-wrap: nowrap;
        }

        .time-capsule {
            background: var(--bg-darker);
            border: 1px solid var(--border-color, #CBD5E1);
            color: var(--text-primary);
            padding: 3px 8px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 11.5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            box-shadow: var(--neumorph-flat);
            white-space: nowrap;
        }

        .time-capsule.pending-time {
            background: rgba(220, 38, 38, 0.12);
            border-color: #DC2626;
            color: #DC2626;
        }

        .patient-name-title {
            margin: 4px 0 4px 0;
            font-size: 17px;
            font-weight: 900;
            color: var(--text-primary);
            letter-spacing: -0.2px;
            line-height: 1.38;
            word-break: normal;
            overflow-wrap: break-word;
            min-height: 24px;
        }

        .patient-age-tag {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text-secondary);
            margin-left: 4px;
            white-space: nowrap;
        }

        .patient-cid-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11.5px;
            color: var(--text-muted);
            font-weight: 600;
            margin-top: 2px;
        }

        .hoscode-tag {
            background: var(--bg-darker);
            border: 1px solid var(--border-color, rgba(0,0,0,0.06));
            padding: 1px 7px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 800;
            color: var(--text-secondary);
        }

        .vitals-grid-block {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            background: var(--bg-darker);
            border-radius: 16px;
            padding: 10px 12px;
            margin-bottom: 12px;
            box-shadow: var(--neumorph-inset);
        }

        .details-list-block {
            font-size: 12.5px;
            color: var(--text-secondary);
            margin-bottom: 12px;
            line-height: 1.45;
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex-grow: 1; /* Key to push lower blocks down uniformly */
        }

        .detail-row-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .detail-crisis-text {
            color: #DC2626;
            font-weight: 800;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-break: normal;
        }

        .contact-phone-block {
            min-height: 40px;
            border-radius: 14px;
            padding: 6px 12px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-sizing: border-box;
        }

        .card-actions-block {
            display: flex;
            gap: 8px;
            margin-top: auto;
            padding-top: 8px;
        }

        /* Fullscreen Overlay Popup */
        #emergency-popup-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999999;
            background: rgba(15, 23, 42, 0.58);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 12px;
            box-sizing: border-box;
        }

        .emergency-modal-card {
            background: rgba(248, 250, 252, 0.94);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: none;
            border-radius: 26px;
            max-width: 900px;
            width: 100%;
            max-height: calc(100vh - 24px);
            max-height: calc(100dvh - 24px);
            overflow: hidden;
            padding: 18px 22px;
            box-shadow: none;
            color: var(--text-primary, #0d2c54);
            text-align: center;
            animation: pop-bounce 0.22s ease-out;
            position: relative;
        }

        .emergency-modal-card > div:first-of-type .neu-disc-icon {
            animation: none !important;
            border: none !important;
            box-shadow: 0 8px 20px rgba(220, 38, 38, 0.34) !important;
        }

        .modal-corner-close-btn {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 38px;
            height: 38px;
            border-radius: 12px;
            border: 1px solid var(--border-color, #CBD5E1);
            background: var(--bg-card, #FFFFFF);
            color: var(--text-secondary, #64748B);
            font-size: 22px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s ease;
            z-index: 10;
        }
        .modal-corner-close-btn:hover {
            color: #DC2626;
            border-color: #DC2626;
            transform: scale(1.06);
        }

        .emergency-modal-actions {
            display: grid;
            grid-template-columns: 1.1fr 1.2fr 1fr 1.2fr;
            gap: 10px;
        }

        .emergency-modal-action {
            min-width: 0;
            min-height: 48px;
            padding: 10px 12px !important;
            border-radius: 14px;
            font-size: 13.5px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            white-space: nowrap;
        }

        .emergency-modal-action .neu-disc-icon {
            width: auto !important;
            height: auto !important;
            min-width: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
            filter: none !important;
        }

        @media (max-width: 760px) {
            .emergency-modal-card { padding: 14px; }
            .emergency-modal-actions { grid-template-columns: 1fr 1fr; gap: 8px; }
            .emergency-modal-action { font-size: 12px; padding: 8px 6px !important; white-space: normal; line-height: 1.25; }
        }

        @media (max-height: 850px) {
            .emergency-modal-card { padding: 12px 18px; }
            .emergency-modal-card > div:first-of-type { margin-bottom: 4px !important; }
            .emergency-modal-card > div:first-of-type .neu-disc-icon { width: 52px !important; height: 52px !important; min-width: 52px !important; }
            #modal-patient-name { margin: 5px 0 9px !important; font-size: 23px !important; }
            .modal-summary-grid { margin-bottom: 8px; }
            .modal-summary-item { padding: 7px 10px; }
        }

        [data-theme="dark"] .emergency-modal-card {
            background: rgba(15, 23, 42, 0.94);
        }

        @keyframes pop-bounce {
            0% { transform: translateY(12px) scale(0.98); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .modal-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin: 0 0 14px;
        }

        .modal-summary-item {
            background: var(--bg-darker);
            border-radius: 14px;
            padding: 10px 12px;
            box-shadow: var(--neumorph-inset);
            text-align: left;
        }

        .modal-summary-label {
            display: block;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .modal-summary-value {
            display: block;
            color: var(--text-primary);
            font-size: 15.5px;
            font-weight: 850;
            overflow-wrap: anywhere;
        }

        @media (max-width: 640px) {
            .emergency-modal-card { padding: 18px; }
            .modal-summary-grid { grid-template-columns: 1fr; }
        }

        .btn-action-glow {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            border: none;
            padding: 15px 24px;
            border-radius: 16px;
            font-size: 15.5px;
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
        }

        .btn-action-glow:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(220, 38, 38, 0.6);
        }

        .tag-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .tag-danger { background: rgba(220, 38, 38, 0.12); color: #DC2626; border: 1px solid rgba(220, 38, 38, 0.3); }
        .tag-warning { background: rgba(245, 158, 11, 0.12); color: #D97706; border: 1px solid rgba(245, 158, 11, 0.3); }
        .tag-success { background: rgba(16, 185, 129, 0.12); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3); }
        .tag-blue { background: rgba(37, 99, 235, 0.12); color: #2563EB; border: 1px solid rgba(37, 99, 235, 0.3); }
        .tag-purple { background: rgba(168, 85, 247, 0.12); color: #9333EA; border: 1px solid rgba(168, 85, 247, 0.3); }

        [data-theme="dark"] .tag-danger { background: rgba(220, 38, 38, 0.22); color: #F87171; border-color: rgba(220, 38, 38, 0.4); }
        [data-theme="dark"] .tag-warning { background: rgba(245, 158, 11, 0.22); color: #FBBF24; border-color: rgba(245, 158, 11, 0.4); }
        [data-theme="dark"] .tag-success { background: rgba(16, 185, 129, 0.22); color: #34D399; border-color: rgba(16, 185, 129, 0.4); }
        [data-theme="dark"] .tag-blue { background: rgba(56, 189, 248, 0.2); color: #38BDF8; border-color: rgba(56, 189, 248, 0.4); }
        [data-theme="dark"] .tag-purple { background: rgba(168, 85, 247, 0.25); color: #C084FC; border-color: rgba(168, 85, 247, 0.4); }

        .btn-station-ctrl {
            background: var(--bg-card, #ffffff);
            border: 1px solid var(--border-color, #CBD5E1);
            color: var(--text-primary, #0d2c54);
            padding: 8px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-station-ctrl:hover {
            transform: translateY(-2px);
            background: var(--bg-darker, #f1f5f9);
        }

        /* Control Panel & Search Bar */
        .station-control-panel {
            background: var(--bg-card, #ffffff);
            border: 1.5px solid var(--border-color, rgba(0,0,0,0.06));
            border-radius: 22px;
            padding: 18px 20px;
            margin-bottom: 22px;
            box-shadow: var(--neumorph-flat);
            display: flex;
            flex-direction: column;
            gap: 14px;
            transition: all 0.3s ease;
        }

        .kpi-mini-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(170px, 1fr));
            gap: 12px;
            margin-bottom: 4px;
        }

        @media (max-width: 1180px) {
            .kpi-mini-grid {
                grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            }
        }

        .kpi-mini-card {
            background: var(--bg-darker, #f8fafc);
            border: 1.5px solid var(--border-color, rgba(0,0,0,0.06));
            border-radius: 16px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--neumorph-inset);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .kpi-mini-card:hover {
            transform: translateY(-2px);
            border-color: #3B82F6;
        }

        .kpi-mini-card.active-kpi {
            border-color: #2563EB;
            background: var(--bg-card, #ffffff);
            box-shadow: var(--neumorph-flat), 0 0 0 2px rgba(37, 99, 235, 0.2);
        }

        .search-box-wrap {
            position: relative;
            flex: 1;
            min-width: 260px;
        }

        .search-box-wrap input {
            width: 100%;
            box-sizing: border-box;
            background: var(--bg-darker, #f8fafc);
            color: var(--text-primary, #0d2c54);
            border: 1.5px solid var(--border-color, #CBD5E1);
            padding: 11px 42px 11px 40px;
            border-radius: 14px;
            font-size: 14px;
            font-family: inherit;
            font-weight: 600;
            outline: none;
            transition: all 0.2s ease;
            box-shadow: var(--neumorph-inset);
        }

        .search-box-wrap input:focus {
            border-color: #2563EB;
            background: var(--bg-card, #ffffff);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .search-icon-left {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted, #64748B);
            pointer-events: none;
            font-size: 15px;
        }

        .search-clear-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(148, 163, 184, 0.2);
            border: none;
            color: var(--text-muted);
            width: 22px;
            height: 22px;
            border-radius: 50%;
            font-size: 12px;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .search-clear-btn:hover {
            background: #EF4444;
            color: #fff;
        }

        .filter-pills-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-tab-btn {
            background: var(--bg-darker, #f1f5f9);
            color: var(--text-secondary, #475569);
            border: 1px solid var(--border-color, #CBD5E1);
            padding: 7px 14px;
            border-radius: 12px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .filter-tab-btn:hover {
            background: var(--bg-card);
            color: var(--text-primary);
            transform: translateY(-1px);
        }

        .filter-tab-btn.active {
            background: #2563EB !important;
            color: #ffffff !important;
            border-color: #1D4ED8 !important;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
        }

        .filter-tab-btn.active.pending-tab {
            background: #DC2626 !important;
            border-color: #B91C1C !important;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.35);
        }

        .filter-tab-btn.active.ack-tab {
            background: #D97706 !important;
            border-color: #B45309 !important;
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.35);
        }

        .filter-tab-btn.active.refer-tab {
            background: #059669 !important;
            border-color: #047857 !important;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
        }

        .filter-tab-btn .badge-num {
            background: rgba(0,0,0,0.08);
            color: inherit;
            padding: 1px 7px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 900;
        }

        .filter-tab-btn.active .badge-num {
            background: rgba(255,255,255,0.25);
            color: #fff;
        }

        .control-select {
            background: var(--bg-darker);
            color: var(--text-primary);
            border: 1px solid var(--border-color, #CBD5E1);
            padding: 7px 12px;
            border-radius: 12px;
            font-size: 12.5px;
            font-weight: 700;
            outline: none;
            box-shadow: var(--neumorph-inset);
            cursor: pointer;
        }

        .control-select:focus {
            border-color: #2563EB;
        }

        /* View Mode Switcher */
        .view-mode-group {
            display: inline-flex;
            background: var(--bg-darker);
            padding: 3px;
            border-radius: 12px;
            border: 1px solid var(--border-color, #CBD5E1);
            box-shadow: var(--neumorph-inset);
        }

        .view-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 5px 10px;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s ease;
        }

        .view-btn.active {
            background: var(--bg-card);
            color: var(--text-primary);
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        /* Compact Table Mode */
        .table-responsive-wrap {
            background: var(--bg-card);
            border: 1.5px solid var(--border-color, rgba(0,0,0,0.06));
            border-radius: 22px;
            box-shadow: var(--neumorph-flat);
            overflow-x: auto;
            margin-bottom: 20px;
        }

        .station-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
            text-align: left;
        }

        .station-table th:nth-child(1),
        .station-table td:nth-child(1) { width: 8%; }
        .station-table th:nth-child(2),
        .station-table td:nth-child(2) { width: 9%; }
        .station-table th:nth-child(3),
        .station-table td:nth-child(3) { width: 16%; }
        .station-table th:nth-child(4),
        .station-table td:nth-child(4) { width: 7.5%; }
        .station-table th:nth-child(5),
        .station-table td:nth-child(5) { width: 7%; }
        .station-table th:nth-child(6),
        .station-table td:nth-child(6) { width: 11%; }
        .station-table th:nth-child(7),
        .station-table td:nth-child(7) { width: 18%; }
        .station-table th:nth-child(8),
        .station-table td:nth-child(8) { width: 14%; }
        .station-table th:nth-child(9),
        .station-table td:nth-child(9) { width: 9.5%; }

        .station-table td:nth-child(3) > div:first-child,
        .station-table td:nth-child(8) > div:first-child {
            white-space: nowrap;
        }

        .station-table-actions {
            display: grid;
            grid-template-columns: 38px 104px;
            align-items: center;
            justify-content: end;
            gap: 7px;
            width: 100%;
        }

        .station-table-actions .station-map-action {
            width: 38px;
            height: 34px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            font-size: 15px;
            line-height: 1;
        }

        .station-table-actions .station-refer-action {
            width: 104px;
            min-height: 34px;
            padding: 5px 8px !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            white-space: nowrap;
        }

        .station-row-operations {
            width: 104px;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 5px;
        }

        .station-row-operations > button {
            width: 104px;
            min-height: 34px;
            justify-content: center;
            box-sizing: border-box;
        }

        @media (max-width: 1400px) {
            .station-table {
                min-width: 1320px;
            }
        }

        .station-table th {
            box-sizing: border-box;
            background: var(--bg-darker);
            color: var(--text-secondary);
            font-weight: 700;
            padding: 12px 14px;
            border-bottom: 1.5px solid var(--border-color, rgba(0,0,0,0.08));
            white-space: nowrap;
            font-size: 12px;
            letter-spacing: 0.2px;
        }

        .station-table td {
            box-sizing: border-box;
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-color, rgba(0,0,0,0.05));
            color: var(--text-primary);
            vertical-align: middle;
        }

        .station-table tr:hover td {
            background: rgba(37, 99, 235, 0.03);
        }

        .station-table tr.pending-row td {
            background: rgba(220, 38, 38, 0.04);
        }
        .station-table tr.pending-row td:first-child {
            border-left: 4px solid #DC2626;
        }
        .station-table tr.pending-row:hover td {
            background: rgba(220, 38, 38, 0.08);
        }

        .station-table tr.ack-row td {
            background: rgba(245, 158, 11, 0.03);
        }
        .station-table tr.ack-row td:first-child {
            border-left: 4px solid #D97706;
        }
        .station-table tr.ack-row:hover td {
            background: rgba(245, 158, 11, 0.07);
        }

        .station-table tr.referred-row td {
            background: rgba(16, 185, 129, 0.03);
        }
        .station-table tr.referred-row td:first-child {
            border-left: 4px solid #059669;
        }
        .station-table tr.referred-row:hover td {
            background: rgba(16, 185, 129, 0.07);
        }

        /* Pagination Bar */
        .pagination-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
            padding: 14px 18px;
            background: var(--bg-card);
            border: 1.5px solid var(--border-color, rgba(0,0,0,0.06));
            border-radius: 18px;
            box-shadow: var(--neumorph-flat);
        }

        .pagination-pages {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .page-btn {
            min-width: 34px;
            height: 34px;
            padding: 0 8px;
            border-radius: 10px;
            border: 1px solid var(--border-color, #CBD5E1);
            background: var(--bg-darker);
            color: var(--text-primary);
            font-weight: 800;
            font-size: 12.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .page-btn:hover:not(:disabled) {
            background: var(--bg-card);
            border-color: #2563EB;
            color: #2563EB;
            transform: translateY(-1px);
        }

        .page-btn.active {
            background: #2563EB !important;
            color: #ffffff !important;
            border-color: #1D4ED8 !important;
            box-shadow: 0 3px 8px rgba(37, 99, 235, 0.35);
        }

        .page-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

    <!-- Station Top Bar (Hospital Emergency Dispatcher Header) -->
    <header class="station-header">
        <div class="header-left-brand">
            <div id="station-pulsing-dot" class="pulsing-dot"></div>
            <div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <h1 style="margin: 0; font-size: 18px; font-weight: 900; color: var(--text-primary);">
                        NCDs Red Alert Station
                    </h1>
                    <span style="font-size: 10.5px; background: #DC2626; color: white; padding: 2px 8px; border-radius: 6px; font-weight: 800; letter-spacing: 0.5px;">LIVE DISPATCH</span>
                </div>
                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 1px;">
                    ศูนย์รับสัญญาณเคสวิกฤตฉุกเฉินประจำ รพ.สต. • แสดงเหตุใหม่เมื่อเครื่องรับสัญญาณเปิดใช้งานและเชื่อมต่ออินเทอร์เน็ต
                </div>
            </div>
        </div>

        <div class="header-right-tools">
            <!-- 1. Grouped Menu Toolbar (ชุดเมนูเครื่องมือปฏิบัติการ รวมไว้ด้วยกัน) -->
            <div class="station-action-group">
                <!-- Audio Siren Test Button -->
                <button type="button" onclick="testAudio()" id="btn-audio-toggle" class="btn-station-ctrl" title="ทดสอบเสียงไซเรนเตือนภัย">
                    <span class="neu-disc-icon xs disc-blue">🔊</span>
                    <span>ทดสอบเสียงไซเรน</span>
                </button>

                <!-- Test Alert Trigger -->
                <button type="button" onclick="simulateTestAlert()" class="btn-station-ctrl btn-simulate-sos" title="จำลองส่งสัญญาณฉุกเฉินเข้าระบบ">
                    <span class="neu-disc-icon xs disc-red-subtle">⚡</span>
                    <span>จำลองส่งสัญญาณฉุกเฉิน</span>
                </button>

                <button type="button" onclick="openStationDownloadModal()" class="btn-station-ctrl btn-download-app" title="ดาวน์โหลด NCDs Red Alert Station">
                    <span class="neu-disc-icon xs disc-green-subtle">📥</span>
                    <span>ดาวน์โหลด</span>
                </button>

                <!-- Referral Board -->
                <a href="critical_referrals.php" onclick="openOrFocusTab('critical_referrals.php', 'ncd_critical_referrals_tab'); return false;" class="btn-station-ctrl btn-referral-board" title="เปิดบอร์ดส่งต่อเคสวิกฤต รพ.">
                    <span class="neu-disc-icon xs disc-blue-subtle">📋</span>
                    <span>บอร์ดส่งต่อ</span>
                </a>
            </div>

            <div class="header-divider"></div>

            <!-- 2. Developer & System Info Button (ปุ่มดูรายละเอียดระบบและผู้พัฒนา ขนาดเล็ก ไม่โดดเด่น) -->
            <button type="button" id="btn-dev-modal" class="dev-info-subtle-btn" onclick="openDevModal(event)" title="รายละเอียดระบบและผู้พัฒนา (System & Developer Info)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
            </button>

            <!-- 3. Dark/Light Theme Toggle (Icon only, pinned to top-right corner) -->
            <button id="theme-toggle-btn" class="theme-switch-btn" onclick="toggleTheme()" title="สลับโหมด มืด/สว่าง">
                <!-- Sun Icon -->
                <svg id="theme-toggle-sun" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="display: none;">
                    <circle cx="12" cy="12" r="5"></circle>
                    <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path>
                </svg>
                <!-- Moon Icon -->
                <svg id="theme-toggle-moon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"></path>
                </svg>
            </button>
        </div>

        <!-- Compact Streamlined Live Status Bar (fixed with station header) -->
        <div id="status-hero" class="status-hero-bar">
            <div class="status-bar-left">
                <div id="status-icon-container" class="neu-disc-icon xs" style="width: 30px; height: 30px; min-width: 30px; background: radial-gradient(circle at 35% 35%, #34D399 0%, #10B981 70%, #047857 100%); color: #fff; border: 1.5px solid rgba(255, 255, 255, 0.85); box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3); display: flex; align-items: center; justify-content: center;">
                    <svg width="18" height="18" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M24 4 L40 10 V22 C40 32.5 33.2 41.8 24 45 C14.8 41.8 8 32.5 8 22 V10 L24 4 Z" fill="#FFFFFF"/>
                        <path d="M24 8 L36 12.5 V22 C36 30.5 30.8 38 24 40.8 C17.2 38 12 30.5 12 22 V12.5 L24 8 Z" fill="#E6FDF5"/>
                        <path d="M16 23 L22 29 L32 17" stroke="#059669" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="status-bar-text-group">
                    <span id="status-headline" class="status-bar-title">สถานีพร้อมรับสัญญาณฉุกเฉิน (Active Standby)</span>
                    <span id="status-sub" class="status-bar-subtitle">• เชื่อมต่อระบบรับเหตุแล้ว • การตอบสนองขึ้นอยู่กับความพร้อมของเจ้าหน้าที่และหน่วยบริการ</span>
                </div>
            </div>
            <div class="status-bar-right">
                <div class="status-ping-capsule">
                    <div class="pulsing-dot" style="width: 8px; height: 8px;"></div>
                    <span style="color: var(--text-muted);">อัปเดตสตรีมสด:</span>
                    <span id="last-ping-time" style="color: #10B981; font-weight: 800;">เชื่อมต่อแล้ว</span>
                </div>
            </div>
        </div>
    </header>

    <div class="station-header-spacer" aria-hidden="true"></div>

    <!-- Main Live Container -->
    <main class="station-container">

        <!-- ================================================================= -->
        <!-- Smart Control Panel: Fast Search, Filter Pills, View & Pagination -->
        <!-- ================================================================= -->
        <section class="station-control-panel">
            
            <!-- Row 1: KPI Quick Counter Pills (Clickable shortcuts) -->
            <div class="kpi-mini-grid">
                <div class="kpi-mini-card" onclick="setQuickStatus('pending')" id="kpi-card-pending" title="คลิกเพื่อดูกลุ่มรอรับเรื่องด่วน">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="neu-disc-icon xs disc-red" style="width: 32px; height: 32px; font-size: 14px;">🚨</span>
                        <div>
                            <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 800;">รอรับเรื่องด่วน</div>
                            <div id="stat-pending-num" style="font-size: 18px; font-weight: 900; color: #DC2626;">0</div>
                        </div>
                    </div>
                    <span class="tag-pill tag-danger" style="font-size: 11px; padding: 2px 8px;">Pending</span>
                </div>

                <div class="kpi-mini-card" onclick="setQuickStatus('acknowledged')" id="kpi-card-ack" title="คลิกเพื่อดูกลุ่มรับเรื่อง/กำลังดูแล">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="neu-disc-icon xs disc-yellow" style="width: 32px; height: 32px; font-size: 14px;">⏳</span>
                        <div>
                            <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 800;">รับเรื่องแล้ว</div>
                            <div id="stat-ack-num" style="font-size: 18px; font-weight: 900; color: #D97706;">0</div>
                        </div>
                    </div>
                    <span class="tag-pill tag-warning" style="font-size: 11px; padding: 2px 8px;">In Action</span>
                </div>

                <div class="kpi-mini-card" onclick="setQuickStatus('referred_hospital')" id="kpi-card-refer" title="คลิกเพื่อดูกลุ่มสั่งส่งต่อ รพ.">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="neu-disc-icon xs disc-green" style="width: 32px; height: 32px; font-size: 14px;">🏥</span>
                        <div>
                            <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 800;">สั่งส่งต่อ รพ.</div>
                            <div id="stat-refer-num" style="font-size: 18px; font-weight: 900; color: #059669;">0</div>
                        </div>
                    </div>
                    <span class="tag-pill tag-success" style="font-size: 11px; padding: 2px 8px;">Referred</span>
                </div>

                <div class="kpi-mini-card" onclick="setQuickStatus('all')" id="kpi-card-all" title="คลิกเพื่อดูเคสทั้งหมด">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="neu-disc-icon xs disc-blue" style="width: 32px; height: 32px; font-size: 14px;">📊</span>
                        <div>
                            <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 800;">เคสทั้งหมดในสถานี</div>
                            <div id="stat-total-num" style="font-size: 18px; font-weight: 900; color: #2563EB;">0</div>
                        </div>
                    </div>
                    <span class="tag-pill tag-blue" style="font-size: 11px; padding: 2px 8px;">Total</span>
                </div>

                <div class="kpi-mini-card" onclick="setQuickStatus('test')" id="kpi-card-test" title="คลิกเพื่อดูเคสทดสอบ">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="neu-disc-icon xs" style="width: 32px; height: 32px; font-size: 14px; color: #7C3AED;">🧪</span>
                        <div>
                            <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 800;">เคสทดสอบระบบ</div>
                            <div id="stat-test-num" style="font-size: 18px; font-weight: 900; color: #7C3AED;">0</div>
                        </div>
                    </div>
                    <span class="tag-pill tag-purple" style="font-size: 11px; padding: 2px 8px;">Test</span>
                </div>
            </div>

            <!-- Row 2: Search Box & View Mode Toggle -->
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <!-- Real-time Live Search Input -->
                <div class="search-box-wrap">
                    <span class="search-icon-left">🔍</span>
                    <input 
                        type="text" 
                        id="station-search-input" 
                        placeholder="พิมพ์ค้นหา: ชื่อผู้ป่วย, เลข CID, เบอร์โทร, หมู่บ้าน, อสม. หรืออาการวิกฤต..." 
                        oninput="handleSearchInput(this.value)"
                        autocomplete="off"
                    >
                    <button type="button" id="search-clear-btn" class="search-clear-btn" onclick="clearSearch()" title="ล้างข้อความค้นหา">✕</button>
                </div>

                <!-- View Switcher -->
                <div class="view-mode-group">
                    <button type="button" id="view-btn-card" class="view-btn active" onclick="switchViewMode('card')" title="มุมมองการ์ดละเอียด">
                        <span>🎴 การ์ด</span>
                    </button>
                    <button type="button" id="view-btn-table" class="view-btn" onclick="switchViewMode('table')" title="มุมมองตารางสรุปด่วน (ดูได้เยอะ ไม่ต้อง scroll ไกล)">
                        <span>📋 ตารางด่วน</span>
                    </button>
                </div>

                <!-- Page Size Selector -->
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="font-size: 12px; color: var(--text-muted); font-weight: 700; white-space: nowrap;">แสดง:</span>
                    <select id="select-page-size" class="control-select" onchange="changePageSize(this.value)">
                        <option value="6">6 เคส/หน้า</option>
                        <option value="12" selected>12 เคส/หน้า</option>
                        <option value="24">24 เคส/หน้า</option>
                        <option value="50">50 เคส/หน้า</option>
                        <option value="all">ทั้งหมด</option>
                    </select>
                </div>
            </div>

            <!-- Row 3: Status Filter Pills + Secondary Filters -->
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                <!-- Secondary Filters: Crisis Type, Sub-district (ตำบล), Village (หมู่บ้าน), Sort Order -->
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <!-- Health Center Selector -->
                    <select id="select-hoscode" onchange="changeHoscode(this.value)" class="control-select" title="เลือกรหัส รพ.สต. หรือดูภาพรวม">
                        <option value="ALL">ทุก รพ.สต. (ภาพรวมอำเภอ)</option>
                        <?php foreach ($hc_names as $code => $name): ?>
                            <option value="<?= $code ?>" <?= $selected_hoscode == $code ? 'selected' : '' ?>>
                                [<?= $code ?>] <?= $name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Crisis Type Dropdown -->
                    <select id="select-crisis-type" class="control-select" onchange="changeCrisisTypeFilter(this.value)" title="กรองตามภาวะวิกฤต">
                        <option value="all">ทุกภาวะวิกฤต</option>
                        <option value="ht">🩺 ความดันสูงวิกฤต (HT Crisis)</option>
                        <option value="dtx">🩸 น้ำตาลวิกฤต (DTX Crisis)</option>
                        <option value="red_flags">⚠️ มีอาการเตือน (Red Flags)</option>
                    </select>

                    <!-- Sub-district (ตำบล) Filter Dropdown (ตรวจจับตำบลในเขตอำเภอ) -->
                    <select id="select-subdistrict" class="control-select" onchange="changeSubDistrictFilter(this.value)" title="ตรวจจับและกรองตามตำบลในเขตอำเภอ">
                        <option value="all">ทุกตำบล (ทุกเขต)</option>
                        <?php foreach ($sub_districts as $sd): ?>
                            <option value="<?= htmlspecialchars($sd['sub_district_code']) ?>">
                                ตำบล<?= htmlspecialchars($sd['sub_district_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Dynamic Village (หมู่บ้าน) Filter Dropdown (ดึงรายชื่อหมู่บ้านที่ตรงตามตำบล) -->
                    <select id="select-village" class="control-select" onchange="changeVillageFilter(this.value)" title="ดึงรายชื่อหมู่บ้านที่ตรงตามตำบล">
                        <option value="all">ทุกหมู่บ้าน (ทุก ม.)</option>
                    </select>

                    <!-- Sort Dropdown -->
                    <select id="select-sort-by" class="control-select" onchange="changeSortBy(this.value)" title="จัดเรียงลำดับเคส">
                        <option value="newest">🕒 ล่าสุดไปเก่าสุด</option>
                        <option value="oldest">⏳ เก่าสุดไปล่าสุด</option>
                        <option value="bp_desc">🩺 ความดันสูงสุด (SBP สูง)</option>
                        <option value="dtx_desc">🩸 น้ำตาลสูงสุด (DTX สูง)</option>
                    </select>

                    <!-- Reset Filters Button -->
                    <button type="button" onclick="resetAllFilters()" class="btn-station-ctrl" style="padding: 6px 12px; font-size: 12px;" title="ล้างการค้นหาและตัวกรองทั้งหมด">
                        <span>🔄 รีเซ็ต</span>
                    </button>
                </div>
            </div>
        </section>

        <!-- Section: Active Feeds Header with Results Counter -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 800; display: flex; align-items: center; gap: 10px; color: var(--text-primary);">
                <span class="neu-disc-icon xs disc-blue">📡</span> 
                <span>รายการเคสวิกฤตที่ส่งเข้ามา (Live Emergency Feeds)</span>
            </h3>
            <div style="display: flex; align-items: center; gap: 8px;">
                <span id="results-count-text" style="font-size: 13px; color: var(--text-secondary); font-weight: 700;">กำลังโหลดข้อมูล...</span>
                <span id="active-count-badge" class="tag-pill tag-success">0 เคสรอรับเรื่อง</span>
            </div>
        </div>

        <!-- Main Display Container (Card Grid or Compact Table) -->
        <div id="alert-display-root">
            <div id="alert-cards-container" class="alert-card-grid">
                <!-- Initial Loading / Empty State -->
                <div style="grid-column: 1/-1; text-align: center; padding: 48px 20px; background: var(--bg-card); border-radius: 24px; box-shadow: var(--neumorph-flat); border: 1px dashed var(--border-color, #CBD5E1);">
                    <div style="display: flex; justify-content: center; margin-bottom: 12px;">
                        <div class="neu-disc-icon lg disc-green" style="width: 60px; height: 60px; font-size: 28px;">
                            🛡️
                        </div>
                    </div>
                    <div style="font-size: 16px; font-weight: 800; color: var(--text-primary);">กำลังเชื่อมต่อศูนย์รับสัญญาณฉุกเฉิน...</div>
                    <div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">เมื่อ อสม. คัดกรองพบเคสวิกฤต สัญญาณและข้อมูลจะเด้งขึ้นมาพร้อมเสียงเตือนทันที</div>
                </div>
            </div>
        </div>

        <!-- Pagination Controls Bar -->
        <div id="pagination-wrapper" class="pagination-container" style="display: none;">
            <div style="font-size: 13px; color: var(--text-secondary); font-weight: 700;">
                <span id="pagination-info-text">แสดงเคสที่ 1 - 12 จากทั้งหมด 0 รายการ</span>
            </div>
            <div class="pagination-pages" id="pagination-buttons">
                <!-- Dynamically rendered -->
            </div>
        </div>
    </main>

    <!-- Discreet Bottom Station Footer (คลิกดูเวอร์ชันและผู้พัฒนา) -->
    <footer style="margin-top: auto; padding: 18px 24px 24px 24px; text-align: center; font-size: 11.5px; color: var(--text-muted); opacity: 0.75; display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap;">
        <span>NCDs Red Alert Station • ระบบสนับสนุนการแจ้งและติดตามเหตุ ไม่ทดแทนการโทร 1669</span>
        <span>•</span>
        <button type="button" onclick="openDevModal(event)" style="background: none; border: none; padding: 0; color: var(--text-muted); font-size: 11.5px; cursor: pointer; text-decoration: underline; opacity: 0.85; transition: color 0.2s ease;" onmouseover="this.style.color='#2563EB'" onmouseout="this.style.color='var(--text-muted)'" title="คลิกดูรายละเอียดระบบและทีมพัฒนา">
            v3.0 (Build Info & Developer)
        </button>
    </footer>

    <!-- Fullscreen Emergency Pop-up Modal (Modern Clean Glassmorphism) -->
    <div id="emergency-popup-overlay" onclick="closeEmergencyPopupWithoutAction()">
        <div class="emergency-modal-card" onclick="event.stopPropagation()">
            <!-- Top Right Close Button -->
            <button type="button" onclick="closeEmergencyPopupWithoutAction()" class="modal-corner-close-btn" aria-label="ปิดหน้าต่าง" title="ปิดการแจ้งเตือนไว้ก่อน (ยังไม่ได้รับเคส)">
                ✕
            </button>

            <div style="display: flex; justify-content: center; margin-bottom: 6px;">
                <div class="neu-disc-icon" style="width: 62px; height: 62px; min-width: 62px; background: radial-gradient(circle at 35% 35%, #EF4444 0%, #DC2626 70%, #991B1B 100%); color: #fff; border: 0; box-shadow: 0 8px 20px rgba(220,38,38,0.34); display: flex; align-items: center; justify-content: center; animation: emergencyBeaconPulse 1.8s infinite;">
                    <svg width="46" height="46" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: block;">
                        <line x1="24" y1="3" x2="24" y2="8" stroke="#FFFFFF" stroke-width="3.5" stroke-linecap="round"/>
                        <line x1="10" y1="8" x2="14" y2="12" stroke="#FFFFFF" stroke-width="3.5" stroke-linecap="round"/>
                        <line x1="38" y1="8" x2="34" y2="12" stroke="#FFFFFF" stroke-width="3.5" stroke-linecap="round"/>
                        <line x1="3" y1="22" x2="8" y2="22" stroke="#FFFFFF" stroke-width="3.5" stroke-linecap="round"/>
                        <line x1="45" y1="22" x2="40" y2="22" stroke="#FFFFFF" stroke-width="3.5" stroke-linecap="round"/>
                        <path d="M14 26 C14 15, 34 15, 34 26 Z" fill="#FFFFFF"/>
                        <path d="M28 18 C31 20, 32 23, 31 25" stroke="rgba(220, 38, 38, 0.65)" stroke-width="2.2" stroke-linecap="round" fill="none"/>
                        <rect x="10" y="26" width="28" height="5" rx="2.5" fill="#1E293B"/>
                        <text x="24" y="42" text-anchor="middle" font-family="'Outfit', 'Sarabun', sans-serif" font-weight="900" font-size="11.5" fill="#FFFFFF" letter-spacing="1.2">SOS</text>
                    </svg>
                </div>
            </div>

            <h2 id="modal-patient-name" style="margin: 6px 0 10px 0; font-size: 25px; line-height: 1.25; font-weight: 900; color: #DC2626;">
                คุณ... (อายุ ... ปี)
            </h2>

            <div class="modal-summary-grid">
                <div class="modal-summary-item">
                    <span class="modal-summary-label">รหัสเคส</span>
                    <span id="modal-case-id" class="modal-summary-value">-</span>
                </div>
                <div class="modal-summary-item">
                    <span class="modal-summary-label">เลขประจำตัวเป้าหมาย (CID)</span>
                    <span id="modal-cid" class="modal-summary-value">-</span>
                </div>
                <div class="modal-summary-item">
                    <span class="modal-summary-label">สถานะเคส</span>
                    <span id="modal-status" class="modal-summary-value">-</span>
                </div>
            </div>
            
            <div style="background: var(--bg-darker); border-radius: 18px; padding: 13px 16px; margin-bottom: 12px; border: none; text-align: left; box-shadow: var(--neumorph-inset);">
                <!-- Time of Alert Header in Modal -->
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px dashed var(--border-color, rgba(0,0,0,0.1));">
                    <span style="font-size: 13.5px; color: var(--text-muted); font-weight: 700;">วันและเวลาที่แจ้ง:</span>
                    <span id="modal-alert-time" style="font-size: 14.5px; font-weight: 900; color: #DC2626; background: rgba(220, 38, 38, 0.12); padding: 5px 12px; border-radius: 9px;">-</span>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div style="background: var(--bg-card); padding: 10px 12px; border-radius: 14px; box-shadow: var(--neumorph-flat);">
                        <div style="font-size: 13px; color: var(--text-muted); font-weight: 700; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                            <span class="neu-disc-icon xs disc-red" style="width: 22px; height: 22px; font-size: 11px;">🩺</span>
                            <span>ความดันโลหิต</span>
                        </div>
                        <div id="modal-bp-val" style="font-size: 28px; font-weight: 900; color: #DC2626;">-</div>
                    </div>
                    <div style="background: var(--bg-card); padding: 10px 12px; border-radius: 14px; box-shadow: var(--neumorph-flat);">
                        <div style="font-size: 13px; color: var(--text-muted); font-weight: 700; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                            <span class="neu-disc-icon xs disc-yellow" style="width: 22px; height: 22px; font-size: 11px;">🩸</span>
                            <span>น้ำตาล DTX</span>
                        </div>
                        <div id="modal-dtx-val" style="font-size: 28px; font-weight: 900; color: #D97706;">-</div>
                    </div>
                </div>
                <div style="font-size: 15.5px; line-height: 1.5; margin-bottom: 8px; color: var(--text-primary); display: flex; align-items: flex-start; gap: 9px;">
                    <span class="neu-disc-icon xs disc-blue" style="width: 22px; height: 22px; font-size: 11px;">📍</span>
                    <div><strong>ที่อยู่:</strong> <span id="modal-address">-</span></div>
                </div>
                <div style="font-size: 15.5px; line-height: 1.5; margin-bottom: 8px; color: var(--text-primary); display: flex; align-items: flex-start; gap: 9px;">
                    <span class="neu-disc-icon xs disc-red" style="width: 22px; height: 22px; font-size: 11px;">⚠️</span>
                    <div><strong>ภาวะวิกฤต:</strong> <span id="modal-crisis-type" style="color: #DC2626; font-weight: 800;">-</span></div>
                </div>
                <div style="font-size: 15.5px; line-height: 1.5; color: var(--text-secondary); margin-bottom: 10px; display: flex; align-items: flex-start; gap: 9px;">
                    <span class="neu-disc-icon xs disc-green" style="width: 22px; height: 22px; font-size: 11px;">👩‍⚕️</span>
                    <div><strong>อสม. ผู้แจ้ง:</strong> <span id="modal-vhv-info">-</span></div>
                </div>

                <!-- Prominent Callback Phone Box -->
                <div style="background: rgba(16, 185, 129, 0.12); border: 1.5px solid #10B981; border-radius: 14px; padding: 10px 14px; display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 8px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="neu-disc-icon sm disc-green" style="font-size: 15px;">📱</span>
                        <div>
                            <div style="font-size: 12.5px; color: #059669; font-weight: 800;">เบอร์โทรติดต่อกลับด่วน:</div>
                            <div style="font-size: 19px; font-weight: 900; color: var(--text-primary); letter-spacing: 0.5px;">
                                <span id="modal-contact-phone">-</span> <span id="modal-contact-type" style="font-size: 11.5px; font-weight: normal; color: var(--text-secondary);"></span>
                            </div>
                        </div>
                    </div>
                    <a id="modal-btn-call" href="#" style="background: #10B981; color: white; text-decoration: none; padding: 8px 16px; border-radius: 10px; font-size: 13px; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);">
                        <span>📞 โทรทันที</span>
                    </a>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="emergency-modal-actions">
                <?php if ($is_main_admin): ?>
                <button type="button" id="modal-btn-delete" onclick="deleteCurrentAlertFromModal()" class="emergency-modal-action" style="background: #FEF2F2; color: #DC2626; border: 1.5px solid rgba(220,38,38,0.3); box-shadow: var(--neumorph-flat);" title="ลบเคสนี้ออกจากระบบ (สิทธิ์เฉพาะแอดมินหลัก)">
                    <span class="neu-disc-icon xs" style="color: #DC2626; background: rgba(220,38,38,0.1);">🗑️</span>
                    <span>ลบเคส</span>
                </button>
                <?php endif; ?>
                <button type="button" id="btn-modal-close" onclick="closeEmergencyPopupWithoutAction()" class="emergency-modal-action" style="background: var(--bg-card); color: var(--text-secondary); border: 1.5px solid var(--border-color, #CBD5E1); box-shadow: var(--neumorph-flat);" title="ปิดหน้าต่างการแจ้งเตือนไว้ก่อน เคสยังคงอยู่ในสถานะรอรับเรื่อง">
                    <span class="neu-disc-icon xs" style="color: #64748B;">✕</span>
                    <span>ปิดไว้ก่อน</span>
                </button>
                <button type="button" id="btn-modal-ack" onclick="acknowledgeCurrentAlert()" class="btn-action-glow emergency-modal-action" title="กดรับทราบเคสและส่งสัญญาณยืนยัน">
                    <span class="neu-disc-icon xs" style="background: rgba(255,255,255,0.2); color: #fff; border-color: rgba(255,255,255,0.4); box-shadow: none;">🔕</span>
                    <span>รับทราบเคส</span>
                </button>
                <a id="modal-btn-map" href="#" target="_blank" class="emergency-modal-action" style="background: #2563EB; color: white; text-decoration: none; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);" title="เปิดพิกัดแผนที่บน Google Maps">
                    <span class="neu-disc-icon xs">📍</span>
                    <span>เปิดแผนที่ GPS</span>
                </a>
                <a id="modal-btn-refer" href="critical_referrals.php" onclick="openOrFocusTab(this.href, 'ncd_critical_referrals_tab'); return false;" class="emergency-modal-action" style="background: #10B981; color: white; text-decoration: none; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);" title="เปิดหน้าส่งต่อผู้ป่วยเข้า รพ.">
                    <span class="neu-disc-icon xs">🏥</span>
                    <span>ส่งต่อ รพ.</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Web Audio Siren Synthesizer, State Manager & SSE / Polling Client -->
    <script>
        const IS_MAIN_ADMIN = <?= $is_main_admin ? 'true' : 'false' ?>;
        const ALL_SUBDISTRICTS = <?= json_encode($sub_districts, JSON_UNESCAPED_UNICODE) ?>;
        const ALL_VILLAGES = <?= json_encode($villages_data, JSON_UNESCAPED_UNICODE) ?>;
        let currentHoscode = '<?= htmlspecialchars($selected_hoscode) ?>';
        let audioCtx = null;
        let sirenOscillator = null;
        let isSirenPlaying = false;
        let activeCrisisAlertId = null;
        let modalOpenedManually = false;

        // ----------------------------------------------------
        // Station State Management (Search, Filters, Pagination, View)
        // ----------------------------------------------------
        // Load previously dismissed alert IDs in this session
        let initialDismissedAlertIds = new Set();
        try {
            const savedDismissed = JSON.parse(sessionStorage.getItem('ncd_dismissed_alert_ids') || '[]');
            if (Array.isArray(savedDismissed)) initialDismissedAlertIds = new Set(savedDismissed.map(String));
        } catch(e) {}

        const stationState = {
            allAlerts: [],
            filteredAlerts: [],
            searchQuery: '',
            statusFilter: 'all',
            crisisTypeFilter: 'all',
            subDistrictFilter: 'all',
            villageFilter: 'all',
            sortBy: 'newest',
            viewMode: localStorage.getItem('red_alert_view_mode') || 'card',
            currentPage: 1,
            pageSize: 12,
            knownPendingAlertIds: new Set(),
            dismissedAlertIds: initialDismissedAlertIds
        };

        // ----------------------------------------------------
        // Cross-Tab Navigator & Smart Focus Manager
        // ----------------------------------------------------
        const MY_TAB_NAME = "ncd_red_alert_station_tab";
        window.name = MY_TAB_NAME;

        function openOrFocusTab(url, targetTabName) {
            try {
                const bc = new BroadcastChannel('ncd_tab_channel');
                bc.postMessage({
                    action: 'focus_and_navigate',
                    target: targetTabName,
                    url: url,
                    timestamp: Date.now()
                });
            } catch(e) {}

            try {
                localStorage.setItem('ncd_focus_tab_signal', JSON.stringify({
                    target: targetTabName,
                    url: url,
                    timestamp: Date.now()
                }));
            } catch(e) {}

            const targetWin = window.open(url, targetTabName);
            if (targetWin) {
                try {
                    targetWin.focus();
                } catch(e) {}
            }
        }

        // Setup incoming cross-tab focus listener
        (function setupCrossTabFocusListener() {
            function handleTabFocus(data) {
                if (!data || data.target !== MY_TAB_NAME) return;
                try {
                    window.focus();
                } catch(e) {}

                if (data.url) {
                    const currentUrl = window.location.href;
                    const targetUrl = new URL(data.url, window.location.origin).href;
                    if (currentUrl !== targetUrl && data.url.indexOf('emergency_receiver.php') !== -1) {
                        window.location.href = data.url;
                    }
                }

                const originalTitle = document.title.replace(/⚡ \[สลับมาแท็บนี้\] /g, '');
                document.title = "⚡ [สลับมาแท็บนี้] " + originalTitle;
                setTimeout(() => {
                    document.title = originalTitle;
                }, 2500);
            }

            try {
                const bc = new BroadcastChannel('ncd_tab_channel');
                bc.onmessage = (event) => {
                    if (event.data && event.data.action === 'focus_and_navigate') {
                        handleTabFocus(event.data);
                    }
                };
            } catch(e) {}

            window.addEventListener('storage', (e) => {
                if (e.key === 'ncd_focus_tab_signal' && e.newValue) {
                    try {
                        const payload = JSON.parse(e.newValue);
                        if (Date.now() - payload.timestamp < 3000) {
                            handleTabFocus(payload);
                        }
                    } catch(err) {}
                }
            });
        })();

        // Formats alert timestamp to prominent Thai display with elapsed time indicator
        function formatAlertTimeThai(dateStr) {
            if (!dateStr) return { fullTime: '-', timeAgo: '', dateText: '' };
            const cleanStr = dateStr.replace(/-/g, '/');
            const alertDate = new Date(cleanStr);
            const now = new Date();
            const diffSec = Math.max(0, Math.floor((now - alertDate) / 1000));
            
            const hours = alertDate.getHours().toString().padStart(2, '0');
            const mins = alertDate.getMinutes().toString().padStart(2, '0');
            const timeFormatted = `${hours}:${mins} น.`;
            const day = alertDate.getDate().toString().padStart(2, '0');
            const month = (alertDate.getMonth() + 1).toString().padStart(2, '0');
            const buddhistYear = alertDate.getFullYear() + 543;
            const dateFormatted = `${day}/${month}/${buddhistYear}`;

            // Calculate calendar day difference
            const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
            const startOfAlertDay = new Date(alertDate.getFullYear(), alertDate.getMonth(), alertDate.getDate()).getTime();
            const dayDiff = Math.max(0, Math.floor((startOfToday - startOfAlertDay) / (86400 * 1000)));

            let timeAgo = '';
            if (dayDiff === 0) {
                // อยู่ในวันเดียวกัน (วันนี้) -> ระบุเป็นนาทีหรือชั่วโมง
                if (diffSec < 45) {
                    timeAgo = 'เมื่อสักครู่';
                } else if (diffSec < 3600) {
                    timeAgo = `${Math.floor(diffSec / 60)} นาทีที่แล้ว`;
                } else {
                    timeAgo = `${Math.floor(diffSec / 3600)} ชม. ที่แล้ว`;
                }
            } else if (dayDiff === 1) {
                // เมื่อวาน
                timeAgo = '1 วันที่แล้ว';
            } else {
                // เกิน 1 วัน -> ระบุกี่วันที่แล้ว
                timeAgo = `${dayDiff} วันที่แล้ว`;
            }

            return {
                fullTime: timeFormatted,
                timeAgo: timeAgo,
                dateText: dateFormatted,
                dayDiff: dayDiff
            };
        }

        // Theme Toggle Functionality (Synced with main navbar)
        function updateThemeButtonUI(theme) {
            const sun = document.getElementById('theme-toggle-sun');
            const moon = document.getElementById('theme-toggle-moon');
            if (!sun || !moon) return;
            if (theme === 'dark') {
                sun.style.display = 'block';
                moon.style.display = 'none';
            } else {
                sun.style.display = 'none';
                moon.style.display = 'block';
            }
        }

        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme') || 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            updateThemeButtonUI(next);
        }

        // Web Audio Siren Synthesizer
        function startSirenSound() {
            if (isSirenPlaying) return;
            try {
                if (!audioCtx) {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                if (audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }

                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();

                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(800, audioCtx.currentTime);
                
                const now = audioCtx.currentTime;
                const sirenRounds = 2;
                for (let i = 0; i < sirenRounds; i++) {
                    osc.frequency.linearRampToValueAtTime(1300, now + (i * 0.8) + 0.4);
                    osc.frequency.linearRampToValueAtTime(750, now + (i * 0.8) + 0.8);
                }

                gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
                gain.gain.setValueAtTime(0.3, now + (sirenRounds * 0.8) - 0.12);
                gain.gain.linearRampToValueAtTime(0.001, now + (sirenRounds * 0.8));
                osc.connect(gain);
                gain.connect(audioCtx.destination);

                osc.start();
                osc.stop(now + (sirenRounds * 0.8));
                sirenOscillator = osc;
                isSirenPlaying = true;
                osc.onended = () => {
                    if (sirenOscillator === osc) sirenOscillator = null;
                    isSirenPlaying = false;
                    const testButton = document.getElementById('btn-audio-toggle');
                    if (testButton) testButton.innerHTML = '<span class="neu-disc-icon xs disc-blue">🔊</span><span>ทดสอบเสียงไซเรน</span>';
                };
            } catch (e) {
                console.warn('Audio play prevented:', e);
            }
        }

        function stopSirenSound() {
            if (sirenOscillator) {
                try {
                    sirenOscillator.stop();
                    sirenOscillator.disconnect();
                } catch(e) {}
                sirenOscillator = null;
            }
            isSirenPlaying = false;
        }

        function testAudio() {
            if (isSirenPlaying) {
                stopSirenSound();
                document.getElementById('btn-audio-toggle').innerHTML = '<span class="neu-disc-icon xs disc-blue">🔊</span><span>ทดสอบเสียงไซเรน</span>';
            } else {
                startSirenSound();
                document.getElementById('btn-audio-toggle').innerHTML = '<span class="neu-disc-icon xs disc-red">⏹️</span><span>หยุดเสียงทดสอบ</span>';
                setTimeout(() => {
                    if (isSirenPlaying && !activeCrisisAlertId) {
                        stopSirenSound();
                        document.getElementById('btn-audio-toggle').innerHTML = '<span class="neu-disc-icon xs disc-blue">🔊</span><span>ทดสอบเสียงไซเรน</span>';
                    }
                }, 3000);
            }
        }

        // Change Hoscode
        function changeHoscode(val) {
            window.location.href = `emergency_receiver.php?hoscode=${val}`;
        }

        // ----------------------------------------------------
        // Search & Filter Controllers
        // ----------------------------------------------------
        function handleSearchInput(val) {
            stationState.searchQuery = (val || '').trim().toLowerCase();
            const clearBtn = document.getElementById('search-clear-btn');
            if (clearBtn) {
                clearBtn.style.display = stationState.searchQuery ? 'flex' : 'none';
            }
            stationState.currentPage = 1;
            applyFiltersAndRender();
        }

        function clearSearch() {
            const input = document.getElementById('station-search-input');
            if (input) input.value = '';
            handleSearchInput('');
        }

        function filterByStatus(status) {
            stationState.statusFilter = status;
            stationState.currentPage = 1;
            
            // Update active state in UI tabs
            document.querySelectorAll('.filter-tab-btn').forEach(btn => btn.classList.remove('active'));
            const activeTab = document.getElementById(`tab-status-${status === 'referred_hospital' ? 'refer' : (status === 'acknowledged' ? 'ack' : status)}`);
            if (activeTab) activeTab.classList.add('active');

            // Update KPI mini-cards active state
            document.querySelectorAll('.kpi-mini-card').forEach(c => c.classList.remove('active-kpi'));
            const activeKpi = document.getElementById(`kpi-card-${status === 'referred_hospital' ? 'refer' : (status === 'acknowledged' ? 'ack' : status)}`);
            if (activeKpi) activeKpi.classList.add('active-kpi');

            applyFiltersAndRender();
        }

        function setQuickStatus(status) {
            filterByStatus(status);
        }

        function changeCrisisTypeFilter(val) {
            stationState.crisisTypeFilter = val;
            stationState.currentPage = 1;
            applyFiltersAndRender();
        }

        // Populates the Village dropdown based on detected / selected Sub-district (ตำบล)
        function populateVillageDropdown(subDistrictCode) {
            const selectVill = document.getElementById('select-village');
            if (!selectVill) return;

            const prevVal = stationState.villageFilter;
            selectVill.innerHTML = '';

            if (!subDistrictCode || subDistrictCode === 'all') {
                let html = '<option value="all">หมู่บ้านทั้งหมด</option>';
                
                // Group ALL_VILLAGES by sub_district_code
                const groups = {};
                ALL_VILLAGES.forEach(v => {
                    const sCode = v.sub_district_code || 'other';
                    if (!groups[sCode]) {
                        groups[sCode] = {
                            name: v.sub_district_name || 'อื่นๆ',
                            villages: []
                        };
                    }
                    groups[sCode].villages.push(v);
                });

                Object.keys(groups).forEach(sCode => {
                    const g = groups[sCode];
                    html += `<optgroup label="📍 ตำบล${g.name}">`;
                    g.villages.forEach(v => {
                        const valKey = `${v.sub_district_code || ''}_${v.moo}`;
                        const isSelected = (valKey === prevVal || String(v.moo) === prevVal) ? 'selected' : '';
                        html += `<option value="${valKey}" data-sub="${v.sub_district_code || ''}" data-moo="${v.moo}" ${isSelected}>หมู่ที่ ${v.moo} บ้าน${v.village_name}</option>`;
                    });
                    html += `</optgroup>`;
                });
                selectVill.innerHTML = html;
            } else {
                const subObj = ALL_SUBDISTRICTS.find(s => String(s.sub_district_code) === String(subDistrictCode));
                const subName = subObj ? subObj.sub_district_name : '';
                const vills = ALL_VILLAGES.filter(v => String(v.sub_district_code) === String(subDistrictCode));

                let html = `<option value="all">ทุกหมู่บ้านในตำบล${subName || subDistrictCode}</option>`;
                vills.forEach(v => {
                    const valKey = `${v.sub_district_code || ''}_${v.moo}`;
                    const isSelected = (valKey === prevVal || String(v.moo) === prevVal) ? 'selected' : '';
                    html += `<option value="${valKey}" data-sub="${v.sub_district_code || ''}" data-moo="${v.moo}" ${isSelected}>หมู่ที่ ${v.moo} บ้าน${v.village_name}</option>`;
                });
                selectVill.innerHTML = html;
            }
        }

        function changeSubDistrictFilter(val) {
            stationState.subDistrictFilter = val;
            stationState.villageFilter = 'all';
            populateVillageDropdown(val);
            stationState.currentPage = 1;
            applyFiltersAndRender();
        }

        function changeVillageFilter(val) {
            stationState.villageFilter = val;
            stationState.currentPage = 1;
            applyFiltersAndRender();
        }

        // Resolves village and sub-district metadata for an alert item
        function getAlertVillageInfo(a) {
            if (!a) return { villageName: '', subDistrictName: '', hosname: '' };
            let v = null;
            // 1. Match by sub_district_code + moo
            if (a.sub_district_code && a.moo) {
                v = ALL_VILLAGES.find(item => String(item.sub_district_code) === String(a.sub_district_code) && String(item.moo) === String(a.moo));
            }
            // 2. Match by hoscode + moo
            if (!v && a.hoscode && a.moo) {
                v = ALL_VILLAGES.find(item => String(item.hoscode) === String(a.hoscode) && String(item.moo) === String(a.moo));
            }
            // 3. Fallback match by moo only if unambiguous
            if (!v && a.moo) {
                const candidates = ALL_VILLAGES.filter(item => String(item.moo) === String(a.moo));
                if (candidates.length === 1) {
                    v = candidates[0];
                }
            }

            return {
                villageName: v ? v.village_name : '',
                subDistrictName: v ? v.sub_district_name : '',
                hosname: v ? v.hosname : ''
            };
        }

        function changeSortBy(val) {
            stationState.sortBy = val;
            stationState.currentPage = 1;
            applyFiltersAndRender();
        }

        function changePageSize(val) {
            stationState.pageSize = val === 'all' ? 9999 : parseInt(val, 10);
            stationState.currentPage = 1;
            applyFiltersAndRender();
        }

        function switchViewMode(mode) {
            stationState.viewMode = mode;
            localStorage.setItem('red_alert_view_mode', mode);
            
            document.getElementById('view-btn-card').classList.toggle('active', mode === 'card');
            document.getElementById('view-btn-table').classList.toggle('active', mode === 'table');
            
            applyFiltersAndRender();
        }

        function resetAllFilters() {
            stationState.searchQuery = '';
            stationState.statusFilter = 'all';
            stationState.crisisTypeFilter = 'all';
            stationState.subDistrictFilter = 'all';
            stationState.villageFilter = 'all';
            stationState.sortBy = 'newest';
            stationState.currentPage = 1;

            const searchInput = document.getElementById('station-search-input');
            if (searchInput) searchInput.value = '';
            const clearBtn = document.getElementById('search-clear-btn');
            if (clearBtn) clearBtn.style.display = 'none';

            if (document.getElementById('select-crisis-type')) document.getElementById('select-crisis-type').value = 'all';
            if (document.getElementById('select-subdistrict')) document.getElementById('select-subdistrict').value = 'all';
            populateVillageDropdown('all');
            if (document.getElementById('select-village')) document.getElementById('select-village').value = 'all';
            if (document.getElementById('select-sort-by')) document.getElementById('select-sort-by').value = 'newest';

            filterByStatus('all');
        }

        function changePage(pageNum) {
            stationState.currentPage = pageNum;
            applyFiltersAndRender();
            
            // Scroll smoothly to top of alert list
            const root = document.getElementById('alert-display-root');
            if (root) {
                root.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        // ----------------------------------------------------
        // Filter, Sort & Render Engine
        // ----------------------------------------------------
        function applyFiltersAndRender() {
            const raw = stationState.allAlerts || [];
            
            // 1. Calculate Global Summary Statistics
            const totalCount = raw.length;
            const pendingCount = raw.filter(a => a.alert_status === 'pending').length;
            const ackCount = raw.filter(a => a.alert_status === 'acknowledged' || a.alert_status === 'dispatched').length;
            const referCount = raw.filter(a => a.alert_status === 'referred_hospital' || a.is_jhcis_synced == 1).length;
            const testCount = raw.filter(a => (a.patient_name && a.patient_name.includes('ทดสอบ')) || (a.vhv_name && a.vhv_name.includes('ทดสอบ'))).length;

            // Update KPI Pills
            document.getElementById('stat-total-num').innerText = totalCount;
            document.getElementById('stat-pending-num').innerText = pendingCount;
            document.getElementById('stat-ack-num').innerText = ackCount;
            document.getElementById('stat-refer-num').innerText = referCount;
            document.getElementById('stat-test-num').innerText = testCount;

            // Update Top Active Badge
            const activeBadge = document.getElementById('active-count-badge');
            activeBadge.innerText = `${pendingCount} เคสรอรับเรื่อง`;
            activeBadge.className = pendingCount > 0 ? 'tag-pill tag-danger' : 'tag-pill tag-success';

            // 2. Filter Alerts
            let filtered = raw.filter(a => {
                // Status filter
                if (stationState.statusFilter === 'pending' && a.alert_status !== 'pending') return false;
                if (stationState.statusFilter === 'acknowledged' && a.alert_status !== 'acknowledged' && a.alert_status !== 'dispatched') return false;
                if (stationState.statusFilter === 'referred_hospital' && a.alert_status !== 'referred_hospital' && a.is_jhcis_synced != 1) return false;
                if (stationState.statusFilter === 'test') {
                    const isTest = (a.patient_name && a.patient_name.includes('ทดสอบ')) || (a.vhv_name && a.vhv_name.includes('ทดสอบ'));
                    if (!isTest) return false;
                }

                // Crisis Type filter
                if (stationState.crisisTypeFilter === 'ht') {
                    if (!(parseInt(a.sbp || 0, 10) >= 180 || (a.crisis_type && a.crisis_type.toLowerCase().includes('ht')))) return false;
                } else if (stationState.crisisTypeFilter === 'dtx') {
                    if (!(parseInt(a.dtx || 0, 10) >= 300 || parseInt(a.dtx || 0, 10) <= 70 || (a.crisis_type && a.crisis_type.toLowerCase().includes('dtx')))) return false;
                } else if (stationState.crisisTypeFilter === 'red_flags') {
                    if (!a.red_flags || a.red_flags === 'NONE' || a.red_flags === '-' || a.red_flags === '') return false;
                }

                // Sub-district (ตำบล) filter
                if (stationState.subDistrictFilter !== 'all') {
                    const alertSub = a.sub_district_code || (ALL_VILLAGES.find(v => v.hoscode === a.hoscode && String(v.moo) === String(a.moo)) || {}).sub_district_code;
                    if (String(alertSub) !== String(stationState.subDistrictFilter)) {
                        return false;
                    }
                }

                // Village (หมู่บ้าน) filter
                if (stationState.villageFilter !== 'all') {
                    const parts = stationState.villageFilter.split('_');
                    if (parts.length === 2) {
                        const targetSub = parts[0];
                        const targetMoo = parts[1];
                        if (String(a.moo) !== String(targetMoo)) return false;
                        if (targetSub) {
                            const alertSub = a.sub_district_code || (ALL_VILLAGES.find(v => v.hoscode === a.hoscode && String(v.moo) === String(a.moo)) || {}).sub_district_code;
                            if (alertSub && String(alertSub) !== String(targetSub)) return false;
                        }
                    } else {
                        if (String(a.moo) !== String(stationState.villageFilter)) return false;
                    }
                }

                // Text Search query (Name, CID, Phone, Moo, Village Name, Sub-district, House No, VHV, Symptoms, Alert ID)
                if (stationState.searchQuery) {
                    const q = stationState.searchQuery;
                    const vInfo = getAlertVillageInfo(a);
                    const matchName = (a.patient_name || '').toLowerCase().includes(q);
                    const matchCid = (a.target_cid || '').includes(q);
                    const matchPhone = (a.contact_phone || '').includes(q) || (a.vhv_phone || '').includes(q);
                    const matchVhv = (a.vhv_name || '').toLowerCase().includes(q);
                    const matchHouse = (a.house_no || '').toLowerCase().includes(q);
                    const matchMoo = (`ม.${a.moo}`.includes(q) || `หมู่ ${a.moo}`.includes(q) || `หมู่ที่ ${a.moo}`.includes(q));
                    const matchVillage = (vInfo.villageName || '').toLowerCase().includes(q) || `บ้าน${vInfo.villageName}`.toLowerCase().includes(q);
                    const matchSub = (vInfo.subDistrictName || '').toLowerCase().includes(q) || `ต.${vInfo.subDistrictName}`.toLowerCase().includes(q) || `ตำบล${vInfo.subDistrictName}`.toLowerCase().includes(q);
                    const matchCrisis = (a.crisis_type || '').toLowerCase().includes(q) || (a.red_flags || '').toLowerCase().includes(q);
                    const matchId = (`#${a.alert_id}`.includes(q) || String(a.alert_id).includes(q));

                    if (!matchName && !matchCid && !matchPhone && !matchVhv && !matchHouse && !matchMoo && !matchVillage && !matchSub && !matchCrisis && !matchId) {
                        return false;
                    }
                }

                return true;
            });

            // 3. Sort Alerts
            filtered.sort((a, b) => {
                if (stationState.sortBy === 'newest') {
                    return (b.alert_id || 0) - (a.alert_id || 0);
                } else if (stationState.sortBy === 'oldest') {
                    return (a.alert_id || 0) - (b.alert_id || 0);
                } else if (stationState.sortBy === 'bp_desc') {
                    return (parseInt(b.sbp || 0, 10)) - (parseInt(a.sbp || 0, 10));
                } else if (stationState.sortBy === 'dtx_desc') {
                    return (parseInt(b.dtx || 0, 10)) - (parseInt(a.dtx || 0, 10));
                }
                return 0;
            });

            stationState.filteredAlerts = filtered;

            // 4. Pagination Calculation
            const filteredTotal = filtered.length;
            const pageSize = stationState.pageSize;
            const totalPages = Math.max(1, Math.ceil(filteredTotal / pageSize));

            if (stationState.currentPage > totalPages) {
                stationState.currentPage = totalPages;
            }
            const currentPage = stationState.currentPage;

            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = Math.min(startIndex + pageSize, filteredTotal);
            const pageAlerts = filtered.slice(startIndex, endIndex);

            // Update Result Counters Text
            const resultText = document.getElementById('results-count-text');
            if (filteredTotal === totalCount) {
                resultText.innerText = `พบทั้งหมด ${filteredTotal} เคส (แสดง ${filteredTotal > 0 ? startIndex + 1 : 0} - ${endIndex})`;
            } else {
                resultText.innerText = `พบ ${filteredTotal} จากทั้งหมด ${totalCount} เคส (แสดง ${filteredTotal > 0 ? startIndex + 1 : 0} - ${endIndex})`;
            }

            // 5. Render Container Content (Card or Table)
            const root = document.getElementById('alert-display-root');
            if (filteredTotal === 0) {
                renderEmptyState(root);
                document.getElementById('pagination-wrapper').style.display = 'none';
                return;
            }

            if (stationState.viewMode === 'table') {
                renderTableView(root, pageAlerts);
            } else {
                renderCardGridView(root, pageAlerts);
            }

            // 6. Render Pagination Controls
            renderPagination(filteredTotal, totalPages, currentPage, startIndex + 1, endIndex);
        }

        // Render Empty State
        function renderEmptyState(root) {
            const hasFilter = stationState.searchQuery || stationState.statusFilter !== 'all' || stationState.crisisTypeFilter !== 'all' || stationState.mooFilter !== 'all';
            
            root.innerHTML = `
                <div style="text-align: center; padding: 48px 20px; background: var(--bg-card); border-radius: 24px; box-shadow: var(--neumorph-flat); border: 1px dashed var(--border-color, #CBD5E1);">
                    <div style="display: flex; justify-content: center; margin-bottom: 14px;">
                        <div class="neu-disc-icon lg ${hasFilter ? 'disc-yellow' : 'disc-green'}" style="width: 64px; height: 64px; font-size: 30px;">
                            ${hasFilter ? '🔍' : '🛡️'}
                        </div>
                    </div>
                    <div style="font-size: 17px; font-weight: 800; color: var(--text-primary);">
                        ${hasFilter ? 'ไม่พบเคสตามเงื่อนไขการค้นหา/ตัวกรอง' : 'ยังไม่มีเคสวิกฤตฉุกเฉินในขณะนี้'}
                    </div>
                    <div style="font-size: 13.5px; color: var(--text-secondary); margin-top: 6px; max-width: 500px; margin-left: auto; margin-right: auto;">
                        ${hasFilter ? 'ลองเปลี่ยนคำค้นหา หรือกดปุ่มรีเซ็ตเพื่อแสดงเคสทั้งหมดในสถานี' : 'เมื่อ อสม. คัดกรองพบเคสวิกฤต สัญญาณและข้อมูลจะเด้งขึ้นมาพร้อมเสียงเตือนทันที'}
                    </div>
                    ${hasFilter ? `
                        <button type="button" onclick="resetAllFilters()" class="btn-station-ctrl" style="margin-top: 16px; background: #2563EB; color: white; border: none; box-shadow: 0 4px 12px rgba(37,99,235,0.35);">
                            <span>🔄 ล้างการค้นหาและตัวกรองทั้งหมด</span>
                        </button>
                    ` : ''}
                </div>
            `;
        }

        // Render Card Grid View
        function renderCardGridView(root, alerts) {
            let html = '<div id="alert-cards-container" class="alert-card-grid">';
            
            alerts.forEach(a => {
                const isPending = a.alert_status === 'pending';
                const isReferred = a.alert_status === 'referred_hospital' || a.is_jhcis_synced == 1;
                const cardStatusClass = isPending ? 'pending' : (isReferred ? 'referred_hospital' : 'acknowledged');
                const timeInfo = formatAlertTimeThai(a.created_at);
                const vInfo = getAlertVillageInfo(a);

                let statusTag = '';
                if (isPending) {
                    statusTag = `<span class="tag-pill tag-danger"><span class="neu-disc-icon xs disc-red" style="width:16px;height:16px;font-size:10px;">🚨</span><span>รอรับเรื่องด่วน</span></span>`;
                } else if (isReferred) {
                    statusTag = `<span class="tag-pill tag-success"><span class="neu-disc-icon xs disc-green" style="width:16px;height:16px;font-size:10px;">🏥</span><span>ส่งต่อ รพ. แล้ว</span></span>`;
                } else {
                    let ackBy = a.acknowledged_by || 'จนท.';
                    ackBy = ackBy.replace(/^เจ้าหน้าที่\s*|^จนท\.\s*/, '').trim();
                    if (ackBy.length > 15) {
                        ackBy = ackBy.substring(0, 13) + '...';
                    }
                    const ackLabel = (ackBy && ackBy !== 'รพ.สต.' && ackBy !== 'ส่วนกลาง') ? `รับเรื่องแล้ว (${ackBy})` : 'รับเรื่องแล้ว';
                    statusTag = `<span class="tag-pill tag-warning" title="${a.acknowledged_by || ''}"><span class="neu-disc-icon xs disc-yellow" style="width:16px;height:16px;font-size:10px;">⏳</span><span>${ackLabel}</span></span>`;
                }

                const mapLink = (a.latitude && a.longitude)
                    ? `https://www.google.com/maps?q=${a.latitude},${a.longitude}`
                    : `https://www.google.com/maps/search/อำเภอตาลสุม+อุบลราชธานี`;

                const phone = a.contact_phone || a.vhv_phone || '';
                const phoneTypeLabel = a.contact_phone ? (a.contact_type === 'relative' ? 'ญาติ/ผู้ป่วย' : 'ผู้ป่วย') : 'อสม.';

                html += `
                    <div class="alert-item-card ${cardStatusClass}" role="button" tabindex="0" onclick="openAlertDetails(event, ${a.alert_id})" onkeydown="if(event.key === 'Enter' || event.key === ' '){ event.preventDefault(); openAlertDetails(event, ${a.alert_id}); }" title="คลิกเพื่อดูรายละเอียดเคสทั้งหมด">
                        <!-- 1. Header Section (Top Meta + Full Width Patient Name) -->
                        <div class="card-header-block">
                            <div class="card-top-meta">
                                <div class="case-id-badge">
                                    <span class="neu-disc-icon xs disc-blue" style="width: 18px; height: 18px; font-size: 10px;">🏷️</span>
                                    <span>รหัสเคส #${a.alert_id}</span>
                                </div>
                                <div class="card-meta-right">
                                    <div class="time-capsule ${isPending ? 'pending-time' : ''}">
                                        <span class="neu-disc-icon xs ${isPending ? 'disc-red' : 'disc-blue'}" style="width: 16px; height: 16px; font-size: 9.5px;">🕒</span>
                                        <span>${timeInfo.fullTime}</span>
                                        <span style="font-size: 10.5px; opacity: 0.85; font-weight: 700;">(${timeInfo.timeAgo})</span>
                                    </div>
                                    ${statusTag}
                                </div>
                            </div>

                            <h4 class="patient-name-title">
                                <span>${a.patient_name}</span>
                                ${a.age ? `<span class="patient-age-tag">(${a.age} ปี)</span>` : ''}
                            </h4>

                            <div class="patient-cid-row">
                                <span>CID: <strong>${a.target_cid || '-'}</strong></span>
                                <span class="hoscode-tag">รพ.สต. ${a.hoscode}</span>
                            </div>
                        </div>

                        <!-- 2. Vital Signs Highlight Tiles (Standard Grid) -->
                        <div class="vitals-grid-block">
                            <div style="background: var(--bg-card); padding: 8px 10px; border-radius: 14px; box-shadow: var(--neumorph-flat);">
                                <div style="font-size: 11px; color: var(--text-muted); font-weight: 700; display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                                    <span class="neu-disc-icon xs disc-red" style="width: 18px; height: 18px; font-size: 9.5px;">🩺</span>
                                    <span>ความดันโลหิต</span>
                                </div>
                                <div style="font-size: 16.5px; font-weight: 900; color: ${parseInt(a.sbp || 0) >= 180 ? '#DC2626' : '#10B981'};">
                                    ${a.sbp ? `${a.sbp}/${a.dbp}` : '-'} <span style="font-size: 11px; font-weight: 700; color: var(--text-muted);">mmHg</span>
                                </div>
                            </div>
                            <div style="background: var(--bg-card); padding: 8px 10px; border-radius: 14px; box-shadow: var(--neumorph-flat);">
                                <div style="font-size: 11px; color: var(--text-muted); font-weight: 700; display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                                    <span class="neu-disc-icon xs disc-yellow" style="width: 18px; height: 18px; font-size: 9.5px;">🩸</span>
                                    <span>น้ำตาล DTX</span>
                                </div>
                                <div style="font-size: 16.5px; font-weight: 900; color: ${parseInt(a.dtx || 0) >= 300 ? '#DC2626' : '#D97706'};">
                                    ${a.dtx ? `${a.dtx}` : '-'} <span style="font-size: 11px; font-weight: 700; color: var(--text-muted);">mg%</span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Details List Section (Location, Symptoms, VHV) -->
                        <div class="details-list-block">
                            <div class="detail-row-item">
                                <span class="neu-disc-icon xs disc-blue" style="width: 20px; height: 20px; font-size: 10px; margin-top: 1px; flex-shrink: 0;">📍</span>
                                <div title="บ.${a.house_no || '-'} ม.${a.moo || '-'}${vInfo.villageName ? ` บ้าน${vInfo.villageName}` : ''}${vInfo.subDistrictName ? ` ต.${vInfo.subDistrictName}` : ''}">
                                    <strong>ที่อยู่:</strong> บ.${a.house_no || '-'} ม.${a.moo || '-'}${vInfo.villageName ? ` บ้าน${vInfo.villageName}` : ''}${vInfo.subDistrictName ? ` <span style="color:var(--text-muted); font-size:11.5px;">(ต.${vInfo.subDistrictName})</span>` : ''}
                                </div>
                            </div>
                            <div class="detail-row-item">
                                <span class="neu-disc-icon xs disc-red" style="width: 20px; height: 20px; font-size: 10px; margin-top: 1px; flex-shrink: 0;">⚠️</span>
                                <div class="detail-crisis-text" title="${a.crisis_type} ${a.red_flags || ''}">
                                    ${a.crisis_type} ${a.red_flags ? `(${a.red_flags})` : ''}
                                </div>
                            </div>
                            <div class="detail-row-item">
                                <span class="neu-disc-icon xs disc-green" style="width: 20px; height: 20px; font-size: 10px; flex-shrink: 0;">👩‍⚕️</span>
                                <div style="color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <strong>อสม.:</strong> ${a.vhv_name || '-'} ${a.vhv_phone ? `(${a.vhv_phone})` : ''}
                                </div>
                            </div>
                        </div>

                        <!-- 4. Callback Phone Highlight Pill (Always Standard Height) -->
                        <div class="contact-phone-block" style="${phone ? 'background: rgba(16, 185, 129, 0.12); border: 1.5px solid #10B981;' : 'background: var(--bg-darker); border: 1px dashed var(--border-color, #CBD5E1);'}">
                            ${phone ? `
                                <div style="display: flex; align-items: center; gap: 8px; overflow: hidden;">
                                    <span class="neu-disc-icon xs disc-green" style="width: 22px; height: 22px; font-size: 11px; flex-shrink: 0;">📱</span>
                                    <div style="font-size: 12.5px; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <strong>${phone}</strong> <span style="font-size: 11px; color: var(--text-muted);">(${phoneTypeLabel})</span>
                                    </div>
                                </div>
                                <a href="tel:${phone}" style="background: #10B981; color: white; text-decoration: none; padding: 4px 10px; border-radius: 8px; font-size: 11.5px; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 3px 8px rgba(16, 185, 129, 0.35); flex-shrink: 0;">
                                    <span>📞 โทรออก</span>
                                </a>
                            ` : `
                                <div style="display: flex; align-items: center; gap: 8px; color: var(--text-muted); font-size: 12px;">
                                    <span class="neu-disc-icon xs" style="width: 22px; height: 22px; font-size: 11px; opacity: 0.6;">📱</span>
                                    <span>ไม่มีเบอร์โทรระบุในระบบ</span>
                                </div>
                            `}
                        </div>

                        <!-- 5. Action Buttons Row (Pinned to bottom) -->
                        <div class="card-actions-block">
                            ${IS_MAIN_ADMIN ? `
                                <button type="button" onclick="event.stopPropagation(); deleteAlertRecord(${a.alert_id}, '${(a.patient_name || '').replace(/'/g, "\\'")}')" class="btn-station-ctrl" style="padding: 9px 10px; color: #DC2626; background: #FEF2F2; border: 1.5px solid rgba(220,38,38,0.25); border-radius: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center;" title="ลบเคส #${a.alert_id} (สิทธิ์เฉพาะแอดมินหลัก)">
                                    <span style="font-size: 13px;">🗑️</span>
                                </button>
                            ` : ''}
                            ${isPending ? `
                                <button type="button" onclick="ackAlertById(${a.alert_id})" style="flex: 1.2; padding: 9px 12px; background: #DC2626; color: white; border: none; border-radius: 12px; font-weight: 800; font-size: 12.5px; cursor: pointer; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.35); display: flex; align-items: center; justify-content: center; gap: 6px;">
                                    <span class="neu-disc-icon xs" style="background: rgba(255,255,255,0.2); color: #fff; border-color: rgba(255,255,255,0.4); box-shadow: none; width: 18px; height: 18px; font-size: 10px;">🔕</span>
                                    <span>รับเรื่อง</span>
                                </button>
                            ` : ''}
                            <a href="${mapLink}" target="_blank" style="flex: 1; padding: 9px 12px; background: var(--bg-card); color: var(--text-primary); text-align: center; text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 12.5px; border: 1px solid var(--border-color, #CBD5E1); box-shadow: var(--neumorph-flat); display: flex; align-items: center; justify-content: center; gap: 6px;">
                                <span class="neu-disc-icon xs disc-blue" style="width: 18px; height: 18px; font-size: 10px;">⚓️</span>
                                <span>แผนที่</span>
                            </a>
                            ${isReferred ? `
                                <a href="critical_referrals.php?alert_id=${a.alert_id}" onclick="openOrFocusTab(this.href, 'ncd_critical_referrals_tab'); return false;" style="flex: 1.4; padding: 9px 12px; background: #059669; color: white; text-align: center; text-decoration: none; border-radius: 12px; font-weight: 800; font-size: 12.5px; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35); display: flex; align-items: center; justify-content: center; gap: 6px;" title="เปิดดูข้อมูลการส่งต่อ รพ.">
                                    <span class="neu-disc-icon xs" style="background: rgba(255,255,255,0.2); color: #fff; border-color: rgba(255,255,255,0.4); box-shadow: none; width: 18px; height: 18px; font-size: 10px;">✅</span>
                                    <span>ส่งต่อแล้ว (JHCIS)</span>
                                </a>
                            ` : `
                                <a href="critical_referrals.php?alert_id=${a.alert_id}" onclick="openOrFocusTab(this.href, 'ncd_critical_referrals_tab'); return false;" style="flex: 1.2; padding: 9px 12px; background: #2563EB; color: white; text-align: center; text-decoration: none; border-radius: 12px; font-weight: 800; font-size: 12.5px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35); display: flex; align-items: center; justify-content: center; gap: 6px;" title="ส่งต่อไปยัง รพ.">
                                    <span class="neu-disc-icon xs" style="background: rgba(255,255,255,0.2); color: #fff; border-color: rgba(255,255,255,0.4); box-shadow: none; width: 18px; height: 18px; font-size: 10px;">🏥</span>
                                    <span>ส่งต่อ รพ.</span>
                                </a>
                            `}
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            root.innerHTML = html;
        }

        // Render Compact Table View (High-density Triage List)
        function renderTableView(root, alerts) {
            let html = `
                <div class="table-responsive-wrap">
                    <table class="station-table">
                        <thead>
                            <tr>
                                <th>รหัส / เวลา</th>
                                <th>สถานะ</th>
                                <th>ชื่อ-สกุล / อายุ</th>
                                <th style="text-align: center;">ความดัน (BP)</th>
                                <th style="text-align: center;">น้ำตาล (DTX)</th>
                                <th>ที่อยู่ / หมู่</th>
                                <th>ภาวะวิกฤต / สัญญาณเตือน</th>
                                <th>ผู้แจ้ง / เบอร์ติดต่อ</th>
                                <th style="text-align: right; padding-right: 18px;">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            alerts.forEach(a => {
                const isPending = a.alert_status === 'pending';
                const isReferred = a.alert_status === 'referred_hospital' || a.is_jhcis_synced == 1;
                const rowStatusClass = isPending ? 'pending-row' : (isReferred ? 'referred-row' : 'ack-row');
                const timeInfo = formatAlertTimeThai(a.created_at);
                const vInfo = getAlertVillageInfo(a);

                let statusBadge = '';
                if (isPending) {
                    statusBadge = `<span class="tag-pill tag-danger" style="font-size:11px;">🚨 รอรับเรื่อง</span>`;
                } else if (isReferred) {
                    statusBadge = `<span class="tag-pill tag-success" style="font-size:11px;">🏥 ส่งต่อ รพ.</span>`;
                } else {
                    statusBadge = `<span class="tag-pill tag-warning" style="font-size:11px;">⏳ รับเรื่องแล้ว</span>`;
                }

                const mapLink = (a.latitude && a.longitude)
                    ? `https://www.google.com/maps?q=${a.latitude},${a.longitude}`
                    : `https://www.google.com/maps/search/อำเภอตาลสุม+อุบลราชธานี`;

                const phone = a.contact_phone || a.vhv_phone || '';

                html += `
                    <tr class="${rowStatusClass}" role="button" tabindex="0" onclick="openAlertDetails(event, ${a.alert_id})" onkeydown="if(event.key === 'Enter' || event.key === ' '){ event.preventDefault(); openAlertDetails(event, ${a.alert_id}); }" style="cursor: pointer;" title="คลิกเพื่อดูรายละเอียดเคสทั้งหมด">
                        <td>
                            <div style="font-weight: 800; color: var(--text-primary); white-space: nowrap;">#${a.alert_id} <span style="font-size: 10.5px; color: var(--text-secondary); font-weight: 500;">• ${timeInfo.dateText}</span></div>
                            <div style="font-size: 10.5px; font-weight: 400; color: var(--text-muted); line-height: 1.3; margin-top: 3px; white-space: nowrap;">${timeInfo.fullTime} <span style="font-weight:400;">(${timeInfo.timeAgo})</span></div>
                        </td>
                        <td>${statusBadge}</td>
                        <td>
                            <div style="font-weight: 600; color: var(--text-primary);">${a.patient_name}</div>
                            <div style="font-size: 11.5px; font-weight: 400; color: var(--text-muted);">
                                ${a.age ? `อายุ ${a.age} ปี • ` : ''}${a.target_cid ? `CID: ${a.target_cid}` : ''}
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <div style="font-weight: 900; font-size: 14.5px; color: ${parseInt(a.sbp || 0) >= 180 ? '#DC2626' : '#10B981'};">
                                ${a.sbp ? `${a.sbp}/${a.dbp}` : '-'}
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <div style="font-weight: 900; font-size: 14.5px; color: ${parseInt(a.dtx || 0) >= 300 ? '#DC2626' : '#D97706'};">
                                ${a.dtx ? `${a.dtx}` : '-'}
                            </div>
                        </td>
                        <td>
                            <div style="font-weight: 500; color: var(--text-primary);">บ.${a.house_no || '-'} ม.${a.moo || '-'}${vInfo.villageName ? ` บ้าน${vInfo.villageName}` : ''}</div>
                            <div style="font-size: 11px; font-weight: 400; color: var(--text-muted);">${vInfo.subDistrictName ? `ต.${vInfo.subDistrictName} • ` : ''}รพ.สต. ${a.hoscode}</div>
                        </td>
                        <td>
                            <div style="color: #DC2626; font-weight: 650; max-width: none; white-space: normal; line-height: 1.3;">
                                ${a.crisis_type}
                            </div>
                            ${a.red_flags ? `<div style="font-size: 11.5px; color: var(--text-muted); max-width: none; white-space: normal; line-height: 1.35;">${a.red_flags}</div>` : ''}
                        </td>
                        <td>
                            <div style="font-size: 12px; color: var(--text-primary); font-weight: 500;">${a.vhv_name || '-'}</div>
                            ${phone ? `
                                <a href="tel:${phone}" style="font-size: 11.5px; color: #059669; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                    <span>📞 ${phone}</span>
                                </a>
                            ` : '<span style="font-size: 11px; color: var(--text-muted);">-</span>'}
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <div class="station-table-actions">
                                ${IS_MAIN_ADMIN ? `
                                    <button type="button" onclick="event.stopPropagation(); deleteAlertRecord(${a.alert_id}, '${(a.patient_name || '').replace(/'/g, "\\'")}')" class="btn-station-ctrl" style="padding: 6px 8px; color: #DC2626; background: #FEF2F2; border: 1px solid rgba(220,38,38,0.3); border-radius: 8px; font-size: 12px; cursor: pointer;" title="ลบเคส #${a.alert_id} (สิทธิ์เฉพาะแอดมินหลัก)">
                                        <span>🗑️</span>
                                    </button>
                                ` : ''}
                                <a href="${mapLink}" target="_blank" class="btn-station-ctrl station-map-action" title="เปิดตำแหน่งบนแผนที่">
                                    <span>⚓️</span>
                                </a>
                                <div class="station-row-operations">
                                    ${isPending ? `
                                        <button type="button" onclick="ackAlertById(${a.alert_id})" style="background: #DC2626; color: white; border: none; padding: 6px 10px; border-radius: 8px; font-weight: 800; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                            <span>🔕 รับเรื่อง</span>
                                        </button>
                                    ` : ''}
                                    ${isReferred ? `
                                        <a href="critical_referrals.php?alert_id=${a.alert_id}" onclick="openOrFocusTab(this.href, 'ncd_critical_referrals_tab'); return false;" class="btn-station-ctrl station-refer-action" style="font-size: 11.5px; background: #059669; color: white; border: none;" title="เปิดดูประวัติการส่งต่อ">
                                            <span>✅ ส่งต่อแล้ว</span>
                                        </a>
                                    ` : `
                                        <a href="critical_referrals.php?alert_id=${a.alert_id}" onclick="openOrFocusTab(this.href, 'ncd_critical_referrals_tab'); return false;" class="btn-station-ctrl station-refer-action" style="font-size: 11.5px; background: #2563EB; color: white; border: none;" title="ส่งต่อ รพ.">
                                            <span>🏥 ส่งต่อ</span>
                                        </a>
                                    `}
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            root.innerHTML = html;
        }

        // Render Pagination Numbers
        function renderPagination(totalItems, totalPages, currentPage, startItem, endItem) {
            const wrapper = document.getElementById('pagination-wrapper');
            const infoText = document.getElementById('pagination-info-text');
            const buttonsWrap = document.getElementById('pagination-buttons');

            if (totalItems <= stationState.pageSize) {
                wrapper.style.display = 'none';
                return;
            }

            wrapper.style.display = 'flex';
            infoText.innerText = `แสดงเคสที่ ${startItem} - ${endItem} จากทั้งหมด ${totalItems} รายการ`;

            let btnsHtml = '';

            // Prev Button
            btnsHtml += `
                <button type="button" class="page-btn" onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''} title="หน้าก่อนหน้า">
                    ◀
                </button>
            `;

            // Smart page number buttons (Show max 5 numbered pages)
            let startP = Math.max(1, currentPage - 2);
            let endP = Math.min(totalPages, startP + 4);
            if (endP - startP < 4) {
                startP = Math.max(1, endP - 4);
            }

            if (startP > 1) {
                btnsHtml += `<button type="button" class="page-btn" onclick="changePage(1)">1</button>`;
                if (startP > 2) btnsHtml += `<span style="padding: 0 4px; color: var(--text-muted);">...</span>`;
            }

            for (let p = startP; p <= endP; p++) {
                btnsHtml += `
                    <button type="button" class="page-btn ${p === currentPage ? 'active' : ''}" onclick="changePage(${p})">
                        ${p}
                    </button>
                `;
            }

            if (endP < totalPages) {
                if (endP < totalPages - 1) btnsHtml += `<span style="padding: 0 4px; color: var(--text-muted);">...</span>`;
                btnsHtml += `<button type="button" class="page-btn" onclick="changePage(${totalPages})">${totalPages}</button>`;
            }

            // Next Button
            btnsHtml += `
                <button type="button" class="page-btn" onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''} title="หน้าถัดไป">
                    ▶
                </button>
            `;

            buttonsWrap.innerHTML = btnsHtml;
        }

        // ----------------------------------------------------
        // Pop-up Modal & Sound Handling
        // ----------------------------------------------------
        function openAlertDetails(event, alertId) {
            if (event && event.target && event.target.closest('a, button, input, select, textarea')) return;
            const alert = stationState.allAlerts.find(item => String(item.alert_id) === String(alertId));
            if (alert) showEmergencyPopup(alert, 'manual');
        }

        function showEmergencyPopup(alert, source = 'auto') {
            activeCrisisAlertId = alert.alert_id;
            modalOpenedManually = source === 'manual';
            const timeInfo = formatAlertTimeThai(alert.created_at);
            const villageInfo = getAlertVillageInfo(alert);
            const isPending = alert.alert_status === 'pending';
            const isReferred = alert.alert_status === 'referred_hospital' || alert.is_jhcis_synced == 1;
            const statusText = isPending ? 'รอรับเรื่องด่วน' : (isReferred ? 'ส่งต่อโรงพยาบาลแล้ว' : 'รับเรื่องแล้ว / กำลังดูแล');
            
            document.getElementById('modal-patient-name').innerText = `${alert.patient_name} ${alert.age ? `(อายุ ${alert.age} ปี)` : ''}`;
            document.getElementById('modal-case-id').innerText = `#${alert.alert_id}`;
            document.getElementById('modal-cid').innerText = alert.target_cid || '-';
            document.getElementById('modal-status').innerText = statusText;
            document.getElementById('modal-alert-time').innerText = `🕒 ${timeInfo.dateText} • ${timeInfo.fullTime} (${timeInfo.timeAgo})`;
            document.getElementById('modal-bp-val').innerText = alert.sbp ? `${alert.sbp}/${alert.dbp} mmHg` : '-';
            document.getElementById('modal-dtx-val').innerText = alert.dtx ? `${alert.dtx} mg/dL` : '-';
            document.getElementById('modal-address').innerText = `บ้านเลขที่ ${alert.house_no || '-'} หมู่ ${alert.moo || '-'}${villageInfo.villageName ? ` บ้าน${villageInfo.villageName}` : ''}${villageInfo.subDistrictName ? ` ต.${villageInfo.subDistrictName}` : ''} (รพ.สต. ${alert.hoscode})`;
            document.getElementById('modal-crisis-type').innerText = `${alert.crisis_type} ${alert.red_flags ? `• ${alert.red_flags}` : ''}`;
            document.getElementById('modal-vhv-info').innerText = `${alert.vhv_name || '-'} ${alert.vhv_phone ? `(${alert.vhv_phone})` : ''}`;

            const ackButton = document.getElementById('btn-modal-ack');
            if (ackButton) ackButton.style.display = isPending ? 'inline-flex' : 'none';

            const modalReferBtn = document.getElementById('modal-btn-refer');
            if (modalReferBtn) {
                modalReferBtn.href = `critical_referrals.php?alert_id=${alert.alert_id}`;
                modalReferBtn.target = 'ncd_critical_referrals_tab';
            }

            const contactPhone = alert.contact_phone || alert.vhv_phone || '';
            const contactType = (alert.contact_type === 'relative') ? 'เบอร์ญาติ/ผู้ป่วย' : 'เบอร์ อสม.';
            document.getElementById('modal-contact-phone').innerText = contactPhone || 'ไม่มีเบอร์';
            document.getElementById('modal-contact-type').innerText = contactPhone ? `(${contactType})` : '';
            const btnCall = document.getElementById('modal-btn-call');
            if (contactPhone) {
                btnCall.href = `tel:${contactPhone}`;
                btnCall.style.display = 'inline-flex';
            } else {
                btnCall.style.display = 'none';
            }

            if (alert.latitude && alert.longitude) {
                document.getElementById('modal-btn-map').href = `https://www.google.com/maps?q=${alert.latitude},${alert.longitude}`;
            } else {
                document.getElementById('modal-btn-map').href = `https://www.google.com/maps/search/อำเภอตาลสุม+อุบลราชธานี`;
            }

            document.getElementById('emergency-popup-overlay').style.display = 'flex';
        }

        function closeEmergencyPopupWithoutAction() {
            stopSirenSound();
            if (activeCrisisAlertId) {
                stationState.dismissedAlertIds.add(String(activeCrisisAlertId));
                try {
                    sessionStorage.setItem('ncd_dismissed_alert_ids', JSON.stringify(Array.from(stationState.dismissedAlertIds)));
                } catch(e) {}
            }
            hideEmergencyPopup();
            showStationToast('🔕 ปิดการแจ้งเตือนแล้ว เคสยังคงอยู่ในสถานะ "รอรับเรื่อง" ในรายการ');
            applyFiltersAndRender();
        }

        function hideEmergencyPopup() {
            stopSirenSound();
            const overlay = document.getElementById('emergency-popup-overlay');
            if (overlay) overlay.style.display = 'none';
            activeCrisisAlertId = null;
            modalOpenedManually = false;
        }

        function showStationToast(msg) {
            let toast = document.getElementById('station-toast-msg');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'station-toast-msg';
                toast.style.cssText = 'position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #1E293B; color: #F8FAFC; padding: 12px 24px; border-radius: 16px; font-size: 13.5px; font-weight: 700; box-shadow: 0 10px 30px rgba(0,0,0,0.35); z-index: 10000000; display: flex; align-items: center; gap: 8px; border: 1px solid rgba(255,255,255,0.15); transition: opacity 0.3s ease; opacity: 0; pointer-events: none;';
                document.body.appendChild(toast);
            }
            toast.innerHTML = msg;
            toast.style.opacity = '1';
            if (window._toastTimer) clearTimeout(window._toastTimer);
            window._toastTimer = setTimeout(() => {
                toast.style.opacity = '0';
            }, 3500);
        }

        function acknowledgeCurrentAlert() {
            if (!activeCrisisAlertId) {
                hideEmergencyPopup();
                stopSirenSound();
                return;
            }
            ackAlertById(activeCrisisAlertId);
        }

        function ackAlertById(alertId) {
            const formData = new FormData();
            formData.append('action', 'acknowledge_alert');
            formData.append('alert_id', alertId);
            formData.append('staff_name', 'เจ้าหน้าที่ รพ.สต.');

            fetch('../api/emergency_alert.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    stopSirenSound();
                    if (stationState.dismissedAlertIds) {
                        stationState.dismissedAlertIds.delete(String(alertId));
                        try {
                            sessionStorage.setItem('ncd_dismissed_alert_ids', JSON.stringify(Array.from(stationState.dismissedAlertIds)));
                        } catch(e) {}
                    }
                    hideEmergencyPopup();
                    fetchActiveAlerts();
                }
            })
            .catch(err => console.error(err));
        }

        // Live Fetch / Realtime Polling
        function fetchActiveAlerts() {
            fetch(`../api/emergency_alert.php?action=get_active_alerts&hoscode=${currentHoscode}&limit=200`)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('last-ping-time').innerText = new Date().toLocaleTimeString('th-TH');
                        const alerts = data.alerts || [];
                        stationState.allAlerts = alerts;

                        // All pending cases sorted FIFO
                        const allPendingAlerts = alerts
                            .filter(a => a.alert_status === 'pending')
                            .sort((a, b) => Number(a.alert_id) - Number(b.alert_id));

                        // Filter out cases that were explicitly dismissed/closed on this station
                        const unhandledPendingAlerts = allPendingAlerts
                            .filter(a => !stationState.dismissedAlertIds.has(String(a.alert_id)));

                        const pendingCrisis = unhandledPendingAlerts[0] || null;
                        const statusHero = document.getElementById('status-hero');
                        const statusIconContainer = document.getElementById('status-icon-container');

                        if (pendingCrisis) {
                            statusHero.classList.add('alerting');
                            statusIconContainer.style.background = 'radial-gradient(circle at 35% 35%, #EF4444 0%, #DC2626 70%, #991B1B 100%)';
                            statusIconContainer.style.animation = 'emergencyBeaconPulse 1.8s infinite';
                            statusIconContainer.innerHTML = `
                                <svg width="18" height="18" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M24 4 L44 40 H4 Z" fill="#FFFFFF"/>
                                    <path d="M24 16 V28" stroke="#DC2626" stroke-width="4.5" stroke-linecap="round"/>
                                    <circle cx="24" cy="34" r="2.5" fill="#DC2626"/>
                                </svg>
                            `;
                            document.getElementById('status-headline').innerText = `⚠️ พบสัญญาณแจ้งเหตุวิกฤต! (${pendingCrisis.patient_name})`;
                            document.getElementById('status-sub').innerText = `• ความดัน ${pendingCrisis.sbp || '-'}/${pendingCrisis.dbp || '-'} | น้ำตาล DTX ${pendingCrisis.dtx || '-'} mg% • ต้องการการดูแลฉุกเฉินด่วน`;
                            document.getElementById('station-pulsing-dot').className = 'pulsing-dot active-crisis';

                            const activeStillPending = unhandledPendingAlerts.some(a => String(a.alert_id) === String(activeCrisisAlertId));
                            // Never overwrite an alert being handled. Show the oldest queued case next.
                            if (!modalOpenedManually && (!activeCrisisAlertId || !activeStillPending)) {
                                if (activeCrisisAlertId && !activeStillPending) hideEmergencyPopup();
                                showEmergencyPopup(pendingCrisis, 'auto');
                                startSirenSound();
                            }
                        } else if (allPendingAlerts.length > 0) {
                            // There are pending cases in the list, but all have been dismissed/snoozed by user
                            statusHero.classList.remove('alerting');
                            statusIconContainer.style.background = 'radial-gradient(circle at 35% 35%, #F59E0B 0%, #D97706 70%, #B45309 100%)';
                            statusIconContainer.style.animation = 'none';
                            statusIconContainer.innerHTML = `
                                <svg width="18" height="18" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="24" cy="24" r="20" fill="#FFFFFF"/>
                                    <path d="M24 14 V26" stroke="#D97706" stroke-width="4.5" stroke-linecap="round"/>
                                    <circle cx="24" cy="33" r="2.5" fill="#D97706"/>
                                </svg>
                            `;
                            document.getElementById('status-headline').innerText = `มีเคสวิกฤตรอดำเนินการ ${allPendingAlerts.length} เคส (ปิดการแจ้งเตือนไว้)`;
                            document.getElementById('status-sub').innerText = '• ปิดเสียงเตือนชั่วคราวแล้ว • คลิกที่การ์ดในรายการเพื่อดูรายละเอียดเมื่อพร้อมดำเนินการ';
                            document.getElementById('station-pulsing-dot').className = 'pulsing-dot';
                            
                            if (!modalOpenedManually) hideEmergencyPopup();
                            stopSirenSound();
                        } else {
                            statusHero.classList.remove('alerting');
                            statusIconContainer.style.background = 'radial-gradient(circle at 35% 35%, #34D399 0%, #10B981 70%, #047857 100%)';
                            statusIconContainer.style.animation = 'none';
                            statusIconContainer.innerHTML = `
                                <svg width="18" height="18" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M24 4 L40 10 V22 C40 32.5 33.2 41.8 24 45 C14.8 41.8 8 32.5 8 22 V10 L24 4 Z" fill="#FFFFFF"/>
                                    <path d="M24 8 L36 12.5 V22 C36 30.5 30.8 38 24 40.8 C17.2 38 12 30.5 12 22 V12.5 L24 8 Z" fill="#E6FDF5"/>
                                    <path d="M16 23 L22 29 L32 17" stroke="#059669" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            `;
                            document.getElementById('status-headline').innerText = 'สถานีพร้อมรับสัญญาณฉุกเฉิน (Active Standby)';
                            document.getElementById('status-sub').innerText = '• เชื่อมต่อระบบรับเหตุแล้ว • การตอบสนองขึ้นอยู่กับความพร้อมของเจ้าหน้าที่และหน่วยบริการ';
                            document.getElementById('station-pulsing-dot').className = 'pulsing-dot';
                            
                            if (!modalOpenedManually) hideEmergencyPopup();
                            stopSirenSound();
                        }

                        // Update known pending set
                        stationState.knownPendingAlertIds = new Set(alerts.filter(a => a.alert_status === 'pending').map(a => a.alert_id));

                        applyFiltersAndRender();
                    }
                })
                .catch(err => console.error('Poll error:', err));
        }

        // Simulate Test Alert
        async function simulateTestAlert() {
            const unitCodes = <?= json_encode(array_values(array_keys($hc_names)), JSON_UNESCAPED_UNICODE) ?>;
            const testHoscode = (currentHoscode && !['ALL', 'GLOBAL', '99999'].includes(currentHoscode))
                ? currentHoscode
                : '00325';
            if (!/^\d{5}$/.test(testHoscode)) {
                alert('ไม่พบรหัสหน่วยบริการสำหรับการทดสอบ');
                return;
            }
            try {
                const formData = new FormData();
                formData.append('action', 'trigger_alert');
                formData.append('hoscode', testHoscode);
                formData.append('target_cid', '0032500000001');
                formData.append('patient_name', 'เคสทดสอบสัญญาณฉุกเฉิน (สสอ.ตาลสุม)');
                formData.append('age', '67');
                formData.append('house_no', 'TEST-1');
                formData.append('moo', '1');
                formData.append('crisis_type', 'โหมดทดสอบสัญญาณฉุกเฉิน');
                formData.append('sbp', '215');
                formData.append('dbp', '120');
                formData.append('dtx', '340');
                formData.append('red_flags', 'ชุดข้อมูลจำลองสำหรับทดสอบสัญญาณ สังกัด สสอ.ตาลสุม (00325)');
                formData.append('vhv_name', 'ผู้ทดสอบระบบ (สสอ.)');
                const result = await fetch('../api/emergency_alert.php', { method: 'POST', body: formData }).then(r => r.json());
                if (result.status !== 'success') throw new Error(result.message || 'สร้างเคสทดสอบไม่สำเร็จ');
                if (result.alert_id && stationState.dismissedAlertIds) {
                    stationState.dismissedAlertIds.delete(String(result.alert_id));
                }
                fetchActiveAlerts();
            } catch (error) {
                alert('ทดสอบสัญญาณไม่สำเร็จ: ' + error.message);
            }
        }

        // Delete Single Alert Record (Restricted strictly to Main District Admin)
        async function deleteAlertRecord(alertId, patientName) {
            if (!IS_MAIN_ADMIN) {
                alert('⚠️ สิทธิ์ไม่เพียงพอ: การลบเคสสงวนสิทธิ์เฉพาะแอดมินหลัก (สสอ.ตาลสุม) เท่านั้น\nผู้รับผิดชอบ รพ. หรือระดับ รพ.สต. ไม่สามารถลบได้');
                return;
            }
            const nameText = patientName ? `เคส #${alertId} (${patientName})` : `เคส #${alertId}`;
            if (!confirm(`ยืนยันการลบ ${nameText} ออกจากระบบ?`)) {
                return;
            }
            try {
                const formData = new FormData();
                formData.append('action', 'delete_alert');
                formData.append('alert_id', alertId);
                const result = await fetch('../api/emergency_alert.php', { method: 'POST', body: formData }).then(r => r.json());
                if (result.status !== 'success') throw new Error(result.message || 'ลบเคสไม่สำเร็จ');
                showStationToast(`🗑️ ${result.message}`);
                if (activeCrisisAlertId && String(activeCrisisAlertId) === String(alertId)) {
                    hideEmergencyPopup();
                }
                fetchActiveAlerts();
            } catch (error) {
                alert('เกิดข้อผิดพลาด: ' + error.message);
            }
        }

        function deleteCurrentAlertFromModal() {
            if (!activeCrisisAlertId) return;
            const patientName = document.getElementById('modal-patient-name')?.innerText || '';
            deleteAlertRecord(activeCrisisAlertId, patientName);
        }

        // Initialize Live Loop & Keyboard Shortcuts
        document.addEventListener('DOMContentLoaded', () => {
            const stationHeader = document.querySelector('.station-header');
            const syncStationHeaderHeight = () => {
                if (stationHeader) {
                    document.documentElement.style.setProperty('--station-header-height', `${stationHeader.offsetHeight}px`);
                }
            };
            syncStationHeaderHeight();
            window.addEventListener('resize', syncStationHeaderHeight);
            if (stationHeader && 'ResizeObserver' in window) {
                new ResizeObserver(syncStationHeaderHeight).observe(stationHeader);
            }

            const savedTheme = localStorage.getItem('theme') || 'light';
            updateThemeButtonUI(savedTheme);

            // Set initial view mode button states
            if (stationState.viewMode === 'table') {
                document.getElementById('view-btn-card').classList.remove('active');
                document.getElementById('view-btn-table').classList.add('active');
            }

            // Initialize Sub-district and Village cascading dropdown
            if (currentHoscode && currentHoscode !== 'ALL' && currentHoscode !== 'GLOBAL' && currentHoscode !== '99999') {
                const subForHos = ALL_VILLAGES.find(v => v.hoscode === currentHoscode);
                if (subForHos && subForHos.sub_district_code) {
                    const selectSub = document.getElementById('select-subdistrict');
                    if (selectSub) {
                        selectSub.value = subForHos.sub_district_code;
                        stationState.subDistrictFilter = subForHos.sub_district_code;
                        populateVillageDropdown(subForHos.sub_district_code);
                    } else {
                        populateVillageDropdown('all');
                    }
                } else {
                    populateVillageDropdown('all');
                }
            } else {
                populateVillageDropdown('all');
            }

            fetchActiveAlerts();
            setInterval(fetchActiveAlerts, 3500);

            // User gesture unlock for audio
            document.body.addEventListener('click', () => {
                if (!audioCtx) {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                if (audioCtx && audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }
            }, { once: true });

            // Keyboard Shortcuts
            document.addEventListener('keydown', (e) => {
                // '/' key to focus search box (when not already in input)
                if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'SELECT') {
                    e.preventDefault();
                    const searchInput = document.getElementById('station-search-input');
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                }
                // 'Escape' key to clear search or close modal
                if (e.key === 'Escape') {
                    if (document.getElementById('emergency-popup-overlay').style.display === 'flex') {
                        hideEmergencyPopup();
                        stopSirenSound();
                    } else if (document.activeElement.id === 'station-search-input') {
                        clearSearch();
                        document.activeElement.blur();
                    }
                }
            });
        });
    </script>
    <?php include __DIR__ . '/station_download_modal.php'; ?>
    <?php include_once __DIR__ . '/../config/dev_modal.php'; ?>
</body>
</html>
