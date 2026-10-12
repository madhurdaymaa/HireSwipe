<?php
// Shared config + helpers. Edit the 4 constants for your XAMPP/WAMP setup.
const DB_HOST = 'localhost';
const DB_NAME = 'HireSwipe';
const DB_USER = 'your_user';
const DB_PASS = 'your_password';

session_start();

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed. Check the settings in db.php.');
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
