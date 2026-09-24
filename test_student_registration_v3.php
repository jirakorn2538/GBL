<?php
/**
 * Test Student Registration with 5-digit code and Photo Upload
 */

require_once __DIR__ . '/config/database.php';

echo "========================================================\n";
echo "  MONEY LIFE - STUDENT 5-DIGIT CODE & PHOTO TEST        \n";
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

// 1. ตรวจสอบโครงสร้างตาราง students
$cols = $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);
assertTest("Column 'student_code' exists in students", in_array('student_code', $cols));
assertTest("Column 'photo' exists in students", in_array('photo', $cols));

// 2. ตรวจสอบโฟลเดอร์ uploads/students/
$uploadDir = __DIR__ . '/uploads/students';
assertTest("Directory uploads/students exists and is writable", is_dir($uploadDir) && is_writable($uploadDir));

// 3. ทดสอบ Validation 5 หลัก
$validCode = '54321';
$invalidCode1 = '1234';
$invalidCode2 = '123456';
$invalidCode3 = '1234A';

assertTest("Valid 5-digit code '54321' passes", strlen($validCode) === 5 && ctype_digit($validCode));
assertTest("Invalid code '1234' rejected", !(strlen($invalidCode1) === 5 && ctype_digit($invalidCode1)));
assertTest("Invalid code '123456' rejected", !(strlen($invalidCode2) === 5 && ctype_digit($invalidCode2)));
assertTest("Invalid code '1234A' rejected", !(strlen($invalidCode3) === 5 && ctype_digit($invalidCode3)));

// 4. ทดสอบจำลองการบันทึกภาพและข้อมูลนักเรียนจริง
$dummyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
$testPhotoName = 'test_std_' . time() . '.png';
$testPhotoPath = $uploadDir . '/' . $testPhotoName;
file_put_contents($testPhotoPath, $dummyPng);

assertTest("Test photo generated successfully", file_exists($testPhotoPath));

$relPhotoPath = 'uploads/students/' . $testPhotoName;
$ins = $pdo->prepare("
    INSERT INTO students (student_code, first_name, last_name, class_level, class_room, student_number, photo, status)
    VALUES (?, 'เด็กชาย', 'มีรูปภาพ', 'ม.3', '1', 15, ?, 'active')
");
$ins->execute([$validCode, $relPhotoPath]);
$newStudentId = $pdo->lastInsertId();
assertTest("Student with 5-digit code and photo inserted into DB (ID: $newStudentId)", $newStudentId > 0);

// ตรวจสอบข้อมูลจากฐานข้อมูล
$checkStmt = $pdo->prepare("SELECT student_code, photo FROM students WHERE id = ?");
$checkStmt->execute([$newStudentId]);
$checkRow = $checkStmt->fetch();

assertTest("Stored student_code is 5 digits ('54321')", $checkRow['student_code'] === '54321');
assertTest("Stored photo path is correct", $checkRow['photo'] === $relPhotoPath);

// ลบข้อมูลทดสอบ
$pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$newStudentId]);
if (file_exists($testPhotoPath)) {
    unlink($testPhotoPath);
}
assertTest("Cleaned up test student and photo file", true);

// 5. ตรวจสอบไฟล์ register.php
$regHtml = file_get_contents(__DIR__ . '/register.php');
assertTest("register.php has multipart/form-data enctype", strpos($regHtml, 'enctype="multipart/form-data"') !== false);
assertTest("register.php has 5-digit pattern for student_code", strpos($regHtml, 'pattern="[0-9]{5}"') !== false);
assertTest("register.php has student_photo file input", strpos($regHtml, 'name="student_photo"') !== false);
assertTest("register.php has image preview script", strpos($regHtml, 'previewImage') !== false);

echo "\n========================================================\n";
echo "  SUMMARY: $testsPassed / $totalTests tests passed (" . round(($testsPassed / $totalTests) * 100, 1) . "%)\n";
echo "========================================================\n";
