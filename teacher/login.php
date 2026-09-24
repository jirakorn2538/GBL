<?php
/**
 * Money Life - Teacher Login Portal V2
 * Supports Login with Thai National ID (13 digits) OR Phone Number + Password
 * Strict Status Enforcement: Pending, Rejected, Suspended, Approved
 */

require_once __DIR__ . '/auth/permissions.php';

// หาก Login อยู่แล้ว พาไป Dashboard ทันที
if (is_teacher_logged_in()) {
    header('Location: /money-life/teacher/index.php');
    exit;
}

$error = $_GET['error'] ?? '';
$msg = $_GET['msg'] ?? '';
$msgType = $_GET['type'] ?? 'info';
$step = 'login'; // 'login' or 'totp'

// จัดการกรณี 2FA intermediate session
if (!empty($_SESSION['2fa_pending_user_id'])) {
    $step = 'totp';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        if (!verify_csrf_token()) {
            $error = 'คำขอไม่ถูกต้องหรือหมดเวลา กรุณาลองใหม่อีกครั้ง';
        } else {
            // รับค่า identifier เป็นเลข 13 หลัก หรือเบอร์โทรศัพท์
            $identifierRaw = trim($_POST['identifier'] ?? '');
            $identifier = preg_replace('/[^0-9]/', '', $identifierRaw);
            $password = $_POST['password'] ?? '';
            $remember = !empty($_POST['remember']);

            if ($identifier === '' || $password === '') {
                $error = 'กรุณากรอกเลขประจำตัวประชาชนหรือเบอร์โทรศัพท์ และรหัสผ่าน';
            } else {
                // 1. ตรวจสอบ Rate Limit / Brute Force
                $rateLimit = check_rate_limit($pdo, $identifier);
                if ($rateLimit['locked']) {
                    $error = $rateLimit['message'];
                } else {
                    // 2. ดึงข้อมูลครูโดยค้นหาจาก national_id หรือ phone
                    $stmt = $pdo->prepare("
                        SELECT id, first_name, last_name, position, national_id, phone, password_hash, 
                               status, phone_verified, role, two_factor_enabled, two_factor_secret 
                        FROM teachers 
                        WHERE national_id = ? OR phone = ?
                        LIMIT 1
                    ");
                    $stmt->execute([$identifier, $identifier]);
                    $teacher = $stmt->fetch();

                    $passwordValid = false;
                    if ($teacher) {
                        $passwordValid = password_verify($password, $teacher['password_hash']);
                    }

                    if ($teacher && $passwordValid) {
                        // 3. ตรวจสอบสถานะบัญชีครู (ส่วนที่ 5 & 9)
                        if ($teacher['status'] === 'pending') {
                            $error = 'บัญชีของคุณอยู่ระหว่างการตรวจสอบโดยผู้ดูแลระบบ';
                            log_audit($pdo, 'LOGIN_BLOCKED_PENDING', 'teachers', $teacher['id']);
                        } elseif ($teacher['status'] === 'rejected') {
                            $error = 'บัญชีของคุณยังไม่ได้รับอนุมัติ กรุณาติดต่อผู้ดูแลระบบ';
                            log_audit($pdo, 'LOGIN_BLOCKED_REJECTED', 'teachers', $teacher['id']);
                        } elseif ($teacher['status'] === 'suspended') {
                            $error = 'บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';
                            log_audit($pdo, 'LOGIN_BLOCKED_SUSPENDED', 'teachers', $teacher['id']);
                        } elseif ($teacher['status'] !== 'approved') {
                            $error = 'สถานะบัญชีไม่ถูกต้อง';
                        } else {
                            // รหัสผ่านและสถานะถูกต้อง: ตรวจสอบ 2FA
                            if ((int)$teacher['two_factor_enabled'] === 1 && !empty($teacher['two_factor_secret'])) {
                                $_SESSION['2fa_pending_user_id'] = $teacher['id'];
                                $_SESSION['2fa_pending_remember'] = $remember;
                                $step = 'totp';
                            } else {
                                // เข้าสู่ระบบสำเร็จ
                                session_regenerate_id(true);
                                $_SESSION['teacher_logged_in'] = true;
                                $_SESSION['teacher_id'] = $teacher['id'];
                                $_SESSION['teacher_name'] = $teacher['first_name'] . ' ' . $teacher['last_name'];
                                $_SESSION['teacher_position'] = $teacher['position'];
                                $_SESSION['teacher_role'] = $teacher['role'];
                                $_SESSION['teacher_national_id_masked'] = mask_national_id($teacher['national_id']);
                                $_SESSION['teacher_phone_masked'] = mask_phone_number($teacher['phone']);

                                reset_failed_login($pdo, $teacher['id']);
                                log_audit($pdo, 'LOGIN_SUCCESS', 'teachers', $teacher['id']);

                                header('Location: /money-life/teacher/index.php');
                                exit;
                            }
                        }
                    } else {
                        // รหัสผ่านผิด หรือไม่พบบัญชี
                        record_failed_login($pdo, $identifier);
                        log_audit($pdo, 'LOGIN_FAILED', 'teachers', null, [
                            'identifier' => mask_national_id($identifier)
                        ]);
                        // ข้อความปลอดภัยแบบไม่เปิดเผยข้อมูล (ส่วนที่ 9 & 20)
                        $error = 'ข้อมูลเข้าสู่ระบบไม่ถูกต้อง';
                    }
                }
            }
        }
    } elseif ($action === 'verify_2fa') {
        if (!verify_csrf_token()) {
            $error = 'คำขอไม่ถูกต้องหรือหมดเวลา';
            $step = 'totp';
        } else {
            $userId = $_SESSION['2fa_pending_user_id'] ?? null;
            $code = trim($_POST['totp_code'] ?? '');

            if (!$userId) {
                unset($_SESSION['2fa_pending_user_id']);
                header('Location: login.php');
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
            $stmt->execute([$userId]);
            $teacher = $stmt->fetch();

            if ($teacher && TOTP::verifyCode($teacher['two_factor_secret'], $code)) {
                unset($_SESSION['2fa_pending_user_id']);
                unset($_SESSION['2fa_pending_remember']);

                session_regenerate_id(true);
                $_SESSION['teacher_logged_in'] = true;
                $_SESSION['teacher_id'] = $teacher['id'];
                $_SESSION['teacher_name'] = $teacher['first_name'] . ' ' . $teacher['last_name'];
                $_SESSION['teacher_position'] = $teacher['position'];
                $_SESSION['teacher_role'] = $teacher['role'];
                $_SESSION['teacher_national_id_masked'] = mask_national_id($teacher['national_id']);
                $_SESSION['teacher_phone_masked'] = mask_phone_number($teacher['phone']);

                reset_failed_login($pdo, $teacher['id']);
                log_audit($pdo, 'LOGIN_2FA_SUCCESS', 'teachers', $teacher['id']);

                header('Location: /money-life/teacher/index.php');
                exit;
            } else {
                $error = 'รหัสยืนยัน 2FA ไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
                log_audit($pdo, 'LOGIN_2FA_FAILED', 'teachers', $userId);
                $step = 'totp';
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
    <title>เข้าสู่ระบบสำหรับครู - Money Life</title>
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
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
            width: 100%;
            max-width: 440px;
            padding: 38px 40px;
            position: relative;
            overflow: hidden;
        }
        .login-card::before {
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
            margin-bottom: 26px;
        }
        .brand-icon {
            font-size: 42px;
            margin-bottom: 8px;
            display: inline-block;
        }
        .brand-title {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .brand-subtitle {
            font-size: 14px;
            color: #64748b;
        }
        .login-btn {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 10px;
            margin-top: 6px;
        }
        .nav-links {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            gap: 10px;
            text-align: center;
            font-size: 13.5px;
        }
        .nav-links a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }
        .nav-links a:hover {
            text-decoration: underline;
        }
        .nav-links a.home-link {
            color: #64748b;
            font-size: 13px;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="brand-header">
        <div class="brand-icon">🛡️</div>
        <h1 class="brand-title">ระบบสำหรับผู้ดูแลระบบ (Admin)</h1>
        <p class="brand-subtitle">Money Life: ติดตามข้อมูลนักเรียน การใช้งาน และผลคะแนน</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span>⚠️</span>
            <div><?= e($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType === 'warning' ? 'warning' : 'success' ?>">
            <span><?= $msgType === 'warning' ? '⏳' : '✓' ?></span>
            <div><?= e($msg) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($step === 'login'): ?>
        <!-- ฟอร์มเข้าสู่ระบบ Admin ด้วยเลข 13 หลัก หรือ เบอร์โทรศัพท์ -->
        <form method="POST" action="login.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="login">

            <div class="form-group">
                <label class="form-label" for="identifier">
                    User: เลขประจำตัว 13 หลัก / เบอร์โทรศัพท์
                </label>
                <input type="text" id="identifier" name="identifier" class="form-control" 
                       placeholder="กรอกเลขประจำตัว 13 หลัก หรือ เบอร์โทร" required autofocus autocomplete="username"
                       value="<?= e($_POST['identifier'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password: รหัสผ่าน</label>
                <input type="password" id="password" name="password" class="form-control" 
                       placeholder="กรอกรหัสผ่าน" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary login-btn">
                เข้าสู่ระบบ Admin
            </button>
        </form>

        <div class="nav-links">
            <div style="display: flex; justify-content: space-between;">
                <a href="forgot_password.php">🔑 ลืมรหัสผ่าน?</a>
                <a href="../index.php" class="home-link">← กลับหน้าเริ่มเกม</a>
            </div>
        </div>

    <?php else: ?>
        <!-- ขั้นตอนยืนยัน 2FA TOTP -->
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="font-size: 32px; margin-bottom: 8px;">🔐</div>
            <h3 style="font-size: 18px; color: #0f172a;">การยืนยันสองขั้นตอน (2FA)</h3>
            <p style="font-size: 13.5px; color: #64748b;">
                กรุณากรอกรหัส 6 หลักจาก Google Authenticator หรือ Microsoft Authenticator
            </p>
        </div>

        <form method="POST" action="login.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify_2fa">

            <div class="form-group">
                <label class="form-label" style="text-align: center;" for="totp_code">รหัสยืนยัน 6 หลัก</label>
                <input type="text" id="totp_code" name="totp_code" class="form-control" 
                       placeholder="000000" maxlength="6" pattern="[0-9]{6}" required 
                       style="text-align: center; font-size: 26px; letter-spacing: 8px; font-weight: 700;" autofocus>
            </div>

            <button type="submit" class="btn btn-primary login-btn">
                ยืนยันตัวตน
            </button>
        </form>

        <div style="text-align: center; margin-top: 15px;">
            <a href="login.php" style="color: #64748b; font-size: 13px; text-decoration: none;">← กลับไปหน้าเข้าสู่ระบบ</a>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
