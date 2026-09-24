<?php
/**
 * Test Admin Portal & Credentials
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/teacher/auth/permissions.php';

echo "========================================================\n";
echo "   MONEY LIFE - ADMIN PORTAL & CREDENTIALS TEST         \n";
echo "========================================================\n\n";

$testsPassed = 0;
$totalTests = 0;

function assertTest($name, $condition, $details = '') {
    global $testsPassed, $totalTests;
    $totalTests++;
    if ($condition) {
        $testsPassed++;
        echo " [PASS] $name\n";
    } else {
        echo " [FAIL] $name: $details\n";
    }
}

// 1. ตรวจสอบ Admin ในฐานข้อมูล
$targetUser = '1329900585149';
$targetPass = '0873565288';

$stmt = $pdo->prepare("SELECT * FROM teachers WHERE national_id = ?");
$stmt->execute([$targetUser]);
$admin = $stmt->fetch();

assertTest("Admin account exists in DB with National ID 1329900585149", !empty($admin));
assertTest("Admin has role = 'admin'", ($admin['role'] ?? '') === 'admin');
assertTest("Admin has status = 'approved'", ($admin['status'] ?? '') === 'approved');
assertTest("Admin password verifies correctly with '0873565288'", password_verify($targetPass, $admin['password_hash'] ?? ''));

// 2. ตรวจสอบ Checksum เลขประจำตัว 13 หลัก 1329900585149
assertTest("National ID 1329900585149 passes Thai checksum validation", validate_thai_national_id($targetUser));

// 3. ตรวจสอบการ Login ด้วยเบอร์โทรศัพท์ (สำรอง)
$stmtPhone = $pdo->prepare("SELECT * FROM teachers WHERE phone = ?");
$stmtPhone->execute([$targetPass]);
$adminByPhone = $stmtPhone->fetch();
assertTest("Admin account found by phone 0873565288", !empty($adminByPhone) && $adminByPhone['id'] === $admin['id']);

// 4. ตรวจสอบไฟล์หน้าหลัก index.php
$indexHtml = file_get_contents(__DIR__ . '/index.php');
assertTest("index.php contains 'สำหรับผู้ดูแลระบบ (Admin)' section", strpos($indexHtml, 'สำหรับผู้ดูแลระบบ (Admin)') !== false);
assertTest("index.php has button linking to teacher/login.php", strpos($indexHtml, 'teacher/login.php') !== false);

// 5. ตรวจสอบหน้า login.php
$loginHtml = file_get_contents(__DIR__ . '/teacher/login.php');
assertTest("login.php displays Admin branding", strpos($loginHtml, 'ระบบสำหรับผู้ดูแลระบบ (Admin)') !== false);
assertTest("login.php has User 13 digits input field", strpos($loginHtml, 'User: เลขประจำตัว 13 หลัก') !== false);

// 6. ตรวจสอบข้อมูลนักเรียน การใช้งาน และคะแนน
$studentCount = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$sessionCount = (int)$pdo->query("SELECT COUNT(*) FROM game_sessions")->fetchColumn();
assertTest("Admin can query student records (Found: $studentCount students)", $studentCount > 0);
assertTest("Admin can query game session & score records (Found: $sessionCount sessions)", $sessionCount > 0);

echo "\n========================================================\n";
echo "  SUMMARY: $testsPassed / $totalTests tests passed (" . round(($testsPassed / $totalTests) * 100, 1) . "%)\n";
echo "========================================================\n";
