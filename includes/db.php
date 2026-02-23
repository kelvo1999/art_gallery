<?php
// includes/db.php - Database connection

define('DB_HOST', '192.168.10.20');
define('DB_USER', 'root');        // change to your MySQL user
define('DB_PASS', 'root');            // change to your MySQL password
define('DB_NAME', 'artvault');
// define('DB_HOST', 'localhost');
// define('DB_USER', 'root');        // change to your MySQL user
// define('DB_PASS', '');            // change to your MySQL password
// define('DB_NAME', 'artvault');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');