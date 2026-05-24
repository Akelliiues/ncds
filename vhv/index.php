<?php
// vhv/index.php
session_start();

if (!isset($_SESSION['vhv_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$vhvId = $_SESSION['vhv_id'];
$vhvName = $_SESSION['vhv_name'];
$vhvMoo = $_SESSION['vhv_moo'];
$vhidCode = $_SESSION['vhid_code'];
$isLeader = $_SESSION['is_leader'];
$hoscode = $_SESSION['hoscode'];

// Fetch assigned tasks for budget year 2026
// Grouped by status
$pendingTasks = [];
$completedTasks = [];
$dpacTasks = [];
$subVhvs = [];
$db_error = '';

try {
    $pendingStmt = $pdo->prepare("
        SELECT a.assignment_id, a.assignment_status, p.cid, p.hid, p.first_name, p.last_name, p.house_no, p.moo, p.need_screen_dm, p.need_screen_ht, p.health_status_origin,
               s.skipped_reason
        FROM task_assignments a
        JOIN target_population p ON a.target_cid = p.cid
        LEFT JOIN (
            SELECT assignment_id, skipped_reason 
            FROM screening_results 
            WHERE skipped_reason IS NOT NULL
        ) s ON a.assignment_id = s.assignment_id
        WHERE a.vhv_id = ? AND a.budget_year = 2026 AND a.assignment_status IN ('pending', 'skipped')
        ORDER BY LENGTH(p.house_no), p.house_no
    ");
    $pendingStmt->execute([$vhvId]);
    $pendingTasks = $pendingStmt->fetchAll();

    $completedStmt = $pdo->prepare("
        SELECT a.assignment_id, a.assignment_status, p.cid, p.first_name, p.last_name, p.house_no, p.moo
        FROM task_assignments a
        JOIN target_population p ON a.target_cid = p.cid
        WHERE a.vhv_id = ? AND a.budget_year = 2026 AND a.assignment_status = 'completed'
        ORDER BY a.assigned_at DESC
    ");
    $completedStmt->execute([$vhvId]);
    $completedTasks = $completedStmt->fetchAll();

    // Fetch DPAC followups
    $dpacStmt = $pdo->prepare("
        SELECT f.followup_id, f.round_number, f.status, e.risk_type,
               p.cid, p.hid, p.first_name, p.last_name, p.house_no, p.moo
        FROM dpac_followups f
        JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
        JOIN target_population p ON e.cid = p.cid
        WHERE f.vhv_id = ? AND f.status = 'pending'
        ORDER BY p.moo, p.house_no
    ");
    $dpacStmt->execute([$vhvId]);
    $dpacTasks = $dpacStmt->fetchAll();

    // If leader, fetch other VHVs in the same village for password reset
    if ($isLeader) {
        $subStmt = $pdo->prepare("SELECT vhv_id, vhv_name FROM vhv_users WHERE vhid_code = ? AND vhv_id != ?");
        $subStmt->execute([$vhidCode, $vhvId]);
        $subVhvs = $subStmt->fetchAll();
    }
} catch (\Throwable $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อสม. นครตาลสุม - รายการงานคัดกรอง</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <style>
        .tabs {
            display: flex;
            background-color: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 6px;
            margin-bottom: 20px;
            box-shadow: var(--neumorph-inset);
        }
        .tab-btn {
            flex: 1;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 16px;
            font-weight: 800;
            padding: 12px;
            cursor: pointer;
            border-radius: calc(var(--border-radius) - 6px);
            transition: all var(--transition-speed);
        }
        .tab-btn.active {
            background-color: var(--bg-main);
            color: var(--color-accent);
            box-shadow: var(--neumorph-flat);
        }
        .task-card {
            background-color: var(--bg-card);
            border: none;
            border-radius: var(--border-radius);
            padding: 16px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--neumorph-flat);
            cursor: pointer;
            transition: all var(--transition-speed);
        }
        .task-card:active {
            box-shadow: var(--neumorph-inset);
            transform: scale(0.98);
        }
        .task-info h4 {
            margin: 0 0 6px 0;
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 800;
        }
        .task-info p {
            margin: 0;
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 600;
        }
        .badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 800;
            box-shadow: var(--neumorph-flat);
        }
    </style>
</head>
<body class="vhv-accessibility">
    <div class="mobile-wrapper">
        <?php if (!empty($db_error)): ?>
            <div style="background-color: rgba(239, 68, 68, 0.15); border: 2px solid var(--color-red); color: var(--color-red); padding: 16px; border-radius: var(--border-radius); margin-bottom: 20px; font-weight: bold; font-size: 15px; text-align: center;">
                เกิดข้อผิดพลาดในการโหลดข้อมูล: <?= htmlspecialchars($db_error) ?>
            </div>
        <?php endif; ?>

        <!-- VHV Info Header -->
        <div class="vhv-header">
            <h3 style="color: var(--color-accent); margin: 0; font-size: 16px; font-weight: 800;">อสม. ประจำบ้าน ตาลสุม</h3>
            <h2 style="color: var(--text-primary); margin: 6px 0; font-size: 22px; font-weight: 800;"><?= htmlspecialchars($vhvName) ?></h2>
            <p style="color: var(--text-secondary); margin: 0; font-size: 14px;">
                หมู่ที่ <?= $vhvMoo ?> • สังกัดรพ.สต. [<?= htmlspecialchars($hoscode) ?>]
                <?php if ($isLeader): ?>
                    • <span style="color: var(--color-accent); font-weight: bold;">ประธาน อสม.</span>
                <?php endif; ?>
            </p>
        </div>

        <!-- Leader Password Reset Tool -->
        <?php if ($isLeader && !empty($subVhvs)): ?>
            <div class="card-dark" style="padding: 16px;">
                <h4 style="color: var(--color-accent); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; font-size: 18px; font-weight: 800;">
                    🔑 รีเซ็ตรหัสผ่าน อสม. ในหมู่บ้าน
                </h4>
                <div style="display: flex; gap: 12px;">
                    <select id="reset_target_vhv" class="form-select" style="flex-grow: 1; height: 48px; font-size: 15px;">
                        <option value="">-- เลือก อสม. --</option>
                        <?php foreach ($subVhvs as $sv): ?>
                            <option value="<?= $sv['vhv_id'] ?>"><?= htmlspecialchars($sv['vhv_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button onclick="resetPassword()" class="numpad-btn btn-action" style="height: 48px; width: 120px; font-size: 14px; margin-top: 0; border-radius: var(--border-radius); font-weight: 800;">
                        รีเซ็ต "1234"
                    </button>
                </div>
                <div id="reset-result" style="margin-top: 8px; font-size: 14px; text-align: center; font-weight: bold;"></div>
            </div>
        <?php endif; ?>



        <!-- Task Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('pending-list', this)">
                งานค้าง (<?= count($pendingTasks) ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('dpac-list', this)" style="color: #b91c1c;">
                DPAC (<?= count($dpacTasks) ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('completed-list', this)">
                เสร็จสิ้น/ข้าม (<?= count($completedTasks) ?>)
            </button>
        </div>

        <!-- Pending Tasks List -->
        <div id="pending-list" class="tab-content">
            <?php if (empty($pendingTasks)): ?>
                <div style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                    🎉 เยี่ยมมาก! ไม่มีงานค้างในเขตรับผิดชอบของคุณ
                </div>
            <?php else: ?>
                <?php foreach ($pendingTasks as $pt): ?>
                    <div class="task-card" onclick="openTestModal('<?= htmlspecialchars($pt['house_no']) ?>', '<?= htmlspecialchars($pt['hid'] ?? '') ?>', '<?= htmlspecialchars($pt['cid']) ?>')">
                        <div class="task-info">
                            <h4>บ้านเลขที่ <?= htmlspecialchars($pt['house_no']) ?></h4>
                            <p>ผู้รับคัดกรอง: <?= htmlspecialchars($pt['first_name'] . ' ' . $pt['last_name']) ?></p>
                            <p style="font-size: 12px; margin-top: 4px; color: var(--text-muted);">
                                สิทธิ์การคัดกรอง: 
                                <?php if ($pt['need_screen_dm']): ?>
                                    <span style="color: var(--color-accent);">DM</span>
                                <?php endif; ?>
                                <?php if ($pt['need_screen_ht']): ?>
                                    <span style="color: var(--color-primary); margin-left: 5px;">HT</span>
                                <?php endif; ?>
                            </p>
                            <?php if ($pt['assignment_status'] === 'skipped'): ?>
                                <p style="font-size: 13px; margin-top: 6px; color: var(--color-yellow); font-weight: bold; background: rgba(245,158,11,0.1); padding: 4px 8px; border-radius: 4px; display: inline-block;">
                                    ⚠️ ข้ามชั่วคราว: <?= htmlspecialchars($pt['skipped_reason'] ?: 'ไม่อยู่บ้าน') ?> (รอคัดกรองใหม่)
                                </p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <svg width="24" height="24" fill="none" stroke="var(--border-color)" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"></path></svg>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Completed Tasks List -->
        <div id="completed-list" class="tab-content" style="display: none;">
            <?php if (empty($completedTasks)): ?>
                <div style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                    ยังไม่มีประวัติการคัดกรองที่บันทึก
                </div>
            <?php else: ?>
                <?php foreach ($completedTasks as $ct): ?>
                    <div class="task-card" style="opacity: 0.8;">
                        <div class="task-info">
                            <h4>บ้านเลขที่ <?= htmlspecialchars($ct['house_no']) ?></h4>
                            <p>ผู้รับคัดกรอง: <?= htmlspecialchars($ct['first_name'] . ' ' . $ct['last_name']) ?></p>
                            <p style="color: var(--color-green); font-size: 13px; font-weight: bold;">
                                ✅ คัดกรองสำเร็จเรียบร้อย
                            </p>
                        </div>
                        <div>
                            <span class="badge" style="background-color: rgba(16,185,129,0.2); color: var(--color-green);">เสร็จสิ้น</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- DPAC Followup List -->
        <div id="dpac-list" class="tab-content" style="display: none;">
            <?php if (empty($dpacTasks)): ?>
                <div style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                    ไม่มีงานติดตามปรับเปลี่ยนพฤติกรรม (DPAC) ในขณะนี้
                </div>
            <?php else: ?>
                <?php foreach ($dpacTasks as $dt): ?>
                    <div class="task-card" onclick="window.location.href='dpac_form.php?fid=<?= $dt['followup_id'] ?>'" style="border-left: 4px solid #b91c1c;">
                        <div class="task-info">
                            <h4>บ้านเลขที่ <?= htmlspecialchars($dt['house_no']) ?></h4>
                            <p><?= htmlspecialchars($dt['first_name'] . ' ' . $dt['last_name']) ?></p>
                            <p style="font-size: 13px; color: #b91c1c; font-weight: bold; margin-top: 4px;">
                                📌 รอบติดตามที่ <?= $dt['round_number'] ?> (เสี่ยง <?= $dt['risk_type'] ?>)
                            </p>
                        </div>
                        <div>
                            <svg width="24" height="24" fill="none" stroke="#b91c1c" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"></path></svg>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Bottom Navigation Bar -->
        <div class="bottom-nav">
            <a href="index.php" class="nav-link active">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                หน้าแรก
            </a>
            <a href="scan.php" class="nav-link nav-scan-fab fab-scan-pulse">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                <span>สแกนบ้าน</span>
            </a>
            <a href="leaderboard.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                กระดานคะแนน
            </a>
            <a href="profile.php" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                ข้อมูลส่วนตัว
            </a>
        </div>
    </div>

    <!-- Test Modal -->
    <div id="test-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div class="card-dark" style="width: 90%; max-width: 400px; padding: 24px;">
            <h3 style="color: var(--color-accent); margin-top: 0; display: flex; align-items: center; gap: 8px;">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                โหมดทดสอบคัดกรอง
            </h3>
            <p style="color: var(--text-secondary); margin-bottom: 20px; font-size: 15px; line-height: 1.5;">
                คุณต้องการเข้าสู่หน้าคัดกรองของ บ้านเลขที่ <span id="test-house-no" style="color: white; font-weight: 800; font-size: 16px;"></span> โดยไม่ผ่านการสแกน QR Code ใช่หรือไม่?
            </p>
            <div style="display: flex; gap: 12px;">
                <button type="button" onclick="closeTestModal()" class="btn-giant btn-giant-secondary" style="flex: 1; margin: 0; font-size: 16px;">ยกเลิก</button>
                <button type="button" id="btn-enter-test" class="btn-giant btn-giant-primary" style="flex: 1; margin: 0; font-size: 16px;">เข้าคัดกรอง</button>
            </div>
        </div>
    </div>

    <script>
        let currentTestHid = '';
        let currentTestCid = '';
        function openTestModal(houseNo, hid, cid) {
            document.getElementById('test-house-no').textContent = houseNo;
            currentTestHid = hid;
            currentTestCid = cid;
            document.getElementById('test-modal').style.display = 'flex';
        }
        function closeTestModal() {
            document.getElementById('test-modal').style.display = 'none';
        }
        document.getElementById('btn-enter-test').onclick = function() {
            if (currentTestHid) {
                window.location.href = 'screening_form.php?hid=' + currentTestHid;
            } else {
                window.location.href = 'screening_form.php?cid=' + currentTestCid;
            }
        };
        function switchTab(tabId, btn) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            // Remove active from all buttons
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active');
            });
            // Show selected tab & set button active
            document.getElementById(tabId).style.display = 'block';
            btn.classList.add('active');
        }

        function resetPassword() {
            const vhvId = document.getElementById('reset_target_vhv').value;
            const resDiv = document.getElementById('reset-result');
            
            if (!vhvId) {
                resDiv.style.color = 'var(--color-red)';
                resDiv.innerText = 'กรุณาเลือก อสม. ที่ต้องการรีเซ็ต';
                return;
            }

            resDiv.style.color = 'var(--text-secondary)';
            resDiv.innerText = 'กำลังดำเนินการ...';

            fetch('../api/auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'reset_password',
                    'target_vhv_id': vhvId
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    resDiv.style.color = 'var(--color-green)';
                    resDiv.innerText = 'สำเร็จ! รหัสผ่านถูกรีเซ็ตเป็น "1234" แล้ว';
                } else {
                    resDiv.style.color = 'var(--color-red)';
                    resDiv.innerText = 'ล้มเหลว: ' + data.message;
                }
            })
            .catch(err => {
                resDiv.style.color = 'var(--color-red)';
                resDiv.innerText = 'เกิดข้อผิดพลาดทางเทคนิค';
            });
        }
    </script>
</body>
</html>
