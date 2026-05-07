<?php

require_once __DIR__ . '/app/includes/auth.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'dashboard.php');
}

$formData = array(
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'course_name' => '',
);

if (is_post()) {
    $formData = array(
        'full_name' => request_value('full_name'),
        'email' => request_value('email'),
        'phone' => request_value('phone'),
        'course_name' => request_value('course_name'),
    );

    $password = request_value('password');
    $confirmPassword = request_value('confirm_password');
    remember_form_data($formData);

    $errors = array();

    if (!verify_csrf_token(request_value('csrf_token'))) {
        $errors[] = 'Your session token expired. Please submit the form again.';
    }

    if (!db_ready()) {
        $errors[] = 'The database is not connected yet, so registration cannot be completed.';
    }

    if ($formData['full_name'] === '' || $formData['email'] === '' || $password === '') {
        $errors[] = 'Full name, email address and password are required.';
    }

    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Choose a password with at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!$errors && find_user_by_email($formData['email'])) {
        $errors[] = 'That email address is already registered.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            set_flash('error', $error);
        }

        redirect('register.php');
    }

    try {
        db()->beginTransaction();

        $userInsert = db()->prepare('
            INSERT INTO users (email, password_hash, role, is_active, created_at)
            VALUES (:email, :password_hash, "member", 1, NOW())
        ');
        $userInsert->execute(array(
            'email' => $formData['email'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ));

        $userId = db()->lastInsertId();

        $nextMemberNumber = db()->query('SELECT LPAD(COALESCE(MAX(id), 0) + 1, 4, "0") FROM members')->fetchColumn();
        $membershipNumber = 'LIB-' . date('Y') . '-' . $nextMemberNumber;

        $memberInsert = db()->prepare('
            INSERT INTO members (user_id, membership_number, full_name, phone, course_name, joined_on, is_active)
            VALUES (:user_id, :membership_number, :full_name, :phone, :course_name, CURDATE(), 1)
        ');
        $memberInsert->execute(array(
            'user_id' => $userId,
            'membership_number' => $membershipNumber,
            'full_name' => $formData['full_name'],
            'phone' => $formData['phone'],
            'course_name' => $formData['course_name'],
        ));

        db()->commit();

        clear_form_data();
        login_user($userId);
        set_flash('success', 'Welcome to the library. Your membership number is ' . $membershipNumber . '.');
        redirect('dashboard.php');
    } catch (PDOException $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        set_flash('error', 'The account could not be created right now. Please try again.');
        redirect('register.php');
    }
}

$pageTitle = 'Register';

include __DIR__ . '/app/includes/header.php';
?>

<section class="grid grid-2">
    <article class="form-panel">
        <p class="eyebrow">Membership Registration</p>
        <h1>Create your library account</h1>
        <p class="muted">Register as a member to reserve books, view loans, and manage your personal borrowing activity.</p>

        <form class="stack" method="post" action="register.php">
            <?php echo csrf_field(); ?>
            <div class="field">
                <label for="full_name">Full name</label>
                <input id="full_name" name="full_name" type="text" value="<?php echo h(old('full_name', $formData['full_name'])); ?>" required>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="<?php echo h(old('email', $formData['email'])); ?>" required>
                </div>
                <div class="field">
                    <label for="phone">Phone number</label>
                    <input id="phone" name="phone" type="text" value="<?php echo h(old('phone', $formData['phone'])); ?>">
                </div>
            </div>
            <div class="field">
                <label for="course_name">Course or department</label>
                <input id="course_name" name="course_name" type="text" value="<?php echo h(old('course_name', $formData['course_name'])); ?>" placeholder="e.g. Computer Science">
            </div>
            <div class="form-grid">
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required>
                </div>
                <div class="field">
                    <label for="confirm_password">Confirm password</label>
                    <input id="confirm_password" name="confirm_password" type="password" required>
                </div>
            </div>
            <button type="submit">Create Member Account</button>
        </form>
    </article>

    <aside class="panel">
        <h2>What happens next</h2>
        <div class="stack">
            <div class="card">
                <h3>Automatic membership number</h3>
                <p class="muted">The system creates a unique membership reference for every new member record.</p>
            </div>
            <div class="card">
                <h3>Responsive account area</h3>
                <p class="muted">Once signed in, members can access loans, reservation statuses, and history from any screen size.</p>
            </div>
            <div class="card">
                <h3>Already registered?</h3>
                <a class="button-link" href="login.php">Sign In Instead</a>
            </div>
        </div>
    </aside>
</section>

<?php include __DIR__ . '/app/includes/footer.php'; ?>
