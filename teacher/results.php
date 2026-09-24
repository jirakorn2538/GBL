<?php
/**
 * Money Life - Overall Game Results
 * Displays 1 row per student with latest/best scores and view history
 */

require_once __DIR__ . '/auth/check.php';

$pageTitle = 'ผลการเล่นของนักเรียน';

$search = trim($_GET['search'] ?? '');
$filterClass = trim($_GET['class_level'] ?? '');
$filterRoom = trim($_GET['class_room'] ?? '');

$classLevels = $pdo->query("SELECT DISTINCT class_level FROM students WHERE class_level != '' ORDER BY class_level")->fetchAll(PDO::FETCH_COLUMN);
$classRooms = $pdo->query("SELECT DISTINCT class_room FROM students WHERE class_room != '' ORDER BY class_room")->fetchAll(PDO::FETCH_COLUMN);

// ดึงข้อมูลสรุปผล 1 แถวต่อนักเรียน 1 คน
$sql = "
    SELECT 
        s.id as student_id,
        s.student_code,
        s.first_name,
        s.last_name,
        s.class_level,
        s.class_room,
        s.student_number,
        COUNT(gs.id) as session_count,
        MAX(gs.total_score) as max_score,
        MAX(gs.percentage) as max_percentage,
        SUBSTRING_INDEX(GROUP_CONCAT(gs.total_score ORDER BY gs.id DESC), ',', 1) as latest_score,
        SUBSTRING_INDEX(GROUP_CONCAT(gs.percentage ORDER BY gs.id DESC), ',', 1) as latest_percentage,
        SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(gs.completed_at, 'in_progress') ORDER BY gs.id DESC), ',', 1) as latest_status,
        MAX(gs.started_at) as latest_play_date
    FROM students s
    LEFT JOIN game_sessions gs ON s.id = gs.student_id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_code LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($filterClass !== '') {
    $sql .= " AND s.class_level = ?";
    $params[] = $filterClass;
}

if ($filterRoom !== '') {
    $sql .= " AND s.class_room = ?";
    $params[] = $filterRoom;
}

$sql .= " GROUP BY s.id ORDER BY s.class_level ASC, s.class_room ASC, s.student_number ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="filter-bar">
    <form method="GET" action="results.php" class="filter-group">
        <input type="text" name="search" class="form-control" placeholder="🔍 ค้นหาชื่อ, รหัส..." 
               value="<?= e($search) ?>" style="width: 220px;">

        <select name="class_level" class="form-control" style="width: 140px;">
            <option value="">ทุกระดับชั้น</option>
            <?php foreach ($classLevels as $lvl): ?>
                <option value="<?= e($lvl) ?>" <?= $filterClass === $lvl ? 'selected' : '' ?>><?= e($lvl) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="class_room" class="form-control" style="width: 120px;">
            <option value="">ทุกห้อง</option>
            <?php foreach ($classRooms as $rm): ?>
                <option value="<?= e($rm) ?>" <?= $filterRoom === $rm ? 'selected' : '' ?>>ห้อง <?= e($rm) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-accent btn-sm">กรอง</button>
        <?php if ($search || $filterClass || $filterRoom): ?>
            <a href="results.php" class="btn btn-outline btn-sm">ล้างตัวกรอง</a>
        <?php endif; ?>
    </form>

    <div>
        <a href="reports.php" class="btn btn-primary btn-sm">
            📄 สรุปรายงาน / Export
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>🎮</span> สรุปผลการเล่นรายบุคคล (1 แถวต่อนักเรียน 1 คน)
        </div>
        <span style="font-size: 13.5px; color: #64748b;">พบข้อมูล <?= count($results) ?> คน</span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ชื่อ - นามสกุล</th>
                    <th>ชั้น</th>
                    <th>ห้อง</th>
                    <th>เลขที่</th>
                    <th>จำนวนครั้ง</th>
                    <th>คะแนนล่าสุด</th>
                    <th>คะแนนสูงสุด</th>
                    <th>เปอร์เซ็นต์</th>
                    <th>สถานะล่าสุด</th>
                    <th>วันที่เล่นล่าสุด</th>
                    <th style="text-align: right;">ประวัติ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($results) === 0): ?>
                    <tr>
                        <td colspan="11" style="text-align: center; color: #94a3b8; padding: 30px;">
                            ไม่พบข้อมูลผลการเล่นที่ตรงกับเงื่อนไข
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td>
                                <a href="student.php?id=<?= $row['student_id'] ?>" style="font-weight: 600; color: #0f172a; text-decoration: none;">
                                    <?= e($row['first_name'] . ' ' . $row['last_name']) ?>
                                </a>
                            </td>
                            <td><?= e($row['class_level']) ?></td>
                            <td><?= e($row['class_room']) ?></td>
                            <td><strong><?= e($row['student_number']) ?></strong></td>
                            <td>
                                <span class="badge <?= $row['session_count'] > 0 ? 'badge-info' : 'badge-secondary' ?>">
                                    <?= $row['session_count'] ?> ครั้ง
                                </span>
                            </td>
                            <td>
                                <?= $row['latest_score'] !== null ? "<strong>{$row['latest_score']}</strong> / 50" : '-' ?>
                            </td>
                            <td>
                                <?= $row['max_score'] !== null ? "<strong style='color: #10b981;'>{$row['max_score']}</strong> / 50" : '-' ?>
                            </td>
                            <td>
                                <?php if ($row['max_percentage'] !== null): ?>
                                    <strong style="color: <?= $row['max_percentage'] >= 70 ? '#10b981' : ($row['max_percentage'] >= 50 ? '#3b82f6' : '#ef4444') ?>;">
                                        <?= $row['max_percentage'] ?>%
                                    </strong>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['session_count'] == 0): ?>
                                    <span class="badge badge-secondary">ยังไม่เล่น</span>
                                <?php elseif ($row['latest_status'] !== 'in_progress'): ?>
                                    <span class="badge badge-success">✓ จบแล้ว</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">⏳ เล่นค้างอยู่</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 13px; color: #64748b;">
                                <?= $row['latest_play_date'] ? date('d/m/Y H:i', strtotime($row['latest_play_date'])) : '-' ?>
                            </td>
                            <td style="text-align: right;">
                                <a href="student.php?id=<?= $row['student_id'] ?>" class="btn btn-outline btn-sm">
                                    [ดูประวัติ]
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
