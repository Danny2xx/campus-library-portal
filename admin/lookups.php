<?php

require_once dirname(__DIR__) . '/app/includes/auth.php';

require_admin('../login.php');

$sections = array(
    'authors' => array(
        'title' => 'Authors',
        'singular' => 'author',
        'table' => 'authors',
        'fields' => array(
            'name' => 'Author name',
            'biography' => 'Biography',
        ),
        'dependency_sql' => 'SELECT COUNT(*) FROM book_authors WHERE author_id = :id',
        'dependency_message' => 'This author is still linked to one or more books.',
    ),
    'categories' => array(
        'title' => 'Categories',
        'singular' => 'category',
        'table' => 'categories',
        'fields' => array(
            'name' => 'Category name',
            'description' => 'Description',
        ),
        'dependency_sql' => 'SELECT COUNT(*) FROM books WHERE category_id = :id',
        'dependency_message' => 'This category is still assigned to one or more books.',
    ),
    'publishers' => array(
        'title' => 'Publishers',
        'singular' => 'publisher',
        'table' => 'publishers',
        'fields' => array(
            'name' => 'Publisher name',
            'website_url' => 'Website URL',
            'description' => 'Description',
        ),
        'dependency_sql' => 'SELECT COUNT(*) FROM books WHERE publisher_id = :id',
        'dependency_message' => 'This publisher is still linked to one or more books.',
    ),
);

$sectionKey = request_value('section', 'authors');

if (!isset($sections[$sectionKey])) {
    $sectionKey = 'authors';
}

$section = $sections[$sectionKey];
$editingId = (int) request_value('edit', 0);
$formData = array();

foreach ($section['fields'] as $fieldKey => $label) {
    $formData[$fieldKey] = '';
}

if (is_post()) {
    if (!verify_csrf_token(request_value('csrf_token'))) {
        set_flash('error', 'Your session token expired. Please try again.');
        redirect('lookups.php?section=' . urlencode($sectionKey) . ($editingId ? '&edit=' . $editingId : ''));
    }

    if (!db_ready()) {
        set_flash('error', 'The database is not connected yet.');
        redirect('lookups.php?section=' . urlencode($sectionKey));
    }

    $action = request_value('action');

    if ($action === 'delete') {
        $recordId = (int) request_value('record_id');
        $dependencyCheck = db()->prepare($section['dependency_sql']);
        $dependencyCheck->execute(array('id' => $recordId));

        if ((int) $dependencyCheck->fetchColumn() > 0) {
            set_flash('error', $section['dependency_message']);
            redirect('lookups.php?section=' . urlencode($sectionKey));
        }

        $deleteStatement = db()->prepare('DELETE FROM ' . $section['table'] . ' WHERE id = :id');
        $deleteStatement->execute(array('id' => $recordId));
        set_flash('success', $section['title'] . ' record deleted.');
        redirect('lookups.php?section=' . urlencode($sectionKey));
    }

    $recordId = (int) request_value('record_id');
    $payload = array();

    foreach ($section['fields'] as $fieldKey => $label) {
        $payload[$fieldKey] = request_value($fieldKey);
    }

    remember_form_data($payload);

    if ($payload['name'] === '') {
        set_flash('error', 'The name field is required.');
        redirect('lookups.php?section=' . urlencode($sectionKey) . ($action === 'update' ? '&edit=' . $recordId : ''));
    }

    $nameCheck = db()->prepare('
        SELECT COUNT(*)
        FROM ' . $section['table'] . '
        WHERE name = :name
        AND id <> :id
    ');
    $nameCheck->execute(array(
        'name' => $payload['name'],
        'id' => $recordId,
    ));

    if ((int) $nameCheck->fetchColumn() > 0) {
        set_flash('error', 'A record with that name already exists.');
        redirect('lookups.php?section=' . urlencode($sectionKey) . ($action === 'update' ? '&edit=' . $recordId : ''));
    }

    $columns = array_keys($section['fields']);
    $parameters = array();

    foreach ($columns as $column) {
        $parameters[$column] = $payload[$column];
    }

    if ($action === 'update' && $recordId > 0) {
        $assignments = array();

        foreach ($columns as $column) {
            $assignments[] = $column . ' = :' . $column;
        }

        $parameters['id'] = $recordId;
        $statement = db()->prepare('UPDATE ' . $section['table'] . ' SET ' . implode(', ', $assignments) . ' WHERE id = :id');
        $statement->execute($parameters);
        set_flash('success', $section['title'] . ' record updated.');
    } else {
        $placeholders = array();

        foreach ($columns as $column) {
            $placeholders[] = ':' . $column;
        }

        $statement = db()->prepare('
            INSERT INTO ' . $section['table'] . ' (' . implode(', ', $columns) . ')
            VALUES (' . implode(', ', $placeholders) . ')
        ');
        $statement->execute($parameters);
        set_flash('success', $section['title'] . ' record created.');
    }

    clear_form_data();
    redirect('lookups.php?section=' . urlencode($sectionKey));
}

if (!empty($_SESSION['form_data'])) {
    $formData = array_merge($formData, $_SESSION['form_data']);
} elseif ($editingId > 0 && db_ready()) {
    $editStatement = db()->prepare('SELECT * FROM ' . $section['table'] . ' WHERE id = :id LIMIT 1');
    $editStatement->execute(array('id' => $editingId));
    $editingRecord = $editStatement->fetch();

    if ($editingRecord) {
        foreach (array_keys($section['fields']) as $fieldKey) {
            $formData[$fieldKey] = $editingRecord[$fieldKey];
        }
    }
}

$records = db_ready() ? db()->query('SELECT * FROM ' . $section['table'] . ' ORDER BY name ASC')->fetchAll() : array();

$pageTitle = $section['title'];
$basePath = '../';
$isAdminSection = true;

include dirname(__DIR__) . '/app/includes/header.php';
?>

<section class="grid grid-2">
    <article class="form-panel">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Lookup Data</p>
                <h1><?php echo $editingId > 0 ? 'Edit ' . h($section['singular']) : 'Manage ' . strtolower($section['title']); ?></h1>
            </div>
            <?php if ($editingId > 0): ?>
                <a class="button button-ghost" href="lookups.php?section=<?php echo urlencode($sectionKey); ?>">Cancel edit</a>
            <?php endif; ?>
        </div>

        <div class="action-row">
            <?php foreach ($sections as $key => $config): ?>
                <a class="button <?php echo $key === $sectionKey ? 'button-secondary' : 'button-ghost'; ?>" href="lookups.php?section=<?php echo urlencode($key); ?>">
                    <?php echo h($config['title']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form class="stack" method="post" action="lookups.php?section=<?php echo urlencode($sectionKey); ?><?php echo $editingId > 0 ? '&edit=' . h($editingId) : ''; ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo $editingId > 0 ? 'update' : 'create'; ?>">
            <input type="hidden" name="record_id" value="<?php echo h($editingId); ?>">

            <?php foreach ($section['fields'] as $fieldKey => $label): ?>
                <div class="field">
                    <label for="<?php echo h($fieldKey); ?>"><?php echo h($label); ?></label>
                    <?php if (in_array($fieldKey, array('description', 'biography'), true)): ?>
                        <textarea id="<?php echo h($fieldKey); ?>" name="<?php echo h($fieldKey); ?>"><?php echo h($formData[$fieldKey]); ?></textarea>
                    <?php else: ?>
                        <input id="<?php echo h($fieldKey); ?>" name="<?php echo h($fieldKey); ?>" type="text" value="<?php echo h($formData[$fieldKey]); ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit"><?php echo $editingId > 0 ? 'Update Record' : 'Create Record'; ?></button>
        </form>
    </article>

    <aside class="panel">
        <h2>Lookup purpose</h2>
        <div class="stack">
            <div class="card">
                <h3>Authors</h3>
                <p class="muted">Maintain the many-to-many author relationships that sit behind the catalogue.</p>
            </div>
            <div class="card">
                <h3>Categories</h3>
                <p class="muted">Provide a clean taxonomy for search, filtering, and reporting queries.</p>
            </div>
            <div class="card">
                <h3>Publishers</h3>
                <p class="muted">Keep imprint metadata separate from book records to reduce duplication.</p>
            </div>
        </div>
    </aside>
</section>

<section class="table-panel">
    <div class="section-heading">
        <h2><?php echo h($section['title']); ?> Directory</h2>
        <p class="muted"><?php echo h(count($records)); ?> records</p>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <?php foreach ($section['fields'] as $fieldKey => $label): ?>
                        <th><?php echo h($label); ?></th>
                    <?php endforeach; ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <?php foreach (array_keys($section['fields']) as $fieldKey): ?>
                            <td><?php echo h($record[$fieldKey]); ?></td>
                        <?php endforeach; ?>
                        <td class="table-actions">
                            <a class="button button-inline button-ghost" href="lookups.php?section=<?php echo urlencode($sectionKey); ?>&edit=<?php echo h($record['id']); ?>">Edit</a>
                            <form class="inline-form" method="post" action="lookups.php?section=<?php echo urlencode($sectionKey); ?>" data-confirm="Delete this record?">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="record_id" value="<?php echo h($record['id']); ?>">
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
