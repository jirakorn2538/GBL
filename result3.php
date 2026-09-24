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
      AND scenario_number = 3
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$session_id]);
$answer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$answer) {
    header('Location: game3.php');
    exit;
}

$score = (int)$answer['score'];

$labels = [
    13 => 'ยังใช้เครื่องเดิมและประหยัดเงิน',
    9  => 'เปลี่ยนแบตเตอรี่ 800 บาท',
    -5 => 'ซื้อโทรศัพท์ใหม่ 4,500 บาท'
];

$selected_text = $labels[$score] ?? trim((string)$answer['selected_option']);

if ($score === 13) {
    $icon = '🟢';
    $title = 'ยอดเยี่ยม!';
    $statusType = 'great';
    $bfxType = 'success';
    $message = 'คุณเลือกใช้โทรศัพท์เครื่องเดิมต่อไปก่อน จึงไม่ต้องเสียค่าใช้จ่ายเพิ่มเติม และยังสามารถรักษาเงินออมและเงินสำรองไว้ได้';
} elseif ($score === 9) {
    $icon = '🟡';
    $title = 'พอใช้';
    $statusType = 'fair';
    $bfxType = 'coin';
    $message = 'คุณเลือกซ่อมเฉพาะส่วนที่จำเป็น การเปลี่ยนแบตเตอรี่ช่วยให้สามารถใช้งานโทรศัพท์เครื่องเดิมต่อได้ โดยไม่ต้องเสียเงินก้อนใหญ่ซื้อเครื่องใหม่';
} else {
    $icon = '🔴';
    $title = 'ต้องระวัง';
    $statusType = 'caution';
    $bfxType = 'warning';
    $message = 'คุณเลือกซื้อโทรศัพท์ใหม่ราคา 4,500 บาท แม้จะได้รับความสะดวกจากเครื่องใหม่ แต่เป็นการใช้เงินก้อนใหญ่และทำให้เงินสำรองลดลง';
}

$tipText = "ก่อนซื้อของชิ้นใหญ่ ควรตรวจสอบว่าเงินที่เหลือเพียงพอสำหรับค่าใช้จ่ายจำเป็นและเหตุฉุกเฉินหรือไม่ หากซ่อมแซมได้ควรพิจารณาซ่อมก่อน";
$scoreSpeakText = "ผลลัพธ์สถานการณ์ที่ 3 คุณ{$title} คุณเลือก {$selected_text} ได้คะแนน " . ($score > 0 ? "+{$score}" : $score) . " คะแนน คะแนนรวมปัจจุบันคือ {$game['total_score']} คะแนน";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Money Life - ผลลัพธ์สถานการณ์ที่ 3</title>
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
                <div style="font-size: 13px; color: #64748b;">สถานการณ์ที่ 3 คุณเลือก:</div>
                <div style="font-size: 17px; font-weight: 700; color: #0f172a; margin-top: 4px;">
                    👉 <?= htmlspecialchars($selected_text) ?>
                </div>
            </div>

            <div class="feedback-desc">
                <?= htmlspecialchars($message) ?>
            </div>

            <div style="background: white; border-radius: 16px; padding: 12px 20px; margin: 15px auto 0; max-width: 500px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 20px;">📱</span>
                    <span style="font-size: 14px; color: #475569;">การจัดการโทรศัพท์: <strong><?= $score === 13 ? 'ใช้ต่อ (฿0)' : ($score === 9 ? 'ซ่อมแบต (฿800)' : 'ซื้อใหม่ (฿4,500)') ?></strong></span>
                </div>
                <span class="comp-badge" style="background: <?= $score === 13 ? '#dcfce7' : ($score === 9 ? '#fef9c3' : '#fee2e2') ?>; color: <?= $score === 13 ? '#15803d' : ($score === 9 ? '#a16207' : '#b91c1c') ?>; font-size: 13px; padding: 4px 12px;">
                    <?= $score === 13 ? '✅ ประหยัด 100%' : ($score === 9 ? '⚡ คุ้มค่าซ่อมจำเป็น' : '🚨 เกินตัวเสี่ยงหนี้') ?>
                </span>
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
            <a href="game4.php" class="btn-game btn-game-next" style="font-size: 18px; padding: 18px;">
                ➡️ ลุยต่อ สถานการณ์สุดท้าย (ข้อ 4)
            </a>
            <a href="game3.php" class="btn-game btn-game-back">
                ⬅️ ย้อนกลับไปดูโจทย์ข้อ 3
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
