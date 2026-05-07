<?php

require_once __DIR__ . '/app/includes/auth.php';

$stats = array(
    'book_count' => 0,
    'member_count' => 0,
    'active_loans' => 0,
    'available_copies' => 0,
);

$featuredBooks = array();
$recentReservations = array();

if (db_ready()) {
    $stats = db()->query('
        SELECT
            (SELECT COUNT(*) FROM books WHERE is_active = 1) AS book_count,
            (SELECT COUNT(*) FROM members WHERE is_active = 1) AS member_count,
            (SELECT COUNT(*) FROM loans WHERE returned_at IS NULL) AS active_loans,
            (SELECT COUNT(*) FROM copies WHERE status = "available") AS available_copies
    ')->fetch();

    $featuredBooks = db()->query('
        SELECT
            b.id,
            b.title,
            b.summary,
            b.published_year,
            c.name AS category_name,
            p.name AS publisher_name,
            GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR ", ") AS authors,
            COALESCE(SUM(CASE WHEN cp.status = "available" THEN 1 ELSE 0 END), 0) AS available_copies
        FROM books b
        INNER JOIN categories c ON c.id = b.category_id
        INNER JOIN publishers p ON p.id = b.publisher_id
        INNER JOIN book_authors ba ON ba.book_id = b.id
        INNER JOIN authors a ON a.id = ba.author_id
        LEFT JOIN copies cp ON cp.book_id = b.id
        WHERE b.is_active = 1 AND b.featured = 1
        GROUP BY b.id, b.title, b.summary, b.published_year, c.name, p.name
        ORDER BY b.title ASC
        LIMIT 6
    ')->fetchAll();

    $recentReservations = db()->query('
        SELECT
            r.requested_at,
            r.status,
            b.title,
            m.full_name
        FROM reservations r
        INNER JOIN books b ON b.id = r.book_id
        INNER JOIN members m ON m.id = r.member_id
        ORDER BY r.requested_at DESC
        LIMIT 4
    ')->fetchAll();
}

$pageTitle = 'Home';

include __DIR__ . '/app/includes/header.php';
?>

<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">Library Services</p>
        <h1>Discover, reserve, and manage the collection through one modern library experience.</h1>
        <p>
            Search the catalogue, check live availability, manage reservations, and keep day-to-day
            operations moving through a calm, responsive interface for members and staff.
        </p>
        <div class="action-row">
            <a class="button-link" href="catalogue.php">Browse Catalogue</a>
            <?php if (is_logged_in()): ?>
                <a class="button button-secondary" href="<?php echo is_admin() ? 'admin/index.php' : 'dashboard.php'; ?>">
                    Open Dashboard
                </a>
            <?php else: ?>
                <a class="button button-ghost" href="register.php">Create Member Account</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="hero-art" aria-hidden="true"></div>
</section>

<section class="metric-grid">
    <article>
        <span class="metric-label">Active Titles</span>
        <span class="metric-value"><?php echo h($stats['book_count']); ?></span>
    </article>
    <article>
        <span class="metric-label">Library Members</span>
        <span class="metric-value"><?php echo h($stats['member_count']); ?></span>
    </article>
    <article>
        <span class="metric-label">Current Loans</span>
        <span class="metric-value"><?php echo h($stats['active_loans']); ?></span>
    </article>
    <article>
        <span class="metric-label">Available Copies</span>
        <span class="metric-value"><?php echo h($stats['available_copies']); ?></span>
    </article>
</section>

<section class="grid grid-2">
    <div class="panel">
        <div class="section-heading">
            <h2>Featured Collection</h2>
            <a href="catalogue.php">View all titles</a>
        </div>

        <?php if (!$featuredBooks): ?>
            <div class="empty-state">
                <p>No titles are available yet.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-2">
                <?php foreach ($featuredBooks as $book): ?>
                    <article class="card">
                        <h3><a href="book.php?id=<?php echo h($book['id']); ?>"><?php echo h($book['title']); ?></a></h3>
                        <p class="muted"><?php echo h($book['authors']); ?></p>
                        <p><?php echo h(substr($book['summary'], 0, 150)); ?>...</p>
                        <ul class="meta-list">
                            <li><strong>Category:</strong> <?php echo h($book['category_name']); ?></li>
                            <li><strong>Publisher:</strong> <?php echo h($book['publisher_name']); ?></li>
                            <li><strong>Available Copies:</strong> <?php echo h($book['available_copies']); ?></li>
                        </ul>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="section-heading">
            <h2>What You Can Do</h2>
        </div>
        <div class="stack">
            <div class="card">
                <h3>Search With Precision</h3>
                <p class="muted">Browse the collection by title, author, category, or availability to find the right book quickly.</p>
            </div>
            <div class="card">
                <h3>Manage Member Activity</h3>
                <p class="muted">Members can create accounts, track active loans, and reserve titles from any screen size.</p>
            </div>
            <div class="card">
                <h3>Keep Operations Moving</h3>
                <p class="muted">Staff can manage catalogue records, circulation, inventory, and reservation queues from one workspace.</p>
            </div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="section-heading">
        <h2>Recent Reservation Activity</h2>
        <p class="muted">A live view of recent requests moving through the collection.</p>
    </div>
    <?php if (!$recentReservations): ?>
        <div class="empty-state">
            <p>No reservation data is available yet.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Book</th>
                        <th>Status</th>
                        <th>Requested</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentReservations as $reservation): ?>
                        <tr>
                            <td><?php echo h($reservation['full_name']); ?></td>
                            <td><?php echo h($reservation['title']); ?></td>
                            <td><?php echo render_status_badge(ucwords(str_replace('_', ' ', $reservation['status'])), $reservation['status']); ?></td>
                            <td><?php echo h(format_date_display($reservation['requested_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/app/includes/footer.php'; ?>
