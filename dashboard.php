<?php

require_once __DIR__ . '/app/includes/auth.php';

require_login('login.php');

if (is_admin()) {
    redirect('admin/index.php');
}

$user = current_user();

if (!$user || empty($user['member_id'])) {
    set_flash('error', 'A linked member record is required to view the dashboard.');
    redirect('login.php');
}

if (is_post()) {
    if (!verify_csrf_token(request_value('csrf_token'))) {
        set_flash('error', 'Your session token expired. Please try the action again.');
        redirect('dashboard.php');
    }

    if (db_ready() && request_value('action') === 'cancel_reservation') {
        $reservationId = (int) request_value('reservation_id');
        $cancelStatement = db()->prepare('
            UPDATE reservations
            SET status = "cancelled"
            WHERE id = :id
            AND member_id = :member_id
            AND status IN ("pending", "ready_for_collection")
        ');
        $cancelStatement->execute(array(
            'id' => $reservationId,
            'member_id' => $user['member_id'],
        ));

        if ($cancelStatement->rowCount() > 0) {
            set_flash('success', 'Reservation cancelled successfully.');
        } else {
            set_flash('info', 'No matching active reservation could be cancelled.');
        }
    }

    redirect('dashboard.php');
}

$activeLoans = array();
$loanHistory = array();
$reservations = array();

if (db_ready()) {
    $activeLoanStatement = db()->prepare('
        SELECT
            l.id,
            l.loaned_at,
            l.due_at,
            c.accession_code,
            c.shelf_location,
            b.title,
            GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR ", ") AS authors
        FROM loans l
        INNER JOIN copies c ON c.id = l.copy_id
        INNER JOIN books b ON b.id = c.book_id
        INNER JOIN book_authors ba ON ba.book_id = b.id
        INNER JOIN authors a ON a.id = ba.author_id
        WHERE l.member_id = :member_id
        AND l.returned_at IS NULL
        GROUP BY l.id, l.loaned_at, l.due_at, c.accession_code, c.shelf_location, b.title
        ORDER BY l.due_at ASC
    ');
    $activeLoanStatement->execute(array('member_id' => $user['member_id']));
    $activeLoans = $activeLoanStatement->fetchAll();

    $historyStatement = db()->prepare('
        SELECT
            l.id,
            l.loaned_at,
            l.due_at,
            l.returned_at,
            b.title
        FROM loans l
        INNER JOIN copies c ON c.id = l.copy_id
        INNER JOIN books b ON b.id = c.book_id
        WHERE l.member_id = :member_id
        ORDER BY l.loaned_at DESC
        LIMIT 8
    ');
    $historyStatement->execute(array('member_id' => $user['member_id']));
    $loanHistory = $historyStatement->fetchAll();

    $reservationStatement = db()->prepare('
        SELECT
            r.id,
            r.status,
            r.requested_at,
            r.expires_at,
            b.title,
            GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR ", ") AS authors
        FROM reservations r
        INNER JOIN books b ON b.id = r.book_id
        INNER JOIN book_authors ba ON ba.book_id = b.id
        INNER JOIN authors a ON a.id = ba.author_id
        WHERE r.member_id = :member_id
        GROUP BY r.id, r.status, r.requested_at, r.expires_at, b.title
        ORDER BY r.requested_at DESC
    ');
    $reservationStatement->execute(array('member_id' => $user['member_id']));
    $reservations = $reservationStatement->fetchAll();
}

$pageTitle = 'My Dashboard';

include __DIR__ . '/app/includes/header.php';
?>

<section class="grid grid-2">
    <article class="panel">
        <p class="eyebrow">Member Profile</p>
        <h1><?php echo h($user['full_name']); ?></h1>
        <ul class="meta-list">
            <li><strong>Membership number:</strong> <?php echo h($user['membership_number']); ?></li>
            <li><strong>Email:</strong> <?php echo h($user['email']); ?></li>
            <li><strong>Phone:</strong> <?php echo h($user['phone'] ? $user['phone'] : 'Not provided'); ?></li>
            <li><strong>Course:</strong> <?php echo h($user['course_name'] ? $user['course_name'] : 'Not provided'); ?></li>
            <li><strong>Joined:</strong> <?php echo h(format_date_display($user['joined_on'])); ?></li>
        </ul>
    </article>

    <aside class="metric-grid">
        <article>
            <span class="metric-label">Active Loans</span>
            <span class="metric-value"><?php echo h(count($activeLoans)); ?></span>
        </article>
        <article>
            <span class="metric-label">Reservations</span>
            <span class="metric-value"><?php echo h(count($reservations)); ?></span>
        </article>
    </aside>
</section>

<section class="table-panel">
    <div class="section-heading">
        <h2>Current Loans</h2>
        <a href="catalogue.php">Browse more titles</a>
    </div>
    <?php if (!$activeLoans): ?>
        <div class="empty-state">
            <p>You do not have any active loans right now.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Authors</th>
                        <th>Copy Code</th>
                        <th>Shelf</th>
                        <th>Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeLoans as $loan): ?>
                        <tr>
                            <td><?php echo h($loan['title']); ?></td>
                            <td><?php echo h($loan['authors']); ?></td>
                            <td><?php echo h($loan['accession_code']); ?></td>
                            <td><?php echo h($loan['shelf_location']); ?></td>
                            <td>
                                <?php echo h(format_date_display($loan['due_at'])); ?>
                                <?php if (strtotime($loan['due_at']) < time()): ?>
                                    <?php echo render_status_badge('Overdue', 'overdue'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="grid grid-2">
    <div class="table-panel">
        <h2>Reservations</h2>
        <?php if (!$reservations): ?>
            <div class="empty-state">
                <p>No reservations have been made yet.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th>Expiry</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $reservation): ?>
                            <tr>
                                <td>
                                    <strong><?php echo h($reservation['title']); ?></strong>
                                    <div class="muted"><?php echo h($reservation['authors']); ?></div>
                                </td>
                                <td><?php echo render_status_badge(ucwords(str_replace('_', ' ', $reservation['status'])), $reservation['status']); ?></td>
                                <td><?php echo h(format_date_display($reservation['requested_at'])); ?></td>
                                <td><?php echo h(format_date_display($reservation['expires_at'])); ?></td>
                                <td>
                                    <?php if (in_array($reservation['status'], array('pending', 'ready_for_collection'), true)): ?>
                                        <form class="inline-form" method="post" action="dashboard.php" data-confirm="Cancel this reservation?">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="cancel_reservation">
                                            <input type="hidden" name="reservation_id" value="<?php echo h($reservation['id']); ?>">
                                            <button class="button-inline button-danger" type="submit">Cancel</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted">No action</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-panel">
        <h2>Loan History</h2>
        <?php if (!$loanHistory): ?>
            <div class="empty-state">
                <p>Your borrowing history will appear here once you have returned books.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Loaned</th>
                            <th>Due</th>
                            <th>Returned</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loanHistory as $history): ?>
                            <tr>
                                <td><?php echo h($history['title']); ?></td>
                                <td><?php echo h(format_date_display($history['loaned_at'])); ?></td>
                                <td><?php echo h(format_date_display($history['due_at'])); ?></td>
                                <td>
                                    <?php if ($history['returned_at']): ?>
                                        <?php echo h(format_date_display($history['returned_at'])); ?>
                                    <?php else: ?>
                                        <?php echo render_status_badge('Active', 'active'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/app/includes/footer.php'; ?>
