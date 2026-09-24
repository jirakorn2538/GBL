<?php
/**
 * Money Life - Individual Student Profile & Session History
 */

require_once __DIR__ . '/auth/check.php';

$studentId = (int)($_GET['id'] ?? 0);

if ($studentId <= 0) {
    header('Location: students.php');
    exit;
}

// ดึงข้อมูลนักเรียน
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: students.php?error=' . urlencode('ไม่พบข้อมูลนักเรียน'));
    exit;
}

$pageTitle = 'ประวัติของ ' . $student['first_name'] . ' ' . $student['last_name'];

// สรุปสถิติของนักเรียนคนนี้
$statStmt = $pdo->prepare("
    SELECT 
        COUNT(id) as total_sessions,
        COUNT(completed_at) as completed_sessions,
        MAX(total_score) as max_score,
        AVG(CASE WHEN completed_at IS NOT NULL THEN total_score ELSE NULL END) as avg_score,
        MAX(percentage) as max_percentage,
        AVG(CASE WHEN completed_at IS NOT NULL THEN percentage ELSE NULL END) as avg_percentage
    FROM game_sessions
    WHERE student_id = ?
");
$statStmt->execute([$studentId]);
$stats = $statStmt->fetch();

// ดึงประวัติทุก Session
$sessionStmt = $pdo->prepare("
    SELECT * FROM game_sessions
    WHERE student_id = ?
    ORDER BY id DESC
");
$sessionStmt->execute([$studentId]);
$sessions = $sessionStmt->fetchAll();

// Log audit ครูดูประวัตินักเรียน
log_audit($pdo, 'VIEW_STUDENT_HISTORY', 'students', $studentId);

require_once __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 20px;">
    <a href="students.php" style="color: #64748b; text-decoration: none; font-size: 14px;">← กลับไปยังรายชื่อนักเรียน</a>
</div>

<!-- ข้อมูลนักเรียนส่วนหัว -->
<div class="card" style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
    <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
        <div style="display: flex; align-items: center; gap: 20px;">
            <?php if (!empty($student['photo']) && file_exists(__DIR__ . '/../' . $student['photo'])): ?>
                <img src="../<?= e($student['photo']) ?>" alt="Student Photo" 
                     style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #10b981; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
            <?php else: ?>
                <div style="width: 80px; height: 80px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 36px; color: #94a3b8; border: 3px solid #cbd5e1;">
                    👤
                </div>
            <?php endif; ?>

            <div>
                <div style="font-size: 13px; color: #10b981; font-weight: 600; text-transform: uppercase;">
                    🎓 ข้อมูลนักเรียน #<?= $student['id'] ?>
                </div>
                <h2 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 4px 0 8px;">
                    <?= e($student['first_name'] . ' ' . $student['last_name']) ?>
                </h2>
                <div style="font-size: 15px; color: #64748b; display: flex; gap: 20px; flex-wrap: wrap;">
                    <span>รหัสประจำตัว: <strong style="color: #0f172a; font-family: monospace; font-size: 16px;"><?= e($student['student_code'] ?: '-') ?></strong></span>
                    <span>ระดับชั้น: <strong><?= e($student['class_level']) ?></strong></span>
                    <span>ห้อง: <strong><?= e($student['class_room']) ?></strong></span>
                    <span>เลขที่: <strong><?= e($student['student_number']) ?></strong></span>
                </div>
            </div>
        </div>
        <div>
            <span class="badge <?= $student['status'] === 'active' ? 'badge-success' : 'badge-warning' ?>" style="font-size: 14px; padding: 6px 14px;">
                สถานะ: <?= ucfirst($student['status']) ?>
            </span>
        </div>
    </div>
</div>

<!-- 6 กล่องสรุปผลของนักเรียน -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">🎮</div>
        <div class="stat-details">
            <h4>จำนวนครั้งที่เล่น</h4>
            <div class="value"><?= number_format($stats['total_sessions']) ?></div>
            <div class="sub">เซสชันทั้งหมด</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green">✅</div>
        <div class="stat-details">
            <h4>จำนวนครั้งที่จบ</h4>
            <div class="value"><?= number_format($stats['completed_sessions']) ?></div>
            <div class="sub">เล่นจนจบรอบ</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple">🏆</div>
        <div class="stat-details">
            <h4>คะแนนสูงสุด</h4>
            <div class="value"><?= $stats['max_score'] !== null ? $stats['max_score'] : '-' ?> <span style="font-size: 14px; font-weight: 400; color: #64748b;">/ 50</span></div>
            <div class="sub">คะแนนดีที่สุด</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon emerald">📊</div>
        <div class="stat-details">
            <h4>คะแนนเฉลี่ย</h4>
            <div class="value"><?= $stats['avg_score'] !== null ? round($stats['avg_score'], 1) : '-' ?></div>
            <div class="sub">จากรอบที่เล่นจบ</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon amber">📈</div>
        <div class="stat-details">
            <h4>เปอร์เซ็นต์สูงสุด</h4>
            <div class="value"><?= $stats['max_percentage'] !== null ? round($stats['max_percentage'], 1) . '%' : '-' ?></div>
            <div class="sub">สถิติดีที่สุด</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon rose">🎯</div>
        <div class="stat-details">
            <h4>เปอร์เซ็นต์เฉลี่ย</h4>
            <div class="value"><?= $stats['avg_percentage'] !== null ? round($stats['avg_percentage'], 1) . '%' : '-' ?></div>
            <div class="sub">ภาพรวมความเข้าใจ</div>
        </div>
    </div>
</div>

<!-- รายการประวัติการเล่นทุก Session -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>📜</span> ประวัติการเล่นเกมทุกครั้ง (<?= count($sessions) ?> Sessions)
        </div>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Session</th>
                    <th>คะแนนที่ได้</th>
                    <th>เปอร์เซ็นต์</th>
                    <th>ผลการประเมิน</th>
                    <th>เวลาเริ่ม</th>
                    <th>เวลาเล่นจบ</th>
                    <th style="text-align: right;">รายละเอียด</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($sessions) === 0): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #94a3b8; padding: 30px;">
                            นักเรียนยังไม่ได้เริ่มเล่นเกม
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sessions as $ses): ?>
                        <tr>
                            <td>
                                <strong>Session <?= $ses['id'] ?></strong>
                            </td>
                            <td>
                                <strong style="font-size: 15px;"><?= $ses['total_score'] ?></strong> / <?= $ses['max_score'] ?>
                            </td>
                            <td>
                                <span style="font-weight: 700; color: <?= $ses['percentage'] >= 70 ? '#10b981' : ($ses['percentage'] >= 50 ? '#3b82f6' : '#ef4444') ?>;">
                                    <?= $ses['percentage'] ?>%
                                </span>
                            </td>
                            <td>
                                <?php if (!$ses['completed_at']): ?>
                                    <span class="badge badge-warning">⏳ เล่นค้างอยู่</span>
                                <?php elseif ($ses['percentage'] >= 80): ?>
                                    <span class="badge badge-success">🌟 ยอดเยี่ยม</span>
                                <?php elseif ($ses['percentage'] >= 60): ?>
                                    <span class="badge badge-info">👍 ดี</span>
                                <?php elseif ($ses['percentage'] >= 50): ?>
                                    <span class="badge badge-warning">👌 ผ่านเกณฑ์</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">⚠️ ควรปรับปรุง</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 13px; color: #64748b;">
                                <?= date('d/m/Y H:i:s', strtotime($ses['started_at'])) ?>
                            </td>
                            <td style="font-size: 13px; color: #64748b;">
                                <?= $ses['completed_at'] ? date('d/m/Y H:i:s', strtotime($ses['completed_at'])) : '<span style="color:#f59e0b;">ยังไม่จบ</span>' ?>
                            </td>
                            <td style="text-align: right;">
                                <a href="session.php?id=<?= $ses['id'] ?>" class="btn btn-primary btn-sm">
                                    [ดูรายละเอียด]
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
