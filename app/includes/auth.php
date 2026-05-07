<?php

require_once __DIR__ . '/functions.php';

function find_user_by_email($email)
{
    if (!db_ready()) {
        return null;
    }

    $statement = db()->prepare('
        SELECT
            u.id,
            u.email,
            u.password_hash,
            u.role,
            u.is_active,
            m.id AS member_id,
            m.membership_number,
            m.full_name,
            m.phone,
            m.course_name,
            m.joined_on,
            m.is_active AS member_is_active
        FROM users u
        LEFT JOIN members m ON m.user_id = u.id
        WHERE u.email = :email
        LIMIT 1
    ');
    $statement->execute(array('email' => $email));
    return $statement->fetch();
}

function find_user_by_id($userId)
{
    if (!db_ready()) {
        return null;
    }

    $statement = db()->prepare('
        SELECT
            u.id,
            u.email,
            u.role,
            u.is_active,
            m.id AS member_id,
            m.membership_number,
            m.full_name,
            m.phone,
            m.course_name,
            m.joined_on,
            m.is_active AS member_is_active
        FROM users u
        LEFT JOIN members m ON m.user_id = u.id
        WHERE u.id = :id
        LIMIT 1
    ');
    $statement->execute(array('id' => (int) $userId));
    return $statement->fetch();
}

function current_user()
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    return find_user_by_id($_SESSION['user_id']);
}

function is_logged_in()
{
    return current_user() !== null;
}

function is_admin()
{
    $user = current_user();
    return $user && $user['role'] === 'admin';
}

function login_user($userId)
{
    $_SESSION['user_id'] = (int) $userId;
}

function logout_user()
{
    unset($_SESSION['user_id']);
}

function attempt_login($email, $password)
{
    $user = find_user_by_email($email);

    if (!$user || (int) $user['is_active'] !== 1) {
        return null;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return null;
    }

    return $user;
}

function require_login($redirectPath)
{
    if (!is_logged_in()) {
        set_flash('error', 'Please sign in to continue.');
        redirect($redirectPath);
    }
}

function require_admin($redirectPath)
{
    if (!is_admin()) {
        set_flash('error', 'Administrator access is required to view that page.');
        redirect($redirectPath);
    }
}
