<?php
/**
 * Money Life - Student Management
 * List, Filter, Search, Add, Edit, Delete, CSV Import, and Pending Approvals
 */

require_once __DIR__ . '/auth/check.php';

$pageTitle = 'จัดการข้อมูลนักเรียน';
$message = '';
$messageType = 'success';

// ประมวลผล POST Actions (Add, Edit, Delete, Approve, Reject, Import CSV)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $message = 'CSRF Token ไม่ถูกต้องหรือหมดเวลา กรุณาลองใหม่';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. เพิ่มนักเรียนรายบุคคล
        if ($action === 'add') {
            $studentCode = trim($_POST['student_code'] ?? '');
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $classLevel = trim($_POST['class_level'] ?? '');
            $classRoom = trim($_POST['class_room'] ?? '');
            $studentNumber = (int)($_POST['student_number'] ?? 0);

            if ($firstName === '' || $lastName === '' || $classLevel === '' || $classRoom === '' || $studentNumber <= 0) {
                $message = 'กรุณากรอกข้อมูลนักเรียนให้ครบถ้วนถูกต้อง';
                $messageType = 'danger';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO students (student_code, first_name, last_name, class_level, class_room, student_number, status)
                        VALUES (?, ?, ?, ?, ?, ?, 'active')
                    ");
                    $stmt->execute([
                        $studentCode ?: null,
                        $firstName,
                        $lastName,
                        $classLevel,
                        $classRoom,
                        $studentNumber
                    ]);
                    $newId = $pdo->lastInsertId();
                    log_audit($pdo, 'ADD_STUDENT', 'students', $newId, ['name' => "$firstName $lastName", 'class' => "$classLevel/$classRoom"]);
                    $message = "เพิ่มข้อมูลนักเรียน {$firstName} {$lastName} เรียบร้อยแล้ว";
                } catch (PDOException $e) {
                    $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }

        // 2. แก้ไขข้อมูลนักเรียน
        elseif ($action === 'edit') {
            $id = (int)($_POST['student_id'] ?? 0);
            $studentCode = trim($_POST['student_code'] ?? '');
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $classLevel = trim($_POST['class_level'] ?? '');
            $classRoom = trim($_POST['class_room'] ?? '');
            $studentNumber = (int)($_POST['student_number'] ?? 0);
            $status = in_array($_POST['status'] ?? '', ['active', 'pending', 'rejected']) ? $_POST['status'] : 'active';

            if ($id <= 0 || $firstName === '' || $lastName === '' || $classLevel === '' || $classRoom === '' || $studentNumber <= 0) {
                $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
                $messageType = 'danger';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE students 
                        SET student_code = ?, first_name = ?, last_name = ?, class_level = ?, class_room = ?, student_number = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $studentCode ?: null,
                        $firstName,
                        $lastName,
                        $classLevel,
                        $classRoom,
                        $studentNumber,
                        $status,
                        $id
                    ]);
                    log_audit($pdo, 'EDIT_STUDENT', 'students', $id, ['name' => "$firstName $lastName"]);
                    $message = "อัปเดตข้อมูลนักเรียนเรียบร้อยแล้ว";
                } catch (PDOException $e) {
                    $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }

        // 3. ลบนักเรียน
        elseif ($action === 'delete') {
            $id = (int)($_POST['student_id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
                    $stmt->execute([$id]);
                    log_audit($pdo, 'DELETE_STUDENT', 'students', $id);
                    $message = "ลบข้อมูลนักเรียนเรียบร้อยแล้ว";
                } catch (PDOException $e) {
                    $message = "ไม่สามารถลบได้: " . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }

        // 4. อนุมัติ / ปฏิเสธ Pending Student
        elseif ($action === 'set_status') {
            $id = (int)($_POST['student_id'] ?? 0);
            $newStatus = $_POST['new_status'] ?? 'active';
            if ($id > 0 && in_array($newStatus, ['active', 'rejected'])) {
                try {
                    $stmt = $pdo->prepare("UPDATE students SET status = ? WHERE id = ?");
                    $stmt->execute([$newStatus, $id]);
                    log_audit($pdo, 'UPDATE_STUDENT_STATUS', 'students', $id, ['status' => $newStatus]);
                    $message = $newStatus === 'active' ? "อนุมัติข้อมูลนักเรียนเรียบร้อยแล้ว" : "ปฏิเสธข้อมูลนักเรียนแล้ว";
                } catch (PDOException $e) {
                    $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }

        // 5. Import จาก CSV
        elseif ($action === 'import_csv') {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmp = $_FILES['csv_file']['tmp_name'];
                $handle = fopen($fileTmp, "r");

                if ($handle !== FALSE) {
                    $importedCount = 0;
                    $rowIdx = 0;
                    
                    // ข้าม UTF-8 BOM ถ้ามี
                    $bom = fread($handle, 3);
                    if ($bom !== "\xEF\xBB\xBF") {
                        rewind($handle);
                    }

                    $insertStmt = $pdo->prepare("
                        INSERT INTO students (student_code, first_name, last_name, class_level, class_room, student_number, status)
                        VALUES (?, ?, ?, ?, ?, ?, 'active')
                    ");

                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $rowIdx++;
                        // ข้ามแถว header
                        if ($rowIdx === 1 && (strpos($data[0] ?? '', 'รหัส') !== false || strpos(strtolower($data[0] ?? ''), 'code') !== false || strpos($data[1] ?? '', 'ชื่อ') !== false)) {
                            continue;
                        }

                        // รูปแบบ: รหัสนักเรียน, ชื่อ, นามสกุล, ระดับชั้น, ห้อง, เลขที่
                        $sCode = trim($data[0] ?? '');
                        $sFirst = trim($data[1] ?? '');
                        $sLast = trim($data[2] ?? '');
                        $sLevel = trim($data[3] ?? '');
                        $sRoom = trim($data[4] ?? '');
                        $sNum = (int)trim($data[5] ?? 0);

                        if ($sFirst !== '' && $sLast !== '' && $sLevel !== '' && $sRoom !== '') {
                            $insertStmt->execute([
                                $sCode ?: null,
                                $sFirst,
                                $sLast,
                                $sLevel,
                                $sRoom,
                                $sNum
                            ]);
                            $importedCount++;
                        }
                    }
                    fclose($handle);
                    log_audit($pdo, 'IMPORT_CSV_STUDENTS', 'students', null, ['count' => $importedCount]);
                    $message = "นำเข้านักเรียนสำเร็จทั้งหมด {$importedCount} รายการ";
                } else {
                    $message = "ไม่สามารถเปิดไฟล์ CSV ได้";
                    $messageType = 'danger';
                }
            } else {
                $message = "กรุณาเลือกไฟล์ CSV ที่ถูกต้อง";
                $messageType = 'danger';
            }
        }
    }
}

// Filters & Search
$search = trim($_GET['search'] ?? '');
$filterClass = trim($_GET['class_level'] ?? '');
$filterRoom = trim($_GET['class_room'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

// ดึงรายการระดับชั้นและห้องที่มีทั้งหมดสำหรับ Filter Dropdown
$classLevels = $pdo->query("SELECT DISTINCT class_level FROM students WHERE class_level != '' ORDER BY class_level")->fetchAll(PDO::FETCH_COLUMN);
$classRooms = $pdo->query("SELECT DISTINCT class_room FROM students WHERE class_room != '' ORDER BY class_room")->fetchAll(PDO::FETCH_COLUMN);

// สร้าง Dynamic Query
$query = "
    SELECT s.*, 
           COUNT(gs.id) as session_count,
           MAX(gs.total_score) as max_score,
           MAX(gs.percentage) as max_percentage
    FROM students s
    LEFT JOIN game_sessions gs ON s.id = gs.student_id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_code LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($filterClass !== '') {
    $query .= " AND s.class_level = ?";
    $params[] = $filterClass;
}

if ($filterRoom !== '') {
    $query .= " AND s.class_room = ?";
    $params[] = $filterRoom;
}

if ($filterStatus !== '') {
    $query .= " AND s.status = ?";
    $params[] = $filterStatus;
}

$query .= " GROUP BY s.id ORDER BY s.class_level ASC, s.class_room ASC, s.student_number ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// นับ Pending
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status = 'pending'")->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <span><?= $messageType === 'success' ? '✓' : '⚠️' ?></span>
        <div><?= e($message) ?></div>
    </div>
<?php endif; ?>

<?php if ($pendingCount > 0 && $filterStatus !== 'pending'): ?>
    <div class="alert alert-warning" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <strong>⏳ มีนักเรียนรอการอนุมัติ <?= $pendingCount ?> คน</strong> กรุณาตรวจสอบและอนุมัติก่อนเริ่มเล่น
        </div>
        <a href="students.php?status=pending" class="btn btn-outline btn-sm" style="background: white;">
            ดูรายการรออนุมัติ →
        </a>
    </div>
<?php endif; ?>

<!-- Action Header & Filter -->
<div class="filter-bar">
    <form method="GET" action="students.php" class="filter-group">
        <input type="text" name="search" class="form-control" placeholder="🔍 ค้นหาชื่อ, นามสกุล, รหัส..." 
               value="<?= e($search) ?>" style="width: 220px;">

        <select name="class_level" class="form-control" style="width: 140px;">
            <option value="">ทุกระดับชั้น</option>
            <?php foreach ($classLevels as $lvl): ?>
                <option value="<?= e($lvl) ?>" <?= $filterClass === $lvl ? 'selected' : '' ?>>
                    <?= e($lvl) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="class_room" class="form-control" style="width: 120px;">
            <option value="">ทุกห้อง</option>
            <?php foreach ($classRooms as $rm): ?>
                <option value="<?= e($rm) ?>" <?= $filterRoom === $rm ? 'selected' : '' ?>>
                    ห้อง <?= e($rm) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="form-control" style="width: 130px;">
            <option value="">ทุกสถานะ</option>
            <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>อนุมัติแล้ว</option>
            <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>รออนุมัติ</option>
            <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>ปฏิเสธ</option>
        </select>

        <button type="submit" class="btn btn-accent btn-sm">กรอง</button>
        <?php if ($search || $filterClass || $filterRoom || $filterStatus): ?>
            <a href="students.php" class="btn btn-outline btn-sm">ล้างตัวกรอง</a>
        <?php endif; ?>
    </form>

    <div style="display: flex; gap: 10px;">
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addStudentModal')">
            ➕ เพิ่มนักเรียน
        </button>
        <button type="button" class="btn btn-outline btn-sm" onclick="openModal('importCsvModal')">
            📥 นำเข้า CSV
        </button>
    </div>
</div>

<!-- Students List Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>👥</span> รายชื่อนักเรียน (<?= count($students) ?> คน)
        </div>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50px;">รูป</th>
                    <th>รหัสประจำตัว</th>
                    <th>ชื่อ - นามสกุล</th>
                    <th>ชั้น / ห้อง</th>
                    <th>เลขที่</th>
                    <th>สถานะ</th>
                    <th>เล่นแล้ว (ครั้ง)</th>
                    <th>คะแนนสูงสุด</th>
                    <th>เปอร์เซ็นต์</th>
                    <th style="text-align: right;">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($students) === 0): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; color: #94a3b8; padding: 30px;">
                            ไม่พบข้อมูลนักเรียนที่ตรงกับเงื่อนไข
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $st): ?>
                        <tr>
                            <td>
                                <?php if (!empty($st['photo']) && file_exists(__DIR__ . '/../' . $st['photo'])): ?>
                                    <img src="../<?= e($st['photo']) ?>" alt="Avatar" 
                                         style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid #10b981;">
                                <?php else: ?>
                                    <div style="width: 38px; height: 38px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #94a3b8;">
                                        👤
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="font-family: monospace; font-size: 14px; color: #0f172a;">
                                    <?= e($st['student_code'] ?: '-') ?>
                                </strong>
                            </td>
                            <td>
                                <a href="student.php?id=<?= $st['id'] ?>" style="font-weight: 600; color: #0f172a; text-decoration: none;">
                                    <?= e($st['first_name'] . ' ' . $st['last_name']) ?>
                                </a>
                            </td>
                            <td><?= e($st['class_level']) ?> / <?= e($st['class_room']) ?></td>
                            <td><strong><?= e($st['student_number']) ?></strong></td>
                            <td>
                                <?php if ($st['status'] === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php elseif ($st['status'] === 'pending'): ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info"><?= $st['session_count'] ?> ครั้ง</span>
                            </td>
                            <td>
                                <?= $st['max_score'] !== null ? $st['max_score'] . ' / 50' : '-' ?>
                            </td>
                            <td>
                                <?php if ($st['max_percentage'] !== null): ?>
                                    <strong style="color: <?= $st['max_percentage'] >= 70 ? '#10b981' : '#3b82f6' ?>;">
                                        <?= $st['max_percentage'] ?>%
                                    </strong>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if ($st['status'] === 'pending'): ?>
                                    <form method="POST" action="students.php" style="display: inline-block;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="set_status">
                                        <input type="hidden" name="student_id" value="<?= $st['id'] ?>">
                                        <input type="hidden" name="new_status" value="active">
                                        <button type="submit" class="btn btn-primary btn-sm" title="อนุมัติ">✓ อนุมัติ</button>
                                    </form>
                                    <form method="POST" action="students.php" style="display: inline-block;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="set_status">
                                        <input type="hidden" name="student_id" value="<?= $st['id'] ?>">
                                        <input type="hidden" name="new_status" value="rejected">
                                        <button type="submit" class="btn btn-danger btn-sm" title="ปฏิเสธ">✕ ปฏิเสธ</button>
                                    </form>
                                <?php endif; ?>

                                <a href="student.php?id=<?= $st['id'] ?>" class="btn btn-outline btn-sm" title="ดูประวัติการเล่น">
                                    🎮 ประวัติ
                                </a>

                                <button type="button" class="btn btn-outline btn-sm" 
                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($st), ENT_QUOTES, 'UTF-8') ?>)">
                                    ✏️ แก้ไข
                                </button>

                                <form method="POST" action="students.php" style="display: inline-block;" onsubmit="return confirm('ยืนยันลบข้อมูลนักเรียนนี้? ข้อมูลเซสชันเกมทั้งหมดของนักเรียนจะถูกลบด้วย')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="student_id" value="<?= $st['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="ลบ">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal 1: เพิ่มนักเรียน -->
<div id="addStudentModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3 style="font-size: 17px; font-weight: 700;">➕ เพิ่มข้อมูลนักเรียนใหม่</h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('addStudentModal')">✕</button>
        </div>
        <form method="POST" action="students.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">รหัสนักเรียน (ไม่บังคับ)</label>
                    <input type="text" name="student_code" class="form-control" placeholder="เช่น 6601001">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">ชื่อ *</label>
                        <input type="text" name="first_name" class="form-control" required placeholder="ชื่อจริง">
                    </div>
                    <div class="form-group">
                        <label class="form-label">นามสกุล *</label>
                        <input type="text" name="last_name" class="form-control" required placeholder="นามสกุล">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">ระดับชั้น *</label>
                        <input type="text" name="class_level" class="form-control" required placeholder="เช่น ม.1 หรือ ป.6">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ห้อง *</label>
                        <input type="text" name="class_room" class="form-control" required placeholder="เช่น 1 หรือ 2">
                    </div>
                    <div class="form-group">
                        <label class="form-label">เลขที่ *</label>
                        <input type="number" name="student_number" min="1" max="99" class="form-control" required placeholder="เลขที่">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addStudentModal')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: แก้ไขนักเรียน -->
<div id="editStudentModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3 style="font-size: 17px; font-weight: 700;">✏️ แก้ไขข้อมูลนักเรียน</h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('editStudentModal')">✕</button>
        </div>
        <form method="POST" action="students.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="student_id" id="edit_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">รหัสนักเรียน</label>
                    <input type="text" name="student_code" id="edit_code" class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">ชื่อ *</label>
                        <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">นามสกุล *</label>
                        <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">ระดับชั้น *</label>
                        <input type="text" name="class_level" id="edit_class_level" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ห้อง *</label>
                        <input type="text" name="class_room" id="edit_class_room" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">เลขที่ *</label>
                        <input type="number" name="student_number" id="edit_student_number" min="1" max="99" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">สถานะ</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="active">Active (อนุมัติแล้ว)</option>
                        <option value="pending">Pending (รอตรวจสอบ)</option>
                        <option value="rejected">Rejected (ปฏิเสธ)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editStudentModal')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Import CSV -->
<div id="importCsvModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3 style="font-size: 17px; font-weight: 700;">📥 นำเข้ารายชื่อนักเรียนจากไฟล์ CSV</h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('importCsvModal')">✕</button>
        </div>
        <form method="POST" action="students.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="import_csv">
            <div class="modal-body">
                <p style="font-size: 13.5px; color: #64748b; margin-bottom: 14px;">
                    รองรับไฟล์ <code>.csv</code> โดยเรียงคอลัมน์ดังนี้:<br>
                    <strong>รหัสนักเรียน, ชื่อ, นามสกุล, ระดับชั้น, ห้อง, เลขที่</strong>
                </p>
                <div class="form-group">
                    <label class="form-label">เลือกไฟล์ CSV</label>
                    <input type="file" name="csv_file" accept=".csv,text/csv" required class="form-control">
                </div>
                <div style="background: #f8fafc; padding: 12px; border-radius: 8px; font-size: 12px; color: #64748b;">
                    💡 ตัวอย่างแถวในไฟล์:<br>
                    <code>6601001,สมปอง,สุขใจ,ม.1,1,1</code><br>
                    <code>6601002,กานดา,มารื่น,ม.1,1,2</code>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('importCsvModal')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">นำเข้าข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(student) {
    document.getElementById('edit_id').value = student.id;
    document.getElementById('edit_code').value = student.student_code || '';
    document.getElementById('edit_first_name').value = student.first_name || '';
    document.getElementById('edit_last_name').value = student.last_name || '';
    document.getElementById('edit_class_level').value = student.class_level || '';
    document.getElementById('edit_class_room').value = student.class_room || '';
    document.getElementById('edit_student_number').value = student.student_number || '';
    document.getElementById('edit_status').value = student.status || 'active';
    openModal('editStudentModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
