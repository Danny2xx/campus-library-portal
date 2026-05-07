<?php

require_once __DIR__ . '/app/includes/auth.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'dashboard.php');
}

$email = '';

if (is_post()) {
    $email = request_value('email');
    $password = request_value('password');

    remember_form_data(array('email' => $email));

    if (!verify_csrf_token(request_value('csrf_token'))) {
        set_flash('error', 'Your session token expired. Please try again.');
        redirect('login.php');
    }

    if (!db_ready()) {
        set_flash('error', 'The database is not connected yet, so sign-in is unavailable.');
        redirect('login.php');
    }

    if ($email === '' || $password === '') {
        set_flash('error', 'Email and password are both required.');
        redirect('login.php');
    }

    $user = attempt_login($email, $password);

    if (!$user) {
        set_flash('error', 'The supplied credentials were not recognised.');
        redirect('login.php');
    }

    clear_form_data();
    login_user($user['id']);
    set_flash('success', 'Welcome back, ' . ($user['full_name'] ? $user['full_name'] : $user['email']) . '.');
    redirect($user['role'] === 'admin' ? 'admin/index.php' : 'dashboard.php');
}

$pageTitle = 'Sign In';

include __DIR__ . '/app/includes/header.php';
?>

<section class="grid grid-2">
    <article class="form-panel">
        <p class="eyebrow">Member Access</p>
        <h1>Sign in to your library account</h1>
        <p class="muted">Use your library credentials to access member services or the staff workspace.</p>

        <form class="stack" method="post" action="login.php">
            <?php echo csrf_field(); ?>
            <div class="field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" value="<?php echo h(old('email', $email)); ?>" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>
            <button type="submit">Sign In</button>
        </form>
    </article>

    <aside class="panel">
        <h2>Account access</h2>
        <div class="stack">
            <div class="card">
                <h3>Members</h3>
                <p class="muted">View current loans, manage reservations, and track previous borrowing history.</p>
            </div>
            <div class="card">
                <h3>Staff</h3>
                <p class="muted">Issue loans, record returns, manage inventory, and access joined report views.</p>
            </div>
            <div class="card">
                <h3>Need an account?</h3>
                <p class="muted">Create a fresh member account and the application will generate a membership number automatically.</p>
                <a class="button-link" href="register.php">Create Account</a>
            </div>
        </div>
    </aside>
</section>

<?php include __DIR__ . '/app/includes/footer.php'; ?>
