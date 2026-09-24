<?php
/**
 * Money Life - Teacher Registration Portal
 * Full validation: Thai National ID checksum, Phone OTP verification, Pending status workflow
 */

require_once __DIR__ . '/auth/permissions.php';

if (is_teacher_logged_in()) {
    header('Location: /money-life/teacher/index.php');
    exit;
}

$error = '';
$success = '';
$step = 'register'; // 'register' or 'verify_otp'

// ตรวจสอบว่ามี intermediate registration session หรือไม่
if (!empty($_SESSION['pending_reg_teacher_id'])) {
    $step = 'verify_otp';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'register';

    // 1. ขั้นตอนกรอกข้อมูลสมัครสมาชิก
    if ($action === 'register') {
        if (!verify_csrf_token()) {
            $error = 'คำขอไม่ถูกต้องหรือหมดเวลา กรุณาลองใหม่อีกครั้ง';
        } else {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $nationalId = preg_replace('/[^0-9]/', '', (string)($_POST['national_id'] ?? ''));
            $phone = preg_replace('/[^0-9]/', '', (string)($_POST['phone'] ?? ''));
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            // ตรวจสอบความครบถ้วน
            if ($firstName === '' || $lastName === '' || $position === '' || $nationalId === '' || $phone === '' || $password === '') {
                $error = 'กรุณากรอกข้อมูลให้ครบถ้วนทุกช่อง';
            }
            // ตรวจสอบเลขประจำตัวประชาชน 13 หลักและ Checksum (ส่วนที่ 4)
            elseif (!validate_thai_national_id($nationalId)) {
                $error = 'เลขประจำตัวประชาชนไม่ถูกต้องตามหลักการคำนวณ 13 หลักของไทย';
            }
            // ตรวจสอบเบอร์โทรศัพท์
            elseif (!validate_phone_number($phone)) {
                $error = 'เบอร์โทรศัพท์ต้องขึ้นต้นด้วย 0 และมีความยาว 9-10 หลัก';
            }
            // ตรวจสอบรหัสผ่าน
            elseif (strlen($password) < 6) {
                $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
            }
            elseif ($password !== $confirmPassword) {
                $error = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
            }
            else {
                // ตรวจสอบเลขบัตรประชาชนหรือเบอร์โทรศัพท์ซ้ำ
                $chk = $pdo->prepare("SELECT id, status FROM teachers WHERE national_id = ? OR phone = ?");
                $chk->execute([$nationalId, $phone]);
                $existing = $chk->fetch();

                if ($existing) {
                    $error = 'เลขประจำตัวประชาชนหรือเบอร์โทรศัพท์นี้ถูกใช้ลงทะเบียนในระบบแล้ว';
                } else {
                    try {
                        // สร้าง OTP 6 หลักสำหรับยืนยันเบอร์โทรศัพท์ (SMS simulation)
                        $otpCode = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                        $otpExpiry = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 นาที
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                        $ins = $pdo->prepare("
                            INSERT INTO teachers 
                            (first_name, last_name, position, national_id, phone, password_hash, status, phone_verified, role, otp_code, otp_expires_at)
                            VALUES (?, ?, ?, ?, ?, ?, 'pending', 0, 'teacher', ?, ?)
                        ");
                        $ins->execute([
                            $firstName,
                            $lastName,
                            $position,
                            $nationalId,
                            $phone,
                            $passwordHash,
                            $otpCode,
                            $otpExpiry
                        ]);

                        $newTeacherId = $pdo->lastInsertId();
                        $_SESSION['pending_reg_teacher_id'] = $newTeacherId;
                        $_SESSION['pending_reg_phone'] = $phone;
                        $_SESSION['demo_otp_display'] = $otpCode; // สำหรับทดสอบ Demo SMS

                        log_audit($pdo, 'TEACHER_REGISTER_PENDING', 'teachers', $newTeacherId, [
                            'name' => "$firstName $lastName",
                            'phone' => mask_phone_number($phone)
                        ]);

                        $step = 'verify_otp';
                    } catch (PDOException $e) {
                        $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
                    }
                }
            }
        }
    }

    // 2. ขั้นตอนยืนยัน OTP เบอร์โทรศัพท์
    elseif ($action === 'verify_otp') {
        if (!verify_csrf_token()) {
            $error = 'คำขอไม่ถูกต้องหรือหมดเวลา';
        } else {
            $teacherId = $_SESSION['pending_reg_teacher_id'] ?? null;
            $inputOtp = trim($_POST['otp_code'] ?? '');

            if (!$teacherId) {
                unset($_SESSION['pending_reg_teacher_id']);
                header('Location: register.php');
                exit;
            }

            $stmt = $pdo->prepare("SELECT id, otp_code, otp_expires_at FROM teachers WHERE id = ?");
            $stmt->execute([$teacherId]);
            $teacher = $stmt->fetch();

            if ($teacher && $teacher['otp_code'] === $inputOtp) {
                if (strtotime($teacher['otp_expires_at']) < time()) {
                    $error = 'รหัส OTP หมดอายุแล้ว กรุณาสมัครใหม่อีกครั้ง';
                } else {
                    // ยืนยันเบอร์สำเร็จ สถานะเป็น 'pending' รอ Admin อนุมัติ (ส่วนที่ 5 & 7)
                    $upd = $pdo->prepare("UPDATE teachers SET phone_verified = 1, otp_code = NULL, otp_expires_at = NULL WHERE id = ?");
                    $upd->execute([$teacherId]);

                    log_audit($pdo, 'TEACHER_PHONE_VERIFIED', 'teachers', $teacherId);

                    unset($_SESSION['pending_reg_teacher_id']);
                    unset($_SESSION['pending_reg_phone']);
                    unset($_SESSION['demo_otp_display']);

                    // Redirect ไปหน้า login พร้อมข้อความสถานะ pending
                    header('Location: login.php?msg=' . urlencode('ยืนยันเบอร์โทรศัพท์สำเร็จ! บัญชีของคุณอยู่ระหว่างการตรวจสอบโดยผู้ดูแลระบบ และจะสามารถเข้าสู่ระบบได้เมื่อได้รับการอนุมัติ') . '&type=warning');
                    exit;
                }
            } else {
                $error = 'รหัส OTP ไม่ถูกต้อง กรุณาตรวจสอบรหัส 6 หลักอีกครั้ง';
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
    <title>สมัครใช้งานสำหรับครู - Money Life</title>
    <link rel="stylesheet" href="assets/css/teacher.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
        }
        .reg-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
            width: 100%;
            max-width: 540px;
            padding: 36px 40px;
            position: relative;
            overflow: hidden;
        }
        .reg-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #3b82f6, #10b981);
        }
        .brand-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .brand-icon {
            font-size: 38px;
            margin-bottom: 6px;
        }
        .brand-title {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
        }
        .brand-subtitle {
            font-size: 13.5px;
            color: #64748b;
        }
        .helper-text {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
    </style>
</head>
<body>

<div class="reg-card">
    <div class="brand-header">
        <div class="brand-icon">👨‍🏫</div>
        <h1 class="brand-title">สมัครใช้งานสำหรับครู</h1>
        <p class="brand-subtitle">ระบบจำลองการวางแผนการเงิน Money Life</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span>⚠️</span>
            <div><?= e($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($step === 'register'): ?>
        <!-- ขั้นตอนที่ 1: กรอกข้อมูลส่วนตัวและรหัสผ่าน -->
        <form method="POST" action="register.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="register">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">ชื่อ *</label>
                    <input type="text" name="first_name" class="form-control" 
                           value="<?= e($_POST['first_name'] ?? '') ?>" required placeholder="ชื่อจริง">
                </div>
                <div class="form-group">
                    <label class="form-label">นามสกุล *</label>
                    <input type="text" name="last_name" class="form-control" 
                           value="<?= e($_POST['last_name'] ?? '') ?>" required placeholder="นามสกุล">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">ตำแหน่ง *</label>
                <input type="text" name="position" class="form-control" 
                       value="<?= e($_POST['position'] ?? '') ?>" required placeholder="เช่น ครูผู้ช่วย / ครูชำนาญการ / ครูประจำชั้น">
            </div>

            <div class="form-group">
                <label class="form-label">เลขประจำตัวประชาชน (13 หลัก) *</label>
                <input type="text" name="national_id" class="form-control" maxlength="13" pattern="[0-9]{13}" 
                       value="<?= e($_POST['national_id'] ?? '') ?>" required placeholder="เฉพาะตัวเลข 13 หลัก ไม่ต้องใส่ขีด">
                <div class="helper-text">🔒 ระบบจะตรวจสอบความถูกต้องของเลข 13 หลักด้วย Checksum ตามมาตรฐานราชการไทย</div>
            </div>

            <div class="form-group">
                <label class="form-label">เบอร์โทรศัพท์ *</label>
                <input type="tel" name="phone" class="form-control" maxlength="10" pattern="0[0-9]{8,9}" 
                       value="<?= e($_POST['phone'] ?? '') ?>" required placeholder="เช่น 0812345678">
                <div class="helper-text">📱 ต้องเป็นเบอร์ที่สามารถรับรหัส OTP สำหรับยืนยันตัวตนได้</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password (รหัสผ่าน) *</label>
                    <input type="password" name="password" class="form-control" required placeholder="อย่างน้อย 6 ตัวอักษร">
                </div>
                <div class="form-group">
                    <label class="form-label">ยืนยัน Password *</label>
                    <input type="password" name="confirm_password" class="form-control" required placeholder="พิมพ์รหัสผ่านอีกครั้ง">
                </div>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; margin-bottom: 20px; font-size: 12.5px; color: #475569; line-height: 1.5;">
                ℹ️ <strong>ขั้นตอนความปลอดภัย:</strong> หลังสมัครและยืนยัน OTP แล้ว บัญชีจะมีสถานะ <code>Pending</code> เพื่อรอการตรวจสอบและอนุมัติจากผู้ดูแลระบบก่อนเข้าใช้งาน
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 16px;">
                สมัครใช้งาน
            </button>
        </form>

        <div style="text-align: center; margin-top: 20px; font-size: 14px; color: #64748b;">
            มีบัญชีครูอยู่แล้ว? <a href="login.php" style="color: #3b82f6; text-decoration: none; font-weight: 600;">เข้าสู่ระบบ</a>
        </div>
        <div style="text-align: center; margin-top: 10px;">
            <a href="../index.php" style="color: #94a3b8; font-size: 13px; text-decoration: none;">← กลับไปยังหน้าเริ่มเกม</a>
        </div>

    <?php else: ?>
        <!-- ขั้นตอนที่ 2: ยืนยัน OTP เบอร์โทรศัพท์ -->
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="font-size: 36px; margin-bottom: 8px;">📲</div>
            <h3 style="font-size: 18px; color: #0f172a;">ยืนยันเบอร์โทรศัพท์ด้วย OTP</h3>
            <p style="font-size: 13.5px; color: #64748b;">
                รหัสยืนยัน 6 หลักถูกส่งไปยังหมายเลข <strong><?= mask_phone_number($_SESSION['pending_reg_phone'] ?? '') ?></strong>
            </p>

            <?php if (!empty($_SESSION['demo_otp_display'])): ?>
                <!-- แสดง Demo SMS notification เพื่อความสะดวกในการทดสอบ -->
                <div style="margin: 16px 0; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 12px; font-size: 13px; color: #1e40af;">
                    💬 <strong>[จำลอง SMS ข้อความเข้า]:</strong><br>
                    รหัส OTP สำหรับยืนยันการสมัครครู Money Life คือ: <strong style="font-size: 18px; letter-spacing: 3px; color: #1d4ed8;"><?= $_SESSION['demo_otp_display'] ?></strong> (หมดอายุใน 10 นาที)
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" action="register.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify_otp">

            <div class="form-group">
                <label class="form-label" style="text-align: center;">กรอกรหัสยืนยัน OTP 6 หลัก</label>
                <input type="text" name="otp_code" class="form-control" maxlength="6" pattern="[0-9]{6}" required 
                       placeholder="000000" autofocus
                       style="text-align: center; font-size: 24px; letter-spacing: 6px; font-weight: 700;">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 16px;">
                ยืนยันหมายเลขโทรศัพท์
            </button>
        </form>

        <div style="text-align: center; margin-top: 20px;">
            <a href="register.php?cancel=1" onclick="<?php unset($_SESSION['pending_reg_teacher_id']); ?>" style="color: #64748b; font-size: 13px; text-decoration: none;">
                ← ยกเลิกและกรอกข้อมูลใหม่
            </a>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
