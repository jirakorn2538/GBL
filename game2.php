<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['game_session_id'])) {
    header('Location: register.php');
    exit;
}

$session_id = (int)$_SESSION['game_session_id'];

$stmt = $pdo->prepare("
    SELECT gs.total_score, gs.max_score,
           s.student_code, s.photo,
           s.first_name, s.last_name,
           s.class_level, s.class_room, s.student_number
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
      AND scenario_number = 2
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$session_id]);
$answered = $stmt->fetch(PDO::FETCH_ASSOC);

$scores = [
    'green'  => 13,
    'yellow' => 10,
    'red'    => 3
];

$labels = [
    'green'  => 'ยังไม่ซื้อ รองเท้าคู่เดิมยังใช้งานได้ จึงเก็บเงินไว้ก่อน',
    'yellow' => 'เปรียบเทียบราคาก่อน ตรวจสอบราคาและโปรโมชั่นจากหลายร้านค้าก่อนตัดสินใจ',
    'red'    => 'ซื้อทันที ซื้อรองเท้าราคา 1,200 บาท เพราะเป็นสิ่งที่อยากได้'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$answered) {
    $answer = $_POST['answer'] ?? '';

    if (!isset($scores[$answer])) {
        die('ข้อมูลคำตอบไม่ถูกต้อง');
    }

    $score = $scores[$answer];
    $selected_option = $labels[$answer];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO game_answers
            (session_id, scenario_number, selected_option, score)
            VALUES (?, 2, ?, ?)
        ");
        $stmt->execute([$session_id, $selected_option, $score]);

        $stmt = $pdo->prepare("
            UPDATE game_sessions
            SET total_score = total_score + ?
            WHERE id = ?
        ");
        $stmt->execute([$score, $session_id]);

        $pdo->commit();

        header('Location: result2.php');
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die('เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage()));
    }
}

$stmt = $pdo->prepare("SELECT total_score FROM game_sessions WHERE id = ? LIMIT 1");
$stmt->execute([$session_id]);
$current_score = (int)$stmt->fetchColumn();

$answered_text = '';
$answered_score = 0;
$answered_icon = '🟢';

if ($answered) {
    $answered_score = (int)$answered['score'];
    if ($answered_score === 13) {
        $answered_icon = '🟢';
        $answered_text = $labels['green'];
    } elseif ($answered_score === 10) {
        $answered_icon = '🟡';
        $answered_text = $labels['yellow'];
    } elseif ($answered_score === 3) {
        $answered_icon = '🔴';
        $answered_text = $labels['red'];
    } else {
        $answered_icon = '🟢';
        $answered_text = trim((string)$answered['selected_option']) ?: 'ไม่พบข้อความคำตอบ';
    }
}

$scenarioPromptText = "สถานการณ์ที่ 2 จำเป็นหรืออยากได้? ผ่านไป 1 สัปดาห์ คุณกำลังจะซื้อรองเท้าคู่ใหม่ ราคา 1,200 บาท แต่รองเท้าคู่เดิมยังใช้งานได้ คุณจะตัดสินใจอย่างไร?";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Money Life - สถานการณ์ที่ 2 (จำเป็นหรืออยากได้?)</title>
    <link rel="stylesheet" href="assets/css/game_theme.css">
    <script src="assets/js/game_audio.js"></script>
</head>
<body>

<?php require_once __DIR__ . '/includes/game_audio_widget.php'; ?>

<div class="game-container">

    <!-- แถบข้อมูลผู้เล่นด้านบน -->
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
            <div class="score-label">คะแนนปัจจุบัน</div>
            <div class="score-val">⭐ <?= $current_score ?></div>
        </div>
    </div>

    <!-- Stepper แสดงความคืบหน้า -->
    <div class="scenario-stepper">
        <div class="stepper-track">
            <div class="stepper-progress" style="width: 50%;"></div>
        </div>
        <div class="step-item completed">
            <div class="step-circle">✓</div>
            <div class="step-label">เดือนใหม่</div>
        </div>
        <div class="step-item active">
            <div class="step-circle">2</div>
            <div class="step-label">ซื้อของ</div>
        </div>
        <div class="step-item">
            <div class="step-circle">3</div>
            <div class="step-label">ฉุกเฉิน</div>
        </div>
        <div class="step-item">
            <div class="step-circle">4</div>
            <div class="step-label">เป้าหมาย</div>
        </div>
    </div>

    <!-- การ์ดเกมหลัก -->
    <div class="game-card-main">

        <div class="scenario-header">
            <span class="scenario-tag">🛒 ด่านที่ 2 จาก 4</span>
            <h1 class="scenario-title">🛍️ สถานการณ์ที่ 2: จำเป็นหรืออยากได้?</h1>
            <p class="scenario-subtitle">แยกแยะระหว่าง 'สิ่งที่จำเป็น' (Need) กับ 'สิ่งที่อยากได้' (Want)</p>
        </div>

        <!-- กล่องเนื้อเรื่องพร้อมปุ่มฟังโจทย์ -->
        <div class="story-box">
            <div class="story-box-header">
                <span class="tag">📖 เรื่องราวของคุณ</span>
                <button type="button" class="speak-btn" onclick="speakText('<?= addslashes($scenarioPromptText) ?>')">
                    <span class="icon">🔊</span> ฟังโจทย์
                </button>
            </div>
            <p style="margin: 0;">
                ผ่านไป 1 สัปดาห์ คุณกำลังจะซื้อรองเท้าคู่ใหม่ ราคา <strong>1,200 บาท</strong> 
                แต่รองเท้าคู่เดิมของคุณยังอยู่ในสภาพดีและใช้งานได้ เพียงแต่คู่ใหม่มีดีไซน์ที่คุณชอบมากกว่า<br><br>
                คุณจะตัดสินใจอย่างไรกับเงินของคุณ?
            </p>
        </div>

        <!-- กล่องราคาสินค้า -->
        <div class="money-showcase" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-color: #93c5fd;">
            <div class="money-title" style="color: #1e40af;">🏷️ ราคาสินค้าที่อยากได้</div>
            <div class="money-amount" style="color: #1d4ed8;">1,200 บาท</div>
        </div>

        <?php if (!$answered): ?>

            <div class="question-banner">
                <div class="question-text">
                    ❓ คุณจะเลือกวิธีจัดการอย่างไร?
                </div>
            </div>

            <!-- รายการตัวเลือกคำตอบ -->
            <div class="choices-list">

                <!-- ตัวเลือก เขียว -->
                <form method="POST" id="formGreen">
                    <input type="hidden" name="answer" value="green">
                    <button type="button" class="choice-btn" onclick="handleChoiceSelect('formGreen', 'ยังไม่ซื้อ รองเท้าคู่เดิมยังใช้งานได้ จึงเก็บเงินไว้ก่อน')">
                        <div class="choice-circle" style="background: #dcfce7; color: #16a34a;">🟢</div>
                        <div class="choice-text-wrap">
                            <strong>ยังไม่ซื้อ รองเท้าคู่เดิมยังใช้งานได้ จึงเก็บเงินไว้ก่อน</strong>
                            <div style="font-size: 13.5px; color: #64748b; font-weight: normal;">ชะลอความอยากได้ และเก็บรักษาเงินไว้สำหรับสิ่งจำเป็นกว่า</div>
                        </div>
                        <div class="listen-choice" onclick="event.stopPropagation(); speakText('ตัวเลือกสีเขียว: ยังไม่ซื้อ รองเท้าคู่เดิมยังใช้งานได้ จึงเก็บเงินไว้ก่อน');" title="ฟังตัวเลือกนี้">
                            🔊 ฟัง
                        </div>
                    </button>
                </form>

                <!-- ตัวเลือก เหลือง -->
                <form method="POST" id="formYellow">
                    <input type="hidden" name="answer" value="yellow">
                    <button type="button" class="choice-btn" onclick="handleChoiceSelect('formYellow', 'เปรียบเทียบราคาก่อน ตรวจสอบราคาและโปรโมชั่นก่อนตัดสินใจ')">
                        <div class="choice-circle" style="background: #fef3c7; color: #d97706;">🟡</div>
                        <div class="choice-text-wrap">
                            <strong>เปรียบเทียบราคาก่อน ตรวจสอบโปรโมชั่นจากหลายร้านค้า</strong>
                            <div style="font-size: 13.5px; color: #64748b; font-weight: normal;">ยังไม่รีบซื้อทันที แต่หาราคาที่คุ้มค่าที่สุดก่อนตัดสินใจ</div>
                        </div>
                        <div class="listen-choice" onclick="event.stopPropagation(); speakText('ตัวเลือกสีเหลือง: เปรียบเทียบราคาก่อน ตรวจสอบราคาและโปรโมชั่นจากหลายร้านค้าก่อนตัดสินใจ');" title="ฟังตัวเลือกนี้">
                            🔊 ฟัง
                        </div>
                    </button>
                </form>

                <!-- ตัวเลือก แดง -->
                <form method="POST" id="formRed">
                    <input type="hidden" name="answer" value="red">
                    <button type="button" class="choice-btn" onclick="handleChoiceSelect('formRed', 'ซื้อทันที ซื้อรองเท้าราคา 1,200 บาท เพราะเป็นสิ่งที่อยากได้')">
                        <div class="choice-circle" style="background: #fee2e2; color: #dc2626;">🔴</div>
                        <div class="choice-text-wrap">
                            <strong>ซื้อทันที ซื้อรองเท้าราคา 1,200 บาท เพราะเป็นสิ่งที่อยากได้</strong>
                            <div style="font-size: 13.5px; color: #64748b; font-weight: normal;">ตัดสินใจซื้อเลยตามความชอบ โดยยังไม่ได้ประเมินผลกระทบกับเงินที่เหลือ</div>
                        </div>
                        <div class="listen-choice" onclick="event.stopPropagation(); speakText('ตัวเลือกสีแดง: ซื้อทันที ซื้อรองเท้าราคา 1,200 บาท เพราะเป็นสิ่งที่อยากได้');" title="ฟังตัวเลือกนี้">
                            🔊 ฟัง
                        </div>
                    </button>
                </form>

            </div>

        <?php else: ?>

            <!-- แสดงเมื่อเคยตอบแล้ว -->
            <div class="feedback-box <?= $answered_score > 5 ? 'great' : ($answered_score > 0 ? 'fair' : 'caution') ?>">
                <div class="feedback-icon"><?= $answered_icon ?></div>
                <h3 class="feedback-title">คุณตอบสถานการณ์ที่ 2 แล้ว</h3>
                
                <div style="margin: 14px 0;">
                    <button type="button" class="speak-btn" onclick="speakText('คุณตอบสถานการณ์ที่ 2 แล้ว คำตอบของคุณคือ <?= addslashes($answered_text) ?> ได้คะแนน <?= $answered_score ?> คะแนน')">
                        <span class="icon">🔊</span> ฟังคำตอบที่เลือก
                    </button>
                </div>

                <div style="background: white; border-radius: 14px; padding: 14px; margin: 16px auto; max-width: 500px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); text-align: left;">
                    <div style="font-size: 13px; color: #64748b;">คำตอบที่คุณเลือก:</div>
                    <div style="font-size: 17px; font-weight: 700; color: #0f172a; margin-top: 4px;">
                        👉 <?= htmlspecialchars($answered_text) ?>
                    </div>
                </div>

                <div style="font-size: 22px; font-weight: 800; color: <?= $answered_score > 5 ? '#15803d' : '#d97706' ?>;">
                    คะแนนที่ได้รับ: +<?= $answered_score ?> คะแนน
                </div>
            </div>

            <!-- ปุ่มนำทาง -->
            <div class="game-actions">
                <a href="result2.php" class="btn-game btn-game-next">
                    📊 ดูผลลัพธ์และเกร็ดความรู้ข้อ 2
                </a>
                <a href="game3.php" class="btn-game btn-game-next" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    ➡️ ไปสถานการณ์ที่ 3
                </a>
                <a href="game.php" class="btn-game btn-game-back">
                    ⬅️ ย้อนกลับไปข้อ 1
                </a>
                <a href="index.php" class="btn-game btn-game-back">
                    🏠 หน้าหลัก
                </a>
            </div>

        <?php endif; ?>

    </div>

</div>

<script>
function handleChoiceSelect(formId, choiceText) {
    if (window.gameAudio) {
        window.gameAudio.getAudioContext();
        window.gameAudio.playBfx('coin');
        window.gameAudio.speakThai('คุณเลือก ' + choiceText, function() {
            document.getElementById(formId).submit();
        });
        setTimeout(function() {
            document.getElementById(formId).submit();
        }, 650);
    } else {
        document.getElementById(formId).submit();
    }
}
</script>

</body>
</html>
