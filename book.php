<?php

require_once __DIR__ . '/app/includes/auth.php';

$bookId = (int) request_value('id', 0);

if ($bookId <= 0) {
    set_flash('error', 'Choose a book from the catalogue first.');
    redirect('catalogue.php');
}

if (is_post()) {
    if (!verify_csrf_token(request_value('csrf_token'))) {
        set_flash('error', 'Your session token was invalid. Please try again.');
        redirect('book.php?id=' . $bookId);
    }

    if (!is_logged_in()) {
        set_flash('error', 'Sign in to place a reservation.');
        redirect('login.php');
    }

    $user = current_user();

    if (!$user || empty($user['member_id'])) {
        set_flash('error', 'Only member accounts can place reservations.');
        redirect('book.php?id=' . $bookId);
    }

    if (!db_ready()) {
        set_flash('error', 'The database is not connected yet, so reservations are unavailable.');
        redirect('book.php?id=' . $bookId);
    }

    if (db_ready()) {
        $duplicateStatement = db()->prepare('
            SELECT COUNT(*)
            FROM reservations
            WHERE member_id = :member_id
            AND book_id = :book_id
            AND status IN ("pending", "ready_for_collection")
        ');
        $duplicateStatement->execute(array(
            'member_id' => $user['member_id'],
            'book_id' => $bookId,
        ));

        if ((int) $duplicateStatement->fetchColumn() > 0) {
            set_flash('info', 'You already have an active reservation for this title.');
        } else {
            $insert = db()->prepare('
                INSERT INTO reservations (member_id, book_id, status, requested_at, expires_at, notes)
                VALUES (:member_id, :book_id, "pending", NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), :notes)
            ');
            $insert->execute(array(
                'member_id' => $user['member_id'],
                'book_id' => $bookId,
                'notes' => 'Placed via public catalogue page.',
            ));

            set_flash('success', 'Reservation placed successfully. You can track it from your dashboard.');
        }
    }

    redirect('book.php?id=' . $bookId);
}

$book = null;
$queueCount = 0;
$relatedTitles = array();

if (db_ready()) {
    $statement = db()->prepare('
        SELECT
            b.id,
            b.title,
            b.summary,
            b.isbn,
            b.published_year,
            c.name AS category_name,
            p.name AS publisher_name,
            GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR ", ") AS authors,
            COUNT(DISTINCT cp.id) AS total_copies,
            COALESCE(SUM(CASE WHEN cp.status = "available" THEN 1 ELSE 0 END), 0) AS available_copies,
            COALESCE(SUM(CASE WHEN cp.status = "loaned" THEN 1 ELSE 0 END), 0) AS loaned_copies,
            COALESCE(SUM(CASE WHEN cp.status = "maintenance" THEN 1 ELSE 0 END), 0) AS maintenance_copies
        FROM books b
        INNER JOIN categories c ON c.id = b.category_id
        INNER JOIN publishers p ON p.id = b.publisher_id
        INNER JOIN book_authors ba ON ba.book_id = b.id
        INNER JOIN authors a ON a.id = ba.author_id
        LEFT JOIN copies cp ON cp.book_id = b.id
        WHERE b.id = :id AND b.is_active = 1
        GROUP BY b.id, b.title, b.summary, b.isbn, b.published_year, c.name, p.name
        LIMIT 1
    ');
    $statement->execute(array('id' => $bookId));
    $book = $statement->fetch();

    if ($book) {
        $queueStatement = db()->prepare('
            SELECT COUNT(*)
            FROM reservations
            WHERE book_id = :book_id
            AND status IN ("pending", "ready_for_collection")
        ');
        $queueStatement->execute(array('book_id' => $bookId));
        $queueCount = (int) $queueStatement->fetchColumn();

        $relatedStatement = db()->prepare('
            SELECT
                b.id,
                b.title,
                GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR ", ") AS authors
            FROM books b
            INNER JOIN book_authors ba ON ba.book_id = b.id
            INNER JOIN authors a ON a.id = ba.author_id
            WHERE b.category_id = (
                SELECT category_id
                FROM books
                WHERE id = :current_book
            )
            AND b.id <> :current_book
            AND b.is_active = 1
            GROUP BY b.id, b.title
            ORDER BY b.title ASC
            LIMIT 4
        ');
        $relatedStatement->execute(array('current_book' => $bookId));
        $relatedTitles = $relatedStatement->fetchAll();
    }
}

if (!$book) {
    set_flash('error', 'That book could not be found.');
    redirect('catalogue.php');
}

$pageTitle = $book['title'];

include __DIR__ . '/app/includes/header.php';
?>

<section class="detail-grid">
    <article class="panel">
        <p class="eyebrow"><?php echo h($book['category_name']); ?></p>
        <h1><?php echo h($book['title']); ?></h1>
        <p class="muted"><?php echo h($book['authors']); ?></p>
        <p><?php echo h($book['summary']); ?></p>

        <ul class="meta-list">
            <li><strong>Publisher:</strong> <?php echo h($book['publisher_name']); ?></li>
            <li><strong>Published:</strong> <?php echo h($book['published_year']); ?></li>
            <li><strong>ISBN:</strong> <?php echo h($book['isbn']); ?></li>
            <li><strong>Reservation Queue:</strong> <?php echo h($queueCount); ?></li>
        </ul>
    </article>

    <aside class="panel">
        <h2>Availability Snapshot</h2>
        <div class="stack">
            <div class="card">
                <div class="split">
                    <strong>Available copies</strong>
                    <?php echo render_status_badge((string) $book['available_copies'], 'available'); ?>
                </div>
            </div>
            <div class="card">
                <div class="split">
                    <strong>On loan</strong>
                    <?php echo render_status_badge((string) $book['loaned_copies'], 'loaned'); ?>
                </div>
            </div>
            <div class="card">
                <div class="split">
                    <strong>In maintenance</strong>
                    <?php echo render_status_badge((string) $book['maintenance_copies'], 'maintenance'); ?>
                </div>
            </div>
        </div>

        <div class="action-row">
            <?php if (is_logged_in() && !is_admin()): ?>
                <form method="post" action="book.php?id=<?php echo h($book['id']); ?>">
                    <?php echo csrf_field(); ?>
                    <button type="submit">Reserve This Book</button>
                </form>
            <?php else: ?>
                <a class="button-link" href="<?php echo is_logged_in() ? 'dashboard.php' : 'login.php'; ?>">
                    <?php echo is_logged_in() ? 'Open Dashboard' : 'Sign In to Reserve'; ?>
                </a>
            <?php endif; ?>
            <a class="button button-ghost" href="catalogue.php">Back to Catalogue</a>
        </div>
    </aside>
</section>

<section class="grid grid-2">
    <div class="panel">
        <h2>About this title</h2>
        <p class="muted">
            View publishing details, track availability, and explore similar titles before placing a reservation.
        </p>
    </div>

    <div class="panel">
        <h2>Related Titles</h2>
        <?php if (!$relatedTitles): ?>
            <p class="muted">More titles from this subject area will appear here as the collection grows.</p>
        <?php else: ?>
            <ul class="meta-list">
                <?php foreach ($relatedTitles as $related): ?>
                    <li>
                        <strong><a href="book.php?id=<?php echo h($related['id']); ?>"><?php echo h($related['title']); ?></a></strong>
                        <span class="muted"> by <?php echo h($related['authors']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/app/includes/footer.php'; ?>
