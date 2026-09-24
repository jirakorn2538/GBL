<?php
/**
 * Money Life - Edit Teacher & Reset Password
 * Restricted to role = 'admin'
 */

require_once __DIR__ . '/../auth/check.php';
require_admin();

$targetId = (int)($_GET['id'] ?? 0);
if ($targetId <= 0) {
    header('Location: teachers.php');
    exit;
}

// ดึงข้อมูลครูเป้าหมาย
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
$stmt->execute([$targetId]);
$target = $stmt->fetch();

if (!$target) {
    header('Location: teachers.php?msg=' . urlencode('ไม่พบบัญชีครูที่ต้องการแก้ไข') . '&type=danger');
    exit;
}

$pageTitle = 'แก้ไขบัญชีครู: ' . $target['full_name'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'CSRF Token ไม่ถูกต้องหรือหมดเวลา';
    } else {
        $action = $_POST['action'] ?? 'update_info';

        if ($action === 'update_info') {
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = in_array($_POST['role'] ?? '', ['teacher', 'admin']) ? $_POST['role'] : 'teacher';
            $isActive = !empty($_POST['is_active']) ? 1 : 0;

            // ป้องกันลดสิทธิ์ตัวเอง
            if ($targetId === (int)$_SESSION['teacher_id'] && ($role !== 'admin' || $isActive !== 1)) {
                $error = 'ไม่สามารถลดสิทธิ์หรือระงับบัญชีของตนเองได้';
            } elseif ($fullName === '' || $email === '') {
                $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'รูปแบบอีเมลไม่ถูกต้อง';
            } else {
                // ตรวจสอบอีเมลซ้ำกับคนอื่น
                $chk = $pdo->prepare("SELECT id FROM teachers WHERE email = ? AND id != ?");
                $chk->execute([$email, $targetId]);
                if ($chk->fetch()) {
                    $error = 'อีเมลนี้ถูกใช้งานโดยบัญชีอื่นแล้ว';
                } else {
                    $upd = $pdo->prepare("
                        UPDATE teachers 
                        SET full_name = ?, email = ?, role = ?, is_active = ?
                        WHERE id = ?
                    ");
                    $upd->execute([$fullName, $email, $role, $isActive, $targetId]);
                    log_audit($pdo, 'ADMIN_UPDATE_TEACHER', 'teachers', $targetId, ['role' => $role, 'is_active' => $isActive]);
                    $success = 'บันทึกการแก้ไขข้อมูลเรียบร้อยแล้ว';
                    
                    // รีโหลดข้อมูลใหม่
                    $stmt->execute([$targetId]);
                    $target = $stmt->fetch();
                }
            }
        } elseif ($action === 'reset_password') {
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (strlen($newPassword) < 6) {
                $error = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updPwd = $pdo->prepare("
                    UPDATE teachers 
                    SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL 
                    WHERE id = ?
                ");
                $updPwd->execute([$newHash, $targetId]);
                log_audit($pdo, 'ADMIN_RESET_TEACHER_PASSWORD', 'teachers', $targetId);
                $success = 'รีเซ็ตรหัสผ่านใหม่เรียบร้อยแล้ว';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 20px;">
    <a href="teachers.php" style="color: #64748b; text-decoration: none; font-size: 14px;">← กลับไปยังรายการบัญชีครู</a>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; max-width: 900px; margin: 0 auto;">
    <!-- Form 1: แก้ไขข้อมูลทั่วไป -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <span>✏️</span> ข้อมูลทั่วไปของบัญชี
            </div>
        </div>
        <div class="card-body">
            <?php if ($success && ($action ?? '') === 'update_info'): ?>
                <div class="alert alert-success"><span>✓</span> <div><?= e($success) ?></div></div>
            <?php endif; ?>
            <?php if ($error && ($action ?? '') === 'update_info'): ?>
                <div class="alert alert-danger"><span>⚠️</span> <div><?= e($error) ?></div></div>
            <?php endif; ?>

            <form method="POST" action="teacher_edit.php?id=<?= $targetId ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_info">

                <div class="form-group">
                    <label class="form-label">ชื่อผู้ใช้ (Username)</label>
                    <input type="text" class="form-control" value="<?= e($target['username']) ?>" disabled 
                           style="background: #f1f5f9; cursor: not-allowed;">
                    <small style="color: #64748b;">ไม่สามารถแก้ไขชื่อผู้ใช้ได้</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="full_name">ชื่อ - นามสกุล *</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" 
                           value="<?= e($target['full_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">อีเมล (Email) *</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?= e($target['email']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="role">สิทธิ์การใช้งาน (Role) *</label>
                    <select name="role" id="role" class="form-control" <?= $targetId === (int)$_SESSION['teacher_id'] ? 'disabled' : '' ?>>
                        <option value="teacher" <?= $target['role'] === 'teacher' ? 'selected' : '' ?>>ครูผู้สอน (Teacher)</option>
                        <option value="admin" <?= $target['role'] === 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ (Admin)</option>
                    </select>
                    <?php if ($targetId === (int)$_SESSION['teacher_id']): ?>
                        <input type="hidden" name="role" value="admin">
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px;">
                        <input type="checkbox" name="is_active" value="1" <?= $target['is_active'] ? 'checked' : '' ?>
                               <?= $targetId === (int)$_SESSION['teacher_id'] ? 'disabled' : '' ?>>
                        เปิดใช้งานบัญชี (Active)
                    </label>
                    <?php if ($targetId === (int)$_SESSION['teacher_id']): ?>
                        <input type="hidden" name="is_active" value="1">
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                    บันทึกข้อมูลทั่วไป
                </button>
            </form>
        </div>
    </div>

    <!-- Form 2: รีเซ็ตรหัสผ่าน -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <span>🔑</span> รีเซ็ตรหัสผ่าน (Reset Password)
            </div>
        </div>
        <div class="card-body">
            <?php if ($success && ($action ?? '') === 'reset_password'): ?>
                <div class="alert alert-success"><span>✓</span> <div><?= e($success) ?></div></div>
            <?php endif; ?>
            <?php if ($error && ($action ?? '') === 'reset_password'): ?>
                <div class="alert alert-danger"><span>⚠️</span> <div><?= e($error) ?></div></div>
            <?php endif; ?>

            <p style="font-size: 13.5px; color: #64748b; margin-bottom: 16px;">
                ผู้ดูแลระบบสามารถกำหนดรหัสผ่านใหม่ให้กับบัญชีนี้ได้โดยตรง และระบบจะปลดล็อคการระงับอัตโนมัติ
            </p>

            <form method="POST" action="teacher_edit.php?id=<?= $targetId ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_password">

                <div class="form-group">
                    <label class="form-label" for="new_password">รหัสผ่านใหม่ *</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required placeholder="อย่างน้อย 6 ตัวอักษร">
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">ยืนยันรหัสผ่านใหม่ *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="พิมพ์รหัสผ่านใหม่อีกครั้ง">
                </div>

                <button type="submit" class="btn btn-accent" style="width: 100%; margin-top: 10px;">
                    ยืนยันการตั้งรหัสผ่านใหม่
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
