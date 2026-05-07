<?php

require_once dirname(__DIR__) . '/app/includes/auth.php';

require_admin('../login.php');

$stats = array(
    'book_count' => 0,
    'copy_count' => 0,
    'member_count' => 0,
    'active_loans' => 0,
    'pending_reservations' => 0,
);

$lowStockBooks = array();
$recentLoans = array();

if (db_ready()) {
    $stats = db()->query('
        SELECT
            (SELECT COUNT(*) FROM books WHERE is_active = 1) AS book_count,
            (SELECT COUNT(*) FROM copies) AS copy_count,
            (SELECT COUNT(*) FROM members WHERE is_active = 1) AS member_count,
            (SELECT COUNT(*) FROM loans WHERE returned_at IS NULL) AS active_loans,
            (SELECT COUNT(*) FROM reservations WHERE status IN ("pending", "ready_for_collection")) AS pending_reservations
    ')->fetch();

    $lowStockBooks = db()->query('
        SELECT
            b.id,
            b.title,
            GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR ", ") AS authors,
            COUNT(DISTINCT cp.id) AS total_copies,
            COALESCE(SUM(CASE WHEN cp.status = "available" THEN 1 ELSE 0 END), 0) AS available_copies
        FROM books b
        INNER JOIN book_authors ba ON ba.book_id = b.id
        INNER JOIN authors a ON a.id = ba.author_id
        LEFT JOIN copies cp ON cp.book_id = b.id
        WHERE b.is_active = 1
        GROUP BY b.id, b.title
        HAVING available_copies <= 1
        ORDER BY available_copies ASC, b.title ASC
        LIMIT 6
    ')->fetchAll();

    $recentLoans = db()->query('
        SELECT
            l.id,
            l.loaned_at,
            l.due_at,
            l.returned_at,
            m.full_name,
            b.title,
            c.accession_code
        FROM loans l
        INNER JOIN members m ON m.id = l.member_id
        INNER JOIN copies c ON c.id = l.copy_id
        INNER JOIN books b ON b.id = c.book_id
        ORDER BY l.loaned_at DESC
        LIMIT 8
    ')->fetchAll();
}

$pageTitle = 'Staff Overview';
$basePath = '../';
$isAdminSection = true;

include dirname(__DIR__) . '/app/includes/header.php';
?>

<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">Staff Workspace</p>
        <h1>Keep the library running from one connected workspace.</h1>
        <p>
            Manage catalogue records, inventory, member services, circulation, and reporting through
            one clear operational dashboard.
        </p>
        <div class="action-row">
            <a class="button-link" href="books.php">Manage Catalogue</a>
            <a class="button button-secondary" href="loans.php">Issue or Return Loans</a>
        </div>
    </div>
    <div class="hero-art" aria-hidden="true"></div>
</section>

<section class="metric-grid">
    <article>
        <span class="metric-label">Titles</span>
        <span class="metric-value"><?php echo h($stats['book_count']); ?></span>
    </article>
    <article>
        <span class="metric-label">Physical Copies</span>
        <span class="metric-value"><?php echo h($stats['copy_count']); ?></span>
    </article>
    <article>
        <span class="metric-label">Members</span>
        <span class="metric-value"><?php echo h($stats['member_count']); ?></span>
    </article>
    <article>
        <span class="metric-label">Active Loans</span>
        <span class="metric-value"><?php echo h($stats['active_loans']); ?></span>
    </article>
    <article>
        <span class="metric-label">Pending Reservations</span>
        <span class="metric-value"><?php echo h($stats['pending_reservations']); ?></span>
    </article>
</section>

<section class="grid grid-2">
    <div class="table-panel">
        <div class="section-heading">
            <h2>Low Stock Watchlist</h2>
            <a href="inventory.php">Open inventory</a>
        </div>
        <?php if (!$lowStockBooks): ?>
            <div class="empty-state">
                <p>No low-stock titles were identified.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Authors</th>
                            <th>Available</th>
                            <th>Total Copies</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockBooks as $book): ?>
                            <tr>
                                <td><?php echo h($book['title']); ?></td>
                                <td><?php echo h($book['authors']); ?></td>
                                <td><?php echo render_status_badge((string) $book['available_copies'], $book['available_copies'] > 0 ? 'warning' : 'danger'); ?></td>
                                <td><?php echo h($book['total_copies']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-panel">
        <div class="section-heading">
            <h2>Recent Circulation Activity</h2>
            <a href="reports.php">Open reports</a>
        </div>
        <?php if (!$recentLoans): ?>
            <div class="empty-state">
                <p>Loan transactions will appear here once the database has activity.</p>
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
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLoans as $loan): ?>
                            <tr>
                                <td><?php echo h($loan['full_name']); ?></td>
                                <td><?php echo h($loan['title']); ?></td>
                                <td><?php echo h($loan['accession_code']); ?></td>
                                <td><?php echo h(format_date_display($loan['due_at'])); ?></td>
                                <td>
                                    <?php if ($loan['returned_at']): ?>
                                        <?php echo render_status_badge('Returned', 'returned'); ?>
                                    <?php elseif (strtotime($loan['due_at']) < time()): ?>
                                        <?php echo render_status_badge('Overdue', 'overdue'); ?>
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

<?php include dirname(__DIR__) . '/app/includes/footer.php'; ?>
