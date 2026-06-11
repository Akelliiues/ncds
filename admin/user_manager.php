<?php
// admin/user_manager.php
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

$hc_names = get_health_units();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $target_username = trim($_POST['target_username'] ?? '');

    try {
        if ($action === 'add') {
            $new_username = strtolower(trim($_POST['username'] ?? ''));
            $admin_name = trim($_POST['admin_name'] ?? '');
            $hoscode = trim($_POST['hoscode'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (empty($hoscode)) {
                $hoscode = null;
            }

            if (empty($new_username) || empty($admin_name) || empty($password)) {
                throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน");
            }

            if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $new_username)) {
                throw new Exception("ชื่อผู้ใช้งาน (Username) ต้องเป็นตัวอักษรภาษาอังกฤษ ตัวเลข หรือขีดล่าง (_) ความยาว 3-30 ตัวอักษร");
            }

            // Check if username exists
            $stmt = $pdo->prepare("SELECT username FROM admin_users WHERE username = ?");
            $stmt->execute([$new_username]);
            if ($stmt->fetch()) {
                throw new Exception("ชื่อผู้ใช้งาน (Username) นี้มีอยู่แล้วในระบบ");
            }

            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, hoscode, admin_name, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$new_username, $password_hash, $hoscode, $admin_name]);
            $message = "เพิ่มผู้ใช้งานระบบ '$new_username' เรียบร้อยแล้ว";
        } elseif ($action === 'edit') {
            $edit_username = strtolower(trim($_POST['username'] ?? ''));
            $admin_name = trim($_POST['admin_name'] ?? '');
            $hoscode = trim($_POST['hoscode'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (empty($hoscode)) {
                $hoscode = null;
            }

            if (empty($edit_username) || empty($admin_name)) {
                throw new Exception("กรุณากรอกข้อมูลชื่อ-นามสกุลให้เรียบร้อย");
            }

            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admin_users SET admin_name = ?, hoscode = ?, password_hash = ? WHERE username = ?");
                $stmt->execute([$admin_name, $hoscode, $password_hash, $edit_username]);
            } else {
                $stmt = $pdo->prepare("UPDATE admin_users SET admin_name = ?, hoscode = ? WHERE username = ?");
                $stmt->execute([$admin_name, $hoscode, $edit_username]);
            }
            $message = "แก้ไขข้อมูลผู้ใช้งาน '$edit_username' เรียบร้อยแล้ว";
        } elseif ($action === 'suspend') {
            if ($target_username === strtolower($_SESSION['admin_username'])) {
                throw new Exception("ไม่สามารถระงับสิทธิ์บัญชีที่ใช้งานอยู่ได้");
            }
            if ($target_username === 'admin') {
                throw new Exception("ไม่สามารถระงับสิทธิ์บัญชีผู้ดูแลระบบหลัก (admin) ได้");
            }

            $stmt = $pdo->prepare("UPDATE admin_users SET status = 'suspended' WHERE username = ?");
            $stmt->execute([$target_username]);
            $message = "ระงับสิทธิ์การใช้งาน '$target_username' เรียบร้อยแล้ว";
        } elseif ($action === 'activate') {
            $stmt = $pdo->prepare("UPDATE admin_users SET status = 'active' WHERE username = ?");
            $stmt->execute([$target_username]);
            $message = "เปิดสิทธิ์การใช้งาน '$target_username' เรียบร้อยแล้ว";
        } elseif ($action === 'delete') {
            if ($target_username === strtolower($_SESSION['admin_username'])) {
                throw new Exception("ไม่สามารถลบบัญชีที่ใช้งานอยู่ได้");
            }
            if ($target_username === 'admin') {
                throw new Exception("ไม่สามารถลบบัญชีผู้ดูแลระบบหลัก (admin) ได้");
            }

            $stmt = $pdo->prepare("DELETE FROM admin_users WHERE username = ?");
            $stmt->execute([$target_username]);
            $message = "ลบผู้ใช้งานระบบ '$target_username' เรียบร้อยแล้ว";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch all system users
try {
    $search = trim($_GET['search'] ?? '');
    if (!empty($search)) {
        $stmt = $pdo->prepare("SELECT username, hoscode, admin_name, status FROM admin_users WHERE username LIKE ? OR admin_name LIKE ? ORDER BY username ASC");
        $stmt->execute(["%$search%", "%$search%"]);
    } else {
        $stmt = $pdo->query("SELECT username, hoscode, admin_name, status FROM admin_users ORDER BY username ASC");
    }
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "เกิดข้อผิดพลาดในการโหลดข้อมูล: " . $e->getMessage();
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้งานระบบ - SSOTansum NCD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background-color: var(--bg-main); }
        .action-form {
            display: inline-block;
            margin: 0;
        }
        
        .action-btn-container {
            display: inline-flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
            flex-wrap: nowrap;
        }
        
        .action-btn {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-speed);
            box-shadow: var(--neumorph-flat);
            color: white;
            padding: 0;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 4px 4px 8px #cbd5e1;
        }
        
        .action-btn.activate {
            background-color: var(--color-green);
        }
        
        .action-btn.suspend {
            background-color: var(--color-yellow);
        }
        
        .action-btn.edit {
            background-color: #3b82f6;
        }
        
        .action-btn.delete {
            background-color: var(--color-red);
        }
        
        .action-btn svg {
            width: 18px;
            height: 18px;
            stroke-width: 2.5;
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(13, 44, 84, 0.4);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--neumorph-flat);
            width: 100%;
            max-width: 500px;
            margin: 20px;
            padding: 32px;
            animation: modalSlideUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        @keyframes modalSlideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-form-group {
            margin-bottom: 18px;
        }
        
        .modal-form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--text-primary);
            font-size: 15px;
        }
        
        .status-badge {
            font-weight: bold;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-badge.active {
            background-color: rgba(16, 185, 129, 0.15);
            color: var(--color-green);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .status-badge.suspended {
            background-color: rgba(239, 68, 68, 0.15);
            color: var(--color-red);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
    </style>
</head>
<body class="admin-body">
    <?php include 'navbar.php'; ?>

    <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; flex-wrap: wrap; gap: 16px;">
            <div>
                <h2 style="color: var(--color-accent); margin-top: 0; margin-bottom: 8px;">จัดการผู้ใช้งานระบบ</h2>
                <p style="color: var(--text-secondary); margin: 0;">
                    จัดการบัญชีผู้ใช้งานระบบ (แอดมิน, สสอ. และแอดมิน รพ.สต.) ไม่เกี่ยวกับ อสม.
                </p>
            </div>
            <div>
                <button type="button" class="btn-giant btn-giant-primary" onclick="openAddModal()" style="margin: 0; padding: 10px 20px; font-size: 15px; display: inline-flex; align-items: center; gap: 8px; width: auto; height: 44px; border-radius: 22px;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                    เพิ่มผู้ใช้งานระบบ
                </button>
            </div>
        </div>

        <!-- Feedback Messages -->
        <?php if (!empty($message)): ?>
            <div style="background-color: rgba(16, 185, 129, 0.15); border: 2px solid var(--color-green); color: var(--color-green); padding: 16px; border-radius: var(--border-radius); margin-bottom: 24px; font-weight: bold;">
                🎉 <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div style="background-color: rgba(239, 68, 68, 0.15); border: 2px solid var(--color-red); color: var(--color-red); padding: 16px; border-radius: var(--border-radius); margin-bottom: 24px; font-weight: bold;">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="card-dark" style="padding: 16px; margin-bottom: 24px;">
            <form method="GET" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <div style="flex-grow: 1; min-width: 250px;">
                    <input type="text" name="search" class="form-input-text" placeholder="ค้นหาด้วยชื่อแอดมิน หรือชื่อผู้ใช้งาน (Username)..." value="<?= htmlspecialchars($search) ?>" style="box-shadow: var(--neumorph-inset); text-align: left;">
                </div>
                <button type="submit" class="btn-giant btn-giant-primary" style="margin: 0; width: auto; padding: 0 24px; height: 52px; border-radius: var(--border-radius);">
                    ค้นหา
                </button>
                <?php if (!empty($search)): ?>
                    <a href="user_manager.php" class="btn-giant btn-giant-secondary" style="margin: 0; width: auto; padding: 0 24px; height: 52px; border-radius: var(--border-radius); line-height: 52px; text-align: center; display: inline-flex; align-items: center;">
                        ล้างคำค้นหา
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Users Table -->
        <div class="card-dark">
            <h3 style="color: var(--color-accent); margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                <span>👥</span> บัญชีผู้ใช้งานระบบทั้งหมด
            </h3>
            
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ชื่อผู้ใช้งาน (Username)</th>
                            <th>ชื่อ - นามสกุล</th>
                            <th>สังกัดหน่วยบริการ (HOSCODE)</th>
                            <th style="width: 130px; text-align: center;">สถานะ</th>
                            <th style="width: 180px; text-align: center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 30px;">ไม่พบข้อมูลผู้ใช้งานระบบ</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td style="font-weight: bold; color: var(--text-primary);"><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['admin_name'] ?? '-') ?></td>
                                    <td>
                                        <?php if (empty($user['hoscode'])): ?>
                                            <span style="color: var(--color-accent); font-weight: bold;">แอดมินหลัก / สสอ. (ทั้งหมด)</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($hc_names[$user['hoscode']] ?? $user['hoscode']) ?> (<?= htmlspecialchars($user['hoscode']) ?>)
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if (($user['status'] ?? 'active') === 'active'): ?>
                                            <span class="status-badge active">🟢 ใช้งานอยู่</span>
                                        <?php else: ?>
                                            <span class="status-badge suspended">🔴 ระงับสิทธิ์</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <div class="action-btn-container">
                                            <!-- Suspend / Activate Button -->
                                            <?php if ($user['username'] !== 'admin' && $user['username'] !== strtolower($_SESSION['admin_username'])): ?>
                                                <?php if (($user['status'] ?? 'active') === 'active'): ?>
                                                    <form method="POST" class="action-form" onsubmit="return confirm('ยืนยันระงับสิทธิ์การเข้าใช้งานบัญชีนี้?')">
                                                        <input type="hidden" name="target_username" value="<?= htmlspecialchars($user['username']) ?>">
                                                        <input type="hidden" name="action" value="suspend">
                                                        <button type="submit" class="action-btn suspend" title="ระงับสิทธิ์">
                                                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" class="action-form" onsubmit="return confirm('ยืนยันเปิดสิทธิ์การใช้งานบัญชีนี้?')">
                                                        <input type="hidden" name="target_username" value="<?= htmlspecialchars($user['username']) ?>">
                                                        <input type="hidden" name="action" value="activate">
                                                        <button type="submit" class="action-btn activate" title="เปิดสิทธิ์ใช้งาน">
                                                            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <button type="button" class="action-btn" style="background-color: var(--text-muted); opacity: 0.5; cursor: not-allowed;" title="ไม่สามารถปรับสถานะตนเองหรือบัญชีหลักได้" disabled>
                                                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Edit Button -->
                                            <?php 
                                                $userJson = htmlspecialchars(json_encode([
                                                    'username' => $user['username'],
                                                    'admin_name' => $user['admin_name'],
                                                    'hoscode' => $user['hoscode']
                                                ]), ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <button type="button" class="action-btn edit" title="แก้ไขข้อมูล" onclick="openEditModal(<?= $userJson ?>)">
                                                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            </button>

                                            <!-- Delete Button -->
                                            <?php if ($user['username'] !== 'admin' && $user['username'] !== strtolower($_SESSION['admin_username'])): ?>
                                                <form method="POST" class="action-form" onsubmit="return confirm('ยืนยันการลบผู้ใช้งานระบบนี้ถาวร? การกระทำนี้ไม่สามารถย้อนกลับได้')">
                                                    <input type="hidden" name="target_username" value="<?= htmlspecialchars($user['username']) ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="action-btn delete" title="ลบออกถาวร">
                                                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="action-btn" style="background-color: var(--text-muted); opacity: 0.5; cursor: not-allowed;" title="ไม่สามารถลบตัวเองหรือบัญชีหลักได้" disabled>
                                                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <h3 style="color: var(--color-accent); margin-top: 0; margin-bottom: 24px; text-align: center; font-size: 20px; font-weight: 800;">
                ➕ เพิ่มผู้ใช้งานระบบใหม่
            </h3>
            
            <form method="POST" id="addForm">
                <input type="hidden" name="action" value="add">

                <div class="modal-form-group">
                    <label for="modal_add_username" class="modal-form-label">ชื่อผู้ใช้งาน (Username)</label>
                    <input type="text" name="username" id="modal_add_username" class="form-input-text" style="box-shadow: var(--neumorph-inset); text-align: left;" placeholder="เช่น adminsso, sub_admin_hosp" required autocomplete="username">
                </div>

                <div class="modal-form-group">
                    <label for="modal_add_admin_name" class="modal-form-label">ชื่อ - นามสกุล</label>
                    <input type="text" name="admin_name" id="modal_add_admin_name" class="form-input-text" style="box-shadow: var(--neumorph-inset); text-align: left;" placeholder="ชื่อผู้รับผิดชอบ" required autocomplete="name">
                </div>

                <div class="modal-form-group">
                    <label for="modal_add_password" class="modal-form-label">รหัสผ่านสำหรับเข้าใช้งาน</label>
                    <input type="password" name="password" id="modal_add_password" class="form-input-text" style="box-shadow: var(--neumorph-inset); text-align: left;" placeholder="รหัสผ่านเริ่มต้น" required autocomplete="new-password">
                </div>

                <div class="modal-form-group" style="margin-bottom: 28px;">
                    <label for="modal_add_hoscode" class="modal-form-label">สังกัดหน่วยบริการ / รพ.สต.</label>
                    <select name="hoscode" id="modal_add_hoscode" class="form-select" style="box-shadow: var(--neumorph-inset);">
                        <option value="">แอดมินหลัก / สสอ. (เข้าดูและจัดการได้ทุก รพ.สต.)</option>
                        <?php foreach ($hc_names as $code => $name): ?>
                            <option value="<?= $code ?>"><?= htmlspecialchars($name) ?> (<?= htmlspecialchars($code) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" onclick="closeAddModal()" class="btn-giant btn-giant-secondary" style="height: 44px; line-height: 44px; padding: 0 24px; font-size: 15px; margin: 0; width: auto; background-color: var(--text-muted); color: white;">
                        ยกเลิก
                    </button>
                    <button type="submit" class="btn-giant btn-giant-primary" style="height: 44px; line-height: 44px; padding: 0 24px; font-size: 15px; margin: 0; width: auto; background-color: var(--color-green);">
                        เพิ่มผู้ใช้
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <h3 style="color: var(--color-accent); margin-top: 0; margin-bottom: 24px; text-align: center; font-size: 20px; font-weight: 800;">
                📝 แก้ไขข้อมูลผู้ใช้งานระบบ
            </h3>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="username" id="modal_edit_username">

                <div class="modal-form-group">
                    <label for="modal_edit_username_display" class="modal-form-label">ชื่อผู้ใช้งาน (Username)</label>
                    <input type="text" id="modal_edit_username_display" class="form-input-text" style="box-shadow: var(--neumorph-inset); text-align: left; background-color: rgba(0, 0, 0, 0.05); color: var(--text-muted);" readonly>
                </div>

                <div class="modal-form-group">
                    <label for="modal_edit_admin_name" class="modal-form-label">ชื่อ - นามสกุล</label>
                    <input type="text" name="admin_name" id="modal_edit_admin_name" class="form-input-text" style="box-shadow: var(--neumorph-inset); text-align: left;" required autocomplete="name">
                </div>

                <div class="modal-form-group">
                    <label for="modal_edit_password" class="modal-form-label">เปลี่ยนรหัสผ่านใหม่ (ปล่อยว่างหากไม่ต้องการเปลี่ยน)</label>
                    <input type="password" name="password" id="modal_edit_password" class="form-input-text" style="box-shadow: var(--neumorph-inset); text-align: left;" placeholder="ระบุรหัสผ่านใหม่" autocomplete="new-password">
                </div>

                <div class="modal-form-group" style="margin-bottom: 28px;">
                    <label for="modal_edit_hoscode" class="modal-form-label">สังกัดหน่วยบริการ / รพ.สต.</label>
                    <select name="hoscode" id="modal_edit_hoscode" class="form-select" style="box-shadow: var(--neumorph-inset);">
                        <option value="">แอดมินหลัก / สสอ. (เข้าดูและจัดการได้ทุก รพ.สต.)</option>
                        <?php foreach ($hc_names as $code => $name): ?>
                            <option value="<?= $code ?>"><?= htmlspecialchars($name) ?> (<?= htmlspecialchars($code) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" onclick="closeEditModal()" class="btn-giant btn-giant-secondary" style="height: 44px; line-height: 44px; padding: 0 24px; font-size: 15px; margin: 0; width: auto; background-color: var(--text-muted); color: white;">
                        ยกเลิก
                    </button>
                    <button type="submit" class="btn-giant btn-giant-primary" style="height: 44px; line-height: 44px; padding: 0 24px; font-size: 15px; margin: 0; width: auto; background-color: var(--color-green);">
                        บันทึกการแก้ไข
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modal_add_username').value = '';
            document.getElementById('modal_add_admin_name').value = '';
            document.getElementById('modal_add_password').value = '';
            document.getElementById('modal_add_hoscode').value = '';
            document.getElementById('addModal').style.display = 'flex';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        function openEditModal(user) {
            document.getElementById('modal_edit_username').value = user.username;
            document.getElementById('modal_edit_username_display').value = user.username;
            document.getElementById('modal_edit_admin_name').value = user.admin_name;
            document.getElementById('modal_edit_hoscode').value = user.hoscode || '';
            document.getElementById('modal_edit_password').value = '';
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside content area
        window.onclick = function(event) {
            let addModal = document.getElementById('addModal');
            let editModal = document.getElementById('editModal');
            if (event.target == addModal) {
                closeAddModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>
