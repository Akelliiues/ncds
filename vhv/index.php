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
$isHlCoach = $_SESSION['is_hl_coach'] ?? false;

// Fetch assigned tasks for budget year 2026
// Grouped by status
$pendingTasks = [];
$completedTasks = [];
$completedDpacTasks = [];
$dpacTasks = [];
$subVhvs = [];
$db_error = '';

try {
    $pendingStmt = $pdo->prepare("
        SELECT a.assignment_id, a.assignment_status, p.cid, p.hid, p.first_name, p.last_name, p.house_no, p.moo, p.need_screen_dm, p.need_screen_ht, p.health_status_origin
        FROM task_assignments a
        JOIN target_population p ON a.target_cid = p.cid
        WHERE a.vhv_id = ? AND a.budget_year = 2026 AND a.assignment_status = 'pending'
        ORDER BY LENGTH(p.house_no), p.house_no
    ");
    $pendingStmt->execute([$vhvId]);
    $pendingTasks = $pendingStmt->fetchAll();

    $completedStmt = $pdo->prepare("
        SELECT a.assignment_id, a.assignment_status, p.cid, p.hid, p.first_name, p.last_name, p.house_no, p.moo, p.sex, p.birth,
               sr.sys_bp1, sr.dia_bp1, sr.sys_bp2, sr.dia_bp2, sr.dtx_value, sr.dtx_type,
               sr.weight, sr.height, sr.waist, sr.bmi, sr.diet_risk, sr.exercise_risk,
               sr.stress_risk, sr.smoking_risk, sr.alcohol_risk, sr.cv_risk_score,
               sr.skipped_reason, sr.advice_given,
               ht.sbp as base_sbp, ht.dbp as base_dbp, dm.bslevel as base_bslevel
        FROM task_assignments a
        JOIN target_population p ON a.target_cid = p.cid
        LEFT JOIN screening_results sr ON a.assignment_id = sr.assignment_id
        LEFT JOIN staging_hdc_ht ht ON p.cid = ht.cid
        LEFT JOIN staging_hdc_dm dm ON p.cid = dm.cid
        WHERE a.vhv_id = ? AND a.budget_year = 2026 AND a.assignment_status IN ('completed', 'skipped')
        ORDER BY a.assigned_at DESC
    ");
    $completedStmt->execute([$vhvId]);
    $completedTasks = $completedStmt->fetchAll();

    // Fetch DPAC followups
    $dpacStmt = $pdo->prepare("
        SELECT f.followup_id, f.round_number, f.status, f.skip_count, e.risk_type,
               p.cid, p.hid, p.first_name, p.last_name, p.house_no, p.moo
        FROM dpac_followups f
        JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
        JOIN target_population p ON e.cid = p.cid
        WHERE f.vhv_id = ? AND f.status = 'pending'
        ORDER BY p.moo, p.house_no
    ");
    $dpacStmt->execute([$vhvId]);
    $dpacTasks = $dpacStmt->fetchAll();

    // Fetch completed DPAC followups
    $completedDpacStmt = $pdo->prepare("
        SELECT f.followup_id, f.round_number, f.completed_at, e.risk_type,
               f.weight, f.height, f.waist, f.bp_sys, f.bp_dia, f.fbs, f.health_risk_level, f.advice_given,
               p.cid, p.first_name, p.last_name, p.house_no, p.moo, p.sex, p.birth,
               ht.sbp as base_sbp, ht.dbp as base_dbp, dm.bslevel as base_bslevel
        FROM dpac_followups f
        JOIN dpac_enrollments e ON f.enrollment_id = e.enrollment_id
        JOIN target_population p ON e.cid = p.cid
        LEFT JOIN staging_hdc_ht ht ON p.cid = ht.cid
        LEFT JOIN staging_hdc_dm dm ON p.cid = dm.cid
        WHERE f.vhv_id = ? AND f.status = 'completed'
        ORDER BY f.completed_at DESC
    ");
    $completedDpacStmt->execute([$vhvId]);
    $completedDpacTasks = $completedDpacStmt->fetchAll();

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
    <title>NCDs by อสม.อำเภอตาลสุม</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <script src="../assets/js/app.js"></script>
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
            position: relative;
            overflow: hidden;
        }
        .task-card:active {
            box-shadow: var(--neumorph-inset);
            transform: scale(0.98);
        }
        .task-card-watermark {
            position: absolute;
            right: 42px;
            bottom: -35px;
            font-size: 110px;
            font-weight: 900;
            color: rgba(185, 28, 28, 0.05);
            pointer-events: none;
            user-select: none;
            font-family: 'Outfit', sans-serif;
            z-index: 1;
            line-height: 1;
        }
        .task-info {
            position: relative;
            z-index: 2;
            min-width: 0;
            flex: 1;
        }
        .task-card > div:last-child {
            position: relative;
            z-index: 2;
            flex-shrink: 0;
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
        <div class="vhv-header" style="display: flex; align-items: center; gap: 16px; padding: 20px 16px;">
            <a href="../about.php" title="เกี่ยวกับระบบและผู้พัฒนา">
                <img src="../assets/icon.png" alt="NCDs Prevention Logo" style="width: 60px; height: 60px; border-radius: 14px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'">
            </a>
            <div style="flex-grow: 1;">
                <h3 style="color: var(--color-accent); margin: 0; font-size: 14px; font-weight: 800; letter-spacing: 0.5px;">อสม. ประจำบ้าน ตาลสุม</h3>
                <h2 style="color: var(--text-primary); margin: 4px 0; font-size: 20px; font-weight: 800;"><?= htmlspecialchars($vhvName) ?></h2>
                <p style="color: var(--text-secondary); margin: 0; font-size: 13px;">
                    หมู่ที่ <?= $vhvMoo ?> • สังกัดรพ.สต. [<?= htmlspecialchars($hoscode) ?>]
                    <?php if ($isLeader): ?>
                        • <span style="color: var(--color-accent); font-weight: bold;">ประธาน อสม.</span>
                    <?php endif; ?>
                </p>
            </div>
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
                เสร็จสิ้น/ข้าม (<?= count($completedTasks) + count($completedDpacTasks) ?>)
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
                    <div class="task-card" data-assignment-id="<?= $pt['assignment_id'] ?>" data-hid="<?= htmlspecialchars($pt['hid'] ?? '') ?>" data-cid="<?= htmlspecialchars($pt['cid']) ?>" onclick="openTestModal('<?= htmlspecialchars($pt['house_no']) ?>', '<?= htmlspecialchars($pt['hid'] ?? '') ?>', '<?= htmlspecialchars($pt['cid']) ?>')">
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
            <?php if (empty($completedTasks) && empty($completedDpacTasks)): ?>
                <div style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                    ยังไม่มีประวัติการคัดกรองที่บันทึก
                </div>
            <?php else: ?>
                <?php foreach ($completedTasks as $ct): ?>
                    <?php if ($ct['assignment_status'] === 'completed'): ?>
                        <div class="task-card" data-assignment-id="<?= $ct['assignment_id'] ?>" onclick="showScreeningDetail(<?= htmlspecialchars(json_encode($ct, JSON_UNESCAPED_UNICODE)) ?>)" style="opacity: 0.9;">
                    <?php else: ?>
                        <div class="task-card" data-assignment-id="<?= $ct['assignment_id'] ?>" data-hid="<?= htmlspecialchars($ct['hid'] ?? '') ?>" data-cid="<?= htmlspecialchars($ct['cid']) ?>" onclick="openTestModal('<?= htmlspecialchars($ct['house_no']) ?>', '<?= htmlspecialchars($ct['hid'] ?? '') ?>', '<?= htmlspecialchars($ct['cid']) ?>')" style="opacity: 0.9;">
                    <?php endif; ?>
                        <div class="task-info">
                            <h4>บ้านเลขที่ <?= htmlspecialchars($ct['house_no']) ?></h4>
                            <p>ผู้รับคัดกรอง: <?= htmlspecialchars($ct['first_name'] . ' ' . $ct['last_name']) ?></p>
                            <?php if ($ct['assignment_status'] === 'completed'): ?>
                                <p style="color: var(--color-green); font-size: 13px; font-weight: bold;">
                                    ✅ คัดกรองสำเร็จเรียบร้อย (คลิกเพื่อดูรายละเอียด)
                                </p>
                            <?php else: ?>
                                <p style="color: var(--color-yellow); font-size: 13px; font-weight: bold;">
                                    ⚠️ ข้ามชั่วคราว: <?= htmlspecialchars($ct['skipped_reason'] ?: 'ไม่อยู่บ้าน') ?> (คลิกเพื่อแก้ไข/คัดกรองใหม่)
                                </p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($ct['assignment_status'] === 'completed'): ?>
                                <span class="badge" style="background-color: rgba(16,185,129,0.2); color: var(--color-green); box-shadow: none;">สำเร็จ</span>
                            <?php else: ?>
                                <span class="badge" style="background-color: rgba(245,158,11,0.2); color: var(--color-yellow); box-shadow: none;">ข้าม</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($completedDpacTasks as $cdt): ?>
                    <div class="task-card" onclick="showDpacDetail(<?= htmlspecialchars(json_encode($cdt, JSON_UNESCAPED_UNICODE)) ?>)" style="opacity: 0.9; border-left: 4px solid #b91c1c; cursor: pointer;">
                        <div class="task-card-watermark"><?= $cdt['round_number'] ?></div>
                        <div class="task-info">
                            <h4>บ้านเลขที่ <?= htmlspecialchars($cdt['house_no']) ?></h4>
                            <p>ผู้รับการติดตาม: <?= htmlspecialchars($cdt['first_name'] . ' ' . $cdt['last_name']) ?></p>
                            <p style="color: var(--color-green); font-size: 13px; font-weight: bold;">
                                ✅ ติดตาม DPAC รอบที่ <?= $cdt['round_number'] ?> สำเร็จเรียบร้อย (คลิกเพื่อดูรายละเอียด)
                            </p>
                            <p style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                                วันที่ติดตาม: <?= htmlspecialchars($cdt['completed_at']) ?>
                            </p>
                        </div>
                        <div>
                            <span class="badge" style="background-color: rgba(16,185,129,0.2); color: var(--color-green); box-shadow: none;">สำเร็จ (DPAC)</span>
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
                    <div class="task-card" data-followup-id="<?= $dt['followup_id'] ?>" onclick="window.location.href='dpac_form.php?fid=<?= $dt['followup_id'] ?>'" style="border-left: 4px solid #b91c1c;">
                        <div class="task-card-watermark"><?= $dt['round_number'] ?></div>
                        <div class="task-info">
                            <h4>บ้านเลขที่ <?= htmlspecialchars($dt['house_no']) ?></h4>
                            <p><?= htmlspecialchars($dt['first_name'] . ' ' . $dt['last_name']) ?></p>
                            <p style="font-size: 13px; color: #b91c1c; font-weight: bold; margin-top: 4px; display: flex; align-items: center; flex-wrap: wrap; gap: 6px;">
                                <span>📌 รอบติดตามที่ <?= $dt['round_number'] ?> (เสี่ยง <?= $dt['risk_type'] ?>)</span>
                                <?php if (($dt['skip_count'] ?? 0) > 0): ?>
                                    <span style="display: inline-block; background-color: #eab308; color: #0f172a; font-size: 11px; padding: 1px 8px; border-radius: 50px; font-weight: 800; border: 1px solid rgba(234, 179, 8, 0.4);">
                                        ⚠️ ข้ามแล้ว <?= $dt['skip_count'] ?>/3 ครั้ง
                                    </span>
                                <?php endif; ?>
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

        function openHistoryDetailModal() {
            document.getElementById('history-detail-modal').style.display = 'flex';
        }
        function closeHistoryDetailModal() {
            document.getElementById('history-detail-modal').style.display = 'none';
        }

        function showScreeningDetail(data) {
            document.getElementById('modal-type-title').innerText = '📊 รายละเอียดผลการคัดกรอง';
            
            let infoHtml = `
                <strong style="color: var(--text-primary); font-size: 16px;">${data.first_name} ${data.last_name}</strong>
                <p style="margin: 4px 0 0; font-size: 14px; color: var(--text-secondary);">บ้านเลขที่ ${data.house_no} หมู่ที่ ${data.moo}</p>
                <p style="margin: 4px 0 0; font-size: 12px; color: var(--text-muted);">วันที่คัดกรอง: ${data.completed_at || '-'}</p>
            `;
            document.getElementById('modal-resident-info').innerHTML = infoHtml;
            
            let bpText = `${data.sys_bp1}/${data.dia_bp1}`;
            if (data.sys_bp2) bpText += ` (ครั้งที่ 2: ${data.sys_bp2}/${data.dia_bp2})`;
            
            let dtxText = data.dtx_value ? `${data.dtx_value} mg/dL (${data.dtx_type === 'fpg' ? 'งดอาหาร (FPG)' : 'ไม่ได้งด (RPG)'})` : 'ไม่ได้ตรวจ';
            
            let measHtml = `
                <h4 style="margin: 0 0 8px 0; color: var(--color-accent); font-size: 15px;">📏 ผลการวัดร่างกาย</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 14px; color: var(--text-primary);">
                    <div>น้ำหนัก: <strong>${data.weight || '-'} กก.</strong></div>
                    <div>ส่วนสูง: <strong>${data.height || '-'} ซม.</strong></div>
                    <div>รอบเอว: <strong>${data.waist || '-'} นิ้ว</strong></div>
                    <div>BMI: <strong>${data.bmi || '-'}</strong></div>
                </div>
                <div style="margin-top: 10px; font-size: 14px; color: var(--text-primary);">
                    <div>ความดันโลหิต: <strong>${bpText} mmHg</strong></div>
                    <div>ระดับน้ำตาล (DTX): <strong>${dtxText}</strong></div>
                    <div style="margin-top: 4px;">Thai CV Risk: <strong style="color: var(--color-primary);">${data.cv_risk_score || '0'}%</strong></div>
                </div>
            `;
            document.getElementById('modal-measurements').innerHTML = measHtml;
            
            let compHtml = '<h4 style="margin: 12px 0 8px 0; color: var(--color-accent); font-size: 15px; border-top: 1px solid var(--border-color); padding-top: 12px;">🔄 เปรียบเทียบกับค่าตั้งต้น</h4>';
            let improvements = 0;
            let worsenings = 0;
            let comparedAny = false;
            
            if (data.sys_bp1 && data.base_sbp) {
                comparedAny = true;
                let diff = data.sys_bp1 - data.base_sbp;
                let diffText = diff > 0 ? `📈 เพิ่มขึ้น +${diff}` : (diff < 0 ? `📉 ลดลง ${diff}` : 'คงเดิม');
                let status = diff < 0 ? '🟢 ดีขึ้น' : (diff > 0 ? '🔴 แย่ลง' : '⚪ คงที่');
                if (diff < 0) improvements++;
                if (diff > 0) worsenings++;
                compHtml += `<div style="font-size: 13.5px; margin-bottom: 6px; color: var(--text-primary);">ตัวบนความดัน (SYS): ${data.base_sbp} -> ${data.sys_bp1} mmHg (${diffText}) | <strong>${status}</strong></div>`;
            }
            
            if (data.dtx_value && data.base_bslevel) {
                comparedAny = true;
                let diff = data.dtx_value - data.base_bslevel;
                let diffText = diff > 0 ? `📈 เพิ่มขึ้น +${diff}` : (diff < 0 ? `📉 ลดลง ${diff}` : 'คงเดิม');
                let status = diff < 0 ? '🟢 ดีขึ้น' : (diff > 0 ? '🔴 แย่ลง' : '⚪ คงที่');
                if (diff < 0) improvements++;
                if (diff > 0) worsenings++;
                compHtml += `<div style="font-size: 13.5px; margin-bottom: 6px; color: var(--text-primary);">ระดับน้ำตาล: ${data.base_bslevel} -> ${data.dtx_value} mg/dL (${diffText}) | <strong>${status}</strong></div>`;
            }
            
            if (!comparedAny) {
                compHtml += `<div style="font-size: 13.5px; margin-bottom: 6px; color: var(--text-muted); font-style: italic;">ไม่มีข้อมูลค่าตั้งต้นสำหรับการเปรียบเทียบ</div>`;
            }
            
            let summaryText = '⚪ ทรงตัว (ไม่มีการเปลี่ยนแปลงมีนัยสำคัญ)';
            let summaryColor = 'var(--text-secondary)';
            if (improvements > worsenings) {
                summaryText = '🟢 ดีขึ้น (การควบคุมสุขภาพดีขึ้น)';
                summaryColor = 'var(--color-green)';
            } else if (worsenings > improvements) {
                summaryText = '🔴 แย่ลง (ควรระมัดระวังและปรับเปลี่ยนพฤติกรรม)';
                summaryColor = 'var(--color-red)';
            }
            
            compHtml += `
                <div style="margin-top: 12px; padding: 10px; background-color: var(--bg-darker); border-radius: 8px; text-align: center; font-weight: bold; color: ${summaryColor}; font-size: 15px; border: 1px solid var(--border-color);">
                    สรุปผลการประเมิน: ${summaryText}
                </div>
            `;
            document.getElementById('modal-comparison').innerHTML = compHtml;
            
            document.getElementById('modal-advice').innerHTML = `
                <strong style="color: var(--color-green); font-size: 14px; display: block; margin-bottom: 4px;">💡 คำแนะนำโดย อสม.:</strong>
                <p style="margin: 0; font-size: 14px; color: var(--text-primary); line-height: 1.5; font-weight: 700;">${data.advice_given || 'ไม่ระบุ/ไม่มีคำแนะนำเพิ่มเติม'}</p>
            `;
            
            openHistoryDetailModal();
        }

        function showDpacDetail(data) {
            document.getElementById('modal-type-title').innerText = `📈 ติดตาม DPAC (รอบที่ ${data.round_number})`;
            
            let infoHtml = `
                <strong style="color: var(--text-primary); font-size: 16px;">${data.first_name} ${data.last_name}</strong>
                <p style="margin: 4px 0 0; font-size: 14px; color: var(--text-secondary);">บ้านเลขที่ ${data.house_no} หมู่ที่ ${data.moo}</p>
                <p style="margin: 4px 0 0; font-size: 12px; color: var(--text-muted);">วันที่ติดตาม: ${data.completed_at || '-'}</p>
            `;
            document.getElementById('modal-resident-info').innerHTML = infoHtml;
            
            let bpText = data.bp_sys ? `${data.bp_sys}/${data.bp_dia}` : 'ไม่ได้วัด';
            let fbsText = data.fbs ? `${data.fbs} mg/dL` : 'ไม่ได้ตรวจ';
            
            let measHtml = `
                <h4 style="margin: 0 0 8px 0; color: var(--color-accent); font-size: 15px;">📏 ผลการติดตามร่างกาย</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 14px; color: var(--text-primary);">
                    <div>น้ำหนัก: <strong>${data.weight || '-'} กก.</strong></div>
                    <div>ส่วนสูง: <strong>${data.height || '-'} ซม.</strong></div>
                    <div>รอบเอว: <strong>${data.waist || '-'} นิ้ว</strong></div>
                </div>
                <div style="margin-top: 10px; font-size: 14px; color: var(--text-primary);">
                    <div>ความดันโลหิต: <strong>${bpText} mmHg</strong></div>
                    <div>ระดับน้ำตาล (FBS): <strong>${fbsText}</strong></div>
                    <div style="margin-top: 4px;">ผลการประเมิน: <strong style="color: var(--color-primary);">${data.health_risk_level || '-'}</strong></div>
                </div>
            `;
            document.getElementById('modal-measurements').innerHTML = measHtml;
            
            let compHtml = '<h4 style="margin: 12px 0 8px 0; color: var(--color-accent); font-size: 15px; border-top: 1px solid var(--border-color); padding-top: 12px;">🔄 เปรียบเทียบกับค่าตั้งต้น</h4>';
            let improvements = 0;
            let worsenings = 0;
            let comparedAny = false;
            
            if (data.bp_sys && data.base_sbp) {
                comparedAny = true;
                let diff = data.bp_sys - data.base_sbp;
                let diffText = diff > 0 ? `📈 เพิ่มขึ้น +${diff}` : (diff < 0 ? `📉 ลดลง ${diff}` : 'คงเดิม');
                let status = diff < 0 ? '🟢 ดีขึ้น' : (diff > 0 ? '🔴 แย่ลง' : '⚪ คงที่');
                if (diff < 0) improvements++;
                if (diff > 0) worsenings++;
                compHtml += `<div style="font-size: 13.5px; margin-bottom: 6px; color: var(--text-primary);">ตัวบนความดัน (SYS): ${data.base_sbp} -> ${data.bp_sys} mmHg (${diffText}) | <strong>${status}</strong></div>`;
            }
            
            if (data.fbs && data.base_bslevel) {
                comparedAny = true;
                let diff = data.fbs - data.base_bslevel;
                let diffText = diff > 0 ? `📈 เพิ่มขึ้น +${diff}` : (diff < 0 ? `📉 ลดลง ${diff}` : 'คงเดิม');
                let status = diff < 0 ? '🟢 ดีขึ้น' : (diff > 0 ? '🔴 แย่ลง' : '⚪ คงที่');
                if (diff < 0) improvements++;
                if (diff > 0) worsenings++;
                compHtml += `<div style="font-size: 13.5px; margin-bottom: 6px; color: var(--text-primary);">ระดับน้ำตาล: ${data.base_bslevel} -> ${data.fbs} mg/dL (${diffText}) | <strong>${status}</strong></div>`;
            }
            
            if (!comparedAny) {
                compHtml += `<div style="font-size: 13.5px; margin-bottom: 6px; color: var(--text-muted); font-style: italic;">ไม่มีข้อมูลค่าตั้งต้นสำหรับการเปรียบเทียบ</div>`;
            }
            
            let summaryText = '⚪ ทรงตัว (ไม่มีการเปลี่ยนแปลงมีนัยสำคัญ)';
            let summaryColor = 'var(--text-secondary)';
            if (improvements > worsenings) {
                summaryText = '🟢 ดีขึ้น (การควบคุมสุขภาพดีขึ้น)';
                summaryColor = 'var(--color-green)';
            } else if (worsenings > improvements) {
                summaryText = '🔴 แย่ลง (ควรระมัดระวังและปรับเปลี่ยนพฤทีพรรม)';
                summaryColor = 'var(--color-red)';
            }
            
            compHtml += `
                <div style="margin-top: 12px; padding: 10px; background-color: var(--bg-darker); border-radius: 8px; text-align: center; font-weight: bold; color: ${summaryColor}; font-size: 15px; border: 1px solid var(--border-color);">
                    สรุปผลการประเมิน: ${summaryText}
                </div>
            `;
            document.getElementById('modal-comparison').innerHTML = compHtml;
            
            document.getElementById('modal-advice').innerHTML = `
                <strong style="color: var(--color-green); font-size: 14px; display: block; margin-bottom: 4px;">💡 คำแนะนำโดย อสม.:</strong>
                <p style="margin: 0; font-size: 14px; color: var(--text-primary); line-height: 1.5; font-weight: 700;">${data.advice_given || 'ไม่ระบุ/ไม่มีคำแนะนำเพิ่มเติม'}</p>
            `;
            
            openHistoryDetailModal();
        }
    </script>

    <!-- History Detail Modal Overlay -->
    <div id="history-detail-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(13, 44, 84, 0.4); backdrop-filter: blur(5px); z-index: 9999; align-items: center; justify-content: center;">
        <div class="card-dark" style="width: 90%; max-width: 460px; max-height: 90vh; overflow-y: auto; background: var(--bg-main); box-shadow: var(--neumorph-flat); border-radius: 28px; padding: 24px; color: var(--text-primary);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1.5px solid var(--border-color); padding-bottom: 12px;">
                <h3 id="modal-type-title" style="color: var(--color-accent); font-size: 20px; font-weight: 800; margin: 0;">รายละเอียด</h3>
                <button type="button" onclick="closeHistoryDetailModal()" style="background: none; border: none; color: var(--text-secondary); font-size: 24px; cursor: pointer; font-weight: bold; line-height: 1;">✕</button>
            </div>
            
            <div id="modal-resident-info" style="margin-bottom: 16px; background-color: var(--bg-darker); padding: 14px; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
            </div>

            <div id="modal-measurements" style="margin-bottom: 16px; background-color: var(--bg-card); padding: 14px; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
            </div>

            <div id="modal-comparison" style="margin-bottom: 16px; background-color: var(--bg-card); padding: 14px; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
            </div>

            <div id="modal-advice" style="margin-bottom: 24px; background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--color-green); padding: 14px; border-radius: var(--border-radius);">
            </div>

            <button type="button" onclick="closeHistoryDetailModal()" class="btn-giant btn-giant-primary" style="margin: 0; width: 100%; border-radius: var(--border-radius);">ปิดหน้าต่าง</button>
        </div>
    </div>

    <script>
        // Store dashboard data in localStorage for offline availability
        if (navigator.onLine) {
            localStorage.setItem('vhv_pending_tasks', JSON.stringify(<?= json_encode($pendingTasks, JSON_UNESCAPED_UNICODE) ?>));
            localStorage.setItem('vhv_completed_tasks', JSON.stringify(<?= json_encode($completedTasks, JSON_UNESCAPED_UNICODE) ?>));
            localStorage.setItem('vhv_dpac_tasks', JSON.stringify(<?= json_encode($dpacTasks, JSON_UNESCAPED_UNICODE) ?>));
            localStorage.setItem('vhv_completed_dpac_tasks', JSON.stringify(<?= json_encode($completedDpacTasks, JSON_UNESCAPED_UNICODE) ?>));
        }

        // Apply offline state modifications to UI dynamically
        document.addEventListener('DOMContentLoaded', () => {
            const queue = JSON.parse(localStorage.getItem('offline_submissions') || '[]');
            if (queue.length === 0) return;

            let pendingCountAdjust = 0;
            let dpacCountAdjust = 0;
            let completedCountAdjust = 0;

            queue.forEach(item => {
                if (item._type === 'screening' || item._type === 'skip_case') {
                    // Find the pending task card
                    const card = document.querySelector(`.task-card[data-assignment-id="${item.assignment_id}"]`);
                    if (card) {
                        pendingCountAdjust--;
                        completedCountAdjust++;
                        
                        // We modify the card UI
                        card.removeAttribute('onclick');
                        const infoDiv = card.querySelector('.task-info');
                        const badgeDiv = card.querySelector('div:last-child');
                        
                        if (item._type === 'screening') {
                            infoDiv.innerHTML = `
                                <h4>${infoDiv.querySelector('h4').innerHTML}</h4>
                                <p>${infoDiv.querySelector('p').innerHTML}</p>
                                <p style="color: var(--color-yellow); font-size: 13px; font-weight: bold;">
                                    ⏳ บันทึกแล้ว (รอส่งข้อมูลเข้าระบบ)
                                </p>
                            `;
                            badgeDiv.innerHTML = '<span class="badge" style="background-color: rgba(245,158,11,0.2); color: var(--color-yellow); box-shadow: none;">รอส่งข้อมูล</span>';
                        } else {
                            infoDiv.innerHTML = `
                                <h4>${infoDiv.querySelector('h4').innerHTML}</h4>
                                <p>${infoDiv.querySelector('p').innerHTML}</p>
                                <p style="color: var(--color-yellow); font-size: 13px; font-weight: bold;">
                                    ⏳ ข้ามเคสแล้ว (รอส่งข้อมูลเข้าระบบ)
                                </p>
                            `;
                            badgeDiv.innerHTML = '<span class="badge" style="background-color: rgba(245,158,11,0.2); color: var(--color-yellow); box-shadow: none;">รอส่งข้อมูล</span>';
                        }
                        
                        // Move card to Completed list
                        const completedList = document.getElementById('completed-list');
                        const emptyNotice = completedList.querySelector('div[style*="text-align: center"]');
                        if (emptyNotice) emptyNotice.remove();
                        completedList.appendChild(card);
                    }
                } else if (item._type === 'dpac') {
                    // Find the pending DPAC task card
                    const card = document.querySelector(`.task-card[data-followup-id="${item.followup_id}"]`);
                    if (card) {
                        dpacCountAdjust--;
                        completedCountAdjust++;
                        
                        card.removeAttribute('onclick');
                        const infoDiv = card.querySelector('.task-info');
                        const badgeDiv = card.querySelector('div:last-child');
                        
                        infoDiv.innerHTML = `
                            <h4>${infoDiv.querySelector('h4').innerHTML}</h4>
                            <p>${infoDiv.querySelector('p').innerHTML}</p>
                            <p style="color: var(--color-yellow); font-size: 13px; font-weight: bold;">
                                ⏳ ติดตาม DPAC แล้ว (รอส่งข้อมูลเข้าระบบ)
                            </p>
                        `;
                        badgeDiv.innerHTML = '<span class="badge" style="background-color: rgba(245,158,11,0.2); color: var(--color-yellow); box-shadow: none;">รอส่งข้อมูล</span>';
                        
                        // Move card to Completed list
                        const completedList = document.getElementById('completed-list');
                        const emptyNotice = completedList.querySelector('div[style*="text-align: center"]');
                        if (emptyNotice) emptyNotice.remove();
                        completedList.appendChild(card);
                    }
                } else if (item._type === 'skip_dpac_case') {
                    // Find the pending DPAC task card
                    const card = document.querySelector(`.task-card[data-followup-id="${item.followup_id}"]`);
                    if (card) {
                        const infoDiv = card.querySelector('.task-info');
                        if (infoDiv) {
                            const pTag = infoDiv.querySelector('p[style*="display: flex"]');
                            if (pTag) {
                                // Add or update warning badge in UI
                                let badge = pTag.querySelector('span[style*="background-color: #eab308"]');
                                if (badge) {
                                    badge.innerHTML = `⏳ ข้ามชั่วคราว (รอส่งข้อมูล)`;
                                } else {
                                    pTag.innerHTML += `
                                        <span style="display: inline-block; background-color: #eab308; color: #0f172a; font-size: 11px; padding: 1px 8px; border-radius: 50px; font-weight: 800; border: 1px solid rgba(234, 179, 8, 0.4);">
                                            ⏳ ข้ามชั่วคราว (รอส่งข้อมูล)
                                        </span>
                                    `;
                                }
                            }
                        }
                    }
                }
            });

            // Adjust tab counts dynamically
            const tabs = document.querySelectorAll('.tab-btn');
            if (tabs.length === 3) {
                // Pending Tab
                const pendingTab = tabs[0];
                const pendingMatch = pendingTab.textContent.match(/\((\d+)\)/);
                if (pendingMatch) {
                    const current = parseInt(pendingMatch[1]);
                    pendingTab.textContent = `งานค้าง (${current + pendingCountAdjust})`;
                }
                
                // DPAC Tab
                const dpacTab = tabs[1];
                const dpacMatch = dpacTab.textContent.match(/\((\d+)\)/);
                if (dpacMatch) {
                    const current = parseInt(dpacMatch[1]);
                    dpacTab.textContent = `DPAC (${current + dpacCountAdjust})`;
                }

                // Completed Tab
                const completedTab = tabs[2];
                const completedMatch = completedTab.textContent.match(/\((\d+)\)/);
                if (completedMatch) {
                    const current = parseInt(completedMatch[1]);
                    completedTab.textContent = `เสร็จสิ้น/ข้าม (${current + completedCountAdjust})`;
                }
            }
        });
    </script>
</body>
</html>
