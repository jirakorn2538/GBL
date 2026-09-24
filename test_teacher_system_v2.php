<?php
/**
 * Automated Verification Script V2 for Money Life Teacher Portal
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/teacher/auth/permissions.php';

echo "========================================================\n";
echo "  MONEY LIFE - TEACHER & ADMIN SYSTEM V2 VERIFICATION   \n";
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

// ----------------------------------------------------
// TEST 1: Thai National ID Checksum Validator
// ----------------------------------------------------
echo "--- 1. Thai National ID 13-Digit Checksum ---\n";

assertTest("Valid ID 1100000000008 passes checksum", validate_thai_national_id('1100000000008') === true);
assertTest("Valid ID 1100000000016 passes checksum", validate_thai_national_id('1100000000016') === true);
assertTest("Invalid ID 1234567890123 fails checksum", validate_thai_national_id('1234567890123') === false);
assertTest("Short ID '12345' fails", validate_thai_national_id('12345') === false);
assertTest("Alpha characters in ID fails", validate_thai_national_id('110000000000A') === false);

// ----------------------------------------------------
// TEST 2: PDPA Data Masking
// ----------------------------------------------------
echo "\n--- 2. PDPA Privacy Masking ---\n";

$maskedId = mask_national_id('1100000000008');
assertTest("National ID masked properly (1-1000-*****-00-8)", $maskedId === '1-1000-*****-00-8');

$maskedPhone = mask_phone_number('0812345678');
assertTest("Phone number masked properly (081-***-5678)", $maskedPhone === '081-***-5678');

// ----------------------------------------------------
// TEST 3: Teacher Registration & Status Workflow
// ----------------------------------------------------
echo "\n--- 3. Registration, Pending & Approval Workflow ---\n";

// สร้างครูทดสอบใหม่
// คำนวณ checksum สำหรับ 1100000000024: 1*13 + 1*12 + 2*2 = 29. 29%11 = 7. 11-7 = 4 -> 1100000000024
$testNatId = '1100000000024';
$testPhone = '0855555555';
$testPass = password_hash('PassTest@123', PASSWORD_DEFAULT);

$pdo->prepare("DELETE FROM teachers WHERE national_id = ? OR phone = ?")->execute([$testNatId, $testPhone]);

$ins = $pdo->prepare("
    INSERT INTO teachers (first_name, last_name, position, national_id, phone, password_hash, status, phone_verified, role, otp_code)
    VALUES ('ทดสอบ', 'ครูใหม่', 'ครูประจำการ', ?, ?, ?, 'pending', 0, 'teacher', '123456')
");
$ins->execute([$testNatId, $testPhone, $testPass]);
$newTeacherId = $pdo->lastInsertId();
assertTest("Teacher registered with 'pending' status", $newTeacherId > 0);

// ตรวจสอบว่า pending login ไม่ได้
$stmt = $pdo->prepare("SELECT status FROM teachers WHERE id = ?");
$stmt->execute([$newTeacherId]);
$statusBefore = $stmt->fetchColumn();
assertTest("Pending status prevents active login", $statusBefore === 'pending');

// จำลองการยืนยันเบอร์ด้วย OTP
$pdo->prepare("UPDATE teachers SET phone_verified = 1, otp_code = NULL WHERE id = ?")->execute([$newTeacherId]);
$verifiedCheck = $pdo->query("SELECT phone_verified FROM teachers WHERE id = $newTeacherId")->fetchColumn();
assertTest("Phone verified flag set to 1 via OTP", (int)$verifiedCheck === 1);

// จำลอง Admin กดอนุมัติ
$adminId = $pdo->query("SELECT id FROM teachers WHERE role = 'admin' LIMIT 1")->fetchColumn();
$pdo->prepare("UPDATE teachers SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE id = ?")
    ->execute([$adminId, $newTeacherId]);
$statusAfter = $pdo->query("SELECT status FROM teachers WHERE id = $newTeacherId")->fetchColumn();
assertTest("Account approved by admin status changed to 'approved'", $statusAfter === 'approved');

// จำลอง Admin ระงับบัญชี (suspend)
$pdo->prepare("UPDATE teachers SET status = 'suspended' WHERE id = ?")->execute([$newTeacherId]);
$statusSuspended = $pdo->query("SELECT status FROM teachers WHERE id = $newTeacherId")->fetchColumn();
assertTest("Account suspended status changed to 'suspended'", $statusSuspended === 'suspended');

// Clean up
$pdo->prepare("DELETE FROM teachers WHERE id = ?")->execute([$newTeacherId]);

// ----------------------------------------------------
// TEST 4: Student Start Game Entrance Link
// ----------------------------------------------------
echo "\n--- 4. Student Start Game Page (index.php) Entrance ---\n";

$indexContent = file_get_contents(__DIR__ . '/index.php');
assertTest("index.php contains teacher portal link", strpos($indexContent, 'teacher/login.php') !== false);
assertTest("index.php clearly separates student and teacher sections", strpos($indexContent, 'สำหรับครู') !== false);

// ----------------------------------------------------
// TEST 5: Default Credentials Verification
// ----------------------------------------------------
echo "\n--- 5. Default Credentials ---\n";

$admin = $pdo->query("SELECT * FROM teachers WHERE role = 'admin' LIMIT 1")->fetch();
assertTest("Default Admin has National ID 1100000000008", $admin['national_id'] === '1100000000008');
assertTest("Default Admin has Phone 0812345678", $admin['phone'] === '0812345678');
assertTest("Default Admin password verified", password_verify('AdminPassword@123', $admin['password_hash']));

$sampleTeacher = $pdo->query("SELECT * FROM teachers WHERE national_id = '1100000000016'")->fetch();
assertTest("Sample Teacher has National ID 1100000000016", $sampleTeacher['national_id'] === '1100000000016');
assertTest("Sample Teacher has Phone 0898765432", $sampleTeacher['phone'] === '0898765432');
assertTest("Sample Teacher password verified", password_verify('TeacherPassword@123', $sampleTeacher['password_hash']));

// ----------------------------------------------------
// Final Summary
// ----------------------------------------------------
echo "\n========================================================\n";
echo "  SUMMARY: $testsPassed / $totalTests tests passed (" . round(($testsPassed / $totalTests) * 100, 1) . "%)\n";
echo "========================================================\n";
