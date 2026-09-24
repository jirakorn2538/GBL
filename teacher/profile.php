<?php
/**
 * Money Life - Teacher Personal Profile & 2FA Setup
 * Manage personal password, Google/Microsoft Authenticator TOTP
 */

require_once __DIR__ . '/auth/check.php';

$pageTitle = 'บัญชีของฉัน & ความปลอดภัย';
$error = '';
$success = '';

$teacherId = (int)$_SESSION['teacher_id'];

// ดึงข้อมูลล่าสุดของครู
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
$stmt->execute([$teacherId]);
$me = $stmt->fetch();

// สร้าง Secret สำหรับ TOTP Setup ถ้ายังไม่ได้เปิดใช้งาน
if (empty($_SESSION['temp_totp_secret'])) {
    $_SESSION['temp_totp_secret'] = TOTP::generateSecret(16);
}
$tempSecret = $_SESSION['temp_totp_secret'];
$totpUri = TOTP::getOtpAuthUrl($me['phone'], $tempSecret, 'MoneyLife');
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($totpUri);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'CSRF Token ไม่ถูกต้องหรือหมดเวลา';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. เปลี่ยนรหัสผ่าน
        if ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (!password_verify($currentPassword, $me['password_hash'])) {
                $error = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
            } elseif (strlen($newPassword) < 6) {
                $error = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE teachers SET password_hash = ? WHERE id = ?");
                $upd->execute([$newHash, $teacherId]);
                log_audit($pdo, 'CHANGE_PASSWORD', 'teachers', $teacherId);
                $success = 'เปลี่ยนรหัสผ่านสำเร็จเรียบร้อยแล้ว';
                
                // รีโหลด
                $stmt->execute([$teacherId]);
                $me = $stmt->fetch();
            }
        }

        // 2. เปิดใช้งาน 2FA TOTP
        elseif ($action === 'enable_2fa') {
            $code = trim($_POST['totp_verify_code'] ?? '');
            if (TOTP::verifyCode($tempSecret, $code)) {
                $upd = $pdo->prepare("UPDATE teachers SET two_factor_enabled = 1, two_factor_secret = ? WHERE id = ?");
                $upd->execute([$tempSecret, $teacherId]);
                unset($_SESSION['temp_totp_secret']);
                log_audit($pdo, 'ENABLE_2FA', 'teachers', $teacherId);
                $success = 'เปิดใช้งานการยืนยันสองขั้นตอน (2FA) สำเร็จแล้ว!';
                
                $stmt->execute([$teacherId]);
                $me = $stmt->fetch();
            } else {
                $error = 'รหัสยืนยัน 6 หลักไม่ถูกต้อง กรุณาตรวจสอบเวลาบนมือถือและลองใหม่';
            }
        }

        // 3. ปิดใช้งาน 2FA
        elseif ($action === 'disable_2fa') {
            $password = $_POST['confirm_password_disable'] ?? '';
            if (!password_verify($password, $me['password_hash'])) {
                $error = 'รหัสผ่านไม่ถูกต้อง ไม่สามารถปิด 2FA ได้';
            } else {
                $upd = $pdo->prepare("UPDATE teachers SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?");
                $upd->execute([$teacherId]);
                log_audit($pdo, 'DISABLE_2FA', 'teachers', $teacherId);
                $success = 'ปิดใช้งานการยืนยันสองขั้นตอน (2FA) เรียบร้อยแล้ว';

                $stmt->execute([$teacherId]);
                $me = $stmt->fetch();
            }
        }
    }
}

// ดึง 8 ประวัติกิจกรรมล่าสุดของฉัน
$myLogsStmt = $pdo->prepare("SELECT * FROM audit_logs WHERE teacher_id = ? ORDER BY id DESC LIMIT 8");
$myLogsStmt->execute([$teacherId]);
$myLogs = $myLogsStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><span>✓</span> <div><?= e($success) ?></div></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><span>⚠️</span> <div><?= e($error) ?></div></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Box 1: ข้อมูลส่วนตัว & เปลี่ยนรหัสผ่าน -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <span>👤</span> ข้อมูลบัญชีและเปลี่ยนรหัสผ่าน
            </div>
        </div>
        <div class="card-body">
            <div style="display: flex; gap: 16px; align-items: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #f1f5f9;">
                <div style="width: 56px; height: 56px; border-radius: 14px; background: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white; font-weight: 700;">
                    <?= mb_substr($me['full_name'], 0, 1, 'UTF-8') ?>
                </div>
                <div>
                    <h3 style="font-size: 18px; color: #0f172a; margin-bottom: 2px;">👨‍🏫 <?= e($me['first_name'] . ' ' . $me['last_name']) ?></h3>
                    <div style="font-size: 13px; color: #64748b;">
                        ตำแหน่ง: <strong><?= e($me['position']) ?></strong> | 
                        เลขบัตร: <code><?= mask_national_id($me['national_id']) ?></code> | 
                        เบอร์โทร: <code><?= mask_phone_number($me['phone']) ?></code> | 
                        Role: <span class="badge <?= $me['role'] === 'admin' ? 'badge-warning' : 'badge-info' ?>"><?= ucfirst($me['role']) ?></span>
                    </div>
                </div>
            </div>

            <form method="POST" action="profile.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">

                <h4 style="font-size: 14px; font-weight: 600; color: #334155; margin-bottom: 14px;">
                    🔑 เปลี่ยนรหัสผ่านเข้าสู่ระบบ
                </h4>

                <div class="form-group">
                    <label class="form-label">รหัสผ่านปัจจุบัน *</label>
                    <input type="password" name="current_password" class="form-control" required placeholder="ใส่รหัสผ่านเดิม">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">รหัสผ่านใหม่ *</label>
                        <input type="password" name="new_password" class="form-control" required placeholder="อย่างน้อย 6 ตัวอักษร">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ยืนยันรหัสผ่านใหม่ *</label>
                        <input type="password" name="confirm_password" class="form-control" required placeholder="พิมพ์ใหม่อีกครั้ง">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 6px;">
                    อัปเดตรหัสผ่าน
                </button>
            </form>
        </div>
    </div>

    <!-- Box 2: 2-Factor Authentication (TOTP) -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <span>🔐</span> การยืนยันตัวตนสองขั้นตอน (2FA)
            </div>
            <div>
                <?php if ($me['two_factor_enabled']): ?>
                    <span class="badge badge-success" style="font-size: 13px;">เปิดใช้งานอยู่</span>
                <?php else: ?>
                    <span class="badge badge-secondary" style="font-size: 13px;">ยังไม่เปิดใช้</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <?php if (!$me['two_factor_enabled']): ?>
                <p style="font-size: 13.5px; color: #64748b; margin-bottom: 16px;">
                    เพิ่มความปลอดภัยสูงสุดด้วยรหัส OTP 6 หลัก จาก <strong>Google Authenticator</strong> หรือ <strong>Microsoft Authenticator</strong>
                </p>

                <div style="text-align: center; margin-bottom: 16px;">
                    <img src="<?= $qrCodeUrl ?>" alt="TOTP QR Code" style="border-radius: 12px; border: 1px solid #e2e8f0; width: 170px; height: 170px;">
                    <div style="margin-top: 8px; font-size: 12.5px; color: #64748b;">
                        สแกน QR Code ด้วยแอป Authenticator
                    </div>
                    <div style="margin-top: 4px;">
                        <code style="background: #f1f5f9; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 700; letter-spacing: 2px;">
                            <?= $tempSecret ?>
                        </code>
                    </div>
                </div>

                <form method="POST" action="profile.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="enable_2fa">

                    <div class="form-group">
                        <label class="form-label" style="text-align: center;">กรอกรหัส 6 หลักที่ปรากฏในแอปเพื่อยืนยัน</label>
                        <input type="text" name="totp_verify_code" class="form-control" maxlength="6" pattern="[0-9]{6}" 
                               placeholder="000000" required 
                               style="text-align: center; font-size: 22px; letter-spacing: 6px; font-weight: 700;">
                    </div>

                    <button type="submit" class="btn btn-accent" style="width: 100%;">
                        เปิดใช้งาน 2FA ทันที
                    </button>
                </form>

            <?php else: ?>
                <div style="background: #ecfdf5; border: 1px solid #bbf7d0; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 20px;">
                    <div style="font-size: 36px; margin-bottom: 6px;">🛡️</div>
                    <h4 style="font-size: 16px; color: #166534; font-weight: 700; margin-bottom: 4px;">ระบบความปลอดภัย 2FA เปิดใช้งานแล้ว</h4>
                    <p style="font-size: 13.5px; color: #15803d;">
                        ทุกครั้งที่เข้าสู่ระบบ จะต้องใช้รหัส OTP จากแอป Authenticator คู่กับรหัสผ่าน
                    </p>
                </div>

                <form method="POST" action="profile.php" onsubmit="return confirm('ยืนยันปิดการใช้งาน 2FA? ความปลอดภัยของบัญชีจะลดลง')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="disable_2fa">

                    <div class="form-group">
                        <label class="form-label">กรุณากรอกรหัสผ่านเพื่อยืนยันการปิด 2FA</label>
                        <input type="password" name="confirm_password_disable" class="form-control" required placeholder="รหัสผ่านเข้าสู่ระบบ">
                    </div>

                    <button type="submit" class="btn btn-danger btn-sm" style="width: 100%;">
                        ปิดใช้งาน 2FA
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- My Audit History -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>📜</span> ประวัติกิจกรรมของบัญชีฉัน (My Activity Log)
        </div>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>เวลา</th>
                    <th>กิจกรรม (Action)</th>
                    <th>เป้าหมาย</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($myLogs) === 0): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: #94a3b8; padding: 20px;">ยังไม่มีประวัติกิจกรรม</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($myLogs as $ml): ?>
                        <tr>
                            <td style="font-size: 13px; color: #64748b;">
                                <?= date('d/m/Y H:i:s', strtotime($ml['created_at'])) ?>
                            </td>
                            <td>
                                <span class="badge badge-info"><?= e($ml['action']) ?></span>
                            </td>
                            <td><?= e($ml['target_type']) ?> <?= $ml['target_id'] ? '#' . e($ml['target_id']) : '' ?></td>
                            <td><code><?= e($ml['ip_address']) ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
