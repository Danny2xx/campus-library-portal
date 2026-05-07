<?php

require_once dirname(__DIR__) . '/app/includes/auth.php';

require_admin('../login.php');

$overdueLoans = array();
$reservationQueue = array();
$categoryUsage = array();
$stockOverview = array();

if (db_ready()) {
    $overdueLoans = db()->query('
        SELECT
            l.id,
            m.full_name,
            m.membership_number,
            b.title,
            c.accession_code,
            l.due_at,
            DATEDIFF(CURDATE(), DATE(l.due_at)) AS days_overdue
        FROM loans l
        INNER JOIN members m ON m.id = l.member_id
        INNER JOIN copies c ON c.id = l.copy_id
        INNER JOIN books b ON b.id = c.book_id
        WHERE l.returned_at IS NULL
        AND DATE(l.due_at) < CURDATE()
        ORDER BY l.due_at ASC
    ')->fetchAll();

    $reservationQueue = db()->query('
        SELECT
            r.id,
            b.title,
            m.full_name,
            r.status,
            r.requested_at,
            DATEDIFF(CURDATE(), DATE(r.requested_at)) AS waiting_days
        FROM reservations r
        INNER JOIN books b ON b.id = r.book_id
        INNER JOIN members m ON m.id = r.member_id
        WHERE r.status IN ("pending", "ready_for_collection")
        ORDER BY r.requested_at ASC
    ')->fetchAll();

    $categoryUsage = db()->query('
        SELECT
            c.name AS category_name,
            COUNT(l.id) AS total_loans,
            COUNT(DISTINCT l.member_id) AS unique_borrowers,
            COUNT(DISTINCT b.id) AS titles_in_category
        FROM categories c
        LEFT JOIN books b ON b.category_id = c.id
        LEFT JOIN copies cp ON cp.book_id = b.id
        LEFT JOIN loans l ON l.copy_id = cp.id
        GROUP BY c.id, c.name
        ORDER BY total_loans DESC, c.name ASC
    ')->fetchAll();

    $stockOverview = db()->query('
        SELECT
            b.title,
            p.name AS publisher_name,
            GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR ", ") AS authors,
            COUNT(DISTINCT cp.id) AS total_copies,
            COALESCE(SUM(CASE WHEN cp.status = "available" THEN 1 ELSE 0 END), 0) AS available_copies,
            COALESCE(SUM(CASE WHEN cp.status = "loaned" THEN 1 ELSE 0 END), 0) AS loaned_copies,
            COALESCE(SUM(CASE WHEN cp.status = "maintenance" THEN 1 ELSE 0 END), 0) AS maintenance_copies
        FROM books b
        INNER JOIN publishers p ON p.id = b.publisher_id
        INNER JOIN book_authors ba ON ba.book_id = b.id
        INNER JOIN authors a ON a.id = ba.author_id
        LEFT JOIN copies cp ON cp.book_id = b.id
        GROUP BY b.id, b.title, p.name
        ORDER BY b.title ASC
    ')->fetchAll();
}

$pageTitle = 'Reports';
$basePath = '../';
$isAdminSection = true;

include dirname(__DIR__) . '/app/includes/header.php';
?>

<section class="panel">
    <p class="eyebrow">Analytical Views</p>
    <h1>Operational reports</h1>
    <p class="muted">
        Track overdue items, reservation demand, category activity, and collection availability from one operational view.
    </p>
</section>

<section class="grid grid-2">
    <div class="table-panel">
        <div class="section-heading">
            <h2>Overdue Loans</h2>
            <p class="muted"><?php echo h(count($overdueLoans)); ?> records</p>
        </div>
        <?php if (!$overdueLoans): ?>
            <div class="empty-state">
                <p>No overdue loans were found.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Book</th>
                            <th>Copy</th>
                            <th>Due</th>
                            <th>Days Overdue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overdueLoans as $loan): ?>
                            <tr>
                                <td>
                                    <strong><?php echo h($loan['full_name']); ?></strong>
                                    <div class="muted"><?php echo h($loan['membership_number']); ?></div>
                                </td>
                                <td><?php echo h($loan['title']); ?></td>
                                <td><?php echo h($loan['accession_code']); ?></td>
                                <td><?php echo h(format_date_display($loan['due_at'])); ?></td>
                                <td><?php echo render_status_badge((string) $loan['days_overdue'], 'overdue'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-panel">
        <div class="section-heading">
            <h2>Reservation Queue</h2>
            <p class="muted"><?php echo h(count($reservationQueue)); ?> active requests</p>
        </div>
        <?php if (!$reservationQueue): ?>
            <div class="empty-state">
                <p>No queued reservations were found.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Member</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th>Days Waiting</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservationQueue as $reservation): ?>
                            <tr>
                                <td><?php echo h($reservation['title']); ?></td>
                                <td><?php echo h($reservation['full_name']); ?></td>
                                <td><?php echo render_status_badge(ucwords(str_replace('_', ' ', $reservation['status'])), $reservation['status']); ?></td>
                                <td><?php echo h(format_date_display($reservation['requested_at'])); ?></td>
                                <td><?php echo h($reservation['waiting_days']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="grid grid-2">
    <div class="table-panel">
        <div class="section-heading">
            <h2>Loans by Category</h2>
            <p class="muted">Multi-table grouped query</p>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Total Loans</th>
                        <th>Unique Borrowers</th>
                        <th>Titles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categoryUsage as $category): ?>
                        <tr>
                            <td><?php echo h($category['category_name']); ?></td>
                            <td><?php echo h($category['total_loans']); ?></td>
                            <td><?php echo h($category['unique_borrowers']); ?></td>
                            <td><?php echo h($category['titles_in_category']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-panel">
        <div class="section-heading">
            <h2>Stock Overview by Title</h2>
            <p class="muted">Joined across publishers, authors and copy statuses</p>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Publisher</th>
                        <th>Authors</th>
                        <th>Available</th>
                        <th>Loaned</th>
                        <th>Maintenance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stockOverview as $row): ?>
                        <tr>
                            <td><?php echo h($row['title']); ?></td>
                            <td><?php echo h($row['publisher_name']); ?></td>
                            <td><?php echo h($row['authors']); ?></td>
                            <td><?php echo h($row['available_copies']); ?></td>
                            <td><?php echo h($row['loaned_copies']); ?></td>
                            <td><?php echo h($row['maintenance_copies']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php include dirname(__DIR__) . '/app/includes/footer.php'; ?>
