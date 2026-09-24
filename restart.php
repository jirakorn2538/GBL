<?php

session_start();

require_once __DIR__ . '/config/database.php';


/* =========================================
   ตรวจสอบว่ามี Session เดิมหรือไม่
========================================= */

if (!isset($_SESSION['game_session_id'])) {
    header('Location: register.php');
    exit;
}


$old_session_id = (int) $_SESSION['game_session_id'];


/* =========================================
   ดึง student_id จาก Game Session เดิม
========================================= */

$sql = "
    SELECT student_id
    FROM game_sessions
    WHERE id = :id
    LIMIT 1
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':id' => $old_session_id
]);

$old_session = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$old_session) {
    header('Location: register.php');
    exit;
}


$student_id = (int) $old_session['student_id'];


/* =========================================
   สร้าง Game Session ใหม่
========================================= */

$sql = "
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

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':student_id' => $student_id
]);


/* =========================================
   ได้ Session ID ใหม่
========================================= */

$new_session_id = $pdo->lastInsertId();


/* =========================================
   เปลี่ยน Session ปัจจุบัน
========================================= */

$_SESSION['game_session_id'] = $new_session_id;


/* =========================================
   เริ่มเกมใหม่
========================================= */

header(
    'Location: game.php?session_id=' . $new_session_id
);

exit;

?>