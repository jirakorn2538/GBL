<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['game_session_id'])) {
    header('Location: register.php');
    exit;
}

$session_id = (int)$_SESSION['game_session_id'];

$stmt = $pdo->prepare("
    SELECT
        gs.total_score,
        gs.max_score,
        s.first_name,
        s.last_name,
        s.class_level,
        s.class_room,
        s.student_number,
        s.student_code,
        s.photo
    FROM game_sessions gs
    INNER JOIN students s ON s.id = gs.student_id
    WHERE gs.id = ?
    LIMIT 1
");
$stmt->execute([$session_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    die('ไม่พบข้อมูลเกม');
}

$stmt = $pdo->prepare("
    SELECT selected_option, score
    FROM game_answers
    WHERE session_id = ?
      AND scenario_number = 4
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$session_id]);
$answer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$answer) {
    header('Location: game4.php');
    exit;
}

$score = (int)$answer['score'];

$labels = [
    12 => 'ใช้เงินฉุกเฉิน 500 บาท',
    5  => 'ใช้เงินสำหรับค่าใช้จ่ายทั่วไป 500 บาท',
    -8 => 'ยืมเงินจากเพื่อน 500 บาท'
];

$selected_text = $labels[$score] ?? trim((string)$answer['selected_option']);
$total_score = (int)$game['total_score'];
$max_score = 50;
$percentage = $max_score > 0 ? ($total_score / $max_score) * 100 : 0;

if ($score === 12) {
    $icon = '🟢';
    $title = 'ยอดเยี่ยม!';
    $statusType = 'great';
    $message = 'คุณเลือกใช้เงินฉุกเฉินสำหรับเหตุการณ์ที่ไม่คาดคิด การมีเงินสำรองช่วยให้สามารถรับมือกับเหตุฉุกเฉินโดยไม่ต้องก่อหนี้เพิ่ม';
} elseif ($score === 5) {
    $icon = '🟡';
    $title = 'พอใช้';
    $statusType = 'fair';
    $message = 'คุณเลือกใช้เงินสำหรับค่าใช้จ่ายทั่วไป วิธีนี้ช่วยหลีกเลี่ยงการก่อหนี้ แต่ทำให้เงินที่เตรียมไว้สำหรับค่าใช้จ่ายประจำลดลง';
} else {
    $icon = '🔴';
    $title = 'ต้องระวัง';
    $statusType = 'caution';
    $message = 'คุณเลือกยืมเงินจากเพื่อนเพื่อจัดการกับเหตุฉุกเฉิน การมีเงินสำรองสำหรับเหตุฉุกเฉินจึงเป็นสิ่งสำคัญ เพราะช่วยลดความจำเป็นในการก่อหนี้';
}

// อัปเดตเปอร์เซ็นต์และเวลาจบเกม
$stmt = $pdo->prepare("UPDATE game_sessions SET percentage = ?, completed_at = CURRENT_TIMESTAMP WHERE id = ?");
$stmt->execute([$percentage, $session_id]);

// การประเมินผลภาพรวม
if ($total_score >= 40) {
    $gradeBadge = '🏆 ยอดนักวางแผนการเงินมือทอง';
    $gradeColor = '#059669';
    $gradeBg = '#ecfdf5';
    $evalDesc = 'ยอดเยี่ยมมาก! คุณมีความเข้าใจในเรื่องการบริหารเงินอย่างดีเลิศ สามารถจัดสรรเงินสำหรับการใช้จ่าย การออม และเงินสำรองฉุกเฉินได้อย่างมีประสิทธิภาพสูงสุด';
} elseif ($total_score >= 25) {
    $gradeBadge = '🌟 นักวางแผนการเงินระดับดี';
    $gradeColor = '#0284c7';
    $gradeBg = '#f0f9ff';
    $evalDesc = 'ทำได้ดี! คุณมีพื้นฐานการเงินที่ดี หากฝึกการชะลอการซื้อของฟุ่มเฟือย และแบ่งเงินสำรองฉุกเฉินสม่ำเสมอจะช่วยให้เงินมั่นคงยิ่งขึ้น';
} else {
    $gradeBadge = '🎯 นักเรียนรู้การเงินมือใหม่';
    $gradeColor = '#d97706';
    $gradeBg = '#fffbeb';
    $evalDesc = 'เป็นโอกาสที่ดีในการเรียนรู้! ในอนาคตควรเน้นการ "ออมก่อนใช้" แยกสิ่งที่จำเป็นออกจากสิ่งที่อยากได้ และเตรียมเงินฉุกเฉินเพื่อหลีกเลี่ยงการเป็นหนี้';
}

$tipText = "การวางแผนการเงินที่ดีในชีวิตประจำวัน ควรแบ่งเงินออกเป็น 3 ส่วนหลัก ได้แก่ 1. เงินสำหรับค่าใช้จ่ายจำเป็น 2. เงินออมเพื่อเป้าหมายในอนาคต และ 3. เงินสำรองสำหรับเหตุฉุกเฉิน";

$summarySpeech = "ยินดีด้วยครับ คุณ {$game['first_name']} ทำภารกิจการเงินเกม Money Life สำเร็จ ได้คะแนนรวมทั้งสิ้น {$total_score} จากคะแนนเต็ม 50 คะแนน คิดเป็นร้อยละ " . number_format($percentage, 0) . " เปอร์เซ็นต์ ได้รับการประเมินในระดับ {$gradeBadge} {$evalDesc}";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Money Life - สรุปผลคะแนนและจบเกม</title>
    <link rel="stylesheet" href="assets/css/game_theme.css">
    <script src="assets/js/game_audio.js"></script>
    <style>
        .final-badge-box {
            background: <?= $gradeBg ?>;
            border: 2px dashed <?= $gradeColor ?>;
            border-radius: 20px;
            padding: 24px;
            margin: 24px 0;
            text-align: center;
        }
        .final-badge-title {
            font-size: 24px;
            font-weight: 800;
            color: <?= $gradeColor ?>;
            margin-bottom: 8px;
        }
        .final-badge-desc {
            font-size: 16px;
            color: #334155;
            line-height: 1.8;
            max-width: 600px;
            margin: 0 auto;
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/game_audio_widget.php'; ?>

<div class="game-container">

    <!-- แถบข้อมูลผู้เล่น -->
    <div class="game-topbar">
        <div class="player-info">
            <?php if (!empty($game['photo']) && file_exists(__DIR__ . '/' . $game['photo'])): ?>
                <img src="<?= htmlspecialchars($game['photo']) ?>" alt="Avatar" class="player-avatar">
            <?php else: ?>
                <div class="player-avatar">👤</div>
            <?php endif; ?>

            <div class="player-details">
                <h3><?= htmlspecialchars($game['first_name'] . ' ' . $game['last_name']) ?></h3>
                <p>
                    <?php if (!empty($game['student_code'])): ?>
                        รหัส <strong><?= htmlspecialchars($game['student_code']) ?></strong> | 
                    <?php endif; ?>
                    ชั้น <?= htmlspecialchars($game['class_level']) ?>/<?= htmlspecialchars($game['class_room']) ?> เลขที่ <?= htmlspecialchars($game['student_number']) ?>
                </p>
            </div>
        </div>

        <div class="score-badge">
            <div class="score-label">คะแนนรวมทั้งเกม</div>
            <div class="score-val">🎉 <?= $total_score ?> / 50</div>
        </div>
    </div>

    <!-- การ์ดผลลัพธ์สุดท้าย -->
    <div class="game-card-main">

        <!-- กล่อง Feedback ประเมินผลข้อ 4 -->
        <div class="feedback-box <?= $statusType ?>">
            <div class="feedback-icon"><?= $icon ?></div>
            <h1 class="feedback-title"><?= htmlspecialchars($title) ?></h1>
            
            <div style="margin: 12px 0;">
                <button type="button" class="speak-btn" onclick="speakText('ผลลัพธ์สถานการณ์ที่ 4 คุณ<?= addslashes($title) ?> คุณเลือก <?= addslashes($selected_text) ?> ได้คะแนน <?= $score > 0 ? "+{$score}" : $score ?> คะแนน <?= addslashes($message) ?>')">
                    <span class="icon">🔊</span> ฟังคำอธิบายผลข้อ 4
                </button>
            </div>

            <div style="background: white; border-radius: 14px; padding: 14px; margin: 16px auto; max-width: 500px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); text-align: left;">
                <div style="font-size: 13px; color: #64748b;">สถานการณ์ที่ 4 คุณเลือก:</div>
                <div style="font-size: 17px; font-weight: 700; color: #0f172a; margin-top: 4px;">
                    👉 <?= htmlspecialchars($selected_text) ?>
                </div>
            </div>

            <div class="feedback-desc">
                <?= htmlspecialchars($message) ?>
            </div>

            <div style="background: white; border-radius: 16px; padding: 12px 20px; margin: 15px auto 0; max-width: 500px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 20px;">🏥</span>
                    <span style="font-size: 14px; color: #475569;">ค่ารักษาพยาบาล: <strong>500 บาท</strong></span>
                </div>
                <span class="comp-badge" style="background: <?= $score === 12 ? '#dcfce7' : ($score === 5 ? '#fef9c3' : '#fee2e2') ?>; color: <?= $score === 12 ? '#15803d' : ($score === 5 ? '#a16207' : '#b91c1c') ?>; font-size: 13px; padding: 4px 12px;">
                    <?= $score === 12 ? '🛡️ คุ้มครองด้วยเงินฉุกเฉิน' : ($score === 5 ? '⚠️ เบียดเบียนเงินกินอยู่' : '💥 ก่อหนี้สินยืมเพื่อน') ?>
                </span>
            </div>
        </div>

        <!-- แสดงคะแนนที่ได้ข้อนี้ และคะแนนรวม -->
        <div class="score-showcase">
            <div class="score-box-item" style="border-color: <?= $score > 0 ? '#86efac' : '#fca5a5' ?>; background: <?= $score > 0 ? '#f0fdf4' : '#fef2f2' ?>;">
                <div class="title">คะแนนจากสถานการณ์ที่ 4</div>
                <div class="num" style="color: <?= $score > 0 ? '#15803d' : '#b91c1c' ?>;">
                    <?= $score > 0 ? "+{$score}" : $score ?>
                </div>
            </div>

            <div class="score-box-item" style="border-color: #facc15; background: #fefce8;">
                <div class="title">🏆 คะแนนรวมทั้งเกม</div>
                <div class="num" style="color: #b45309;">
                    <?= $total_score ?> <span style="font-size: 20px; font-weight: 600; color: #64748b;">/ 50</span>
                </div>
                <div style="font-size: 15px; font-weight: 700; color: #b45309; margin-top: 4px;">
                    คิดเป็น <?= number_format($percentage, 0) ?>%
                </div>
            </div>
        </div>

        <!-- กล่องเหรียญรางวัลและระดับทักษะการเงิน -->
        <div class="final-badge-box">
            <div class="final-badge-title"><?= $gradeBadge ?></div>
            <div class="final-badge-desc"><?= $evalDesc ?></div>
            
            <div style="margin-top: 18px;">
                <button type="button" class="speak-btn" style="background: white; border-color: #0d9488; color: #0d9488; font-size: 16px; padding: 12px 24px;" onclick="speakText('<?= addslashes($summarySpeech) ?>')">
                    <span class="icon">🔊</span> ฟังสรุปผลคะแนนทั้งหมด
                </button>
            </div>
        </div>

        <!-- กล่องเกร็ดความรู้สรุปส่งท้าย -->
        <div class="knowledge-tip">
            <div class="tip-header">
                <div class="tip-title">
                    <span>💡</span> เกร็ดความรู้สรุปส่งท้าย (Financial Wisdom)
                </div>
                <button type="button" class="speak-btn" style="background: #fff; border-color: #fdba74; color: #c2410c;" onclick="speakText('เกร็ดความรู้สรุปส่งท้าย: <?= addslashes($tipText) ?>')">
                    <span class="icon">🔊</span> ฟังเกร็ดความรู้
                </button>
            </div>
            <div class="tip-content">
                <?= $tipText ?>
            </div>
        </div>

        <!-- ปุ่มนำทาง -->
        <div class="game-actions">
            <a href="restart.php" class="btn-game btn-game-primary" style="font-size: 18px; padding: 18px;" onclick="return confirm('ยืนยันเริ่มเล่นใหม่อีกรอบ?')">
                🔄 เล่นเกมท้าทายใหม่อีกครั้ง
            </a>
            <a href="game4.php" class="btn-game btn-game-back">
                ⬅️ ย้อนกลับไปดูข้อ 4
            </a>
            <a href="index.php" class="btn-game btn-game-back">
                🏠 กลับหน้าหลัก
            </a>
        </div>

    </div>

</div>

<script>
// เล่นเสียง Fanfare ชัยชนะและยิงพลุ Confetti ฉลองจบเกม!
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        if (window.gameAudio) {
            window.gameAudio.playBfx('fanfare');
        }
        if (typeof window.triggerConfetti === 'function') {
            window.triggerConfetti();
        }
    }, 300);
});
</script>

</body>
</html>
