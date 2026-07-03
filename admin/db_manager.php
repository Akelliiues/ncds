<?php
// admin/db_manager.php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$admin_username = $_SESSION['admin_username'] ?? '';

// Fetch stats for Hoscodes
if ($admin_hoscode !== null) {
    $statsStmt = $pdo->prepare("
        SELECT 
            p.hoscode, 
            COUNT(DISTINCT p.cid) as total_targets,
            COUNT(DISTINCT a.assignment_id) as total_assignments,
            COUNT(DISTINCT s.screening_id) as total_screenings
        FROM target_population p
        LEFT JOIN task_assignments a ON p.cid = a.target_cid
        LEFT JOIN screening_results s ON a.assignment_id = s.assignment_id
        WHERE p.hoscode = ?
        GROUP BY p.hoscode
    ");
    $statsStmt->execute([$admin_hoscode]);
    $stats = $statsStmt->fetchAll();
} else {
    $statsQuery = "
        SELECT 
            p.hoscode, 
            COUNT(DISTINCT p.cid) as total_targets,
            COUNT(DISTINCT a.assignment_id) as total_assignments,
            COUNT(DISTINCT s.screening_id) as total_screenings
        FROM target_population p
        LEFT JOIN task_assignments a ON p.cid = a.target_cid
        LEFT JOIN screening_results s ON a.assignment_id = s.assignment_id
        GROUP BY p.hoscode
        ORDER BY p.hoscode
    ";
    $stats = $pdo->query($statsQuery)->fetchAll();
}

$hcNames = get_health_units();

$isSandbox = isSandboxMode($admin_hoscode);
$mockTargetCount = (int)$pdo->query("SELECT COUNT(*) FROM target_population WHERE cid IN ('1234567890111', '1234567890112', '1234567890113', '1234567890114')")->fetchColumn();
$mockVhvCount = (int)$pdo->query("SELECT COUNT(*) FROM vhv_users WHERE vhv_id IN ('1001', '1002', '1003')")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการฐานข้อมูล (DB Manager) - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Toggle Switch CSS */
        .switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: var(--bg-main); box-shadow: var(--neumorph-inset);
            transition: .4s; border-radius: 34px;
        }
        .slider:before {
            position: absolute; content: ""; height: 18px; width: 18px;
            left: 4px; bottom: 4px; background-color: var(--text-muted);
            box-shadow: var(--neumorph-flat); transition: .4s; border-radius: 50%;
        }
        input:checked + .slider { background-color: rgba(245, 158, 11, 0.2); }
        input:checked + .slider:before { transform: translateX(24px); background-color: var(--color-yellow); }

        .db-card {
            background-color: var(--bg-card); border-radius: var(--border-radius);
            padding: 20px; box-shadow: var(--neumorph-flat); margin-bottom: 20px;
        }

        /* ── Premium Table ──────────────────────────── */
        .db-table { width: 100%; border-collapse: separate; border-spacing: 0; color: var(--text-primary); }
        .db-table th, .db-table td {
            padding: 14px 18px; text-align: left; white-space: nowrap;
            vertical-align: middle;
        }
        .db-table thead th {
            background: linear-gradient(135deg, rgba(13,44,84,0.06), rgba(59,130,246,0.04));
            color: var(--text-secondary); font-weight: 700; font-size: 12.5px;
            text-transform: uppercase; letter-spacing: 0.8px;
            border-bottom: 2px solid var(--border-color);
        }
        .db-table thead th:first-child { border-radius: 12px 0 0 0; }
        .db-table thead th:last-child { border-radius: 0 12px 0 0; }
        .db-table tbody tr { transition: background 0.2s ease; }
        .db-table tbody tr:hover { background: rgba(59, 130, 246, 0.04); }
        .db-table tbody td { border-bottom: 1px solid var(--border-color); }
        .db-table tbody tr:last-child td { border-bottom: none; }

        .hoscode-chip {
            display: inline-block; background: var(--bg-main); color: var(--text-primary);
            font-weight: 700; font-size: 13px; padding: 4px 12px; border-radius: 8px;
            box-shadow: var(--neumorph-flat); font-family: 'Courier New', monospace;
            letter-spacing: 1.5px;
        }
        .hosname-text { font-weight: 700; color: var(--color-primary); font-size: 14.5px; }
        .stat-value { font-weight: 800; color: var(--color-accent, #3b82f6); font-size: 15px; }
        .stat-label { font-weight: 400; color: var(--text-muted); font-size: 12px; margin-left: 3px; }

        /* ── Icon Action Buttons ──────────────────────── */
        .action-group { display: flex; align-items: center; gap: 8px; }
        .icon-btn {
            position: relative; display: inline-flex; align-items: center; justify-content: center;
            width: 38px; height: 38px; border-radius: 10px; border: none;
            cursor: pointer; transition: all 0.25s ease; text-decoration: none;
        }
        .icon-btn svg { width: 18px; height: 18px; pointer-events: none; }
        .icon-btn-blue {
            background: rgba(59,130,246,0.08); color: var(--color-accent, #3b82f6);
            border: 1px solid rgba(59,130,246,0.2);
        }
        .icon-btn-blue:hover {
            background: var(--color-accent, #3b82f6); color: #fff;
            transform: translateY(-2px); box-shadow: 0 4px 14px rgba(59,130,246,0.35);
        }
        .icon-btn-red {
            background: rgba(239,68,68,0.08); color: var(--color-red, #ef4444);
            border: 1px solid rgba(239,68,68,0.2);
        }
        .icon-btn-red:hover {
            background: var(--color-red, #ef4444); color: #fff;
            transform: translateY(-2px); box-shadow: 0 4px 14px rgba(239,68,68,0.35);
        }
        /* Tooltip */
        .icon-btn[data-tip]::after {
            content: attr(data-tip); position: absolute; bottom: calc(100% + 8px);
            left: 50%; transform: translateX(-50%) scale(0.85);
            background: var(--bg-card, #1e293b); color: var(--text-primary);
            font-size: 12px; font-weight: 600; padding: 5px 12px; border-radius: 8px;
            white-space: nowrap; pointer-events: none; opacity: 0;
            transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: 1px solid var(--border-color); z-index: 100;
        }
        .icon-btn[data-tip]::before {
            content: ''; position: absolute; bottom: calc(100% + 2px);
            left: 50%; transform: translateX(-50%) scale(0.85);
            border: 5px solid transparent; border-top-color: var(--border-color);
            pointer-events: none; opacity: 0; transition: all 0.2s ease; z-index: 100;
        }
        .icon-btn[data-tip]:hover::after,
        .icon-btn[data-tip]:hover::before {
            opacity: 1; transform: translateX(-50%) scale(1);
        }

        /* btn-danger for sandbox section */
        .btn-danger {
            background-color: rgba(239,68,68,0.1); color: var(--color-red);
            border: 1px solid var(--color-red); padding: 7px 14px; border-radius: 8px;
            cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.2s;
        }
        .btn-danger:hover { background-color: var(--color-red); color: white; }
    </style>
</head>
<body class="admin-dashboard">
    <div class="admin-wrapper" style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <h1 style="color: var(--color-accent); margin: 0; font-size: 28px;">⚙️ จัดการฐานข้อมูลระบบ (DB Manager)</h1>
                <p style="color: var(--text-secondary); margin: 5px 0 0 0;">
                    <?= $admin_hoscode !== null ? 'สิทธิ์แอดมินระดับหน่วยบริการ (Area Admin)' : 'สิทธิ์ผู้ดูแลระบบสูงสุดระดับอำเภอ (Super Admin - สสอ.ตาลสุม)' ?>
                </p>
            </div>
            <a href="index.php" class="btn-giant btn-giant-secondary" style="margin: 0; padding: 10px 20px; font-size: 14px;">← กลับหน้าแดชบอร์ด</a>
        </div>

        <!-- Sandbox & Mode Settings Card -->
        <div class="db-card" style="margin-bottom: 20px; display: flex; flex-direction: column; gap: 15px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h3 style="color: var(--color-primary); margin: 0; font-size: 18px; display: flex; align-items: center; gap: 8px;">
                        🛡️ โหมดการทดสอบระบบ (Sandbox Mode)
                    </h3>
                    <p style="color: var(--text-secondary); margin: 5px 0 0 0; font-size: 13px;">
                        <?= $admin_hoscode !== null ? 'เปิดโหมดจำลองเพื่อทดสอบระบบ/จัดอบรม อสม. ในเขต รพ.สต. ของท่าน หรือปิดเพื่อสลับคืนโหมดทำงานจริง' : 'เปิดปิดสถานะโหมดจำลองเริ่มต้นของทั้งอำเภอ (หาก รพ.สต. ใดไม่ได้สลับการทำงานส่วนตัว จะอิงตามโหมดเริ่มต้นนี้)' ?>
                    </p>
                </div>
                
                <!-- Toggle Switch Neumorphic style -->
                <div style="display: flex; align-items: center; gap: 10px; background: var(--bg-darker); padding: 8px 16px; border-radius: 50px; box-shadow: var(--neumorph-inset);">
                    <span style="font-size: 14px; font-weight: bold; color: <?= $isSandbox ? 'var(--color-yellow)' : 'var(--color-green)' ?>;">
                        <?= $isSandbox ? '⚙️ โหมดจำลอง (Sandbox)' : '🚀 โหมดจริง (Production)' ?>
                    </span>
                    <label class="switch">
                        <input type="checkbox" id="sandbox-toggle" onchange="toggleSandboxMode(this, '<?= htmlspecialchars($admin_hoscode ?? '') ?>')" <?= $isSandbox ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
            
            <?php if ($mockTargetCount > 0 || $mockVhvCount > 0): ?>
                <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 12px; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div style="font-size: 13px; color: var(--text-secondary);">
                        <strong style="color: var(--color-yellow);">⚠️ ตรวจพบข้อมูลจำลองทดสอบคงค้างในฐานข้อมูล:</strong><br>
                        มีประชากรจำลอง <strong><?= $mockTargetCount ?></strong> ราย และ บัญชี อสม. จำลอง <strong><?= $mockVhvCount ?></strong> บัญชี (และใบงาน/ผลคัดกรองที่เกี่ยวข้อง)
                    </div>
                    <button class="btn-danger" style="margin: 0; font-size: 13px; display: flex; align-items: center; gap: 5px;" onclick="clearMockData()">
                        🗑️ ล้างข้อมูลจำลองและบัญชีทดสอบทั้งหมด
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($admin_hoscode !== null): ?>
            <!-- Area Admin: Hospital Details and info card -->
            <div class="db-card" style="border-left: 4px solid var(--color-primary); background: rgba(13, 44, 84, 0.03);">
                <h3 style="color: var(--color-primary); margin-top: 0; display: flex; align-items: center; gap: 8px;">
                    ℹ️ ข้อมูลหน่วยบริการ & ขอบเขตความดูแล
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; font-size: 14px; color: var(--text-secondary);">
                    <div>
                        <strong>ชื่อหน่วยบริการ:</strong> <span style="color: var(--text-primary); font-weight: bold;"><?= htmlspecialchars($hcNames[$admin_hoscode] ?? 'ไม่ระบุ') ?></span>
                    </div>
                    <div>
                        <strong>รหัสหน่วยบริการ:</strong> <span style="color: var(--text-primary); font-weight: bold;"><?= htmlspecialchars($admin_hoscode) ?></span>
                    </div>
                    <div>
                        <strong>ขอบเขตสิทธิ์:</strong> <span style="color: var(--color-green); font-weight: bold;">ดูแลเฉพาะเขตพื้นที่ตนเอง (รพ.สต. Isolated)</span>
                    </div>
                </div>
                <p style="color: var(--text-secondary); line-height: 1.6; font-size: 13.5px; margin-top: 15px; border-top: 1px dashed var(--border-color); padding-top: 12px; margin-bottom: 0;">
                    💡 <strong>คำชี้แจงในการควบคุมโหมดทดสอบ:</strong><br>
                    การสลับเปิด/ปิดโหมดจำลอง (Sandbox Mode) ในหน้านี้จะมีผลควบคุมเฉพาะการแสดงผลของ อสม. และใบงานคัดกรองของประชากรที่สังกัด <strong><?= htmlspecialchars($hcNames[$admin_hoscode] ?? 'หน่วยบริการของท่าน') ?></strong> เท่านั้น โดยจะไม่มีผลกระทบใดๆ ต่อการคัดกรองหรือข้อมูลของ รพ.สต. แห่งอื่นในอำเภอตาลสุม
                </p>
            </div>
        <?php else: ?>
            <!-- Super Admin: Warning card -->
            <div class="db-card">
                <h3 style="color: var(--color-red); margin-top: 0;">⚠️ ข้อควรระวังในการเคลียร์ข้อมูล</h3>
                <p style="color: var(--text-secondary); line-height: 1.6; font-size: 14px;">
                    การกดปุ่มเคลียร์ข้อมูล จะทำการลบข้อมูล <strong>กลุ่มเป้าหมาย (target_population), การมอบหมายงาน (task_assignments) และผลการคัดกรอง (screening_results)</strong> ของ รพ.สต. ที่เลือก <strong><u>อย่างถาวร</u></strong><br>
                    ระบบจะลบข้อมูลที่เกี่ยวโยงกันทั้งหมดโดยอัตโนมัติ กรุณาตรวจสอบให้แน่ใจก่อนดำเนินการ เหมาะสำหรับกรณีที่ต้องการล้างข้อมูลเพื่ออัปโหลดไฟล์ Excel ใหม่
                </p>
            </div>
        <?php endif; ?>

        <div class="db-card" style="padding: 0; overflow: hidden;">
            <table class="db-table">
                <thead>
                    <tr>
                        <th>รหัส</th>
                        <th>ชื่อหน่วยบริการ</th>
                        <th>เป้าหมาย</th>
                        <th>ผลคัดกรอง</th>
                        <?php if ($admin_hoscode === null): ?>
                            <th style="text-align: center;">Sandbox</th>
                            <th style="text-align: center;">จัดการ</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $row): ?>
                        <?php $hosSandbox = isSandboxMode($row['hoscode']); ?>
                        <tr>
                            <td><span class="hoscode-chip"><?= htmlspecialchars($row['hoscode']) ?></span></td>
                            <td><span class="hosname-text"><?= $hcNames[$row['hoscode']] ?? 'ไม่ระบุ' ?></span></td>
                            <td><span class="stat-value"><?= number_format($row['total_targets']) ?></span><span class="stat-label">ราย</span></td>
                            <td><span class="stat-value"><?= number_format($row['total_screenings']) ?></span><span class="stat-label">รายการ</span></td>
                            <?php if ($admin_hoscode === null): ?>
                                <td style="text-align: center;">
                                    <label class="switch">
                                        <input type="checkbox" onchange="toggleSandboxMode(this, '<?= htmlspecialchars($row['hoscode']) ?>')" <?= $hosSandbox ? 'checked' : '' ?>>
                                        <span class="slider"></span>
                                    </label>
                                </td>
                                <td>
                                    <?php if ($row['total_targets'] > 0): ?>
                                        <div class="action-group" style="justify-content: center;">
                                            <a href="db_records.php?hoscode=<?= urlencode($row['hoscode']) ?>" class="icon-btn icon-btn-blue" data-tip="จัดการรายบุคคล">
                                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                            </a>
                                            <button class="icon-btn icon-btn-red" data-tip="ล้างข้อมูล รพ.สต." onclick="clearData('<?= htmlspecialchars($row['hoscode']) ?>', '<?= $hcNames[$row['hoscode']] ?? $row['hoscode'] ?>')">
                                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div style="text-align: center;"><span style="color: var(--text-muted); font-size: 12px;">—</span></div>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function clearData(hoscode, name) {
            if (confirm(`⚠️ ยืนยันการล้างข้อมูลของ [${name}]?\n\nข้อมูลเป้าหมายและการคัดกรองทั้งหมดของพื้นที่นี้จะถูกลบอย่างถาวรและไม่สามารถกู้คืนได้!`)) {
                
                // Double confirmation for safety
                let check = prompt(`พิมพ์รหัส รพ.สต. "${hoscode}" เพื่อยืนยันการลบข้อมูล:`);
                if (check !== hoscode) {
                    alert("รหัสไม่ตรงกัน ยกเลิกการลบข้อมูล");
                    return;
                }

                fetch('../api/admin_db.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'action': 'clear_hoscode',
                        'hoscode': hoscode
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(`ล้างข้อมูลสำเร็จแล้ว\nจำนวนประชากรที่ถูกลบ: ${data.deleted_count} ราย`);
                        window.location.reload();
                    } else {
                        alert("เกิดข้อผิดพลาด: " + data.message);
                    }
                })
                .catch(err => {
                    alert("เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย");
                });
            }
        }

        function toggleSandboxMode(element, targetHoscode = '') {
            const isChecked = element.checked;
            const modeVal = isChecked ? '1' : '0';
            
            fetch('../api/toggle_sandbox.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'sandbox_mode': modeVal,
                    'target_hoscode': targetHoscode
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert("เกิดข้อผิดพลาด: " + data.message);
                    element.checked = !isChecked;
                }
            })
            .catch(err => {
                alert("เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย");
                element.checked = !isChecked;
            });
        }

        function clearMockData() {
            if (confirm("⚠️ ยืนยันการล้างข้อมูลจำลองและบัญชีทดสอบทั้งหมด?\n\nข้อมูลจำลอง 4 คน (นายดำ, นางแดง, เขียว, ขาว) และ อสม. จำลอง (1001, 1002, 1003) พร้อมใบงาน/ผลคัดกรองจำลองทั้งหมดจะถูกลบอย่างถาวรจากฐานข้อมูล!")) {
                
                let check = prompt('พิมพ์คำว่า "ลบข้อมูลทดสอบ" เพื่อยืนยัน:');
                if (check !== "ลบข้อมูลทดสอบ") {
                    alert("พิมพ์ข้อความไม่ถูกต้อง ยกเลิกการลบข้อมูล");
                    return;
                }

                fetch('../api/admin_db.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'action': 'clear_mock_data'
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(`ล้างข้อมูลสำเร็จแล้ว!\n- ลบประชากรเป้าหมายจำลอง: ${data.deleted_targets} ราย\n- ลบบัญชี อสม. จำลอง: ${data.deleted_vhvs} บัญชี`);
                        window.location.reload();
                    } else {
                        alert("เกิดข้อผิดพลาด: " + data.message);
                    }
                })
                .catch(err => {
                    alert("เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย");
                });
            }
        }
    </script>
</body>
</html>
