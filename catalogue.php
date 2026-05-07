<?php

require_once __DIR__ . '/app/includes/auth.php';

$search = request_value('q');
$categoryId = request_value('category_id');
$availability = request_value('availability');

$categories = array();
$books = array();

if (db_ready()) {
    $categories = db()->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();

    $conditions = array('b.is_active = 1');
    $params = array();

    if ($search !== '') {
        $conditions[] = '(
            b.title LIKE :search
            OR b.isbn LIKE :search
            OR EXISTS (
                SELECT 1
                FROM book_authors ba2
                INNER JOIN authors a2 ON a2.id = ba2.author_id
                WHERE ba2.book_id = b.id
                AND a2.name LIKE :author_search
            )
        )';
        $params['search'] = '%' . $search . '%';
        $params['author_search'] = '%' . $search . '%';
    }

    if ($categoryId !== '') {
        $conditions[] = 'b.category_id = :category_id';
        $params['category_id'] = (int) $categoryId;
    }

    $sql = '
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
            COALESCE(SUM(CASE WHEN cp.status = "available" THEN 1 ELSE 0 END), 0) AS available_copies
        FROM books b
        INNER JOIN categories c ON c.id = b.category_id
        INNER JOIN publishers p ON p.id = b.publisher_id
        INNER JOIN book_authors ba ON ba.book_id = b.id
        INNER JOIN authors a ON a.id = ba.author_id
        LEFT JOIN copies cp ON cp.book_id = b.id
        WHERE ' . implode(' AND ', $conditions) . '
        GROUP BY b.id, b.title, b.summary, b.isbn, b.published_year, c.name, p.name
    ';

    if ($availability === 'available') {
        $sql .= ' HAVING available_copies > 0';
    } elseif ($availability === 'unavailable') {
        $sql .= ' HAVING available_copies = 0';
    }

    $sql .= ' ORDER BY b.title ASC';

    $statement = db()->prepare($sql);
    $statement->execute($params);
    $books = $statement->fetchAll();
}

$pageTitle = 'Catalogue';

include __DIR__ . '/app/includes/header.php';
?>

<section class="panel">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Searchable Collection</p>
            <h1>Browse the library catalogue</h1>
        </div>
        <p class="muted">Use keyword, category, and availability filters to explore the collection.</p>
    </div>

    <form class="filter-form" method="get" action="catalogue.php">
        <div class="field">
            <label for="q">Search</label>
            <input id="q" name="q" type="text" value="<?php echo h($search); ?>" placeholder="Search title, ISBN or author">
        </div>
        <div class="field">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo h($category['id']); ?>" <?php echo selected($categoryId, $category['id']); ?>>
                        <?php echo h($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="availability">Availability</label>
            <select id="availability" name="availability">
                <option value="">All statuses</option>
                <option value="available" <?php echo selected($availability, 'available'); ?>>Available now</option>
                <option value="unavailable" <?php echo selected($availability, 'unavailable'); ?>>Currently unavailable</option>
            </select>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <div class="action-row">
                <button type="submit">Apply Filters</button>
                <a class="button button-ghost" href="catalogue.php">Reset</a>
            </div>
        </div>
    </form>
</section>

<section class="grid grid-3">
    <?php if (!$books): ?>
        <div class="empty-state">
            <h2>No matching titles</h2>
            <p>Try broadening the search or importing the seed data first.</p>
        </div>
    <?php else: ?>
        <?php foreach ($books as $book): ?>
            <article class="card">
                <div class="split">
                    <h3><a href="book.php?id=<?php echo h($book['id']); ?>"><?php echo h($book['title']); ?></a></h3>
                    <?php echo $book['available_copies'] > 0 ? render_status_badge('Available', 'available') : render_status_badge('On Loan', 'loaned'); ?>
                </div>
                <p class="muted"><?php echo h($book['authors']); ?></p>
                <p><?php echo h(substr($book['summary'], 0, 160)); ?>...</p>
                <ul class="meta-list">
                    <li><strong>Category:</strong> <?php echo h($book['category_name']); ?></li>
                    <li><strong>Publisher:</strong> <?php echo h($book['publisher_name']); ?></li>
                    <li><strong>ISBN:</strong> <?php echo h($book['isbn']); ?></li>
                    <li><strong>Available Copies:</strong> <?php echo h($book['available_copies']); ?> of <?php echo h($book['total_copies']); ?></li>
                </ul>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/app/includes/footer.php'; ?>
