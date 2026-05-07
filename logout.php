<?php

require_once __DIR__ . '/app/includes/auth.php';

logout_user();
set_flash('success', 'You have been signed out.');
redirect('index.php');
