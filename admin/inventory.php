<?php

require_once dirname(__DIR__) . '/app/includes/auth.php';

require_admin('../login.php');

$editingId = (int) request_value('edit', 0);
$statuses = array('available', 'loaned', 'maintenance');
$formData = array(
    'book_id' => '',
    'accession_code' => '',
    'shelf_location' => '',
    'status' => 'available',
    'condition_notes' => '',
);

if (is_post()) {
    if (!verify_csrf_token(request_value('csrf_token'))) {
        set_flash('error', 'Your session token expired. Please try again.');
        redirect('inventory.php' . ($editingId ? '?edit=' . $editingId : ''));
    }

    if (!db_ready()) {
        set_flash('error', 'The database is not connected yet.');
        redirect('inventory.php');
    }

    $action = request_value('action');

    if ($action === 'delete') {
        $copyId = (int) request_value('copy_id');
        $loanCheck = db()->prepare('SELECT COUNT(*) FROM loans WHERE copy_id = :copy_id');
        $loanCheck->execute(array('copy_id' => $copyId));

        if ((int) $loanCheck->fetchColumn() > 0) {
            set_flash('error', 'This copy has loan history and cannot be deleted.');
            redirect('inventory.php');
        }

        $deleteStatement = db()->prepare('DELETE FROM copies WHERE id = :copy_id');
        $deleteStatement->execute(array('copy_id' => $copyId));
        set_flash('success', 'Copy deleted successfully.');
        redirect('inventory.php');
    }

    $copyId = (int) request_value('copy_id');
    $formData = array(
        'book_id' => request_value('book_id'),
        'accession_code' => request_value('accession_code'),
        'shelf_location' => request_value('shelf_location'),
        'status' => request_value('status', 'available'),
        'condition_notes' => request_value('condition_notes'),
    );

    remember_form_data($formData);

    $errors = array();

    if ($formData['book_id'] === '' || $formData['accession_code'] === '' || $formData['shelf_location'] === '') {
        $errors[] = 'Book, accession code and shelf location are required.';
    }

    if (!in_array($formData['status'], $statuses, true)) {
        $errors[] = 'Choose a valid inventory status.';
    }

    $duplicateStatement = db()->prepare('
        SELECT COUNT(*)
        FROM copies
        WHERE accession_code = :accession_code
        AND id <> :copy_id
    ');
    $duplicateStatement->execute(array(
        'accession_code' => $formData['accession_code'],
        'copy_id' => $copyId,
    ));

    if ((int) $duplicateStatement->fetchColumn() > 0) {
        $errors[] = 'That accession code is already in use.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            set_flash('error', $error);
        }

        redirect('inventory.php' . ($action === 'update' ? '?edit=' . $copyId : ''));
    }

    if ($action === 'update' && $copyId > 0) {
        $statement = db()->prepare('
            UPDATE copies
            SET
                book_id = :book_id,
                accession_code = :accession_code,
                shelf_location = :shelf_location,
                status = :status,
                condition_notes = :condition_notes
            WHERE id = :copy_id
        ');
        $statement->execute(array(
            'book_id' => (int) $formData['book_id'],
            'accession_code' => $formData['accession_code'],
            'shelf_location' => $formData['shelf_location'],
            'status' => $formData['status'],
            'condition_notes' => $formData['condition_notes'],
            'copy_id' => $copyId,
        ));
        set_flash('success', 'Inventory record updated.');
    } else {
        $statement = db()->prepare('
            INSERT INTO copies (book_id, accession_code, shelf_location, status, condition_notes, created_at)
            VALUES (:book_id, :accession_code, :shelf_location, :status, :condition_notes, NOW())
        ');
        $statement->execute(array(
            'book_id' => (int) $formData['book_id'],
            'accession_code' => $formData['accession_code'],
            'shelf_location' => $formData['shelf_location'],
            'status' => $formData['status'],
            'condition_notes' => $formData['condition_notes'],
        ));
        set_flash('success', 'Inventory record created.');
    }

    clear_form_data();
    redirect('inventory.php');
}

if (!empty($_SESSION['form_data'])) {
    $formData = array_merge($formData, $_SESSION['form_data']);
} elseif ($editingId > 0 && db_ready()) {
    $editStatement = db()->prepare('SELECT * FROM copies WHERE id = :copy_id LIMIT 1');
    $editStatement->execute(array('copy_id' => $editingId));
    $editingCopy = $editStatement->fetch();

    if ($editingCopy) {
        $formData = array_merge($formData, $editingCopy);
    }
}

$books = db_ready() ? db()->query('SELECT id, title FROM books WHERE is_active = 1 ORDER BY title ASC')->fetchAll() : array();
$copies = array();

if (db_ready()) {
    $copies = db()->query('
        SELECT
            c.id,
            c.accession_code,
            c.shelf_location,
            c.status,
            c.condition_notes,
            b.title,
            (
                SELECT COUNT(*)
                FROM loans l
                WHERE l.copy_id = c.id
                AND l.returned_at IS NULL
            ) AS active_loan_count
        FROM copies c
        INNER JOIN books b ON b.id = c.book_id
        ORDER BY b.title ASC, c.accession_code ASC
    ')->fetchAll();
}

$pageTitle = 'Inventory';
$basePath = '../';
$isAdminSection = true;

include dirname(__DIR__) . '/app/includes/header.php';
?>

<section class="grid grid-2">
    <article class="form-panel">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Physical Copies</p>
                <h1><?php echo $editingId > 0 ? 'Edit copy' : 'Add inventory copy'; ?></h1>
            </div>
            <?php if ($editingId > 0): ?>
                <a class="button button-ghost" href="inventory.php">Cancel edit</a>
            <?php endif; ?>
        </div>

        <form class="stack" method="post" action="inventory.php<?php echo $editingId > 0 ? '?edit=' . h($editingId) : ''; ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo $editingId > 0 ? 'update' : 'create'; ?>">
            <input type="hidden" name="copy_id" value="<?php echo h($editingId); ?>">

            <div class="field">
                <label for="book_id">Book title</label>
                <select id="book_id" name="book_id" required>
                    <option value="">Choose a title</option>
                    <?php foreach ($books as $book): ?>
                        <option value="<?php echo h($book['id']); ?>" <?php echo selected($formData['book_id'], $book['id']); ?>>
                            <?php echo h($book['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="accession_code">Accession code</label>
                    <input id="accession_code" name="accession_code" type="text" value="<?php echo h($formData['accession_code']); ?>" placeholder="CPY-001" required>
                </div>
                <div class="field">
                    <label for="shelf_location">Shelf location</label>
                    <input id="shelf_location" name="shelf_location" type="text" value="<?php echo h($formData['shelf_location']); ?>" placeholder="A2-04" required>
                </div>
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?php echo h($status); ?>" <?php echo selected($formData['status'], $status); ?>>
                            <?php echo h(ucfirst($status)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="condition_notes">Condition notes</label>
                <textarea id="condition_notes" name="condition_notes"><?php echo h($formData['condition_notes']); ?></textarea>
            </div>

            <button type="submit"><?php echo $editingId > 0 ? 'Update Copy' : 'Add Copy'; ?></button>
        </form>
    </article>

    <aside class="panel">
        <h2>Inventory notes</h2>
        <div class="stack">
            <div class="card">
                <h3>Accession codes</h3>
                <p class="muted">Each physical copy has a unique accession code so loans track the exact item, not just the title.</p>
            </div>
            <div class="card">
                <h3>Maintenance handling</h3>
                <p class="muted">Copies can be marked unavailable for repairs without removing the related title from the catalogue.</p>
            </div>
        </div>
    </aside>
</section>

<section class="table-panel">
    <div class="section-heading">
        <h2>Inventory List</h2>
        <p class="muted"><?php echo h(count($copies)); ?> records</p>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Book</th>
                    <th>Accession Code</th>
                    <th>Shelf</th>
                    <th>Status</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($copies as $copy): ?>
                    <tr>
                        <td><?php echo h($copy['title']); ?></td>
                        <td><?php echo h($copy['accession_code']); ?></td>
                        <td><?php echo h($copy['shelf_location']); ?></td>
                        <td>
                            <?php echo render_status_badge(ucfirst($copy['status']), $copy['status']); ?>
                            <?php if ((int) $copy['active_loan_count'] > 0): ?>
                                <?php echo render_status_badge('On active loan', 'warning'); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo h($copy['condition_notes']); ?></td>
                        <td class="table-actions">
                            <a class="button button-inline button-ghost" href="inventory.php?edit=<?php echo h($copy['id']); ?>">Edit</a>
                            <form class="inline-form" method="post" action="inventory.php" data-confirm="Delete this copy?">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="copy_id" value="<?php echo h($copy['id']); ?>">
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
