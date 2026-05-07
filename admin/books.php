<?php

require_once dirname(__DIR__) . '/app/includes/auth.php';

require_admin('../login.php');

$editingId = (int) request_value('edit', 0);
$selectedAuthors = array();
$formData = array(
    'title' => '',
    'isbn' => '',
    'category_id' => '',
    'publisher_id' => '',
    'published_year' => '',
    'summary' => '',
    'featured' => '1',
    'is_active' => '1',
);

if (is_post()) {
    if (!verify_csrf_token(request_value('csrf_token'))) {
        set_flash('error', 'Your session token expired. Please try again.');
        redirect('books.php' . ($editingId ? '?edit=' . $editingId : ''));
    }

    if (!db_ready()) {
        set_flash('error', 'The database is not connected yet.');
        redirect('books.php');
    }

    $action = request_value('action');

    if ($action === 'delete') {
        $bookId = (int) request_value('book_id');

        $copyCountStatement = db()->prepare('SELECT COUNT(*) FROM copies WHERE book_id = :book_id');
        $copyCountStatement->execute(array('book_id' => $bookId));
        $copyCount = (int) $copyCountStatement->fetchColumn();

        $reservationCountStatement = db()->prepare('SELECT COUNT(*) FROM reservations WHERE book_id = :book_id');
        $reservationCountStatement->execute(array('book_id' => $bookId));
        $reservationCount = (int) $reservationCountStatement->fetchColumn();

        if ($copyCount > 0 || $reservationCount > 0) {
            set_flash('error', 'Delete related copies and reservation history before removing this title.');
            redirect('books.php');
        }

        try {
            db()->beginTransaction();

            $authorDelete = db()->prepare('DELETE FROM book_authors WHERE book_id = :book_id');
            $authorDelete->execute(array('book_id' => $bookId));

            $bookDelete = db()->prepare('DELETE FROM books WHERE id = :book_id');
            $bookDelete->execute(array('book_id' => $bookId));

            db()->commit();
            set_flash('success', 'Book deleted successfully.');
        } catch (PDOException $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            set_flash('error', 'The book could not be deleted.');
        }

        redirect('books.php');
    }

    $bookId = (int) request_value('book_id');
    $selectedAuthors = isset($_POST['author_ids']) ? array_map('intval', (array) $_POST['author_ids']) : array();

    $formData = array(
        'title' => request_value('title'),
        'isbn' => request_value('isbn'),
        'category_id' => request_value('category_id'),
        'publisher_id' => request_value('publisher_id'),
        'published_year' => request_value('published_year'),
        'summary' => request_value('summary'),
        'featured' => request_value('featured', '0'),
        'is_active' => request_value('is_active', '0'),
        'author_ids' => $selectedAuthors,
    );

    remember_form_data($formData);

    $errors = array();

    if ($formData['title'] === '' || $formData['isbn'] === '' || $formData['category_id'] === '' || $formData['publisher_id'] === '') {
        $errors[] = 'Title, ISBN, category and publisher are required.';
    }

    if (!$selectedAuthors) {
        $errors[] = 'Select at least one author.';
    }

    if ($formData['published_year'] !== '' && !preg_match('/^[0-9]{4}$/', $formData['published_year'])) {
        $errors[] = 'Published year must be in YYYY format.';
    }

    $duplicateStatement = db()->prepare('
        SELECT COUNT(*)
        FROM books
        WHERE isbn = :isbn
        AND id <> :book_id
    ');
    $duplicateStatement->execute(array(
        'isbn' => $formData['isbn'],
        'book_id' => $bookId,
    ));

    if ((int) $duplicateStatement->fetchColumn() > 0) {
        $errors[] = 'A book with that ISBN already exists.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            set_flash('error', $error);
        }

        redirect('books.php' . ($action === 'update' ? '?edit=' . $bookId : ''));
    }

    try {
        db()->beginTransaction();

        if ($action === 'update' && $bookId > 0) {
            $bookStatement = db()->prepare('
                UPDATE books
                SET
                    title = :title,
                    isbn = :isbn,
                    category_id = :category_id,
                    publisher_id = :publisher_id,
                    published_year = :published_year,
                    summary = :summary,
                    featured = :featured,
                    is_active = :is_active
                WHERE id = :book_id
            ');
            $bookStatement->execute(array(
                'title' => $formData['title'],
                'isbn' => $formData['isbn'],
                'category_id' => (int) $formData['category_id'],
                'publisher_id' => (int) $formData['publisher_id'],
                'published_year' => $formData['published_year'] !== '' ? (int) $formData['published_year'] : null,
                'summary' => $formData['summary'],
                'featured' => (int) $formData['featured'],
                'is_active' => (int) $formData['is_active'],
                'book_id' => $bookId,
            ));

            $authorReset = db()->prepare('DELETE FROM book_authors WHERE book_id = :book_id');
            $authorReset->execute(array('book_id' => $bookId));
        } else {
            $bookStatement = db()->prepare('
                INSERT INTO books (title, isbn, category_id, publisher_id, published_year, summary, featured, is_active, created_at)
                VALUES (:title, :isbn, :category_id, :publisher_id, :published_year, :summary, :featured, :is_active, NOW())
            ');
            $bookStatement->execute(array(
                'title' => $formData['title'],
                'isbn' => $formData['isbn'],
                'category_id' => (int) $formData['category_id'],
                'publisher_id' => (int) $formData['publisher_id'],
                'published_year' => $formData['published_year'] !== '' ? (int) $formData['published_year'] : null,
                'summary' => $formData['summary'],
                'featured' => (int) $formData['featured'],
                'is_active' => (int) $formData['is_active'],
            ));

            $bookId = db()->lastInsertId();
        }

        $authorInsert = db()->prepare('INSERT INTO book_authors (book_id, author_id) VALUES (:book_id, :author_id)');

        foreach ($selectedAuthors as $authorId) {
            $authorInsert->execute(array(
                'book_id' => $bookId,
                'author_id' => $authorId,
            ));
        }

        db()->commit();

        clear_form_data();
        set_flash('success', $action === 'update' ? 'Book updated successfully.' : 'Book created successfully.');
        redirect('books.php');
    } catch (PDOException $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        set_flash('error', 'The book record could not be saved.');
        redirect('books.php' . ($action === 'update' ? '?edit=' . $bookId : ''));
    }
}

if (!empty($_SESSION['form_data'])) {
    $formData = array_merge($formData, $_SESSION['form_data']);
    $selectedAuthors = isset($_SESSION['form_data']['author_ids']) ? array_map('intval', (array) $_SESSION['form_data']['author_ids']) : array();
} elseif ($editingId > 0 && db_ready()) {
    $editStatement = db()->prepare('
        SELECT
            id,
            title,
            isbn,
            category_id,
            publisher_id,
            published_year,
            summary,
            featured,
            is_active
        FROM books
        WHERE id = :book_id
        LIMIT 1
    ');
    $editStatement->execute(array('book_id' => $editingId));
    $editingBook = $editStatement->fetch();

    if ($editingBook) {
        $formData = array_merge($formData, $editingBook);
        $authorStatement = db()->prepare('SELECT author_id FROM book_authors WHERE book_id = :book_id');
        $authorStatement->execute(array('book_id' => $editingId));
        $selectedAuthors = array_map('intval', $authorStatement->fetchAll(PDO::FETCH_COLUMN));
    } else {
        set_flash('error', 'The selected book could not be loaded.');
        redirect('books.php');
    }
}

$categories = db_ready() ? db()->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll() : array();
$publishers = db_ready() ? db()->query('SELECT id, name FROM publishers ORDER BY name ASC')->fetchAll() : array();
$authors = db_ready() ? db()->query('SELECT id, name FROM authors ORDER BY name ASC')->fetchAll() : array();

$books = array();

if (db_ready()) {
    $books = db()->query('
        SELECT
            b.id,
            b.title,
            b.isbn,
            b.featured,
            b.is_active,
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
        GROUP BY b.id, b.title, b.isbn, b.featured, b.is_active, c.name, p.name
        ORDER BY b.title ASC
    ')->fetchAll();
}

$pageTitle = 'Books';
$basePath = '../';
$isAdminSection = true;

include dirname(__DIR__) . '/app/includes/header.php';
?>

<section class="grid grid-2">
    <article class="form-panel">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Title Management</p>
                <h1><?php echo $editingId > 0 ? 'Edit book' : 'Add new book'; ?></h1>
            </div>
            <?php if ($editingId > 0): ?>
                <a class="button button-ghost" href="books.php">Create new instead</a>
            <?php endif; ?>
        </div>

        <form class="stack" method="post" action="books.php<?php echo $editingId > 0 ? '?edit=' . h($editingId) : ''; ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo $editingId > 0 ? 'update' : 'create'; ?>">
            <input type="hidden" name="book_id" value="<?php echo h($editingId); ?>">

            <div class="field">
                <label for="title">Title</label>
                <input id="title" name="title" type="text" value="<?php echo h($formData['title']); ?>" required>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="isbn">ISBN</label>
                    <input id="isbn" name="isbn" type="text" value="<?php echo h($formData['isbn']); ?>" required>
                </div>
                <div class="field">
                    <label for="published_year">Published year</label>
                    <input id="published_year" name="published_year" type="text" value="<?php echo h($formData['published_year']); ?>" placeholder="2025">
                </div>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Choose a category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo h($category['id']); ?>" <?php echo selected($formData['category_id'], $category['id']); ?>>
                                <?php echo h($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="publisher_id">Publisher</label>
                    <select id="publisher_id" name="publisher_id" required>
                        <option value="">Choose a publisher</option>
                        <?php foreach ($publishers as $publisher): ?>
                            <option value="<?php echo h($publisher['id']); ?>" <?php echo selected($formData['publisher_id'], $publisher['id']); ?>>
                                <?php echo h($publisher['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field">
                <label>Authors</label>
                <div class="checkbox-list">
                    <?php foreach ($authors as $author): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="author_ids[]" value="<?php echo h($author['id']); ?>" <?php echo in_array((int) $author['id'], $selectedAuthors, true) ? 'checked' : ''; ?>>
                            <span><?php echo h($author['name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="field">
                <label for="summary">Summary</label>
                <textarea id="summary" name="summary"><?php echo h($formData['summary']); ?></textarea>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="featured">Featured on homepage</label>
                    <select id="featured" name="featured">
                        <option value="1" <?php echo selected($formData['featured'], '1'); ?>>Yes</option>
                        <option value="0" <?php echo selected($formData['featured'], '0'); ?>>No</option>
                    </select>
                </div>
                <div class="field">
                    <label for="is_active">Visibility status</label>
                    <select id="is_active" name="is_active">
                        <option value="1" <?php echo selected($formData['is_active'], '1'); ?>>Active</option>
                        <option value="0" <?php echo selected($formData['is_active'], '0'); ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <button type="submit"><?php echo $editingId > 0 ? 'Update Book' : 'Create Book'; ?></button>
        </form>
    </article>

    <aside class="panel">
        <h2>Catalogue controls</h2>
        <div class="stack">
            <div class="card">
                <h3>Author relationships</h3>
                <p class="muted">A single title can be linked to multiple contributors without duplicating author records.</p>
            </div>
            <div class="card">
                <h3>Safe record handling</h3>
                <p class="muted">Deletion is blocked when related copies or reservations still exist, protecting collection history.</p>
            </div>
            <div class="card">
                <h3>Data consistency</h3>
                <p class="muted">Book metadata, contributor links, and availability stay aligned in one catalogue workspace.</p>
            </div>
        </div>
    </aside>
</section>

<section class="table-panel">
    <div class="section-heading">
        <h2>Book Catalogue Records</h2>
        <p class="muted"><?php echo h(count($books)); ?> records</p>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Authors</th>
                    <th>Category</th>
                    <th>Publisher</th>
                    <th>Copies</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($books as $book): ?>
                    <tr>
                        <td>
                            <strong><?php echo h($book['title']); ?></strong>
                            <div class="muted"><?php echo h($book['isbn']); ?></div>
                        </td>
                        <td><?php echo h($book['authors']); ?></td>
                        <td><?php echo h($book['category_name']); ?></td>
                        <td><?php echo h($book['publisher_name']); ?></td>
                        <td><?php echo h($book['available_copies']); ?> / <?php echo h($book['total_copies']); ?></td>
                        <td>
                            <?php echo (int) $book['is_active'] === 1 ? render_status_badge('Active', 'active') : render_status_badge('Inactive', 'inactive'); ?>
                            <?php if ((int) $book['featured'] === 1): ?>
                                <?php echo render_status_badge('Featured', 'info'); ?>
                            <?php endif; ?>
                        </td>
                        <td class="table-actions">
                            <a class="button button-inline button-ghost" href="books.php?edit=<?php echo h($book['id']); ?>">Edit</a>
                            <form class="inline-form" method="post" action="books.php" data-confirm="Delete this book record?">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="book_id" value="<?php echo h($book['id']); ?>">
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
