<?php
/**
 * Money Life - Add New Teacher Account
 * Restricted to role = 'admin'
 */

require_once __DIR__ . '/../auth/check.php';
require_admin();

$pageTitle = 'เพิ่มบัญชีครูใหม่';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'CSRF Token ไม่ถูกต้องหรือหมดเวลา';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['teacher', 'admin']) ? $_POST['role'] : 'teacher';
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if ($username === '' || $password === '' || $fullName === '' || $email === '') {
            $error = 'กรุณากรอกข้อมูลให้ครบทุกช่องที่จำเป็น';
        } elseif (strlen($password) < 6) {
            $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
        } elseif ($password !== $confirmPassword) {
            $error = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'รูปแบบอีเมลไม่ถูกต้อง';
        } else {
            // ตรวจสอบ username หรือ email ซ้ำ
            $chk = $pdo->prepare("SELECT id FROM teachers WHERE username = ? OR email = ?");
            $chk->execute([$username, $email]);
            if ($chk->fetch()) {
                $error = 'Username หรือ Email นี้มีอยู่ในระบบแล้ว';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO teachers (username, password_hash, full_name, email, role, is_active)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $username,
                    $passwordHash,
                    $fullName,
                    $email,
                    $role,
                    $isActive
                ]);
                $newId = $pdo->lastInsertId();
                log_audit($pdo, 'ADMIN_CREATE_TEACHER', 'teachers', $newId, ['username' => $username, 'role' => $role]);

                header('Location: teachers.php?msg=' . urlencode("สร้างบัญชีครู '{$username}' สำเร็จแล้ว"));
                exit;
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 20px;">
    <a href="teachers.php" style="color: #64748b; text-decoration: none; font-size: 14px;">← กลับไปยังรายการบัญชีครู</a>
</div>

<div class="card" style="max-width: 650px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title">
            <span>➕</span> เพิ่มบัญชีครู / ผู้ดูแลระบบใหม่
        </div>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <span>⚠️</span>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="teacher_add.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="username">ชื่อผู้ใช้ (Username) *</label>
                <input type="text" id="username" name="username" class="form-control" 
                       value="<?= e($_POST['username'] ?? '') ?>" required placeholder="เช่น teacher_somchai">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="password">รหัสผ่าน (Password) *</label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="อย่างน้อย 6 ตัวอักษร">
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirm_password">ยืนยันรหัสผ่าน *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="พิมพ์รหัสผ่านอีกครั้ง">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="full_name">ชื่อ - นามสกุล *</label>
                <input type="text" id="full_name" name="full_name" class="form-control" 
                       value="<?= e($_POST['full_name'] ?? '') ?>" required placeholder="เช่น นายสมชาย ใจดี">
            </div>

            <div class="form-group">
                <label class="form-label" for="email">อีเมล (Email) *</label>
                <input type="email" id="email" name="email" class="form-control" 
                       value="<?= e($_POST['email'] ?? '') ?>" required placeholder="somchai@school.ac.th">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="role">สิทธิ์การใช้งาน (Role) *</label>
                    <select name="role" id="role" class="form-control">
                        <option value="teacher">ครูผู้สอน (Teacher)</option>
                        <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                    </select>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 10px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px;">
                        <input type="checkbox" name="is_active" value="1" checked>
                        เปิดใช้งานบัญชีทันที (Active)
                    </label>
                </div>
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                <a href="teachers.php" class="btn btn-outline">ยกเลิก</a>
                <button type="submit" class="btn btn-primary">บันทึกและสร้างบัญชี</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
