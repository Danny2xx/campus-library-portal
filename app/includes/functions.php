<?php

require_once __DIR__ . '/db.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_post()
{
    return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
}

function redirect($path)
{
    header('Location: ' . $path);
    exit;
}

function db_ready()
{
    return db() instanceof PDO;
}

function set_flash($type, $message)
{
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = array();
    }

    $_SESSION['flash_messages'][] = array(
        'type' => $type,
        'message' => $message,
    );
}

function get_flash_messages()
{
    $messages = isset($_SESSION['flash_messages']) ? $_SESSION['flash_messages'] : array();
    unset($_SESSION['flash_messages']);
    return $messages;
}

function old($key, $default = '')
{
    if (!isset($_SESSION['form_data']) || !array_key_exists($key, $_SESSION['form_data'])) {
        return $default;
    }

    return $_SESSION['form_data'][$key];
}

function remember_form_data($data)
{
    $_SESSION['form_data'] = $data;
}

function clear_form_data()
{
    unset($_SESSION['form_data']);
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf_token($token)
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function request_value($key, $default = '')
{
    if (isset($_POST[$key])) {
        return trim((string) $_POST[$key]);
    }

    if (isset($_GET[$key])) {
        return trim((string) $_GET[$key]);
    }

    return $default;
}

function format_date_display($value)
{
    if (empty($value)) {
        return 'Not set';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('d M Y', $timestamp);
}

function status_class($status)
{
    $map = array(
        'success' => 'success',
        'warning' => 'warning',
        'danger' => 'danger',
        'info' => 'info',
        'muted' => 'muted',
        'available' => 'success',
        'loaned' => 'warning',
        'maintenance' => 'muted',
        'pending' => 'warning',
        'ready_for_collection' => 'info',
        'fulfilled' => 'success',
        'cancelled' => 'danger',
        'active' => 'success',
        'inactive' => 'muted',
        'returned' => 'success',
        'overdue' => 'danger',
    );

    return isset($map[$status]) ? $map[$status] : 'info';
}

function render_status_badge($label, $status = '')
{
    $state = $status !== '' ? $status : strtolower(str_replace(' ', '_', $label));
    return '<span class="badge badge-' . h(status_class($state)) . '">' . h($label) . '</span>';
}

function fetch_pairs($sql)
{
    if (!db_ready()) {
        return array();
    }

    $statement = db()->query($sql);
    $result = array();

    while ($row = $statement->fetch(PDO::FETCH_NUM)) {
        $result[$row[0]] = $row[1];
    }

    return $result;
}

function nav_is_active($scriptName, $targets)
{
    return in_array($scriptName, $targets, true) ? 'is-active' : '';
}

function selected($value, $expected)
{
    return (string) $value === (string) $expected ? 'selected' : '';
}

function checked($value, $expected)
{
    return (string) $value === (string) $expected ? 'checked' : '';
}

function app_notice_message()
{
    if (db_ready()) {
        return '';
    }

    return 'The database connection is not active. Ensure your environment variables (DB_HOST, DB_NAME, DB_USER, DB_PASS) are configured correctly.';
}
