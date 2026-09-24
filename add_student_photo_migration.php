<?php
require_once __DIR__ . '/config/database.php';

echo "--- Updating Students Table for 5-digit code and Photo ---\n";

try {
    // 1. ตรวจสอบคอลัมน์ในตาราง students
    $cols = $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('photo', $cols)) {
        $pdo->exec("ALTER TABLE students ADD COLUMN `photo` VARCHAR(255) NULL AFTER `student_number`");
        echo "✓ Added 'photo' column to students table.\n";
    } else {
        echo "✓ Column 'photo' already exists in students table.\n";
    }

    if (!in_array('student_code', $cols)) {
        $pdo->exec("ALTER TABLE students ADD COLUMN `student_code` VARCHAR(10) NULL AFTER `id`");
        echo "✓ Added 'student_code' column to students table.\n";
    } else {
        echo "✓ Column 'student_code' already exists in students table.\n";
    }

    // 2. สร้างโฟลเดอร์ uploads/students/
    $uploadDir = __DIR__ . '/uploads/students';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
        echo "✓ Created directory: $uploadDir\n";
    } else {
        echo "✓ Directory already exists: $uploadDir\n";
    }

    echo "Migration for students completed successfully!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
