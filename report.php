<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$report = find_report_for($user, $id);
if (!$report) {
    http_response_code(404);
    flash('error', 'That report was not found.');
    redirect(home_for($user['role']));
}

$self = 'report.php?id=' . $id;
$role = $user['role'];
$isOwnJob = $role === 'technician' && (int) $report['technician_id'] === (int) $user['id'];

/** Move the report to a new status and log it on the timeline. */
function change_status(int $reportId, string $status, string $note, int $actorId, bool $resolved = false): void
{
    $stamp = now();
    q(
        'UPDATE reports SET status = ?, updated_at = ?, resolved_at = ? WHERE id = ?',
        [$status, $stamp, $resolved ? $stamp : null, $reportId]
    );
    add_history($reportId, $status, $note, $actorId);
}

if (is_post()) {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $pdo = db();

    // Student: cancel a report nobody has picked up yet.
    if ($action === 'cancel' && $role === 'student' && $report['status'] === 'Pending') {
        change_status($id, 'Cancelled', 'Cancelled by the student.', (int) $user['id']);
        flash('success', 'Report cancelled.');
        redirect($self);
    }

    // Technician: start the job.
    if ($action === 'start' && $isOwnJob && $report['status'] === 'Assigned') {
        change_status($id, 'In Progress', 'Technician started the job.', (int) $user['id']);
        flash('success', 'Job started.');
        redirect($self);
    }

    // Technician: post a progress note, or a final "done" report with proof for admin review.
    if ($action === 'update' && $isOwnJob && in_array($report['status'], ['Assigned', 'In Progress'], true)) {
        $text = trim((string) ($_POST['progress'] ?? ''));
        $final = !empty($_POST['is_final']);
        $uploads = normalize_files($_FILES['proof'] ?? null);
        $error = null;

        if (mb_strlen($text) < 5 || mb_strlen($text) > 1000) {
            $error = 'Please describe the progress (5-1000 characters).';
        } elseif (count($uploads) > 5) {
            $error = 'You can attach up to 5 files at a time.';
        } elseif ($final && !$uploads) {
            $error = 'Attach at least one photo or video as proof before sending the job for review.';
        }

        $saved = [];
        if (!$error) {
            try {
                foreach ($uploads as $file) {
                    $saved[] = store_upload($file, 'updates', IMAGE_TYPES + VIDEO_TYPES, 10 * 1048576);
                }
            } catch (RuntimeException $ex) {
                $error = $ex->getMessage();
            }
        }

        if ($error) {
            foreach ($saved as $s) {
                delete_upload('updates', $s['stored']);
            }
            flash('error', $error);
            redirect($self);
        }

        $pdo->beginTransaction();
        try {
            q(
                'INSERT INTO report_updates (report_id, technician_id, progress_description, is_final, review_status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$id, $user['id'], $text, $final ? 1 : 0, $final ? 'Pending' : 'None', now()]
            );
            $updateId = (int) $pdo->lastInsertId();
            foreach ($saved as $s) {
                q('INSERT INTO update_files (update_id, stored_name, original_name, mime) VALUES (?, ?, ?, ?)',
                  [$updateId, $s['stored'], $s['original'], $s['mime']]);
            }
            if ($final) {
                change_status($id, 'Under Review', 'Technician submitted the completed work for review.', (int) $user['id']);
            } elseif ($report['status'] === 'Assigned') {
                change_status($id, 'In Progress', 'Technician started the job.', (int) $user['id']);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            foreach ($saved as $s) {
                delete_upload('updates', $s['stored']);
            }
            throw $ex;
        }
        flash('success', $final ? 'Sent for admin review.' : 'Progress saved.');
        redirect($self);
    }

    // Admin: approve or reject a technician's completed work.
    if (in_array($action, ['approve', 'reject'], true) && $role === 'admin' && $report['status'] === 'Under Review') {
        $update = q(
            "SELECT id FROM report_updates WHERE report_id = ? AND is_final = 1 AND review_status = 'Pending' ORDER BY id DESC LIMIT 1",
            [$id]
        )->fetch();
        $note = trim((string) ($_POST['review_note'] ?? ''));

        if (!$update) {
            flash('error', 'There is no completed work waiting for review.');
        } elseif ($action === 'reject' && $note === '') {
            flash('error', 'Please tell the technician what still needs fixing.');
        } elseif (mb_strlen($note) > 500) {
            flash('error', 'Review note is too long (max 500 characters).');
        } else {
            $approve = $action === 'approve';
            $pdo->beginTransaction();
            q(
                'UPDATE report_updates SET review_status = ?, review_note = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?',
                [$approve ? 'Approved' : 'Rejected', $note !== '' ? $note : null, $user['id'], now(), $update['id']]
            );
            if ($approve) {
                change_status($id, 'Resolved', 'Work approved by admin.', (int) $user['id'], true);
            } else {
                change_status($id, 'In Progress', 'Sent back to the technician: ' . $note, (int) $user['id']);
            }
            $pdo->commit();
            flash('success', $approve ? 'Report resolved.' : 'Sent back to the technician.');
        }
        redirect($self);
    }

    flash('error', 'That action is not available for this report.');
    redirect($self);
}

$history = q(
    'SELECT h.*, u.name AS actor FROM report_history h LEFT JOIN users u ON u.id = h.actor_id
      WHERE h.report_id = ? ORDER BY h.created_at, h.id',
    [$id]
)->fetchAll();

$updates = q(
    'SELECT ru.*, t.name AS technician_name, rv.name AS reviewer_name
       FROM report_updates ru
       JOIN users t ON t.id = ru.technician_id
       LEFT JOIN users rv ON rv.id = ru.reviewed_by
      WHERE ru.report_id = ? ORDER BY ru.created_at DESC, ru.id DESC',
    [$id]
)->fetchAll();
$filesByUpdate = [];
if ($updates) {
    $marks = implode(',', array_fill(0, count($updates), '?'));
    foreach (q("SELECT * FROM update_files WHERE update_id IN ($marks) ORDER BY id", array_column($updates, 'id'))->fetchAll() as $f) {
        $filesByUpdate[$f['update_id']][] = $f;
    }
}
$pendingReview = array_filter($updates, fn ($u) => $u['review_status'] === 'Pending');

$back = match ($role) {
    'admin'      => ['admin_hofars.php', 'Back to dashboard'],
    'technician' => ['technician_hofars.php', 'Back to my jobs'],
    default      => ['student_hofars.php?view=mine', 'Back to my reports'],
};

page_header(report_code($id), $role === 'student' ? 'mine' : ($role === 'admin' ? 'dashboard' : 'jobs'));
?>
<a class="link back-link" href="<?= e($back[0]) ?>">← <?= e($back[1]) ?></a>

<div class="detail-head">
    <div>
        <h1 class="section-title left"><?= e(report_code($id)) ?> · <?= e($report['facility']) ?></h1>
        <p class="muted">Reported <?= e(fmt_datetime($report['created_at'])) ?> by <?= e($report['student_name']) ?> (<?= e($report['matric_number']) ?>)</p>
    </div>
    <?= status_badge($report['status']) ?>
</div>

<div class="detail-grid">
    <section class="card">
        <h2 class="card-title">Problem</h2>
        <dl class="facts">
            <dt>Location</dt><dd>Block <?= e($report['block_number']) ?>, room <?= e($report['room_number']) ?></dd>
            <dt>Technician</dt><dd><?= e($report['technician_name'] ?? 'Not assigned yet') ?></dd>
            <?php if ($report['resolved_at']): ?><dt>Resolved</dt><dd><?= e(fmt_datetime($report['resolved_at'])) ?></dd><?php endif; ?>
        </dl>
        <p class="description"><?= nl2br(e($report['description'])) ?></p>
        <?php if ($report['photo']): ?>
            <a href="download.php?type=report&id=<?= $id ?>" target="_blank" rel="noopener">
                <img class="report-photo" src="download.php?type=report&id=<?= $id ?>" alt="Photo of the damage">
            </a>
        <?php endif; ?>

        <?php if ($role === 'student' && $report['status'] === 'Pending'): ?>
            <form method="post" data-confirm="Cancel this report?">
                <?= csrf_field() ?><input type="hidden" name="action" value="cancel">
                <button class="btn btn-danger btn-inline" type="submit">Cancel report</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2 class="card-title">Timeline</h2>
        <ol class="timeline">
            <?php foreach ($history as $h): ?>
                <li>
                    <span class="dot <?= e(status_class($h['status'])) ?>"></span>
                    <div>
                        <strong><?= e($h['status']) ?></strong>
                        <p><?= e($h['note']) ?></p>
                        <small class="muted"><?= e(fmt_datetime($h['created_at'])) ?><?= $h['actor'] ? ' · ' . e($h['actor']) : '' ?></small>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
</div>

<?php if ($role === 'admin' && $report['status'] === 'Under Review' && $pendingReview): ?>
    <section class="card review-card">
        <h2 class="card-title">Review completed work</h2>
        <p class="muted">Check the technician's proof below, then approve or send it back.</p>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="review_note">Note <small>(required when sending back)</small></label>
                <textarea id="review_note" name="review_note" rows="2" maxlength="500"></textarea>
            </div>
            <div class="button-row">
                <button class="btn btn-success btn-inline" type="submit" name="action" value="approve">Approve &amp; resolve</button>
                <button class="btn btn-danger btn-inline" type="submit" name="action" value="reject">Send back</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php if ($isOwnJob && $report['status'] === 'Assigned'): ?>
    <section class="card">
        <h2 class="card-title">Ready to begin?</h2>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="start">
            <button class="btn btn-primary btn-inline" type="submit">Start job</button>
        </form>
    </section>
<?php endif; ?>

<?php if ($isOwnJob && in_array($report['status'], ['Assigned', 'In Progress'], true)): ?>
    <section class="card">
        <h2 class="card-title">Add a work report</h2>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?><input type="hidden" name="action" value="update">
            <div class="form-group">
                <label for="progress">What was done?</label>
                <textarea id="progress" name="progress" rows="3" maxlength="1000" required></textarea>
            </div>
            <div class="form-group">
                <label for="proof">Photos / videos <small>(up to 5, 10 MB each)</small></label>
                <input type="file" id="proof" name="proof[]" accept="image/*,video/mp4,video/quicktime,video/x-msvideo" multiple>
            </div>
            <label class="check"><input type="checkbox" name="is_final" value="1"> The job is finished, send it to the admin for review (proof required)</label>
            <button class="btn btn-primary btn-inline" type="submit">Save report</button>
        </form>
    </section>
<?php endif; ?>

<?php if ($updates): ?>
    <section class="card">
        <h2 class="card-title">Work reports</h2>
        <?php foreach ($updates as $u): ?>
            <article class="update">
                <header>
                    <strong><?= e($u['technician_name']) ?></strong>
                    <small class="muted"><?= e(fmt_datetime($u['created_at'])) ?></small>
                    <?php if ($u['is_final']): ?>
                        <span class="status-badge status-<?= e(strtolower($u['review_status'] === 'Pending' ? 'under-review' : ($u['review_status'] === 'Approved' ? 'resolved' : 'cancelled'))) ?>">
                            <?= e($u['review_status'] === 'Pending' ? 'Awaiting review' : $u['review_status']) ?>
                        </span>
                    <?php endif; ?>
                </header>
                <p><?= nl2br(e($u['progress_description'])) ?></p>
                <?php if (!empty($filesByUpdate[$u['id']])): ?>
                    <div class="proof-grid">
                        <?php foreach ($filesByUpdate[$u['id']] as $f): $src = 'download.php?type=update&id=' . (int) $f['id']; ?>
                            <?php if (str_starts_with($f['mime'], 'video/')): ?>
                                <video src="<?= e($src) ?>" controls preload="metadata"></video>
                            <?php else: ?>
                                <a href="<?= e($src) ?>" target="_blank" rel="noopener"><img src="<?= e($src) ?>" alt="<?= e($f['original_name']) ?>" loading="lazy"></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($u['review_note']): ?>
                    <p class="review-note"><strong><?= e($u['reviewer_name'] ?? 'Admin') ?>:</strong> <?= e($u['review_note']) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php page_footer(); ?>
