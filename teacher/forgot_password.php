<?php
/**
 * Money Life - Teacher Forgot Password Flow
 * Secure password reset via National ID + Phone + OTP Verification
 */

require_once __DIR__ . '/auth/permissions.php';

if (is_teacher_logged_in()) {
    header('Location: /money-life/teacher/index.php');
    exit;
}

$error = '';
$success = '';
$step = 'verify_identity'; // 'verify_identity', 'verify_otp', 'reset_password'

if (!empty($_SESSION['fp_step'])) {
    $step = $_SESSION['fp_step'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ขั้นตอนที่ 1: ตรวจสอบเลขประจำตัวประชาชน และเบอร์โทรศัพท์
    if ($action === 'check_identity') {
        if (!verify_csrf_token()) {
            $error = 'คำขอไม่ถูกต้องหรือหมดเวลา';
        } else {
            $nationalId = preg_replace('/[^0-9]/', '', (string)($_POST['national_id'] ?? ''));
            $phone = preg_replace('/[^0-9]/', '', (string)($_POST['phone'] ?? ''));

            if (strlen($nationalId) !== 13 || !validate_phone_number($phone)) {
                $error = 'กรุณากรอกเลขประจำตัวประชาชน 13 หลัก และเบอร์โทรศัพท์ให้ถูกต้อง';
            } else {
                $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM teachers WHERE national_id = ? AND phone = ?");
                $stmt->execute([$nationalId, $phone]);
                $teacher = $stmt->fetch();

                if ($teacher) {
                    $otpCode = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $otpExpiry = date('Y-m-d H:i:s', time() + (10 * 60));

                    $upd = $pdo->prepare("UPDATE teachers SET otp_code = ?, otp_expires_at = ? WHERE id = ?");
                    $upd->execute([$otpCode, $otpExpiry, $teacher['id']]);

                    $_SESSION['fp_teacher_id'] = $teacher['id'];
                    $_SESSION['fp_phone'] = $phone;
                    $_SESSION['fp_demo_otp'] = $otpCode;
                    $_SESSION['fp_step'] = 'verify_otp';
                    $step = 'verify_otp';
                } else {
                    // ไม่เปิดเผยว่าข้อมูลส่วนใดผิด
                    $error = 'ไม่พบข้อมูลที่ตรงกันในระบบ กรุณาตรวจสอบข้อมูลอีกครั้ง';
                }
            }
        }
    }

    // ขั้นตอนที่ 2: ยืนยันรหัส OTP
    elseif ($action === 'verify_otp') {
        if (!verify_csrf_token()) {
            $error = 'คำขอไม่ถูกต้องหรือหมดเวลา';
        } else {
            $teacherId = $_SESSION['fp_teacher_id'] ?? null;
            $inputOtp = trim($_POST['otp_code'] ?? '');

            if (!$teacherId) {
                unset($_SESSION['fp_step']);
                header('Location: forgot_password.php');
                exit;
            }

            $stmt = $pdo->prepare("SELECT id, otp_code, otp_expires_at FROM teachers WHERE id = ?");
            $stmt->execute([$teacherId]);
            $teacher = $stmt->fetch();

            if ($teacher && $teacher['otp_code'] === $inputOtp) {
                if (strtotime($teacher['otp_expires_at']) < time()) {
                    $error = 'รหัส OTP หมดอายุแล้ว กรุณาเริ่มใหม่อีกครั้ง';
                } else {
                    $_SESSION['fp_step'] = 'reset_password';
                    $step = 'reset_password';
                }
            } else {
                $error = 'รหัส OTP ไม่ถูกต้อง กรุณากรอกใหม่อีกครั้ง';
            }
        }
    }

    // ขั้นตอนที่ 3: ตั้งรหัสผ่านใหม่
    elseif ($action === 'set_new_password') {
        if (!verify_csrf_token()) {
            $error = 'คำขอไม่ถูกต้องหรือหมดเวลา';
        } else {
            $teacherId = $_SESSION['fp_teacher_id'] ?? null;
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (!$teacherId) {
                unset($_SESSION['fp_step']);
                header('Location: forgot_password.php');
                exit;
            }

            if (strlen($newPassword) < 6) {
                $error = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("
                    UPDATE teachers 
                    SET password_hash = ?, otp_code = NULL, otp_expires_at = NULL, failed_login_attempts = 0, locked_until = NULL 
                    WHERE id = ?
                ");
                $upd->execute([$newHash, $teacherId]);

                log_audit($pdo, 'FORGOT_PASSWORD_RESET_SUCCESS', 'teachers', $teacherId);

                // ล้าง session ทั้งหมดของ forgot password
                unset($_SESSION['fp_teacher_id']);
                unset($_SESSION['fp_phone']);
                unset($_SESSION['fp_demo_otp']);
                unset($_SESSION['fp_step']);

                header('Location: login.php?msg=' . urlencode('ตั้งรหัสผ่านใหม่สำเร็จแล้ว กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่') . '&type=success');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน - Money Life</title>
    <link rel="stylesheet" href="assets/css/teacher.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .fp-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
            width: 100%;
            max-width: 460px;
            padding: 38px 40px;
            position: relative;
            overflow: hidden;
        }
        .fp-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #f59e0b, #ef4444);
        }
        .brand-header {
            text-align: center;
            margin-bottom: 24px;
        }
    </style>
</head>
<body>

<div class="fp-card">
    <div class="brand-header">
        <div style="font-size: 38px; margin-bottom: 6px;">🔑</div>
        <h1 style="font-size: 22px; font-weight: 700; color: #0f172a;">รีเซ็ตรหัสผ่าน</h1>
        <p style="font-size: 13.5px; color: #64748b;">ยืนยันตัวตนด้วยเลข 13 หลักและ OTP เพื่อความปลอดภัย</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span>⚠️</span>
            <div><?= e($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($step === 'verify_identity'): ?>
        <!-- ขั้นตอนที่ 1: กรอกเลข 13 หลัก + เบอร์โทรศัพท์ -->
        <form method="POST" action="forgot_password.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="check_identity">

            <div class="form-group">
                <label class="form-label">เลขประจำตัวประชาชน (13 หลัก)</label>
                <input type="text" name="national_id" class="form-control" maxlength="13" required 
                       placeholder="เลขประจำตัวประชาชน 13 หลัก" value="<?= e($_POST['national_id'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">เบอร์โทรศัพท์ที่ลงทะเบียน</label>
                <input type="tel" name="phone" class="form-control" maxlength="10" required 
                       placeholder="เช่น 0812345678" value="<?= e($_POST['phone'] ?? '') ?>">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px;">
                ขอรหัสยืนยัน OTP
            </button>
        </form>

    <?php elseif ($step === 'verify_otp'): ?>
        <!-- ขั้นตอนที่ 2: ยืนยันรหัส OTP -->
        <p style="font-size: 13.5px; color: #64748b; text-align: center; margin-bottom: 16px;">
            ระบบได้ส่งรหัส OTP ไปยังหมายเลข <strong><?= mask_phone_number($_SESSION['fp_phone'] ?? '') ?></strong>
        </p>

        <?php if (!empty($_SESSION['fp_demo_otp'])): ?>
            <div style="margin-bottom: 16px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 10px; font-size: 13px; color: #1e40af; text-align: center;">
                💬 [จำลอง SMS]: รหัส OTP คือ <strong><?= $_SESSION['fp_demo_otp'] ?></strong>
            </div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify_otp">

            <div class="form-group">
                <label class="form-label" style="text-align: center;">กรอกรหัสยืนยัน OTP 6 หลัก</label>
                <input type="text" name="otp_code" class="form-control" maxlength="6" pattern="[0-9]{6}" required 
                       placeholder="000000" autofocus
                       style="text-align: center; font-size: 24px; letter-spacing: 6px; font-weight: 700;">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                ยืนยันรหัส OTP
            </button>
        </form>

    <?php elseif ($step === 'reset_password'): ?>
        <!-- ขั้นตอนที่ 3: ตั้งรหัสผ่านใหม่ -->
        <form method="POST" action="forgot_password.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="set_new_password">

            <div class="form-group">
                <label class="form-label">รหัสผ่านใหม่ (อย่างน้อย 6 ตัวอักษร)</label>
                <input type="password" name="new_password" class="form-control" required placeholder="รหัสผ่านใหม่">
            </div>

            <div class="form-group">
                <label class="form-label">ยืนยันรหัสผ่านใหม่อีกครั้ง</label>
                <input type="password" name="confirm_password" class="form-control" required placeholder="ยืนยันรหัสผ่านใหม่">
            </div>

            <button type="submit" class="btn btn-accent" style="width: 100%; padding: 12px;">
                บันทึกรหัสผ่านใหม่
            </button>
        </form>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 20px;">
        <a href="login.php" style="color: #64748b; font-size: 13px; text-decoration: none;">← กลับไปหน้าเข้าสู่ระบบ</a>
    </div>
</div>

</body>
</html>
