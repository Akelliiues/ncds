<?php
// admin/jhcis_sync.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$admin_title = function_exists('get_admin_title') ? get_admin_title() : 'ผู้ดูแลระบบ';
$is_super_admin = !empty($_SESSION['is_super_admin']);
$hc_names = function_exists('get_health_units') ? get_health_units() : [];

$selected_hoscode = $_GET['hoscode'] ?? $admin_hoscode ?? '';
$active_tab = $_GET['tab'] ?? 'sync';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ซิงค์ฐานข้อมูล JHCIS - NCDs Portal อำเภอ<?= DISTRICT_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .jhcis-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 24px;
            box-shadow: var(--neumorph-flat);
            margin-bottom: 24px;
        }

        .sync-grid {
            display: grid;
            grid-template-columns: 1fr 1.25fr;
            gap: 24px;
            align-items: start;
        }

        @media (max-width: 960px) {
            .sync-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 13.5px;
            font-weight: 700;
            box-sizing: border-box;
            outline: none;
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .btn-action-primary {
            background: var(--color-primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35);
        }

        .btn-action-primary:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }

        .btn-action-secondary {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 10px 18px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-action-secondary:hover {
            border-color: var(--color-primary);
            color: var(--color-primary);
        }

        .progress-track {
            width: 100%;
            height: 18px;
            background: var(--bg-darker);
            border-radius: 50px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            position: relative;
            margin: 16px 0 10px 0;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #3B82F6, #10B981);
            width: 0%;
            border-radius: 50px;
            transition: width 0.3s ease;
        }

        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .stat-badge.ready {
            background: rgba(59, 130, 246, 0.12);
            color: #3B82F6;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .stat-badge.success {
            background: rgba(16, 185, 129, 0.12);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .stat-badge.warning {
            background: rgba(245, 158, 11, 0.12);
            color: #F59E0B;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .stat-badge.cross {
            background: rgba(239, 68, 68, 0.12);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .table-responsive {
            overflow-x: auto;
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border-color);
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        .custom-table th {
            background: var(--bg-darker);
            color: var(--color-accent);
            padding: 12px 14px;
            font-weight: 800;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        .custom-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .custom-table tr:hover td {
            background: rgba(59, 130, 246, 0.04);
        }

        .tab-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }

        .tab-btn {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            border-radius: 12px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .tab-btn.active {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .hospital-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
        }
    </style>
</head>
<body class="admin-body">
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <!-- Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
            <div>
                <h2 style="color: var(--color-accent); margin: 0; font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 10px;">
                    🔄 ซิงค์ฐานข้อมูล JHCIS (JHCIS Direct Sync Bridge)
                </h2>
                <p style="color: var(--text-secondary); margin: 4px 0 0 0; font-size: 14px;">
                    ระบบถ่ายโอนผลการคัดกรองเบาหวาน-ความดันของ อสม. เข้าสู่ตาราง <code>ncd_person_ncd_screen</code> ของ JHCIS พร้อมระบบตรวจสอบรหัสสถานบริการ
                </p>
            </div>
            <!-- Hospital Selector -->
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <form method="GET" style="display: flex; align-items: center; gap: 8px; margin: 0;">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
                    <select name="hoscode" onchange="this.form.submit()" style="padding: 8px 12px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-weight: 700; font-size: 13px;">
                        <option value="">-- ทุก รพ.สต. ในอำเภอ --</option>
                        <?php foreach ($hc_names as $code => $name): ?>
                            <option value="<?= htmlspecialchars($code) ?>" <?= $selected_hoscode == $code ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($code) ?>] <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="tab-nav">
            <button class="tab-btn <?= $active_tab === 'sync' ? 'active' : '' ?>" onclick="switchTab('sync')">
                <span>🔄</span> ซิงค์ข้อมูลด่วน (Quick Sync)
            </button>
            <button class="tab-btn <?= $active_tab === 'settings' ? 'active' : '' ?>" onclick="switchTab('settings')">
                <span>⚙️</span> ตั้งค่าการเชื่อมต่อ JHCIS (Settings)
            </button>
            <button class="tab-btn <?= $active_tab === 'logs' ? 'active' : '' ?>" onclick="switchTab('logs')">
                <span>📜</span> ประวัติการซิงค์ข้อมูล (Audit Logs)
            </button>
        </div>

        <!-- TAB 1: QUICK SYNC CONSOLE -->
        <div id="tab-sync" class="tab-content" style="display: <?= $active_tab === 'sync' ? 'block' : 'none' ?>;">
            <!-- Hospital Code Verification Banner -->
            <div style="background: var(--bg-card); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 16px 20px; margin-bottom: 20px; box-shadow: var(--neumorph-flat); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center; font-size: 22px;">
                        🏥
                    </div>
                    <div>
                        <div style="font-size: 12px; font-weight: 700; color: var(--text-secondary);">ฐานข้อมูล JHCIS ปลายทางที่เชื่อมต่อ:</div>
                        <div style="font-size: 15px; font-weight: 800; color: var(--color-accent); display: flex; align-items: center; gap: 8px;">
                            <span id="detected-hosp-name">กำลังตรวจสอบ...</span>
                            <span id="detected-hosp-badge" class="stat-badge ready" style="display: none;">-</span>
                        </div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="text-align: right;">
                        <div style="font-size: 11.5px; font-weight: 700; color: var(--text-secondary);">ความตรงกันของข้อมูล:</div>
                        <div id="match-summary-text" style="font-size: 13px; font-weight: 800; color: #10B981;">กำลังคำนวณ...</div>
                    </div>
                </div>
            </div>

            <div class="sync-grid">
                <!-- Left: Source Status & Control Box -->
                <div class="jhcis-card">
                    <h3 style="color: var(--color-accent); margin: 0 0 16px 0; font-size: 17px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                        <span>📋</span> รายการที่พร้อมซิงค์เข้า JHCIS
                    </h3>

                    <!-- Status Grid -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div style="background: var(--bg-darker); border-radius: 14px; padding: 14px; text-align: center; border: 1px solid var(--border-color);">
                            <span style="font-size: 12px; font-weight: 700; color: var(--text-secondary);">ตรงกับ รพ.สต. นี้ (Match)</span>
                            <div id="badge-matched-count" style="font-size: 26px; font-weight: 800; color: #10B981; margin-top: 4px;">-</div>
                        </div>
                        <div style="background: var(--bg-darker); border-radius: 14px; padding: 14px; text-align: center; border: 1px solid var(--border-color);">
                            <span style="font-size: 12px; font-weight: 700; color: var(--text-secondary);">สังกัด รพ.สต. อื่น (Cross)</span>
                            <div id="badge-cross-count" style="font-size: 26px; font-weight: 800; color: #F59E0B; margin-top: 4px;">-</div>
                        </div>
                    </div>

                    <!-- Cross Hospital Policy Selector -->
                    <div style="margin-bottom: 16px; background: var(--bg-darker); border: 1px solid var(--border-color); border-radius: 14px; padding: 14px;">
                        <label class="form-label" style="margin-bottom: 8px;">
                            <span>🛡️</span> นโยบายข้อมูลข้าม รพ.สต. (Cross-Hospital Policy):
                        </label>
                        <select id="select-cross-policy" class="form-select" style="font-size: 12.5px;">
                            <option value="strict" selected>🔒 ซิงค์เฉพาะข้อมูลที่ตรงกับ รพ.สต. นี้ (Strict Mode - แนะนำ)</option>
                            <option value="smart_lookup">🔍 ค้นหา PID ใน JHCIS (ถ้ามีในทะเบียนให้ซิงค์เข้าได้)</option>
                            <option value="force_current">⚠️ ซิงค์ทั้งหมดและปรับเป็นรหัส รพ.สต. นี้</option>
                        </select>
                        <div style="font-size: 11.5px; color: var(--text-secondary); margin-top: 6px; line-height: 1.4;">
                            * Strict Mode จะป้องกันข้อมูลปนข้ามเขต โดยจะแยกเก็บข้อมูลต่าง รพ.สต. ไว้ให้ซิงค์เข้าฐานของ รพ.สต. นั้นๆ
                        </div>
                    </div>

                    <!-- Health Center Breakdown List -->
                    <div style="margin-bottom: 20px;">
                        <span style="font-size: 12px; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                            <span>📊</span> จำแนกจำนวนข้อมูลตาม รพ.สต. ต้นสังกัด:
                        </span>
                        <div id="breakdown-chips-box" style="display: flex; flex-wrap: wrap; gap: 6px;">
                            <span style="font-size: 12px; color: var(--text-secondary);">กำลังโหลด...</span>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <button type="button" id="btn-start-sync" onclick="startSyncProcess()" class="btn-action-primary" style="width: 100%; padding: 14px; font-size: 15px;">
                            <span>🚀</span> เริ่มซิงค์ข้อมูลเข้า JHCIS ทันที (Direct / Local)
                        </button>
                        <button type="button" onclick="exportSqlScript()" class="btn-action-secondary" style="width: 100%; justify-content: center; background: rgba(16, 185, 129, 0.08); color: #10B981; border-color: rgba(16, 185, 129, 0.3);">
                            <span>📥</span> ส่งออกไฟล์ SQL สำหรับนำเข้า JHCIS (HeidiSQL / Query Tool)
                        </button>
                        <button type="button" onclick="testJHCISConnection()" class="btn-action-secondary" style="width: 100%; justify-content: center;">
                            <span>🔌</span> ทดสอบการเชื่อมต่อ & ตรวจสอบรหัส JHCIS
                        </button>
                    </div>
                </div>

                <!-- Right: Real-time Live Progress & Results -->
                <div class="jhcis-card">
                    <h3 style="color: var(--color-accent); margin: 0 0 16px 0; font-size: 17px; font-weight: 800; display: flex; align-items: center; justify-content: space-between;">
                        <span style="display: flex; align-items: center; gap: 8px;">
                            <span>⚡</span> ความคืบหน้าการทำงาน (Live Sync Monitor)
                        </span>
                        <span id="sync-status-badge" class="stat-badge ready">พร้อมทำงาน</span>
                    </h3>

                    <!-- Live Progress Bar -->
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; font-size: 13.5px; font-weight: 800; color: var(--text-primary);">
                            <span id="progress-text">สถานะ: รอการเริ่มซิงค์</span>
                            <span id="progress-percent">0%</span>
                        </div>
                        <div class="progress-track">
                            <div id="progress-bar" class="progress-bar-fill"></div>
                        </div>
                        <div id="progress-detail" style="font-size: 12px; color: var(--text-secondary); min-height: 18px;">
                            พร้อมเริ่มประมวลผลเมื่อกดปุ่ม
                        </div>
                    </div>

                    <!-- Summary Card (Shown when complete) -->
                    <div id="sync-result-card" style="display: none; background: var(--bg-darker); border: 1.5px solid #10B981; border-radius: 16px; padding: 18px; margin-top: 16px;">
                        <h4 style="color: #10B981; margin: 0 0 10px 0; font-size: 15px; font-weight: 800; display: flex; align-items: center; gap: 6px;">
                            <span>🎉</span> ซิงค์ข้อมูลเข้า JHCIS เสร็จสมบูรณ์!
                        </h4>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; text-align: center; margin-bottom: 10px;">
                            <div>
                                <span style="font-size: 11px; color: var(--text-secondary);">สำเร็จ</span>
                                <div id="res-success" style="font-size: 17px; font-weight: 800; color: #10B981;">0</div>
                            </div>
                            <div>
                                <span style="font-size: 11px; color: var(--text-secondary);">ข้าม/ซ้ำ</span>
                                <div id="res-skipped" style="font-size: 17px; font-weight: 800; color: #F59E0B;">0</div>
                            </div>
                            <div>
                                <span style="font-size: 11px; color: var(--text-secondary);">ต่าง รพ.สต.</span>
                                <div id="res-cross-skipped" style="font-size: 17px; font-weight: 800; color: #EF4444;">0</div>
                            </div>
                            <div>
                                <span style="font-size: 11px; color: var(--text-secondary);">เวลาที่ใช้</span>
                                <div id="res-time" style="font-size: 17px; font-weight: 800; color: var(--color-primary);">0.0s</div>
                            </div>
                        </div>
                        <p id="res-message" style="font-size: 12px; color: var(--text-secondary); margin: 0; text-align: center;"></p>
                    </div>

                    <!-- Connection Log Output Console -->
                    <div style="margin-top: 20px;">
                        <span style="font-size: 12px; font-weight: 700; color: var(--text-secondary);">บันทึกการทำงานสด (Console Log):</span>
                        <div id="sync-console-log" style="background: #0B0F19; color: #10B981; font-family: monospace; font-size: 11.5px; padding: 12px; border-radius: 12px; height: 130px; overflow-y: auto; margin-top: 6px; border: 1px solid var(--border-color); line-height: 1.5;">
                            [SYSTEM READY] Ready to connect to JHCIS MySQL Database.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Sample Table -->
            <div class="jhcis-card" style="margin-top: 24px;">
                <h3 style="color: var(--color-accent); margin: 0 0 16px 0; font-size: 16px; font-weight: 800; display: flex; align-items: center; justify-content: space-between;">
                    <span style="display: flex; align-items: center; gap: 8px;">
                        <span>🔍</span> ตัวอย่างข้อมูลคัดกรอง & การตรวจสอบสังกัด รพ.สต. (Top Preview)
                    </span>
                    <button type="button" onclick="loadSyncPreview()" class="btn-action-secondary" style="padding: 6px 12px; font-size: 12px;">
                        🔄 รีเฟรชรายการ
                    </button>
                </h3>
                <div class="table-responsive">
                    <table class="custom-table" id="preview-table">
                        <thead>
                            <tr>
                                <th>ลำดับ</th>
                                <th>CID</th>
                                <th>ชื่อ - นามสกุล</th>
                                <th>ที่อยู่</th>
                                <th>รพ.สต. สังกัด</th>
                                <th>วันที่คัดกรอง</th>
                                <th>ความดัน (BP)</th>
                                <th>น้ำตาล (DTX)</th>
                                <th>สถานะซิงค์</th>
                            </tr>
                        </thead>
                        <tbody id="preview-tbody">
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 20px; color: var(--text-secondary);">
                                    กำลังโหลดข้อมูล...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: CONNECTION SETTINGS -->
        <div id="tab-settings" class="tab-content" style="display: <?= $active_tab === 'settings' ? 'block' : 'none' ?>;">
            <div class="jhcis-card" style="max-width: 800px; margin: 0 auto;">
                <h3 style="color: var(--color-accent); margin: 0 0 20px 0; font-size: 18px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                    <span>⚙️</span> การตั้งค่าการเชื่อมต่อฐานข้อมูล JHCIS (MySQL Configuration)
                </h3>

                <form id="form-jhcis-settings" onsubmit="saveJHCISSettings(event)">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div>
                            <label class="form-label">🖥️ MySQL Host / Server IP:</label>
                            <input type="text" id="cfg-host" name="jhcis_host" class="form-input" placeholder="เช่น localhost หรือ 192.168.1.100" required>
                        </div>
                        <div>
                            <label class="form-label">🔌 Port:</label>
                            <input type="number" id="cfg-port" name="jhcis_port" class="form-input" placeholder="3333 หรือ 3306" required>
                        </div>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label class="form-label">📁 Database Name:</label>
                        <input type="text" id="cfg-dbname" name="jhcis_dbname" class="form-input" placeholder="jhcisdb" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                        <div>
                            <label class="form-label">👤 Username:</label>
                            <input type="text" id="cfg-user" name="jhcis_user" class="form-input" placeholder="root" required>
                        </div>
                        <div>
                            <label class="form-label">🔑 Password:</label>
                            <input type="password" id="cfg-pass" name="jhcis_pass" class="form-input" placeholder="รหัสผ่านฐานข้อมูล JHCIS">
                        </div>
                    </div>

                    <div style="border-top: 1px solid var(--border-color); padding-top: 20px; margin-bottom: 20px;">
                        <h4 style="color: var(--color-accent); margin: 0 0 14px 0; font-size: 15px; font-weight: 800;">
                            🛠️ นโยบายการบันทึกข้อมูล (Sync Policies)
                        </h4>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <label class="form-label">📅 การระบุวันที่คัดกรอง (Date Mapping):</label>
                                <select id="cfg-date-mode" name="date_mode" class="form-select">
                                    <option value="screening_date">✅ ใช้วันที่ อสม. ลงพื้นที่ตรวจจริง (แนะนำสำหรับ HDC)</option>
                                    <option value="sync_date">⏱️ ใช้วันที่กดซิงค์ข้อมูล (System Date)</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">🔍 กรณีพบข้อมูลเดิมในปีงบประมาณนี้:</label>
                                <select id="cfg-overwrite-mode" name="overwrite_mode" class="form-select">
                                    <option value="skip_existing">🛡️ ข้ามรายการ (Skip) - ไม่บันทึกซ้ำ</option>
                                    <option value="update_newer">🔄 อัปเดตข้อมูลใหม่ (Update)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                        <button type="button" onclick="testJHCISConnection()" class="btn-action-secondary">
                            <span>🔌</span> ทดสอบการเชื่อมต่อ & เช็ครหัส รพ.สต.
                        </button>
                        <button type="submit" class="btn-action-primary">
                            <span>💾</span> บันทึกการตั้งค่า JHCIS
                        </button>
                    </div>
                </form>

                <!-- Connection Help & Guide Box -->
                <div style="background: var(--bg-darker); border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; margin-top: 24px;">
                    <h4 style="color: var(--color-primary); margin: 0 0 12px 0; font-size: 15px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                        <span>💡</span> คำแนะนำการเชื่อมต่อฐานข้อมูล JHCIS (Localhost / Local IP ใน รพ.สต.)
                    </h4>
                    <div style="font-size: 13px; color: var(--text-secondary); line-height: 1.7;">
                        <div style="margin-bottom: 10px;">
                            <strong style="color: var(--text-primary);">1. กรณีเว็บรันบนเครื่องใน รพ.สต. (Local Server / XAMPP / วงแลนเดียวกัน):</strong><br>
                            • ถ้าเว็บอยู่เครื่องเดียวกับ JHCIS: ให้ตั้ง Host เป็น <code>localhost</code> หรือ <code>127.0.0.1</code> Port <code>3333</code><br>
                            • ถ้าเว็บอยู่คนละเครื่องแต่อยู่วงแลนเดียวกัน: ให้ใส่หมายเลข IP ของเครื่องแม่ข่าย JHCIS (เช่น <code>192.168.1.100</code>) Port <code>3333</code>
                        </div>
                        <div style="margin-bottom: 10px;">
                            <strong style="color: var(--text-primary);">2. กรณีเว็บรันบน Cloud / อินเทอร์เน็ตภายนอก:</strong><br>
                            • สามารถกดปุ่ม <strong style="color:#10B981;">"📥 ส่งออกไฟล์ SQL สำหรับนำเข้า JHCIS"</strong> จากแท็บซิงค์ด่วน เพื่อนำไฟล์ไปรันใน JHCIS ได้ทันที สะดวก ปลอดภัย ไม่ต้องเปิด Port ใน Router<br>
                            • หรือใช้โปรแกรม <strong>RedAlert Station</strong> ที่ติดตั้งบนเครื่องใน รพ.สต. เพื่อช่วยเชื่อมโยงข้อมูลกับ JHCIS ท้องถิ่นอัตโนมัติ
                        </div>
                        <div>
                            <strong style="color: var(--text-primary);">3. การตรวจสอบเบื้องต้นเมื่อเชื่อมต่อไม่ได้:</strong><br>
                            • ตรวจสอบว่าเปิดโปรแกรม JHCIS และ Service <code>MySQL_JHCIS</code> ทำงานอยู่<br>
                            • ตรวจสอบว่า Firewall บนเครื่อง JHCIS ไม่ได้บล็อก Port <code>3333</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: SYNC AUDIT LOGS -->
        <div id="tab-logs" class="tab-content" style="display: <?= $active_tab === 'logs' ? 'block' : 'none' ?>;">
            <div class="jhcis-card">
                <h3 style="color: var(--color-accent); margin: 0 0 16px 0; font-size: 17px; font-weight: 800; display: flex; align-items: center; justify-content: space-between;">
                    <span style="display: flex; align-items: center; gap: 8px;">
                        <span>📜</span> ประวัติการซิงค์ข้อมูลเข้า JHCIS (Sync Audit Trail)
                    </span>
                    <button type="button" onclick="loadSyncLogs()" class="btn-action-secondary" style="padding: 6px 12px; font-size: 12px;">
                        🔄 รีเฟรชประวัติ
                    </button>
                </h3>

                <div class="table-responsive">
                    <table class="custom-table" id="logs-table">
                        <thead>
                            <tr>
                                <th>วัน-เวลาที่ซิงค์</th>
                                <th>หน่วยบริการ</th>
                                <th>ช่วงข้อมูล</th>
                                <th>จำนวนรวม</th>
                                <th>สำเร็จ</th>
                                <th>ข้าม/ซ้ำ</th>
                                <th>เวลาที่ใช้</th>
                                <th>ผู้ดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody id="logs-tbody">
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 20px; color: var(--text-secondary);">
                                    กำลังโหลดประวัติการซิงค์...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Controller -->
    <script>
        const currentHoscode = '<?= htmlspecialchars($selected_hoscode) ?>';

        function switchTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');

            document.getElementById('tab-' + tabId).style.display = 'block';
            event.currentTarget.classList.add('active');

            if (tabId === 'settings') loadJHCISSettings();
            if (tabId === 'logs') loadSyncLogs();
            if (tabId === 'sync') loadSyncPreview();
        }

        function logConsole(msg) {
            const consoleBox = document.getElementById('sync-console-log');
            const time = new Date().toLocaleTimeString('th-TH');
            consoleBox.innerHTML += `<div>[${time}] ${msg}</div>`;
            consoleBox.scrollTop = consoleBox.scrollHeight;
        }

        async function apiFetch(url, options = {}) {
            const response = await fetch(url, options);
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                if (text && text.trim().length > 0 && text.length < 300) {
                    throw new Error(text.trim());
                }
                throw new Error('ไม่สามารถเชื่อมต่อไปยังเครื่องแม่ข่ายปลายทางได้ (เนื่องจากเว็บไซต์อยู่บนคลาวด์ภายนอก จึงไม่สามารถต่อตรงเข้า Local IP หรือ localhost ใน รพ.สต. ได้ กรุณาใช้ปุ่ม "ส่งออกไฟล์ SQL" หรือโปรแกรม RedAlert Station)');
            }
        }

        // Load Settings
        function loadJHCISSettings() {
            apiFetch(`../api/jhcis_sync.php?action=get_config&hoscode=${currentHoscode}`)
                .then(data => {
                    if (data.status === 'success' && data.config) {
                        const cfg = data.config;
                        document.getElementById('cfg-host').value = cfg.jhcis_host || 'localhost';
                        document.getElementById('cfg-port').value = cfg.jhcis_port || 3333;
                        document.getElementById('cfg-dbname').value = cfg.jhcis_dbname || 'jhcisdb';
                        document.getElementById('cfg-user').value = cfg.jhcis_user || 'root';
                        document.getElementById('cfg-pass').value = cfg.jhcis_pass_masked || '';
                        document.getElementById('cfg-date-mode').value = cfg.date_mode || 'screening_date';
                        document.getElementById('cfg-overwrite-mode').value = cfg.overwrite_mode || 'skip_existing';
                    }
                })
                .catch(err => logConsole('เกิดข้อผิดพลาดในการโหลดการตั้งค่า: ' + err.message));
        }

        // Save Settings
        function saveJHCISSettings(e) {
            e.preventDefault();
            const form = document.getElementById('form-jhcis-settings');
            const formData = new FormData(form);
            formData.append('action', 'save_config');
            formData.append('hoscode', currentHoscode);

            apiFetch('../api/jhcis_sync.php', {
                method: 'POST',
                body: formData
            })
            .then(data => {
                if (data.status === 'success') {
                    alert('✅ ' + data.message);
                    logConsole('บันทึกการตั้งค่า JHCIS สำเร็จ');
                } else {
                    alert('❌ ' + data.message);
                }
            })
            .catch(err => alert('บันทึกไม่สำเร็จ: ' + err.message));
        }

        // Test Connection
        function testJHCISConnection() {
            logConsole('กำลังทดสอบการเชื่อมต่อไปยังฐานข้อมูล JHCIS...');
            const formData = new FormData();
            formData.append('action', 'test_connection');
            formData.append('hoscode', currentHoscode);
            formData.append('jhcis_host', document.getElementById('cfg-host') ? document.getElementById('cfg-host').value : '');
            formData.append('jhcis_port', document.getElementById('cfg-port') ? document.getElementById('cfg-port').value : '');
            formData.append('jhcis_dbname', document.getElementById('cfg-dbname') ? document.getElementById('cfg-dbname').value : '');
            formData.append('jhcis_user', document.getElementById('cfg-user') ? document.getElementById('cfg-user').value : '');
            formData.append('jhcis_pass', document.getElementById('cfg-pass') ? document.getElementById('cfg-pass').value : '');

            apiFetch('../api/jhcis_sync.php', {
                method: 'POST',
                body: formData
            })
            .then(data => {
                if (data.status === 'success') {
                    const detectedPcu = data.detected_pcucode || 'ไม่ระบุ';
                    const detectedName = data.detected_hosname || '';
                    
                    document.getElementById('detected-hosp-name').innerText = `[${detectedPcu}] ${detectedName}`;
                    const badge = document.getElementById('detected-hosp-badge');
                    badge.style.display = 'inline-flex';
                    badge.className = 'stat-badge success';
                    badge.innerText = 'เชื่อมต่อแล้ว';

                    logConsole(`✅ เชื่อมต่อสำเร็จ! JHCIS PCU: [${detectedPcu}] ${detectedName} (Version: ${data.db_version})`);
                    alert(`✅ เชื่อมต่อสำเร็จ!\n- รหัสสถานบริการใน JHCIS: [${detectedPcu}] ${detectedName}\n- เวอร์ชัน MySQL: ${data.db_version}\n- ตารางที่พบ: ${data.tables_found.join(', ')}`);
                } else {
                    logConsole(`❌ เชื่อมต่อล้มเหลว: ${data.message}`);
                    alert(`❌ เชื่อมต่อล้มเหลว:\n${data.message}`);
                }
            })
            .catch(err => {
                logConsole(`❌ ข้อผิดพลาด: ${err.message}`);
                alert('ไม่สามารถเชื่อมต่อฐานข้อมูลได้:\n' + err.message);
            });
        }

        // Load Sync Preview
        function loadSyncPreview() {
            apiFetch(`../api/jhcis_sync.php?action=get_sync_preview&hoscode=${currentHoscode}`)
                .then(data => {
                    if (data.status === 'success') {
                        const sum = data.summary;
                        const matched = data.matched_count || 0;
                        const cross = data.cross_hospital_count || 0;

                        document.getElementById('badge-matched-count').innerText = Number(matched).toLocaleString();
                        document.getElementById('badge-cross-count').innerText = Number(cross).toLocaleString();
                        
                        if (cross > 0) {
                            document.getElementById('match-summary-text').innerHTML = `<span style="color:#10B981;">ตรงกัน ${matched} คน</span> | <span style="color:#F59E0B;">ต่าง รพ.สต. ${cross} คน</span>`;
                        } else {
                            document.getElementById('match-summary-text').innerHTML = `<span style="color:#10B981;">🟢 ข้อมูลตรงกับ รพ.สต. นี้ 100% (${matched} คน)</span>`;
                        }

                        // Render Breakdown Chips
                        const chipBox = document.getElementById('breakdown-chips-box');
                        if (data.breakdown && data.breakdown.length > 0) {
                            let chipHtml = '';
                            data.breakdown.forEach(b => {
                                const isTarget = b.is_target;
                                const dot = isTarget ? '🟢' : '🔄';
                                const badgeClass = isTarget ? 'stat-badge success' : 'stat-badge warning';
                                chipHtml += `
                                    <div class="hospital-chip">
                                        <span>${dot} [${b.hoscode}] ${b.hosname}:</span>
                                        <span class="${badgeClass}">${b.pending_count} คน</span>
                                    </div>
                                ` ;
                            });
                            chipBox.innerHTML = chipHtml;
                        } else {
                            chipBox.innerHTML = '<span style="font-size:12px; color:var(--text-secondary);">ไม่มีข้อมูลคัดกรองค้าง</span>';
                        }

                        // Render Preview Table
                        const tbody = document.getElementById('preview-tbody');
                        if (!data.samples || data.samples.length === 0) {
                            tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:20px; color:var(--text-secondary);">ไม่พบข้อมูลคัดกรอง</td></tr>`;
                            return;
                        }

                        let html = '';
                        data.samples.forEach((row, i) => {
                            const isSynced = row.is_synced_jhcis == 1;
                            const isMatched = row.is_matched;

                            const syncBadge = isSynced 
                                ? `<span class="stat-badge success">✅ ซิงค์แล้ว</span>`
                                : `<span class="stat-badge ready">⏳ รอซิงค์</span>`;

                            const hcBadge = isMatched
                                ? `<span class="stat-badge success" title="${row.hosname}">🟢 [${row.hoscode}] ตรงกัน</span>`
                                : `<span class="stat-badge cross" title="${row.hosname}">🔄 [${row.hoscode}] ต่าง รพ.สต.</span>`;

                            html += `
                                <tr>
                                    <td>${i + 1}</td>
                                    <td><strong>${row.target_cid}</strong></td>
                                    <td>${row.first_name} ${row.last_name}</td>
                                    <td>${row.house_no} ม.${row.moo}</td>
                                    <td>${hcBadge}</td>
                                    <td>${row.screening_date || '-'}</td>
                                    <td><strong>${row.sys_bp1 || '-'}/${row.dia_bp1 || '-'}</strong></td>
                                    <td>${row.dtx_value ? row.dtx_value + ' mg/dL' : '-'}</td>
                                    <td>${syncBadge}</td>
                                </tr>
                            `;
                        });
                        tbody.innerHTML = html;
                    }
                })
                .catch(err => logConsole('ไม่สามารถโหลดตัวอย่างข้อมูลได้: ' + err.message));
        }

        // Start Sync Process
        function startSyncProcess() {
            const crossPolicy = document.getElementById('select-cross-policy').value;
            const btn = document.getElementById('btn-start-sync');
            btn.disabled = true;
            btn.innerHTML = `<span>⏳</span> กำลังตรวจสอบและซิงค์ข้อมูล...`;

            document.getElementById('sync-status-badge').className = 'stat-badge warning';
            document.getElementById('sync-status-badge').innerText = 'กำลังทำงาน...';
            document.getElementById('sync-result-card').style.display = 'none';

            // Animate progress
            let progress = 10;
            const progressBar = document.getElementById('progress-bar');
            const progressPercent = document.getElementById('progress-percent');
            const progressText = document.getElementById('progress-text');
            const progressDetail = document.getElementById('progress-detail');

            progressBar.style.width = '15%';
            progressPercent.innerText = '15%';
            progressText.innerText = 'สถานะ: กำลังตรวจสอบรหัสสถานบริการและจับคู่ CID...';
            progressDetail.innerText = 'กำลังประมวลผลข้อมูลตามนโยบายตรวจสอบ รพ.สต.';
            logConsole(`เริ่มกระบวนการซิงค์ข้อมูล (นโยบายข้าม รพ.สต.: ${crossPolicy})...`);

            const formData = new FormData();
            formData.append('action', 'execute_sync');
            formData.append('hoscode', currentHoscode);
            formData.append('cross_hospital_mode', crossPolicy);

            const timer = setInterval(() => {
                if (progress < 85) {
                    progress += 15;
                    progressBar.style.width = progress + '%';
                    progressPercent.innerText = progress + '%';
                }
            }, 300);

            apiFetch('../api/jhcis_sync.php', {
                method: 'POST',
                body: formData
            })
            .then(data => {
                clearInterval(timer);
                progressBar.style.width = '100%';
                progressPercent.innerText = '100%';

                if (data.status === 'success') {
                    progressText.innerText = 'สถานะ: ซิงค์ข้อมูลเสร็จสมบูรณ์ 100%';
                    progressDetail.innerText = `สำเร็จ ${data.success} รายการ | ข้าม ${data.skipped} รายการ | ต่าง รพ.สต. ${data.cross_hospital_skipped || 0} รายการ | ใช้เวลา ${data.duration} วินาที`;
                    
                    document.getElementById('sync-status-badge').className = 'stat-badge success';
                    document.getElementById('sync-status-badge').innerText = 'เสร็จสมบูรณ์';

                    document.getElementById('res-success').innerText = data.success;
                    document.getElementById('res-skipped').innerText = data.skipped;
                    document.getElementById('res-cross-skipped').innerText = data.cross_hospital_skipped || 0;
                    document.getElementById('res-time').innerText = data.duration + 's';
                    document.getElementById('res-message').innerText = data.message;
                    document.getElementById('sync-result-card').style.display = 'block';

                    logConsole(`✅ ${data.message}`);
                    loadSyncPreview();
                } else {
                    progressText.innerText = 'สถานะ: เกิดข้อผิดพลาด';
                    progressDetail.innerText = data.message;
                    document.getElementById('sync-status-badge').className = 'stat-badge warning';
                    document.getElementById('sync-status-badge').innerText = 'พบข้อผิดพลาด';
                    logConsole(`❌ ${data.message}`);
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                }
            })
            .catch(err => {
                clearInterval(timer);
                progressText.innerText = 'สถานะ: เชื่อมต่อล้มเหลว';
                logConsole(`❌ ${err.message}`);
                alert('เชื่อมต่อล้มเหลว:\n' + err.message);
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = `<span>🚀</span> เริ่มซิงค์ข้อมูลเข้า JHCIS ทันที (Direct / Local)`;
            });
        }

        // Export SQL Script
        function exportSqlScript() {
            const markSynced = confirm("ต้องการมาร์กสถานะรายการที่ส่งออกว่า 'ซิงค์แล้ว' ในระบบด้วยหรือไม่?\n\n• กด [ตกลง / OK] เพื่อดาวน์โหลดไฟล์ SQL และมาร์กสถานะว่าซิงค์แล้ว\n• กด [ยกเลิก / Cancel] เพื่อดาวน์โหลดไฟล์ SQL อย่างเดียว (คงสถานะรอซิงค์ไว้)");
            const markParam = markSynced ? '&mark_synced=1' : '';
            window.open(`../api/jhcis_sync.php?action=export_sql&hoscode=${currentHoscode}${markParam}`, '_blank');
            logConsole("📥 เริ่มดาวน์โหลดไฟล์ SQL สำหรับนำเข้า JHCIS เรียบร้อยแล้ว");
            if (markSynced) {
                setTimeout(() => { loadSyncPreview(); }, 1500);
            }
        }

        // Load Logs
        function loadSyncLogs() {
            apiFetch(`../api/jhcis_sync.php?action=get_logs&hoscode=${currentHoscode}`)
                .then(data => {
                    const tbody = document.getElementById('logs-tbody');
                    if (!data.logs || data.logs.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:20px; color:var(--text-secondary);">ยังไม่มีประวัติการซิงค์</td></tr>`;
                        return;
                    }

                    let html = '';
                    data.logs.forEach(log => {
                        html += `
                            <tr>
                                <td>${log.created_at}</td>
                                <td><strong>${log.hoscode}</strong></td>
                                <td>${log.date_range || '-'}</td>
                                <td>${log.total_records}</td>
                                <td style="color:#10B981; font-weight:800;">${log.success_records}</td>
                                <td style="color:#F59E0B;">${log.skipped_records}</td>
                                <td>${log.duration_seconds}s</td>
                                <td>${log.synced_by}</td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                })
                .catch(err => logConsole('ไม่สามารถโหลดประวัติได้: ' + err.message));
        }

        // Initial Load
        document.addEventListener('DOMContentLoaded', () => {
            loadSyncPreview();
            loadJHCISSettings();
        });
    </script>
</body>
</html>
