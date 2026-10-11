<?php
/**
 * Single-responsibility: return a PDO connection to PostgreSQL.
 * Edit the DSN constants to match your local environment.
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '5432');
define('DB_NAME', 'daa_project');
define('DB_USER', 'postgres');
define('DB_PASS', 'admin');

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
