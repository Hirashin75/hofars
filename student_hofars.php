<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_role('student');
$view = ($_GET['view'] ?? '') === 'mine' ? 'mine' : 'new';
$facilities = q('SELECT id, name FROM facilities ORDER BY id')->fetchAll();

$errors = [];
$old = [
    'student_name'  => $user['name'],
    'matric_number' => $user['matric_number'] ?? '',
    'room_number'   => '',
    'block_number'  => '',
    'facility_id'   => '',
    'description'   => '',
];

if (is_post()) {
    csrf_check();
    foreach ($old as $key => $_) {
        $old[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    if (in_array('', $old, true)) {
        $errors[] = 'Please fill in all fields.';
    } elseif (mb_strlen($old['student_name']) > 100 || !preg_match("/^[\p{L}\s.'@\/-]+$/u", $old['student_name'])) {
        $errors[] = 'Name may only contain letters, spaces and . \' - / @.';
    } elseif (!preg_match('/^[A-Za-z0-9]{4,30}$/', $old['matric_number'])) {
        $errors[] = 'Matric number must be 4-30 letters or digits.';
    } elseif (mb_strlen($old['room_number']) > 20 || mb_strlen($old['block_number']) > 50) {
        $errors[] = 'Room or block is too long.';
    } elseif (!in_array((int) $old['facility_id'], array_column($facilities, 'id'), false)) {
        $errors[] = 'Please choose a facility.';
    } elseif (mb_strlen($old['description']) < 10 || mb_strlen($old['description']) > 1000) {
        $errors[] = 'Description must be between 10 and 1000 characters.';
    }

    $photo = null;
    if (!$errors) {
        $upload = normalize_files($_FILES['photo'] ?? null);
        if ($upload) {
            try {
                $photo = store_upload($upload[0], 'reports', IMAGE_TYPES, 5 * 1048576)['stored'];
            } catch (RuntimeException $ex) {
                $errors[] = $ex->getMessage();
            }
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stamp = now();
            q(
                'INSERT INTO reports (user_id, student_name, matric_number, room_number, block_number, facility_id,
                                      description, photo, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'Pending\', ?, ?)',
                [$user['id'], $old['student_name'], $old['matric_number'], $old['room_number'], $old['block_number'],
                 (int) $old['facility_id'], $old['description'], $photo, $stamp, $stamp]
            );
            $rid = (int) $pdo->lastInsertId();
            add_history($rid, 'Pending', 'Report submitted by student.', (int) $user['id']);
            if (empty($user['matric_number'])) {
                q('UPDATE users SET matric_number = ? WHERE id = ?', [$old['matric_number'], $user['id']]);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            delete_upload('reports', $photo);
            throw $ex;
        }
        flash('success', 'Report ' . report_code($rid) . ' submitted. You can follow its progress under My Reports.');
        redirect('student_hofars.php?view=mine');
    }
}

$statusFilter = (string) ($_GET['status'] ?? '');
$reports = [];
if ($view === 'mine') {
    $sql = 'SELECT r.id, r.room_number, r.block_number, r.status, r.created_at, f.name AS facility, t.name AS technician_name
              FROM reports r
              JOIN facilities f ON f.id = r.facility_id
              LEFT JOIN users t ON t.id = r.technician_id
             WHERE r.user_id = ?';
    $params = [$user['id']];
    if (in_array($statusFilter, REPORT_STATUSES, true)) {
        $sql .= ' AND r.status = ?';
        $params[] = $statusFilter;
    }
    $reports = q($sql . ' ORDER BY r.created_at DESC, r.id DESC', $params)->fetchAll();
}

page_header($view === 'mine' ? 'My Reports' : 'New Report', $view);
?>
<?php if ($view === 'new'): ?>
    <h1 class="section-title">Report a damaged facility</h1>
    <p class="section-subtitle">Tell us what's broken and where. Staff will assign a technician.</p>

    <form class="card form-card" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <div class="form-row">
            <div class="form-group">
                <label for="student_name">Student name</label>
                <input type="text" id="student_name" name="student_name" value="<?= e($old['student_name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="matric_number">Matric number</label>
                <input type="text" id="matric_number" name="matric_number" value="<?= e($old['matric_number']) ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="room_number">Room number</label>
                <input type="text" id="room_number" name="room_number" value="<?= e($old['room_number']) ?>" placeholder="e.g. TC215" required>
            </div>
            <div class="form-group">
                <label for="block_number">Block</label>
                <input type="text" id="block_number" name="block_number" value="<?= e($old['block_number']) ?>" placeholder="e.g. T" required>
            </div>
        </div>
        <div class="form-group">
            <label for="facility_id">Facility</label>
            <select id="facility_id" name="facility_id" required>
                <option value="">Select facility</option>
                <?php foreach ($facilities as $f): ?>
                    <option value="<?= (int) $f['id'] ?>"<?= (string) $f['id'] === $old['facility_id'] ? ' selected' : '' ?>><?= e($f['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" maxlength="1000" required placeholder="What is damaged, and since when?"><?= e($old['description']) ?></textarea>
        </div>
        <div class="form-group">
            <label for="photo">Photo <small>(optional, JPG/PNG/GIF/WebP, max 5 MB)</small></label>
            <input type="file" id="photo" name="photo" accept="image/*">
            <img id="photoPreview" class="photo-preview" alt="" hidden>
        </div>
        <button type="submit" class="btn btn-primary">Submit report</button>
    </form>

<?php else: ?>
    <h1 class="section-title">My reports</h1>
    <p class="section-subtitle">Everything you've reported and where it stands.</p>

    <form class="filter-bar" method="get">
        <input type="hidden" name="view" value="mine">
        <select name="status" onchange="this.form.submit()" aria-label="Filter by status">
            <option value="">All statuses</option>
            <?php foreach (REPORT_STATUSES as $s): ?>
                <option<?= $statusFilter === $s ? ' selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
        <a class="btn btn-secondary btn-inline" href="student_hofars.php">+ New report</a>
    </form>

    <div class="table-container">
        <table class="report-table">
            <thead>
                <tr><th>Ref</th><th>Submitted</th><th>Facility</th><th>Location</th><th>Technician</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (!$reports): ?>
                <tr><td colspan="7" class="no-data">No reports found.</td></tr>
            <?php else: foreach ($reports as $r): ?>
                <tr>
                    <td><strong><?= e(report_code((int) $r['id'])) ?></strong></td>
                    <td><?= e(fmt_date($r['created_at'])) ?></td>
                    <td><?= e($r['facility']) ?></td>
                    <td><?= e($r['block_number'] . ' / ' . $r['room_number']) ?></td>
                    <td><?= e($r['technician_name'] ?? '-') ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td><a class="link" href="report.php?id=<?= (int) $r['id'] ?>">View</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php page_footer(); ?>
