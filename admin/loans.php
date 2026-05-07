<?php

require_once dirname(__DIR__) . '/app/includes/auth.php';

require_admin('../login.php');

$formData = array(
    'member_id' => '',
    'copy_id' => '',
    'due_at' => date('Y-m-d', strtotime('+14 days')),
    'notes' => '',
);

if (is_post()) {
    if (!verify_csrf_token(request_value('csrf_token'))) {
        set_flash('error', 'Your session token expired. Please try again.');
        redirect('loans.php');
    }

    if (!db_ready()) {
        set_flash('error', 'The database is not connected yet.');
        redirect('loans.php');
    }

    $action = request_value('action');

    if ($action === 'return') {
        $loanId = (int) request_value('loan_id');
        $lookup = db()->prepare('
            SELECT
                l.copy_id,
                c.book_id
            FROM loans l
            INNER JOIN copies c ON c.id = l.copy_id
            WHERE l.id = :loan_id
            AND l.returned_at IS NULL
            LIMIT 1
        ');
        $lookup->execute(array('loan_id' => $loanId));
        $loanRecord = $lookup->fetch();

        if (!$loanRecord) {
            set_flash('error', 'That loan is not currently active.');
            redirect('loans.php');
        }

        try {
            db()->beginTransaction();

            $returnStatement = db()->prepare('UPDATE loans SET returned_at = NOW() WHERE id = :loan_id AND returned_at IS NULL');
            $returnStatement->execute(array('loan_id' => $loanId));

            $copyStatement = db()->prepare('UPDATE copies SET status = "available" WHERE id = :copy_id');
            $copyStatement->execute(array('copy_id' => $loanRecord['copy_id']));

            $reservationStatement = db()->prepare('
                UPDATE reservations
                SET
                    status = "ready_for_collection",
                    expires_at = DATE_ADD(NOW(), INTERVAL 3 DAY),
                    notes = CONCAT(IFNULL(notes, ""), " Copy became available after a return.")
                WHERE book_id = :book_id
                AND status = "pending"
                ORDER BY requested_at ASC
                LIMIT 1
            ');
            $reservationStatement->execute(array(
                'book_id' => $loanRecord['book_id'],
            ));

            db()->commit();
            set_flash('success', 'Book returned successfully.');
        } catch (PDOException $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            set_flash('error', 'The return could not be processed.');
        }

        redirect('loans.php');
    }

    if ($action === 'delete') {
        $loanId = (int) request_value('loan_id');
        $lookup = db()->prepare('SELECT copy_id, returned_at FROM loans WHERE id = :loan_id LIMIT 1');
        $lookup->execute(array('loan_id' => $loanId));
        $loan = $lookup->fetch();

        if (!$loan) {
            set_flash('error', 'The loan record could not be found.');
            redirect('loans.php');
        }

        try {
            db()->beginTransaction();

            if (!$loan['returned_at']) {
                $copyStatement = db()->prepare('UPDATE copies SET status = "available" WHERE id = :copy_id');
                $copyStatement->execute(array('copy_id' => $loan['copy_id']));
            }

            $deleteStatement = db()->prepare('DELETE FROM loans WHERE id = :loan_id');
            $deleteStatement->execute(array('loan_id' => $loanId));

            db()->commit();
            set_flash('success', 'Loan record deleted.');
        } catch (PDOException $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            set_flash('error', 'The loan could not be deleted.');
        }

        redirect('loans.php');
    }

    $formData = array(
        'member_id' => request_value('member_id'),
        'copy_id' => request_value('copy_id'),
        'due_at' => request_value('due_at', date('Y-m-d', strtotime('+14 days'))),
        'notes' => request_value('notes'),
    );

    remember_form_data($formData);

    $errors = array();

    if ($formData['member_id'] === '' || $formData['copy_id'] === '' || $formData['due_at'] === '') {
        $errors[] = 'Member, copy and due date are required.';
    }

    $memberCheck = db()->prepare('SELECT COUNT(*) FROM members WHERE id = :member_id AND is_active = 1');
    $memberCheck->execute(array('member_id' => (int) $formData['member_id']));

    if ((int) $memberCheck->fetchColumn() === 0) {
        $errors[] = 'Choose a valid active member.';
    }

    $copyCheck = db()->prepare('SELECT book_id, status FROM copies WHERE id = :copy_id LIMIT 1');
    $copyCheck->execute(array('copy_id' => (int) $formData['copy_id']));
    $copy = $copyCheck->fetch();

    if (!$copy || $copy['status'] !== 'available') {
        $errors[] = 'The selected copy is not currently available.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            set_flash('error', $error);
        }

        redirect('loans.php');
    }

    try {
        db()->beginTransaction();

        $loanStatement = db()->prepare('
            INSERT INTO loans (copy_id, member_id, loaned_at, due_at, returned_at, notes)
            VALUES (:copy_id, :member_id, NOW(), :due_at, NULL, :notes)
        ');
        $loanStatement->execute(array(
            'copy_id' => (int) $formData['copy_id'],
            'member_id' => (int) $formData['member_id'],
            'due_at' => $formData['due_at'],
            'notes' => $formData['notes'],
        ));

        $copyUpdate = db()->prepare('UPDATE copies SET status = "loaned" WHERE id = :copy_id');
        $copyUpdate->execute(array('copy_id' => (int) $formData['copy_id']));

        if ($copy) {
            $reservationUpdate = db()->prepare('
                UPDATE reservations
                SET
                    status = "fulfilled",
                    expires_at = NOW(),
                    notes = CONCAT(IFNULL(notes, ""), " Fulfilled during issue.")
                WHERE member_id = :member_id
                AND book_id = :book_id
                AND status IN ("pending", "ready_for_collection")
                ORDER BY requested_at ASC
                LIMIT 1
            ');
            $reservationUpdate->execute(array(
                'member_id' => (int) $formData['member_id'],
                'book_id' => $copy['book_id'],
            ));
        }

        db()->commit();
        clear_form_data();
        set_flash('success', 'Loan issued successfully.');
    } catch (PDOException $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        set_flash('error', 'The loan could not be created.');
    }

    redirect('loans.php');
}

if (!empty($_SESSION['form_data'])) {
    $formData = array_merge($formData, $_SESSION['form_data']);
}

$members = db_ready() ? db()->query('
    SELECT id, full_name, membership_number
    FROM members
    WHERE is_active = 1
    ORDER BY full_name ASC
')->fetchAll() : array();

$availableCopies = db_ready() ? db()->query('
    SELECT
        c.id,
        CONCAT(b.title, " (", c.accession_code, ")") AS copy_label
    FROM copies c
    INNER JOIN books b ON b.id = c.book_id
    WHERE c.status = "available"
    ORDER BY b.title ASC, c.accession_code ASC
')->fetchAll() : array();

$activeLoans = array();
$loanHistory = array();

if (db_ready()) {
    $activeLoans = db()->query('
        SELECT
            l.id,
            l.loaned_at,
            l.due_at,
            m.full_name,
            m.membership_number,
            b.title,
            c.accession_code
        FROM loans l
        INNER JOIN members m ON m.id = l.member_id
        INNER JOIN copies c ON c.id = l.copy_id
        INNER JOIN books b ON b.id = c.book_id
        WHERE l.returned_at IS NULL
        ORDER BY l.due_at ASC
    ')->fetchAll();

    $loanHistory = db()->query('
        SELECT
            l.id,
            l.loaned_at,
            l.due_at,
            l.returned_at,
            m.full_name,
            b.title
        FROM loans l
        INNER JOIN members m ON m.id = l.member_id
        INNER JOIN copies c ON c.id = l.copy_id
        INNER JOIN books b ON b.id = c.book_id
        ORDER BY l.loaned_at DESC
        LIMIT 12
    ')->fetchAll();
}

$pageTitle = 'Loans';
$basePath = '../';
$isAdminSection = true;

include dirname(__DIR__) . '/app/includes/header.php';
?>

<section class="grid grid-2">
    <article class="form-panel">
        <p class="eyebrow">Circulation Desk</p>
        <h1>Issue a new loan</h1>
        <form class="stack" method="post" action="loans.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create">

            <div class="field">
                <label for="member_id">Member</label>
                <select id="member_id" name="member_id" required>
                    <option value="">Choose a member</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?php echo h($member['id']); ?>" <?php echo selected($formData['member_id'], $member['id']); ?>>
                            <?php echo h($member['full_name'] . ' (' . $member['membership_number'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="copy_id">Available copy</label>
                <select id="copy_id" name="copy_id" required>
                    <option value="">Choose a copy</option>
                    <?php foreach ($availableCopies as $copy): ?>
                        <option value="<?php echo h($copy['id']); ?>" <?php echo selected($formData['copy_id'], $copy['id']); ?>>
                            <?php echo h($copy['copy_label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="due_at">Due date</label>
                <input id="due_at" name="due_at" type="date" value="<?php echo h($formData['due_at']); ?>" required>
            </div>

            <div class="field">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes"><?php echo h($formData['notes']); ?></textarea>
            </div>

            <button type="submit">Issue Loan</button>
        </form>
    </article>

    <aside class="panel">
        <h2>Workflow summary</h2>
        <div class="stack">
            <div class="card">
                <h3>Create</h3>
                <p class="muted">Issuing a loan inserts the transaction and updates the selected copy status to <code>loaned</code>.</p>
            </div>
            <div class="card">
                <h3>Update</h3>
                <p class="muted">Returning a book updates the existing loan by stamping a return date.</p>
            </div>
            <div class="card">
                <h3>Delete</h3>
                <p class="muted">Administrators can remove mistaken loan records and the copy status is repaired automatically.</p>
            </div>
        </div>
    </aside>
</section>

<section class="table-panel">
    <div class="section-heading">
        <h2>Active Loans</h2>
        <p class="muted"><?php echo h(count($activeLoans)); ?> active records</p>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Book</th>
                    <th>Copy</th>
                    <th>Loaned</th>
                    <th>Due</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activeLoans as $loan): ?>
                    <tr>
                        <td>
                            <strong><?php echo h($loan['full_name']); ?></strong>
                            <div class="muted"><?php echo h($loan['membership_number']); ?></div>
                        </td>
                        <td><?php echo h($loan['title']); ?></td>
                        <td><?php echo h($loan['accession_code']); ?></td>
                        <td><?php echo h(format_date_display($loan['loaned_at'])); ?></td>
                        <td>
                            <?php echo h(format_date_display($loan['due_at'])); ?>
                            <?php if (strtotime($loan['due_at']) < time()): ?>
                                <?php echo render_status_badge('Overdue', 'overdue'); ?>
                            <?php endif; ?>
                        </td>
                        <td class="table-actions">
                            <form class="inline-form" method="post" action="loans.php">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="return">
                                <input type="hidden" name="loan_id" value="<?php echo h($loan['id']); ?>">
                                <button class="button-inline button-secondary" type="submit">Return</button>
                            </form>
                            <form class="inline-form" method="post" action="loans.php" data-confirm="Delete this loan record?">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="loan_id" value="<?php echo h($loan['id']); ?>">
                                <button class="button-inline button-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="table-panel">
    <div class="section-heading">
        <h2>Recent Loan History</h2>
        <p class="muted">Latest twelve transactions</p>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Book</th>
                    <th>Loaned</th>
                    <th>Due</th>
                    <th>Returned</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($loanHistory as $loan): ?>
                    <tr>
                        <td><?php echo h($loan['full_name']); ?></td>
                        <td><?php echo h($loan['title']); ?></td>
                        <td><?php echo h(format_date_display($loan['loaned_at'])); ?></td>
                        <td><?php echo h(format_date_display($loan['due_at'])); ?></td>
                        <td>
                            <?php if ($loan['returned_at']): ?>
                                <?php echo h(format_date_display($loan['returned_at'])); ?>
                            <?php else: ?>
                                <?php echo render_status_badge('Active', 'active'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include dirname(__DIR__) . '/app/includes/footer.php'; ?>
