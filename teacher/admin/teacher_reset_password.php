<?php
/**
 * Money Life - Admin Direct Password Reset
 */

require_once __DIR__ . '/../auth/check_admin.php';

$teacherId = (int)($_GET['id'] ?? 0);
if ($teacherId <= 0) {
    header('Location: teachers.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, first_name, last_name, position, national_id, phone FROM teachers WHERE id = ?");
$stmt->execute([$teacherId]);
$target = $stmt->fetch();

if (!$target) {
    header('Location: teachers.php?error=' . urlencode('ไม่พบบัญชีครูที่ต้องการรีเซ็ตรหัสผ่าน'));
    exit;
}

$pageTitle = 'รีเซ็ตรหัสผ่านสำหรับ: ' . $target['first_name'] . ' ' . $target['last_name'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'คำขอไม่ถูกต้องหรือหมดเวลา';
    } else {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($newPassword) < 6) {
            $error = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("
                UPDATE teachers 
                SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL 
                WHERE id = ?
            ");
            $upd->execute([$newHash, $teacherId]);

            log_audit($pdo, 'ADMIN_RESET_PASSWORD', 'teachers', $teacherId);

            header('Location: teachers.php?msg=' . urlencode('รีเซ็ตรหัสผ่านให้กับ ' . $target['first_name'] . ' สำเร็จแล้ว') . '&type=success');
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 20px;">
    <a href="teachers.php" style="color: #64748b; text-decoration: none; font-size: 14px;">← กลับไปยังรายชื่อครู</a>
</div>

<div class="card" style="max-width: 520px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title">
            <span>🔑</span> รีเซ็ตรหัสผ่านบัญชีครู
        </div>
    </div>
    <div class="card-body">
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin-bottom: 20px;">
            <div style="font-weight: 700; font-size: 16px; color: #0f172a; margin-bottom: 4px;">
                <?= e($target['first_name'] . ' ' . $target['last_name']) ?>
            </div>
            <div style="font-size: 13.5px; color: #64748b; display: flex; flex-direction: column; gap: 2px;">
                <span>ตำแหน่ง: <?= e($target['position']) ?></span>
                <span>เลข 13 หลัก: <code><?= mask_national_id($target['national_id']) ?></code></span>
                <span>เบอร์โทร: <code><?= mask_phone_number($target['phone']) ?></code></span>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <span>⚠️</span>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="teacher_reset_password.php?id=<?= $teacherId ?>">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="new_password">กำหนดรหัสผ่านใหม่ *</label>
                <input type="password" id="new_password" name="new_password" class="form-control" required placeholder="อย่างน้อย 6 ตัวอักษร">
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_password">ยืนยันรหัสผ่านใหม่ *</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="พิมพ์ใหม่อีกครั้ง">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <a href="teachers.php" class="btn btn-outline">ยกเลิก</a>
                <button type="submit" class="btn btn-primary">บันทึกรหัสผ่านใหม่</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
