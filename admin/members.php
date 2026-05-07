<?php

require_once dirname(__DIR__) . '/app/includes/auth.php';

require_admin('../login.php');

$editingId = (int) request_value('edit', 0);
$formData = array(
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'course_name' => '',
    'password' => '',
    'is_active' => '1',
);

if (is_post()) {
    if (!verify_csrf_token(request_value('csrf_token'))) {
        set_flash('error', 'Your session token expired. Please try again.');
        redirect('members.php' . ($editingId ? '?edit=' . $editingId : ''));
    }

    if (!db_ready()) {
        set_flash('error', 'The database is not connected yet.');
        redirect('members.php');
    }

    $action = request_value('action');

    if ($action === 'delete') {
        $memberId = (int) request_value('member_id');

        $loanCheck = db()->prepare('SELECT COUNT(*) FROM loans WHERE member_id = :member_id');
        $loanCheck->execute(array('member_id' => $memberId));
        $reservationCheck = db()->prepare('SELECT COUNT(*) FROM reservations WHERE member_id = :member_id');
        $reservationCheck->execute(array('member_id' => $memberId));

        if ((int) $loanCheck->fetchColumn() > 0 || (int) $reservationCheck->fetchColumn() > 0) {
            set_flash('error', 'This member has circulation history and cannot be deleted.');
            redirect('members.php');
        }

        $memberLookup = db()->prepare('SELECT user_id FROM members WHERE id = :member_id LIMIT 1');
        $memberLookup->execute(array('member_id' => $memberId));
        $userId = $memberLookup->fetchColumn();

        if (!$userId) {
            set_flash('error', 'The member record could not be found.');
            redirect('members.php');
        }

        try {
            db()->beginTransaction();
            $deleteMember = db()->prepare('DELETE FROM members WHERE id = :member_id');
            $deleteMember->execute(array('member_id' => $memberId));

            $deleteUser = db()->prepare('DELETE FROM users WHERE id = :user_id');
            $deleteUser->execute(array('user_id' => $userId));
            db()->commit();

            set_flash('success', 'Member deleted successfully.');
        } catch (PDOException $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            set_flash('error', 'The member could not be deleted.');
        }

        redirect('members.php');
    }

    $memberId = (int) request_value('member_id');
    $formData = array(
        'full_name' => request_value('full_name'),
        'email' => request_value('email'),
        'phone' => request_value('phone'),
        'course_name' => request_value('course_name'),
        'password' => request_value('password'),
        'is_active' => request_value('is_active', '1'),
    );

    remember_form_data($formData);

    $errors = array();

    if ($formData['full_name'] === '' || $formData['email'] === '') {
        $errors[] = 'Full name and email address are required.';
    }

    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if ($action === 'create' && strlen($formData['password']) < 8) {
        $errors[] = 'New member passwords must be at least 8 characters long.';
    }

    if ($action === 'update' && $formData['password'] !== '' && strlen($formData['password']) < 8) {
        $errors[] = 'Updated passwords must be at least 8 characters long.';
    }

    $emailCheck = db()->prepare('
        SELECT COUNT(*)
        FROM users u
        INNER JOIN members m ON m.user_id = u.id
        WHERE u.email = :email
        AND m.id <> :member_id
    ');
    $emailCheck->execute(array(
        'email' => $formData['email'],
        'member_id' => $memberId,
    ));

    if ((int) $emailCheck->fetchColumn() > 0) {
        $errors[] = 'That email address is already assigned to another member.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            set_flash('error', $error);
        }

        redirect('members.php' . ($action === 'update' ? '?edit=' . $memberId : ''));
    }

    try {
        db()->beginTransaction();

        if ($action === 'update' && $memberId > 0) {
            $lookup = db()->prepare('SELECT user_id FROM members WHERE id = :member_id LIMIT 1');
            $lookup->execute(array('member_id' => $memberId));
            $userId = $lookup->fetchColumn();

            if (!$userId) {
                throw new PDOException('Missing member user record.');
            }

            if ($formData['password'] !== '') {
                $userStatement = db()->prepare('
                    UPDATE users
                    SET email = :email, password_hash = :password_hash, is_active = :is_active
                    WHERE id = :user_id
                ');
                $userStatement->execute(array(
                    'email' => $formData['email'],
                    'password_hash' => password_hash($formData['password'], PASSWORD_DEFAULT),
                    'is_active' => (int) $formData['is_active'],
                    'user_id' => $userId,
                ));
            } else {
                $userStatement = db()->prepare('
                    UPDATE users
                    SET email = :email, is_active = :is_active
                    WHERE id = :user_id
                ');
                $userStatement->execute(array(
                    'email' => $formData['email'],
                    'is_active' => (int) $formData['is_active'],
                    'user_id' => $userId,
                ));
            }

            $memberStatement = db()->prepare('
                UPDATE members
                SET
                    full_name = :full_name,
                    phone = :phone,
                    course_name = :course_name,
                    is_active = :is_active
                WHERE id = :member_id
            ');
            $memberStatement->execute(array(
                'full_name' => $formData['full_name'],
                'phone' => $formData['phone'],
                'course_name' => $formData['course_name'],
                'is_active' => (int) $formData['is_active'],
                'member_id' => $memberId,
            ));

            set_flash('success', 'Member updated successfully.');
        } else {
            $userStatement = db()->prepare('
                INSERT INTO users (email, password_hash, role, is_active, created_at)
                VALUES (:email, :password_hash, "member", :is_active, NOW())
            ');
            $userStatement->execute(array(
                'email' => $formData['email'],
                'password_hash' => password_hash($formData['password'], PASSWORD_DEFAULT),
                'is_active' => (int) $formData['is_active'],
            ));

            $userId = db()->lastInsertId();
            $nextNumber = db()->query('SELECT LPAD(COALESCE(MAX(id), 0) + 1, 4, "0") FROM members')->fetchColumn();
            $membershipNumber = 'LIB-' . date('Y') . '-' . $nextNumber;

            $memberStatement = db()->prepare('
                INSERT INTO members (user_id, membership_number, full_name, phone, course_name, joined_on, is_active)
                VALUES (:user_id, :membership_number, :full_name, :phone, :course_name, CURDATE(), :is_active)
            ');
            $memberStatement->execute(array(
                'user_id' => $userId,
                'membership_number' => $membershipNumber,
                'full_name' => $formData['full_name'],
                'phone' => $formData['phone'],
                'course_name' => $formData['course_name'],
                'is_active' => (int) $formData['is_active'],
            ));

            set_flash('success', 'Member created successfully with membership number ' . $membershipNumber . '.');
        }

        db()->commit();
        clear_form_data();
    } catch (PDOException $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        set_flash('error', 'The member record could not be saved.');
        redirect('members.php' . ($action === 'update' ? '?edit=' . $memberId : ''));
    }

    redirect('members.php');
}

if (!empty($_SESSION['form_data'])) {
    $formData = array_merge($formData, $_SESSION['form_data']);
} elseif ($editingId > 0 && db_ready()) {
    $editStatement = db()->prepare('
        SELECT
            m.id,
            m.membership_number,
            m.full_name,
            m.phone,
            m.course_name,
            m.is_active,
            u.email
        FROM members m
        INNER JOIN users u ON u.id = m.user_id
        WHERE m.id = :member_id
        LIMIT 1
    ');
    $editStatement->execute(array('member_id' => $editingId));
    $editingMember = $editStatement->fetch();

    if ($editingMember) {
        $formData = array_merge($formData, $editingMember);
    }
}

$members = array();

if (db_ready()) {
    $members = db()->query('
        SELECT
            m.id,
            m.membership_number,
            m.full_name,
            m.phone,
            m.course_name,
            m.joined_on,
            m.is_active,
            u.email,
            (
                SELECT COUNT(*)
                FROM loans l
                WHERE l.member_id = m.id
                AND l.returned_at IS NULL
            ) AS active_loans,
            (
                SELECT COUNT(*)
                FROM reservations r
                WHERE r.member_id = m.id
                AND r.status IN ("pending", "ready_for_collection")
            ) AS active_reservations
        FROM members m
        INNER JOIN users u ON u.id = m.user_id
        ORDER BY m.full_name ASC
    ')->fetchAll();
}

$pageTitle = 'Members';
$basePath = '../';
$isAdminSection = true;

include dirname(__DIR__) . '/app/includes/header.php';
?>

<section class="grid grid-2">
    <article class="form-panel">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Member Directory</p>
                <h1><?php echo $editingId > 0 ? 'Edit member' : 'Create member'; ?></h1>
            </div>
            <?php if ($editingId > 0): ?>
                <a class="button button-ghost" href="members.php">Cancel edit</a>
            <?php endif; ?>
        </div>

        <form class="stack" method="post" action="members.php<?php echo $editingId > 0 ? '?edit=' . h($editingId) : ''; ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo $editingId > 0 ? 'update' : 'create'; ?>">
            <input type="hidden" name="member_id" value="<?php echo h($editingId); ?>">

            <div class="field">
                <label for="full_name">Full name</label>
                <input id="full_name" name="full_name" type="text" value="<?php echo h($formData['full_name']); ?>" required>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?php echo h($formData['email']); ?>" required>
                </div>
                <div class="field">
                    <label for="phone">Phone</label>
                    <input id="phone" name="phone" type="text" value="<?php echo h($formData['phone']); ?>">
                </div>
            </div>

            <div class="field">
                <label for="course_name">Course or department</label>
                <input id="course_name" name="course_name" type="text" value="<?php echo h($formData['course_name']); ?>">
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="password"><?php echo $editingId > 0 ? 'New password (leave blank to keep current)' : 'Password'; ?></label>
                    <input id="password" name="password" type="password" <?php echo $editingId > 0 ? '' : 'required'; ?>>
                </div>
                <div class="field">
                    <label for="is_active">Account status</label>
                    <select id="is_active" name="is_active">
                        <option value="1" <?php echo selected($formData['is_active'], '1'); ?>>Active</option>
                        <option value="0" <?php echo selected($formData['is_active'], '0'); ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <?php if ($editingId > 0 && !empty($formData['membership_number'])): ?>
                <p class="helper-text">Membership number: <strong><?php echo h($formData['membership_number']); ?></strong></p>
            <?php endif; ?>

            <button type="submit"><?php echo $editingId > 0 ? 'Update Member' : 'Create Member'; ?></button>
        </form>
    </article>

    <aside class="panel">
        <h2>Member handling</h2>
        <div class="stack">
            <div class="card">
                <h3>Linked records</h3>
                <p class="muted">Each member record maps to a user login and keeps borrowing data separated from account credentials.</p>
            </div>
            <div class="card">
                <h3>Controlled deletion</h3>
                <p class="muted">Members with existing loans or reservations are preserved to protect the integrity of circulation history.</p>
            </div>
        </div>
    </aside>
</section>

<section class="table-panel">
    <div class="section-heading">
        <h2>Members</h2>
        <p class="muted"><?php echo h(count($members)); ?> records</p>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Membership</th>
                    <th>Contact</th>
                    <th>Joined</th>
                    <th>Circulation</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td>
                            <strong><?php echo h($member['full_name']); ?></strong>
                            <div class="muted"><?php echo h($member['course_name']); ?></div>
                        </td>
                        <td><?php echo h($member['membership_number']); ?></td>
                        <td>
                            <div><?php echo h($member['email']); ?></div>
                            <div class="muted"><?php echo h($member['phone']); ?></div>
                        </td>
                        <td><?php echo h(format_date_display($member['joined_on'])); ?></td>
                        <td>
                            <div><?php echo h($member['active_loans']); ?> active loans</div>
                            <div class="muted"><?php echo h($member['active_reservations']); ?> active reservations</div>
                        </td>
                        <td>
                            <?php echo (int) $member['is_active'] === 1 ? render_status_badge('Active', 'active') : render_status_badge('Inactive', 'inactive'); ?>
                        </td>
                        <td class="table-actions">
                            <a class="button button-inline button-ghost" href="members.php?edit=<?php echo h($member['id']); ?>">Edit</a>
                            <form class="inline-form" method="post" action="members.php" data-confirm="Delete this member?">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="member_id" value="<?php echo h($member['id']); ?>">
                                <button class="button-inline button-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include dirname(__DIR__) . '/app/includes/footer.php'; ?>
