<?php
session_start();

require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}


/* =========================================
   รับข้อมูลจากฟอร์ม
========================================= */

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$class_level = trim($_POST['class_level'] ?? '');
$class_room = trim($_POST['class_room'] ?? '');
$student_number = intval($_POST['student_number'] ?? 0);


/* =========================================
   ตรวจสอบข้อมูล
========================================= */

if (
    $first_name === '' ||
    $last_name === '' ||
    $class_level === '' ||
    $class_room === '' ||
    $student_number <= 0
) {
    die('กรุณากรอกข้อมูลให้ครบถ้วน');
}


/* =========================================
   ตรวจสอบว่านักเรียนมีอยู่แล้วหรือไม่
   ใช้ ชั้น + ห้อง + เลขที่ เป็นตัวระบุ
========================================= */

$sql = "
    SELECT id
    FROM students
    WHERE class_level = :class_level
      AND class_room = :class_room
      AND student_number = :student_number
    LIMIT 1
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':class_level' => $class_level,
    ':class_room' => $class_room,
    ':student_number' => $student_number
]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);


/* =========================================
   ถ้ามีนักเรียนอยู่แล้ว
   ใช้ student_id เดิม
========================================= */

if ($student) {

    $student_id = $student['id'];

}


/* =========================================
   ถ้ายังไม่มีนักเรียน
   สร้างข้อมูลนักเรียนใหม่
========================================= */

else {

    $sql = "
        INSERT INTO students
        (
            first_name,
            last_name,
            class_level,
            class_room,
            student_number
        )
        VALUES
        (
            :first_name,
            :last_name,
            :class_level,
            :class_room,
            :student_number
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':class_level' => $class_level,
        ':class_room' => $class_room,
        ':student_number' => $student_number
    ]);

    $student_id = $pdo->lastInsertId();

}


/* =========================================
   สร้าง Game Session ใหม่ทุกครั้ง
========================================= */

$sql_session = "
    INSERT INTO game_sessions
    (
        student_id,
        total_score,
        max_score,
        percentage
    )
    VALUES
    (
        :student_id,
        0,
        50,
        0
    )
";

$stmt_session = $pdo->prepare($sql_session);

$stmt_session->execute([
    ':student_id' => $student_id
]);


/* =========================================
   ดึง Session ID ใหม่
========================================= */

$session_id = $pdo->lastInsertId();

$_SESSION['game_session_id'] = $session_id;
$_SESSION['student_id'] = $student_id;

/* =========================================
   ส่งไปหน้าเกม
========================================= */

header(
    'Location: game.php?session_id=' . $session_id
);

exit;

?>