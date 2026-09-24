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

$options = [
    'A' => ['text' => 'ยังใช้เครื่องเดิมและประหยัดเงิน', 'sub' => 'พยายามใช้โทรศัพท์เครื่องเดิมต่อไปก่อน และยังไม่เสียเงิน', 'score' => 13, 'badge' => '🟢 ตัวเลือก A'],
    'B' => ['text' => 'เปลี่ยนแบตเตอรี่ 800 บาท', 'sub' => 'จ่ายเฉพาะค่าซ่อมที่จำเป็น เพื่อให้โทรศัพท์เครื่องเดิมใช้งานต่อได้', 'score' => 9, 'badge' => '🟡 ตัวเลือก B'],
    'C' => ['text' => 'ซื้อโทรศัพท์ใหม่ 4,500 บาท', 'sub' => 'ซื้อเครื่องใหม่ทันทีเพื่อความสะดวก', 'score' => -5, 'badge' => '🔴 ตัวเลือก C']
];

$stmt = $pdo->prepare("
    SELECT selected_option, score
    FROM game_answers
    WHERE session_id = ?
      AND scenario_number = 3
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$session_id]);
$answered = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$answered) {
    $choice = $_POST['choice'] ?? '';

    if (!isset($options[$choice])) {
        die('ข้อมูลคำตอบไม่ถูกต้อง');
    }

    $selected_option = $options[$choice]['text'];
    $score = $options[$choice]['score'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO game_answers
            (session_id, scenario_number, selected_option, score)
            VALUES (?, 3, ?, ?)
        ");
        $stmt->execute([$session_id, $selected_option, $score]);

        $stmt = $pdo->prepare("
            UPDATE game_sessions
            SET total_score = total_score + ?
            WHERE id = ?
        ");
        $stmt->execute([$score, $session_id]);

        $pdo->commit();

        header('Location: result3.php');
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die('เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage()));
    }
}

$stmt = $pdo->prepare("SELECT total_score FROM game_sessions WHERE id = ? LIMIT 1");
$stmt->execute([$session_id]);
$current_score = (int)$stmt->fetchColumn();

$answered_score = $answered ? (int)$answered['score'] : 0;
$answered_text = $answered ? trim((string)$answered['selected_option']) : '';
$answered_icon = '🟢';

if ($answered) {
    if ($answered_score === 13) {
        $answered_icon = '🟢';
        $answered_text = $options['A']['text'];
    } elseif ($answered_score === 9) {
        $answered_icon = '🟡';
        $answered_text = $options['B']['text'];
    } elseif ($answered_score === -5) {
        $answered_icon = '🔴';
        $answered_text = $options['C']['text'];
    }
}

$scenarioQuestionSpeech = "สถานการณ์ที่ 3 โทรศัพท์มีปัญหา ผ่านไปอีก 1 สัปดาห์ โทรศัพท์ของคุณเริ่มมีปัญหา แบตเตอรี่หมดเร็วและใช้งานได้ไม่สะดวก คุณต้องตัดสินใจว่าจะจัดการอย่างไร เงินสำหรับใช้จ่ายที่เหลือ 3,500 บาท คุณจะเลือกวิธีใด";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Money Life - สถานการณ์ที่ 3</title>
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
            <div class="score-label">คะแนนสะสม</div>
            <div class="score-val">⭐ <?= $current_score ?></div>
        </div>
    </div>

    <!-- Stepper ความคืบหน้า 4 สถานการณ์ -->
    <div class="game-stepper">
        <div class="step completed">
            <div class="step-num">✓</div>
            <div class="step-label">1. เงินก้อนแรก</div>
        </div>
        <div class="step-line active"></div>
        <div class="step completed">
            <div class="step-num">✓</div>
            <div class="step-label">2. ช้อปปิ้ง</div>
        </div>
        <div class="step-line active"></div>
        <div class="step active">
            <div class="step-num">3</div>
            <div class="step-label">3. โทรศัพท์พัง</div>
        </div>
        <div class="step-line"></div>
        <div class="step">
            <div class="step-num">4</div>
            <div class="step-label">4. เหตุฉุกเฉิน</div>
        </div>
    </div>

    <!-- การ์ดเนื้อหาเกมหลัก -->
    <div class="game-card-main">
        <div class="scenario-badge">📱 ภารกิจที่ 3 จาก 4</div>
        <h1 class="scenario-title">โทรศัพท์มีปัญหา</h1>
        <p class="scenario-subtitle">แบตเตอรี่เสื่อมสภาพ คุณจะซ่อม ประหยัด หรือซื้อเครื่องใหม่?</p>

        <!-- กล่องเรื่องราว/โจทย์ -->
        <div class="story-box">
            <div class="story-header">
                <span class="story-tag">📖 เรื่องราว</span>
                <button type="button" class="speak-btn" onclick="speakText('<?= addslashes($scenarioQuestionSpeech) ?>')">
                    <span class="icon">🔊</span> ฟังโจทย์
                </button>
            </div>
            <div class="story-content">
                ผ่านไปอีก 1 สัปดาห์ โทรศัพท์ของคุณเริ่มมีปัญหา แบตเตอรี่เสื่อมเร็วและใช้งานได้ไม่สะดวก<br>
                คุณต้องตัดสินใจว่าจะจัดการอย่างไรกับการใช้โทรศัพท์ในชีวิตประจำวัน โดยคำนึงถึงเงินในกระเป๋า
            </div>
        </div>

        <!-- หน้าจอมือถือจำลอง (Phone Simulator) -->
        <div class="phone-sim-wrapper">
            <div class="phone-sim-topbar">
                <div>📶 4G TrueLife</div>
                <div class="battery-badge-low">🪫 แบตเตอรี่ 8% (เสื่อมสภาพ)</div>
            </div>
            <div class="phone-screen-content">
                <div class="phone-icon-glitch">📱</div>
                <div class="phone-screen-text">
                    <h4>⚠️ แจ้งเตือน: แบตเตอรี่เสื่อม ชาร์จไฟไม่เข้า</h4>
                    <p>เครื่องดับบ่อยครั้งระหว่างวัน คุณมีเงินใช้จ่ายเหลืออยู่ในกระเป๋า <strong>3,500 บาท</strong></p>
                </div>
            </div>
        </div>

        <!-- กล่องกระเป๋าเงิน -->
        <div class="money-stat-card">
            <div class="money-label">💰 เงินสำหรับใช้จ่ายที่เหลืออยู่ในขณะนี้</div>
            <div class="money-amount">3,500 <span style="font-size: 20px; font-weight: 500;">บาท</span></div>
        </div>

        <!-- ตารางวิเคราะห์ผลกระทบทางการเงิน (Financial Decision Matrix) -->
        <div style="font-size: 16px; font-weight: 700; color: #1e293b; margin: 15px 0 10px;">
            📊 เปรียบเทียบผลกระทบของ 3 ทางเลือก:
        </div>
        <div class="decision-comparison-grid">
            <div class="comp-card opt-a">
                <span class="comp-badge">🟢 ตัวเลือก A (เครื่องเดิม)</span>
                <div class="comp-cost">฿0</div>
                <div class="comp-balance">เงินคงเหลือ: <strong>3,500 บาท</strong></div>
                <div class="comp-verdict" style="color: #15803d;">
                    ✅ ไม่เสียเงินเพิ่ม รักษาเงินสำรองไว้ครบ 100%
                </div>
            </div>
            <div class="comp-card opt-b">
                <span class="comp-badge">🟡 ตัวเลือก B (เปลี่ยนแบต)</span>
                <div class="comp-cost">฿800</div>
                <div class="comp-balance">เงินคงเหลือ: <strong>2,700 บาท</strong></div>
                <div class="comp-verdict" style="color: #a16207;">
                    ⚡ ซ่อมเฉพาะส่วนจำเป็น ใช้งานได้ยาว ไม่ต้องซื้อใหม่
                </div>
            </div>
            <div class="comp-card opt-c">
                <span class="comp-badge">🔴 ตัวเลือก C (ซื้อเครื่องใหม่)</span>
                <div class="comp-cost">฿4,500</div>
                <div class="comp-balance">เงินคงเหลือ: <strong style="color: #dc2626;">ติดลบ -1,000 บ.</strong></div>
                <div class="comp-verdict" style="color: #b91c1c;">
                    🚨 เงินไม่พอ! เกินตัว เสี่ยงเกิดหนี้สินก้อนโต
                </div>
            </div>
        </div>

        <?php if (!$answered): ?>
            <!-- ฟอร์มเลือกคำตอบ -->
            <form method="POST" id="scenarioForm">
                <div class="choices-container">
                    <?php foreach ($options as $key => $opt): ?>
                        <label class="choice-item" id="label_<?= $key ?>" onclick="selectChoice('<?= $key ?>', '<?= addslashes($opt['badge'] . ' ' . $opt['text'] . '. ' . $opt['sub']) ?>')">
                            <input type="radio" name="choice" id="choice_<?= $key ?>" value="<?= $key ?>" required>
                            <div class="choice-header">
                                <span class="choice-badge"><?= $opt['badge'] ?></span>
                                <span class="choice-read-btn" title="ฟังตัวเลือกนี้" onclick="event.stopPropagation(); speakText('<?= addslashes($opt['badge'] . ' ' . $opt['text'] . '. ' . $opt['sub']) ?>')">
                                    🔊 ฟังเสียง
                                </span>
                            </div>
                            <div class="choice-text">
                                <?= htmlspecialchars($opt['text']) ?>
                            </div>
                            <div class="choice-desc">
                                <?= htmlspecialchars($opt['sub']) ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn-game btn-game-primary" id="submitBtn">
                    ✨ ยืนยันการตัดสินใจ
                </button>
            </form>
        <?php else: ?>
            <!-- กล่องแสดงสถานะเมื่อเคยตอบแล้ว -->
            <div class="feedback-box fair" style="margin-top: 24px;">
                <div class="feedback-icon"><?= $answered_icon ?></div>
                <h2 style="font-size: 22px; font-weight: 800; color: #0f172a; margin-bottom: 8px;">คุณได้ตอบข้อนี้เรียบร้อยแล้ว</h2>
                <div style="font-size: 14px; color: #64748b;">การตัดสินใจที่คุณเลือก:</div>
                <div style="font-size: 18px; font-weight: 800; color: #047857; margin: 8px 0;">
                    👉 <?= htmlspecialchars($answered_text) ?>
                </div>
                <div style="font-size: 20px; font-weight: 800; color: #0284c7; margin: 12px 0;">
                    คะแนนที่ได้รับ: <?= $answered_score > 0 ? '+' : '' ?><?= $answered_score ?> คะแนน
                </div>
                <div style="margin-top: 10px;">
                    <button type="button" class="speak-btn" onclick="speakText('คุณตอบข้อ 3 แล้ว เลือก <?= addslashes($answered_text) ?> ได้คะแนน <?= $answered_score ?> คะแนน')">
                        <span class="icon">🔊</span> ฟังคำตอบและคะแนน
                    </button>
                </div>
            </div>

            <div class="game-actions">
                <a href="result3.php" class="btn-game btn-game-next">
                    📊 ดูผลการวิเคราะห์ข้อ 3
                </a>
                <a href="game2.php" class="btn-game btn-game-back">
                    ⬅️ ย้อนกลับไปดูข้อ 2
                </a>
                <a href="game4.php" class="btn-game btn-game-next">
                    ➡️ ไปสถานการณ์ที่ 4
                </a>
            </div>
        <?php endif; ?>

    </div>

</div>

<script>
// ฟังก์ชันช่วยเหลือเมื่อเลือกตัวเลือก
function selectChoice(key, textToSpeak) {
    const radio = document.getElementById('choice_' + key);
    if (radio) {
        radio.checked = true;
    }

    // ลบ selected จากทุกอัน
    document.querySelectorAll('.choice-item').forEach(el => el.classList.remove('selected'));
    
    // เพิ่ม selected ในอันที่คลิก
    const current = document.getElementById('label_' + key);
    if (current) {
        current.classList.add('selected');
    }

    // เล่นเสียง Effect และอ่านออกเสียงคำตอบที่เลือก
    if (window.gameAudio) {
        window.gameAudio.playBfx('coin');
        window.gameAudio.speakThai(textToSpeak);
    }
}

// ตรวจสอบก่อนส่งฟอร์ม
document.getElementById('scenarioForm')?.addEventListener('submit', function(e) {
    const checked = document.querySelector('input[name="choice"]:checked');
    if (!checked) {
        e.preventDefault();
        alert('กรุณาเลือกคำตอบก่อนยืนยันครับ/ค่ะ');
        return;
    }
    if (window.gameAudio) {
        window.gameAudio.playBfx('success');
    }
});
</script>

</body>
</html>
