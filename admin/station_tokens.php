<?php
require_once __DIR__ . '/../config/session.php';
if (empty($_SESSION['admin_logged_in'])) { header('Location: ../index.php'); exit; }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/station_token_auth.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$admin_hoscode = $_SESSION['admin_hoscode'] ?? null;
$is_main_admin = empty($admin_hoscode)
    && ($_SESSION['admin_role'] ?? '') === 'admin'
    && empty($_SESSION['is_visitor'])
    && empty($_SESSION['is_executive']);
if (!$is_main_admin) { http_response_code(403); header('Location: index.php'); exit; }

if (empty($_SESSION['station_token_csrf'])) $_SESSION['station_token_csrf'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['station_token_csrf'];
$flash = $_SESSION['station_token_flash'] ?? [];
unset($_SESSION['station_token_flash']);
$newToken = $flash['token'] ?? null;
$message = $flash['message'] ?? '';
$error = $flash['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $_SESSION['station_token_flash'] = ['error' => 'คำขอหมดอายุ กรุณาลองใหม่'];
        header('Location: station_tokens.php');
        exit;
    } else {
        try {
            $action = $_POST['action'] ?? '';
            if ($action === 'reveal') {
                header('Content-Type: application/json; charset=utf-8');
                $id = (int)($_POST['token_id'] ?? 0);
                $stmt = $pdo->prepare('SELECT hoscode, station_name, token_ciphertext FROM station_access_tokens WHERE token_id = ? LIMIT 1');
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $plain = $row ? decrypt_station_token($row['token_ciphertext'] ?? null) : '';
                if ($plain === '') {
                    http_response_code(404);
                    echo json_encode(['status'=>'error','message'=>'Token รุ่นเดิมไม่มีข้อมูลเข้ารหัส กรุณาสร้าง Token ใหม่'], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode(['status'=>'success','token'=>$plain,'name'=>$row['hoscode'].' — '.$row['station_name']], JSON_UNESCAPED_UNICODE);
                }
                exit;
            } elseif ($action === 'generate') {
                $hoscode = trim($_POST['hoscode'] ?? '');
                $stationName = trim($_POST['station_name'] ?? '');
                $expiresDays = max(30, min(1095, (int)($_POST['expires_days'] ?? 365)));
                if ($hoscode === '' || $stationName === '') throw new Exception('กรุณาเลือกสถานบริการและระบุชื่อ Token');

                $pdo->prepare("UPDATE station_access_tokens SET revoked_at = NOW()
                    WHERE hoscode = ? AND revoked_at IS NULL")->execute([$hoscode]);

                $plain = 'ncdst_' . rtrim(strtr(base64_encode(random_bytes(36)), '+/', '-_'), '=');
                $prefix = substr($plain, 0, 18);
                $stmt = $pdo->prepare("INSERT INTO station_access_tokens
                    (token_hash, token_ciphertext, token_prefix, hoscode, station_name, permissions, created_by, expires_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))");
                $stmt->execute([
                    hash('sha256', $plain), encrypt_station_token($plain), $prefix, $hoscode, $stationName,
                    'alerts:read,alerts:update,jhcis:sync', $_SESSION['admin_username'], $expiresDays
                ]);
                $_SESSION['station_token_flash'] = [
                    'token' => $plain,
                    'message' => 'สร้าง Token แล้ว Token เดิมของสถานบริการนี้ถูกยกเลิกโดยอัตโนมัติ'
                ];
                header('Location: station_tokens.php');
                exit;
            } elseif ($action === 'revoke') {
                $id = (int)($_POST['token_id'] ?? 0);
                $pdo->prepare("UPDATE station_access_tokens SET revoked_at = NOW() WHERE token_id = ? AND revoked_at IS NULL")->execute([$id]);
                $_SESSION['station_token_flash'] = ['message' => 'เพิกถอน Token แล้ว'];
                header('Location: station_tokens.php');
                exit;
            } elseif ($action === 'delete') {
                $id = (int)($_POST['token_id'] ?? 0);
                $stmt = $pdo->prepare("DELETE FROM station_access_tokens
                    WHERE token_id = ? AND (revoked_at IS NOT NULL OR (expires_at IS NOT NULL AND expires_at <= NOW()))");
                $stmt->execute([$id]);
                if ($stmt->rowCount() === 0) throw new Exception('ลบไม่ได้ ต้องเพิกถอน Token ที่ยังใช้งานอยู่ก่อน');
                $_SESSION['station_token_flash'] = ['message' => 'ลบรายการ Token ที่ยกเลิกแล้ว'];
                header('Location: station_tokens.php');
                exit;
            }
        } catch (Throwable $e) {
            $_SESSION['station_token_flash'] = ['error' => $e->getMessage()];
            header('Location: station_tokens.php');
            exit;
        }
    }
}

$units = $pdo->query("SELECT hoscode, hosname FROM health_units WHERE hoscode <> '' ORDER BY hoscode")->fetchAll(PDO::FETCH_ASSOC);
$tokens = $pdo->query("SELECT * FROM station_access_tokens ORDER BY created_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Station Access Token</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .token-wrap{max-width:1380px;margin:0 auto;padding:0 20px 40px}.token-grid{display:grid;grid-template-columns:minmax(320px,380px) minmax(0,1fr);gap:22px}.token-card{min-width:0;background:var(--bg-card);border-radius:22px;padding:24px;box-shadow:var(--neumorph-flat)}
        .token-card h2{margin:0 0 8px;color:var(--text-primary)}.token-card p{color:var(--text-muted);line-height:1.55}.field{margin:16px 0}.field label{display:block;font-weight:800;margin-bottom:7px}.field input,.field select{width:100%;padding:12px;border:1px solid var(--border-color);border-radius:12px;background:var(--bg-color);color:var(--text-primary)}
        .primary,.danger,.delete-token,.view-token{border:0;border-radius:12px;padding:12px 18px;font-weight:800;cursor:pointer}.primary{background:#2563eb;color:#fff;width:100%}.danger{background:#fee2e2;color:#b91c1c;padding:7px 11px}.delete-token{background:#b91c1c;color:#fff;padding:7px 11px}.view-token{background:#dbeafe;color:#1d4ed8;padding:7px 11px}.view-token:disabled{opacity:.5;cursor:not-allowed}.alert{border-radius:14px;padding:14px 16px;margin-bottom:18px}.ok{background:#dcfce7;color:#166534}.bad{background:#fee2e2;color:#991b1b}
        .new-token{background:#eef6ff;border:2px solid #2563eb;border-radius:16px;padding:16px;margin-bottom:20px}.new-token code{display:block;overflow-wrap:anywhere;background:#fff;padding:12px;border-radius:10px;margin:8px 0}.token-list{display:grid;gap:14px}.token-row{min-width:0;border:1px solid var(--border-color);border-radius:16px;padding:16px;background:var(--bg-color)}.token-details{display:grid;grid-template-columns:minmax(130px,.8fr) minmax(180px,1.25fr) minmax(160px,1fr);gap:14px 18px}.token-detail{min-width:0}.token-detail small{display:block;color:var(--text-muted);font-size:12px;font-weight:800;margin-bottom:5px}.token-detail strong,.token-detail code{display:block;color:var(--text-primary);overflow-wrap:anywhere;word-break:break-word;line-height:1.45}.token-actions{display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin-top:15px;padding-top:13px;border-top:1px solid var(--border-color)}.token-actions form{display:inline-flex;margin:0}.token-active{color:#047857!important;font-weight:800}.token-revoked{color:#b91c1c!important;font-weight:800}
        @media(max-width:1050px){.token-grid{grid-template-columns:1fr}.token-details{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:620px){.token-wrap{padding-left:12px;padding-right:12px}.token-card{padding:18px}.token-details{grid-template-columns:1fr}}
        .token-modal{position:fixed;inset:0;background:rgba(15,23,42,.48);display:none;align-items:center;justify-content:center;padding:20px;z-index:5000}.token-modal.open{display:flex}.token-dialog{width:min(620px,100%);background:var(--bg-card);border-radius:22px;padding:25px;box-shadow:0 24px 70px rgba(15,23,42,.28)}.token-dialog h2{margin:0 0 6px}.token-value{display:block;background:var(--bg-color);border:1px solid var(--border-color);border-radius:12px;padding:14px;margin:18px 0;overflow-wrap:anywhere;line-height:1.6}.modal-actions{display:flex;gap:10px}.modal-actions button{flex:1}.secondary{border:1px solid var(--border-color);background:var(--bg-card);color:var(--text-primary);border-radius:12px;font-weight:800}
    </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<main class="token-wrap">
    <h1>Station Access Token</h1>
    <p>หนึ่ง Token ต่อหนึ่งสถานบริการ การสร้างใหม่จะเพิกถอน Token เดิมของ Hoscode เดียวกันทันที</p>
    <?php if ($message): ?><div class="alert ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert bad"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($newToken): ?><div class="new-token"><strong>Token พร้อมใช้งาน</strong><code id="newToken"><?= htmlspecialchars($newToken) ?></code><button type="button" class="primary" onclick="navigator.clipboard.writeText(document.getElementById('newToken').textContent)">คัดลอก Token</button></div><?php endif; ?>
    <div class="token-grid">
        <section class="token-card">
            <h2>สร้าง Token</h2>
            <p>เลือกสถานบริการที่ Token นี้สามารถรับและส่งข้อมูลได้</p>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="generate">
                <div class="field"><label>สถานบริการ</label><select name="hoscode" required><option value="">-- เลือกสถานบริการ --</option><option value="ALL">ALL — ศูนย์สั่งการระดับอำเภอ</option><?php foreach($units as $u): ?><option value="<?= htmlspecialchars($u['hoscode']) ?>"><?= htmlspecialchars($u['hoscode'].' — '.$u['hosname']) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>ชื่อ Token</label><input name="station_name" placeholder="เช่น รพ.สต.หนองกุง" required></div>
                <div class="field"><label>อายุ Token</label><select name="expires_days"><option value="365">1 ปี</option><option value="180">180 วัน</option><option value="90">90 วัน</option><option value="730">2 ปี</option></select></div>
                <button class="primary">สร้าง Access Token</button>
            </form>
        </section>
        <section class="token-card"><h2>Token ของสถานบริการ</h2><div class="token-list">
        <?php foreach($tokens as $t): $active=empty($t['revoked_at']) && (empty($t['expires_at']) || strtotime($t['expires_at'])>time()); ?>
            <article class="token-row"><div class="token-details">
                <div class="token-detail"><small>สถานบริการ</small><strong><?= htmlspecialchars($t['hoscode']) ?></strong></div>
                <div class="token-detail"><small>ชื่อ Token</small><strong><?= htmlspecialchars($t['station_name']) ?></strong></div>
                <div class="token-detail"><small>Token</small><code><?= htmlspecialchars($t['token_prefix']) ?>…</code></div>
                <div class="token-detail"><small>ใช้งานล่าสุด</small><strong><?= htmlspecialchars($t['last_used_at'] ?: '-') ?></strong></div>
                <div class="token-detail"><small>Station ID</small><strong><?= htmlspecialchars($t['last_station_id'] ?: '-') ?></strong></div>
                <div class="token-detail"><small>สถานะ</small><strong class="<?= $active?'token-active':'token-revoked' ?>"><?= $active?'ใช้งาน':'ยกเลิก/หมดอายุ' ?></strong></div>
            </div><div class="token-actions"><button type="button" class="view-token" data-id="<?= (int)$t['token_id'] ?>" data-name="<?= htmlspecialchars($t['hoscode'].' — '.$t['station_name'], ENT_QUOTES) ?>" onclick="openTokenModal(this)"><?= empty($t['token_ciphertext'])?'Token รุ่นเดิม':'ดู Token' ?></button><?php if($active): ?><form method="post" onsubmit="return confirm('เพิกถอน Token นี้?')"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="token_id" value="<?= (int)$t['token_id'] ?>"><button class="danger">เพิกถอน</button></form><?php else: ?><form method="post" onsubmit="return confirm('ลบรายการ Token นี้ถาวร? การลบไม่กระทบข้อมูลหน่วยบริการ')"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="token_id" value="<?= (int)$t['token_id'] ?>"><button class="delete-token">ลบรายการ</button></form><?php endif; ?></div></article>
        <?php endforeach; ?>
        </div></section>
    </div>
</main>
<div class="token-modal" id="tokenModal" role="dialog" aria-modal="true" aria-labelledby="tokenModalTitle" onclick="if(event.target===this)closeTokenModal()"><div class="token-dialog"><h2 id="tokenModalTitle">Station Access Token</h2><p id="tokenModalName"></p><code class="token-value" id="tokenModalValue"></code><div class="modal-actions"><button type="button" class="primary" onclick="copyModalToken()">คัดลอก Token</button><button type="button" class="secondary" onclick="closeTokenModal()">ปิด</button></div></div></div>
<script>
async function openTokenModal(button){const modal=document.getElementById('tokenModal'),name=document.getElementById('tokenModalName'),value=document.getElementById('tokenModalValue');name.textContent=button.dataset.name||'กำลังโหลดข้อมูล';value.textContent='กำลังโหลด Token…';modal.classList.add('open');try{const form=new FormData();form.append('csrf',<?= json_encode($csrf) ?>);form.append('action','reveal');form.append('token_id',button.dataset.id);const response=await fetch('station_tokens.php',{method:'POST',body:form,credentials:'same-origin',cache:'no-store'});const text=await response.text();let data;try{data=JSON.parse(text)}catch(e){throw new Error('เซิร์ฟเวอร์ไม่ได้ส่งข้อมูล Token กลับมาในรูปแบบที่ถูกต้อง')}if(data.status!=='success')throw new Error(data.message||'ไม่สามารถเปิด Token ได้');name.textContent=data.name;value.textContent=data.token}catch(error){name.textContent='ไม่สามารถแสดง Token';value.textContent=error.message}}
function closeTokenModal(){document.getElementById('tokenModal').classList.remove('open')}
async function copyModalToken(){const value=document.getElementById('tokenModalValue').textContent;if(!value.startsWith('ncdst_')){alert('ไม่มี Token ที่สามารถคัดลอกได้');return}await navigator.clipboard.writeText(value);alert('คัดลอก Token แล้ว')}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeTokenModal()});
</script></body></html>
