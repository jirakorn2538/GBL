<?php
/**
 * Money Life - Teacher Dashboard
 * Real-time KPI statistics and overview
 */

require_once __DIR__ . '/auth/check.php';

$pageTitle = 'Dashboard - ภาพรวมระบบ';

// ดึงข้อมูลสถิติจากฐานข้อมูลจริง
try {
    // 1. นักเรียนทั้งหมด
    $totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

    // 2. จำนวนครั้งที่เล่นทั้งหมด
    $totalSessions = (int)$pdo->query("SELECT COUNT(*) FROM game_sessions")->fetchColumn();

    // 3. เล่นจบแล้ว
    $completedSessions = (int)$pdo->query("SELECT COUNT(*) FROM game_sessions WHERE completed_at IS NOT NULL")->fetchColumn();

    // 4. ยังเล่นไม่จบ
    $inProgressSessions = (int)$pdo->query("SELECT COUNT(*) FROM game_sessions WHERE completed_at IS NULL")->fetchColumn();

    // 5. คะแนนเฉลี่ย (คำนวณจาก session ที่เล่นจบแล้ว)
    $avgScoreStmt = $pdo->query("SELECT AVG(total_score) FROM game_sessions WHERE completed_at IS NOT NULL");
    $avgScore = round((float)$avgScoreStmt->fetchColumn(), 1);

    // 6. เปอร์เซ็นต์เฉลี่ย
    $avgPercentStmt = $pdo->query("SELECT AVG(percentage) FROM game_sessions WHERE completed_at IS NOT NULL");
    $avgPercent = round((float)$avgPercentStmt->fetchColumn(), 1);

    // รายการเล่นล่าสุด 8 รายการ
    $recentStmt = $pdo->query("
        SELECT gs.id, gs.student_id, gs.total_score, gs.max_score, gs.percentage, 
               gs.started_at, gs.completed_at,
               s.first_name, s.last_name, s.class_level, s.class_room, s.student_number
        FROM game_sessions gs
        JOIN students s ON gs.student_id = s.id
        ORDER BY gs.id DESC
        LIMIT 8
    ");
    $recentSessions = $recentStmt->fetchAll();

    // ข้อมูลคะแนนแยกตามชั้นเรียน
    $classStatsStmt = $pdo->query("
        SELECT s.class_level, 
               COUNT(DISTINCT s.id) as student_count,
               COUNT(gs.id) as session_count,
               AVG(CASE WHEN gs.completed_at IS NOT NULL THEN gs.percentage ELSE NULL END) as avg_percent
        FROM students s
        LEFT JOIN game_sessions gs ON s.id = gs.student_id
        GROUP BY s.class_level
        ORDER BY s.class_level ASC
    ");
    $classStats = $classStatsStmt->fetchAll();

} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Welcome Banner -->
<div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; padding: 26px 30px; border-radius: 16px; margin-bottom: 28px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
    <div>
        <h2 style="font-size: 22px; font-weight: 700; margin-bottom: 6px;">🛡️ ยินดีต้อนรับสู่แผงควบคุมผู้ดูแลระบบ (Admin Dashboard)</h2>
        <p style="color: #94a3b8; font-size: 14px;">ติดตามข้อมูลนักเรียน การใช้งานเกมจำลอง และคะแนนการวางแผนการเงินแบบเรียลไทม์</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="students.php" class="btn btn-primary">
            👥 ข้อมูลนักเรียน
        </a>
        <a href="results.php" class="btn btn-accent">
            🎮 คะแนนการเล่น
        </a>
        <a href="reports.php" class="btn btn-outline" style="border-color: rgba(255,255,255,0.3); color: white;">
            📄 ออกรายงาน
        </a>
    </div>
</div>

<!-- 6 KPI Stat Cards -->
<div class="stats-grid">
    <!-- 1. นักเรียนทั้งหมด -->
    <div class="stat-card">
        <div class="stat-icon green">👥</div>
        <div class="stat-details">
            <h4>นักเรียนทั้งหมด</h4>
            <div class="value"><?= number_format($totalStudents) ?> <span style="font-size: 16px; font-weight: 400; color: #64748b;">คน</span></div>
            <div class="sub">ลงทะเบียนในระบบ</div>
        </div>
    </div>

    <!-- 2. จำนวนครั้งที่เล่น -->
    <div class="stat-card">
        <div class="stat-icon blue">🎮</div>
        <div class="stat-details">
            <h4>จำนวนครั้งที่เล่น</h4>
            <div class="value"><?= number_format($totalSessions) ?> <span style="font-size: 16px; font-weight: 400; color: #64748b;">ครั้ง</span></div>
            <div class="sub">เซสชันทั้งหมด</div>
        </div>
    </div>

    <!-- 3. เล่นจบแล้ว -->
    <div class="stat-card">
        <div class="stat-icon emerald">✅</div>
        <div class="stat-details">
            <h4>เล่นจบแล้ว</h4>
            <div class="value"><?= number_format($completedSessions) ?> <span style="font-size: 16px; font-weight: 400; color: #64748b;">ครั้ง</span></div>
            <div class="sub"><?= $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100, 1) : 0 ?>% ของทั้งหมด</div>
        </div>
    </div>

    <!-- 4. ยังเล่นไม่จบ -->
    <div class="stat-card">
        <div class="stat-icon amber">⏳</div>
        <div class="stat-details">
            <h4>ยังเล่นไม่จบ</h4>
            <div class="value"><?= number_format($inProgressSessions) ?> <span style="font-size: 16px; font-weight: 400; color: #64748b;">ครั้ง</span></div>
            <div class="sub">อยู่ระหว่างดำเนินการ</div>
        </div>
    </div>

    <!-- 5. คะแนนเฉลี่ย -->
    <div class="stat-card">
        <div class="stat-icon purple">🏆</div>
        <div class="stat-details">
            <h4>คะแนนเฉลี่ย</h4>
            <div class="value"><?= $avgScore ?> <span style="font-size: 16px; font-weight: 400; color: #64748b;">/ 50</span></div>
            <div class="sub">จากผู้ที่เล่นจบแล้ว</div>
        </div>
    </div>

    <!-- 6. เปอร์เซ็นต์เฉลี่ย -->
    <div class="stat-card">
        <div class="stat-icon rose">📈</div>
        <div class="stat-details">
            <h4>เปอร์เซ็นต์เฉลี่ย</h4>
            <div class="value"><?= $avgPercent ?>%</div>
            <div class="sub"><?= $avgPercent >= 70 ? 'เกณฑ์ดีมาก' : ($avgPercent >= 50 ? 'เกณฑ์ผ่าน' : 'ต้องปรับปรุง') ?></div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Recent Sessions Table -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <span>🕒</span> ผลการเล่นล่าสุด (Recent Sessions)
            </div>
            <a href="results.php" class="btn btn-outline btn-sm">ดูทั้งหมด →</a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Session</th>
                        <th>ชื่อ - นามสกุล</th>
                        <th>ระดับชั้น</th>
                        <th>คะแนน</th>
                        <th>เปอร์เซ็นต์</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recentSessions) === 0): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #94a3b8; padding: 24px;">ยังไม่มีข้อมูลการเล่น</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentSessions as $session): ?>
                            <tr>
                                <td><strong>#<?= $session['id'] ?></strong></td>
                                <td>
                                    <a href="student.php?id=<?= $session['student_id'] ?>" style="color: #0f172a; text-decoration: none; font-weight: 600;">
                                        <?= e($session['first_name'] . ' ' . $session['last_name']) ?>
                                    </a>
                                </td>
                                <td><?= e($session['class_level']) ?>/<?= e($session['class_room']) ?> (เลขที่ <?= e($session['student_number']) ?>)</td>
                                <td>
                                    <strong><?= $session['total_score'] ?></strong> / <?= $session['max_score'] ?>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: <?= $session['percentage'] >= 70 ? '#10b981' : ($session['percentage'] >= 50 ? '#3b82f6' : '#ef4444') ?>;">
                                        <?= $session['percentage'] ?>%
                                    </span>
                                </td>
                                <td>
                                    <?php if ($session['completed_at']): ?>
                                        <span class="badge badge-success">✓ สำเร็จ</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">⏳ กำลังเล่น</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="session.php?id=<?= $session['id'] ?>" class="btn btn-outline btn-sm" title="ดูรายละเอียดคำตอบ">
                                        ดูคำตอบ
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Class breakdown summary -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <span>🏫</span> สรุปตามระดับชั้น
            </div>
            <a href="analytics.php" class="btn btn-outline btn-sm">วิเคราะห์ →</a>
        </div>
        <div class="card-body">
            <?php if (count($classStats) === 0): ?>
                <p style="color: #94a3b8; text-align: center;">ไม่มีข้อมูลชั้นเรียน</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <?php foreach ($classStats as $cs): ?>
                        <div style="background: #f8fafc; padding: 14px; border-radius: 10px; border: 1px solid #e2e8f0;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <strong style="font-size: 15px; color: #0f172a;"><?= e($cs['class_level'] ?: 'ไม่ระบุ') ?></strong>
                                <span class="badge badge-info"><?= $cs['student_count'] ?> คน</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 13px; color: #64748b;">
                                <span>เซสชัน: <?= $cs['session_count'] ?> ครั้ง</span>
                                <span>เฉลี่ย: <strong><?= $cs['avg_percent'] ? round($cs['avg_percent'], 1) . '%' : '-' ?></strong></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
