<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();

if (is_post()) {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'details') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $matric = trim((string) ($_POST['matric_number'] ?? ''));
        $error = null;

        if ($username !== '' && !preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            $error = 'Username must be 3-30 characters: letters, numbers or underscore.';
        } elseif ($username !== '' && q('SELECT 1 FROM users WHERE username = ? AND id <> ?', [$username, $user['id']])->fetch()) {
            $error = 'That username is already taken.';
        } elseif ($user['role'] === 'student' && $matric !== '' && !preg_match('/^[A-Za-z0-9]{4,30}$/', $matric)) {
            $error = 'Matric number must be 4-30 letters or digits.';
        }

        if ($error) {
            flash('error', $error);
        } else {
            q(
                'UPDATE users SET username = ?, matric_number = ? WHERE id = ?',
                [$username !== '' ? $username : null, $user['role'] === 'student' && $matric !== '' ? $matric : $user['matric_number'], $user['id']]
            );
            flash('success', 'Profile saved.');
        }
    }

    if ($action === 'avatar') {
        $files = normalize_files($_FILES['avatar'] ?? null);
        if (!$files) {
            flash('error', 'Choose a picture first.');
        } else {
            try {
                $saved = store_upload($files[0], 'avatars', IMAGE_TYPES, 2 * 1048576);
                q('UPDATE users SET avatar = ? WHERE id = ?', [$saved['stored'], $user['id']]);
                delete_upload('avatars', $user['avatar']);
                flash('success', 'Profile picture updated.');
            } catch (RuntimeException $ex) {
                flash('error', $ex->getMessage());
            }
        }
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');
        $hash = (string) q('SELECT password FROM users WHERE id = ?', [$user['id']])->fetchColumn();

        if (!password_verify($current, $hash)) {
            flash('error', 'Your current password is incorrect.');
        } elseif ($new !== $confirm) {
            flash('error', 'The new passwords do not match.');
        } elseif ($problem = password_problem($new)) {
            flash('error', $problem);
        } else {
            q('UPDATE users SET password = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            flash('success', 'Password changed.');
        }
    }
    redirect('profile.php');
}

page_header('Profile');
?>
<div class="profile-wrap">
    <a class="link back-link" href="<?= e(home_for($user['role'])) ?>">← Back</a>
    <h1 class="section-title">Profile</h1>

    <section class="card">
        <div class="profile-picture-section">
            <?php if ($src = avatar_url($user)): ?>
                <img class="profile-picture" src="<?= e($src) ?>" alt="Your profile picture">
            <?php else: ?>
                <span class="profile-picture initial"><?= e(initial($user['name'])) ?></span>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="avatar-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="avatar">
                <input type="file" name="avatar" accept="image/*" required>
                <button class="btn btn-secondary btn-inline" type="submit">Upload picture</button>
            </form>
        </div>

        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="details">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= e($user['username'] ?? '') ?>" placeholder="Choose a username">
            </div>
            <div class="form-group">
                <label>Name</label>
                <input type="text" value="<?= e($user['name']) ?>" readonly>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" value="<?= e($user['email']) ?>" readonly>
            </div>
            <?php if ($user['role'] === 'student'): ?>
                <div class="form-group">
                    <label for="matric_number">Matric number</label>
                    <input type="text" id="matric_number" name="matric_number" value="<?= e($user['matric_number'] ?? '') ?>">
                </div>
            <?php endif; ?>
            <button class="btn btn-primary" type="submit">Save profile</button>
        </form>
    </section>

    <section class="card">
        <h2 class="card-title">Change password</h2>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="password">
            <div class="form-group">
                <label for="current">Current password</label>
                <input type="password" id="current" name="current" autocomplete="current-password" required>
            </div>
            <div class="form-group">
                <label for="new">New password</label>
                <input type="password" id="new" name="new" autocomplete="new-password" required>
            </div>
            <div class="form-group">
                <label for="confirm">Confirm new password</label>
                <input type="password" id="confirm" name="confirm" autocomplete="new-password" required>
            </div>
            <button class="btn btn-primary" type="submit">Change password</button>
        </form>
    </section>
</div>
<?php page_footer(); ?>
