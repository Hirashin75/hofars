<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

if ($user = current_user()) {
    redirect(home_for($user['role']));
}

$mode = 'login';
$errors = [];
$old = ['email' => '', 'name' => '', 'signup_email' => ''];

if (is_post()) {
    csrf_check();

    if (isset($_POST['login'])) {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $old['email'] = $email;

        $lockedFor = ($_SESSION['login_lock_until'] ?? 0) - time();
        if ($lockedFor > 0) {
            $errors[] = "Too many failed attempts. Try again in {$lockedFor} seconds.";
        } elseif ($email === '' || $password === '') {
            $errors[] = 'Email and password are required.';
        } else {
            $row = q('SELECT id, password FROM users WHERE email = ?', [$email])->fetch();
            // Always run password_verify so response time doesn't reveal whether the email exists.
            $hash = $row['password'] ?? '$2y$10$usesomesillystringforsaltdummyhashvalueXXXXXXXXXXXXXXXXXX';
            if (password_verify($password, $hash) && $row) {
                login_user((int) $row['id']);
                redirect(home_for(current_user(true)['role']));
            }
            $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
            if ($_SESSION['login_fails'] >= 5) {
                $_SESSION['login_lock_until'] = time() + 60;
                $_SESSION['login_fails'] = 0;
            }
            $errors[] = 'Invalid email or password.';
        }
    }

    if (isset($_POST['signup'])) {
        $mode = 'signup';
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');
        $old['name'] = $name;
        $old['signup_email'] = $email;

        if ($name === '' || $email === '' || $password === '' || $confirm === '') {
            $errors[] = 'All fields are required.';
        } elseif (mb_strlen($name) > 100 || !preg_match("/^[\p{L}\s.'@\/-]+$/u", $name)) {
            $errors[] = 'Name may only contain letters, spaces and . \' - / @.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $errors[] = 'Please enter a valid email address.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        } elseif ($problem = password_problem($password)) {
            $errors[] = $problem;
        } elseif (q('SELECT 1 FROM users WHERE email = ?', [$email])->fetch()) {
            $errors[] = 'An account with that email already exists.';
        } else {
            // Public sign-up always creates a student. Staff accounts are created by an admin.
            q(
                'INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, \'student\', ?)',
                [$name, $email, password_hash($password, PASSWORD_DEFAULT), now()]
            );
            flash('success', 'Account created. You can log in now.');
            redirect('index.php');
        }
    }
}

page_header('Login');
$logo = is_file(__DIR__ . '/LOGO POLI.png') ? 'LOGO POLI.png' : null;
?>
<div class="auth-container">
    <div class="auth-box" id="loginBox"<?= $mode === 'signup' ? ' hidden' : '' ?>>
        <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="Logo" class="auth-logo"><?php endif; ?>
        <h1 class="auth-title">Welcome to HOFARS</h1>
        <p class="auth-sub">Report and track hostel facility damage.</p>
        <?php if ($mode === 'login'): foreach ($errors as $err): ?>
            <div class="alert alert-error"><?= e($err) ?></div>
        <?php endforeach; endif; ?>
        <form method="post" autocomplete="on">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="loginEmail">Email</label>
                <input type="email" id="loginEmail" name="email" value="<?= e($old['email']) ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="loginPassword">Password</label>
                <div class="password-wrap">
                    <input type="password" id="loginPassword" name="password" required>
                    <button type="button" class="password-toggle" data-target="loginPassword" aria-label="Show password">👁</button>
                </div>
            </div>
            <button type="submit" name="login" value="1" class="btn btn-primary">Login</button>
            <p class="auth-switch">No account yet? <a href="#" id="showSignup">Sign up</a></p>
        </form>
    </div>

    <div class="auth-box" id="signupBox"<?= $mode === 'signup' ? '' : ' hidden' ?>>
        <h1 class="auth-title">Create a student account</h1>
        <?php if ($mode === 'signup'): foreach ($errors as $err): ?>
            <div class="alert alert-error"><?= e($err) ?></div>
        <?php endforeach; endif; ?>
        <form method="post" id="signupForm">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="signupName">Full name</label>
                <input type="text" id="signupName" name="name" value="<?= e($old['name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="signupEmail">Email</label>
                <input type="email" id="signupEmail" name="email" value="<?= e($old['signup_email']) ?>" required>
            </div>
            <div class="form-group">
                <label for="signupPassword">Password</label>
                <div class="password-wrap">
                    <input type="password" id="signupPassword" name="password" required>
                    <button type="button" class="password-toggle" data-target="signupPassword" aria-label="Show password">👁</button>
                </div>
            </div>
            <div class="form-group">
                <label for="signupConfirm">Confirm password</label>
                <div class="password-wrap">
                    <input type="password" id="signupConfirm" name="confirm" required>
                    <button type="button" class="password-toggle" data-target="signupConfirm" aria-label="Show password">👁</button>
                </div>
            </div>
            <ul class="password-rules" id="passwordRules">
                <li data-rule="length">At least 8 characters</li>
                <li data-rule="upper">At least 1 uppercase letter</li>
                <li data-rule="number">At least 1 number</li>
            </ul>
            <button type="submit" name="signup" value="1" class="btn btn-primary">Sign up</button>
            <p class="auth-switch">Already registered? <a href="#" id="showLogin">Login</a></p>
        </form>
    </div>
</div>
<?php page_footer(); ?>
