<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_role('admin');
$old = ['name' => '', 'email' => '', 'role' => 'technician'];

if (is_post()) {
    csrf_check();
    $old['name'] = trim((string) ($_POST['name'] ?? ''));
    $old['email'] = trim((string) ($_POST['email'] ?? ''));
    $old['role'] = (string) ($_POST['role'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $error = null;
    if ($old['name'] === '' || $old['email'] === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (mb_strlen($old['name']) > 100 || !preg_match("/^[\p{L}\s.'@\/-]+$/u", $old['name'])) {
        $error = 'Name may only contain letters, spaces and . \' - / @.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 150) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array($old['role'], ROLES, true)) {
        $error = 'Please choose a role.';
    } elseif ($problem = password_problem($password)) {
        $error = $problem;
    } elseif (q('SELECT 1 FROM users WHERE email = ?', [$old['email']])->fetch()) {
        $error = 'An account with that email already exists.';
    }

    if ($error) {
        flash('error', $error);
    } else {
        q(
            'INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, ?)',
            [$old['name'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $old['role'], now()]
        );
        flash('success', ucfirst($old['role']) . ' account created for ' . $old['name'] . '.');
        redirect('admin_users.php');
    }
}

$users = q(
    "SELECT id, name, email, role, created_at FROM users
      ORDER BY CASE role WHEN 'admin' THEN 0 WHEN 'technician' THEN 1 ELSE 2 END, name"
)->fetchAll();

page_header('Users', 'users');
?>
<h1 class="section-title">Users</h1>
<p class="section-subtitle">Students sign up themselves. Create technician and admin accounts here.</p>

<div class="detail-grid">
    <section class="card">
        <h2 class="card-title">Create account</h2>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" value="<?= e($old['name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>
            </div>
            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role">
                    <?php foreach (ROLES as $r): ?>
                        <option value="<?= e($r) ?>"<?= $old['role'] === $r ? ' selected' : '' ?>><?= e(ucfirst($r)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="password">Temporary password</label>
                <input type="text" id="password" name="password" autocomplete="off" required>
                <small class="muted">8+ characters, 1 uppercase letter, 1 number. They can change it from their profile.</small>
            </div>
            <button class="btn btn-primary" type="submit">Create account</button>
        </form>
    </section>

    <section class="card">
        <h2 class="card-title"><?= count($users) ?> accounts</h2>
        <div class="table-container flat">
            <table class="report-table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= e($u['name']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><span class="role-pill role-<?= e($u['role']) ?>"><?= e($u['role']) ?></span></td>
                        <td><?= e(fmt_date($u['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php page_footer(); ?>
