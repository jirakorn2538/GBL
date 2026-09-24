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
      AND scenario_number = 1
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$session_id]);
$answer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$answer) {
    header('Location: game.php');
    exit;
}

$score = (int)$answer['score'];

$labels = [
    12 => 'แบ่งเงินและวางแผนก่อนใช้',
    4  => 'ใช้ก่อน เหลือเท่าไรค่อยออม',
    -6 => 'ซื้อของที่อยากได้ก่อน'
];

$selected_text = $labels[$score] ?? trim((string)$answer['selected_option']);

if ($score === 12) {
    $icon = '🟢';
    $title = 'ยอดเยี่ยม!';
    $statusType = 'great';
    $bfxType = 'success';
    $message = 'คุณเลือกวางแผนการเงินก่อนใช้เงิน ซึ่งช่วยให้สามารถแบ่งเงินสำหรับการใช้จ่าย การออม และเหตุฉุกเฉินได้อย่างเหมาะสม';
} elseif ($score === 4) {
    $icon = '🟡';
    $title = 'พอใช้';
    $statusType = 'fair';
    $bfxType = 'coin';
    $message = 'คุณยังสามารถใช้เงินได้ แต่การออมอาจเหลือไม่มาก การวางแผนก่อนใช้จะช่วยให้ควบคุมเงินได้ดีขึ้น';
} else {
    $icon = '🔴';
    $title = 'ต้องระวัง';
    $statusType = 'caution';
    $bfxType = 'warning';
    $message = 'การซื้อสิ่งที่อยากได้ก่อนอาจทำให้เงินสำหรับการออมและเป้าหมายในอนาคตลดลง';
}

$tipText = "การวางแผนการเงินที่ดีควรเริ่มจาก 'การออมก่อนใช้' โดยแบ่งสัดส่วนเงินสำหรับรายจ่ายจำเป็น เงินออม และเงินสำรองฉุกเฉินตั้งแต่ต้นเดือน";
$scoreSpeakText = "ผลลัพธ์สถานการณ์ที่ 1 คุณ{$title} คุณเลือก {$selected_text} ได้คะแนน " . ($score > 0 ? "+{$score}" : $score) . " คะแนน คะแนนรวมปัจจุบันคือ {$game['total_score']} คะแนน";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Money Life - ผลลัพธ์สถานการณ์ที่ 1</title>
    <link rel="stylesheet" href="assets/css/game_theme.css">
    <script src="assets/js/game_audio.js"></script>
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
            <div class="score-label">คะแนนรวมปัจจุบัน</div>
            <div class="score-val">⭐ <?= (int)$game['total_score'] ?></div>
        </div>
    </div>

    <!-- การ์ดผลลัพธ์ -->
    <div class="game-card-main">

        <!-- กล่อง Feedback ประเมินผล -->
        <div class="feedback-box <?= $statusType ?>">
            <div class="feedback-icon"><?= $icon ?></div>
            <h1 class="feedback-title"><?= htmlspecialchars($title) ?></h1>
            
            <div style="margin: 12px 0;">
                <button type="button" class="speak-btn" onclick="speakText('<?= addslashes($scoreSpeakText . ' ' . $message) ?>')">
                    <span class="icon">🔊</span> ฟังคำอธิบายและผลคะแนน
                </button>
            </div>

            <div style="background: white; border-radius: 14px; padding: 14px; margin: 16px auto; max-width: 500px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); text-align: left;">
                <div style="font-size: 13px; color: #64748b;">สถานการณ์ที่ 1 คุณเลือก:</div>
                <div style="font-size: 17px; font-weight: 700; color: #0f172a; margin-top: 4px;">
                    👉 <?= htmlspecialchars($selected_text) ?>
                </div>
            </div>

            <div class="feedback-desc">
                <?= htmlspecialchars($message) ?>
            </div>
        </div>

        <!-- แสดงคะแนนที่ได้และคะแนนรวม -->
        <div class="score-showcase">
            <div class="score-box-item" style="border-color: <?= $score > 0 ? '#86efac' : '#fca5a5' ?>; background: <?= $score > 0 ? '#f0fdf4' : '#fef2f2' ?>;">
                <div class="title">คะแนนที่ได้จากข้อนี้</div>
                <div class="num" style="color: <?= $score > 0 ? '#15803d' : '#b91c1c' ?>;">
                    <?= $score > 0 ? "+{$score}" : $score ?>
                </div>
            </div>

            <div class="score-box-item" style="border-color: #93c5fd; background: #eff6ff;">
                <div class="title">⭐ คะแนนรวมปัจจุบัน</div>
                <div class="num" style="color: #1d4ed8;">
                    <?= (int)$game['total_score'] ?>
                </div>
            </div>
        </div>

        <!-- กล่องเกร็ดความรู้ (Knowledge Tip) -->
        <div class="knowledge-tip">
            <div class="tip-header">
                <div class="tip-title">
                    <span>💡</span> เกร็ดความรู้การเงิน (Financial Tips)
                </div>
                <button type="button" class="speak-btn" style="background: #fff; border-color: #fdba74; color: #c2410c;" onclick="speakText('เกร็ดความรู้: <?= addslashes($tipText) ?>')">
                    <span class="icon">🔊</span> ฟังเกร็ดความรู้
                </button>
            </div>
            <div class="tip-content">
                <?= $tipText ?>
            </div>
        </div>

        <!-- ปุ่มนำทาง -->
        <div class="game-actions">
            <a href="game2.php" class="btn-game btn-game-next" style="font-size: 18px; padding: 18px;">
                ➡️ ลุยต่อ สถานการณ์ที่ 2
            </a>
            <a href="game.php" class="btn-game btn-game-back">
                ⬅️ ย้อนกลับไปดูโจทย์ข้อ 1
            </a>
            <a href="index.php" class="btn-game btn-game-back">
                🏠 หน้าหลัก
            </a>
            <a href="restart.php" class="btn-game btn-game-restart" onclick="return confirm('ยืนยันเริ่มเล่นเกมใหม่ตั้งแต่ข้อ 1?')">
                🔄 เริ่มเล่นใหม่อีกครั้ง
            </a>
        </div>

    </div>

</div>

<script>
// เล่นเสียง Effect ตอนโหลดหน้าผลลัพธ์
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        if (window.gameAudio) {
            window.gameAudio.playBfx('<?= $bfxType ?>');
        }
    }, 200);
});
</script>

</body>
</html>
