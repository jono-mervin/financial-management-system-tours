<?php
/**
 * Database connection (PDO)
 * Update credentials to match your local MySQL / phpMyAdmin setup.
 */
$DB_HOST = 'localhost';
$DB_NAME = 'tourflow_finance';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:2rem;color:#b91c1c">
        <h2>Database connection failed</h2>
        <p>' . htmlspecialchars($e->getMessage()) . '</p>
        <p>Check <code>config/db.php</code> and confirm the <code>tourflow_finance</code> database has been imported via phpMyAdmin.</p>
        </div>');
}

/** Simple session bootstrap used across all modules */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
