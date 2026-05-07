<?php

require_once __DIR__ . '/auth.php';

$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME;
$basePath = isset($basePath) ? $basePath : '';
$isAdminSection = isset($isAdminSection) ? $isAdminSection : false;
$user = current_user();
$currentScript = basename($_SERVER['PHP_SELF']);
$flashMessages = get_flash_messages();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($pageTitle); ?></title>
    <link rel="stylesheet" href="<?php echo h($basePath); ?>assets/css/styles.css">
    <script defer src="<?php echo h($basePath); ?>assets/js/app.js"></script>
</head>
<body class="<?php echo $isAdminSection ? 'admin-shell' : 'public-shell'; ?>">
    <div class="page-frame">
        <header class="site-header">
            <div class="brand-lockup">
                <a class="brand-mark" href="<?php echo h($basePath); ?>index.php">CLP</a>
                <div>
                    <p class="eyebrow"><?php echo $isAdminSection ? 'Staff Workspace' : 'Library Services'; ?></p>
                    <a class="brand-name" href="<?php echo h($basePath); ?>index.php"><?php echo h(APP_NAME); ?></a>
                </div>
            </div>
            <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-label="Toggle navigation">
                Menu
            </button>
            <nav class="primary-nav" data-nav>
                <a class="<?php echo nav_is_active($currentScript, array('index.php')); ?>" href="<?php echo h($basePath); ?>index.php">Home</a>
                <a class="<?php echo nav_is_active($currentScript, array('catalogue.php', 'book.php')); ?>" href="<?php echo h($basePath); ?>catalogue.php">Catalogue</a>
                <?php if ($user && !$isAdminSection): ?>
                    <a class="<?php echo nav_is_active($currentScript, array('dashboard.php')); ?>" href="<?php echo h($basePath); ?>dashboard.php">My Account</a>
                <?php endif; ?>
                <?php if ($user && is_admin()): ?>
                    <a class="<?php echo $isAdminSection ? 'is-active' : ''; ?>" href="<?php echo h($basePath); ?>admin/index.php">Staff</a>
                <?php endif; ?>
                <?php if ($user): ?>
                    <a href="<?php echo h($basePath); ?>logout.php">Sign Out</a>
                <?php else: ?>
                    <a class="<?php echo nav_is_active($currentScript, array('login.php')); ?>" href="<?php echo h($basePath); ?>login.php">Sign In</a>
                    <a class="button-link" href="<?php echo h($basePath); ?>register.php">Join Library</a>
                <?php endif; ?>
            </nav>
        </header>

        <?php if (!db_ready()): ?>
            <section class="notice-banner">
                <strong>Setup notice:</strong>
                <?php echo h(app_notice_message()); ?>
            </section>
        <?php endif; ?>

        <?php if ($isAdminSection): ?>
            <aside class="admin-nav">
                <a class="<?php echo nav_is_active($currentScript, array('index.php')); ?>" href="index.php">Overview</a>
                <a class="<?php echo nav_is_active($currentScript, array('books.php')); ?>" href="books.php">Catalogue</a>
                <a class="<?php echo nav_is_active($currentScript, array('lookups.php')); ?>" href="lookups.php?section=authors">Reference Data</a>
                <a class="<?php echo nav_is_active($currentScript, array('inventory.php')); ?>" href="inventory.php">Inventory</a>
                <a class="<?php echo nav_is_active($currentScript, array('members.php')); ?>" href="members.php">Members</a>
                <a class="<?php echo nav_is_active($currentScript, array('loans.php')); ?>" href="loans.php">Loans</a>
                <a class="<?php echo nav_is_active($currentScript, array('reports.php')); ?>" href="reports.php">Reports</a>
            </aside>
        <?php endif; ?>

        <main class="main-content">
            <?php foreach ($flashMessages as $message): ?>
                <div class="flash flash-<?php echo h($message['type']); ?>">
                    <?php echo h($message['message']); ?>
                </div>
            <?php endforeach; ?>
