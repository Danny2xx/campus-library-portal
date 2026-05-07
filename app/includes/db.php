<?php

require_once __DIR__ . '/config.php';

function db()
{
    static $pdo = null;
    static $attempted = false;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if ($attempted) {
        return null;
    }

    $attempted = true;

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        $GLOBALS['db_connection_error'] = $exception->getMessage();
        return null;
    }

    return $pdo;
}

function db_connection_error()
{
    return isset($GLOBALS['db_connection_error']) ? $GLOBALS['db_connection_error'] : null;
}
