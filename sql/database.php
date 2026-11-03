<?php
// to use in a file:  require_once __DIR__ . '/../includes/database.php';
// Then use $connection (or the db_connect() helper).

// TODO: move credentials into config.php (git-ignored) 
define('DB_HOST', 'localhost');
define('DB_USER', 'auction_admin');   // dedicated user, not root (see 01_schema.sql)
define('DB_PASS', 'change_me');
define('DB_NAME', 'auction_db');

$connection = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$connection) {
    die('Failed to connect to the MySQL server: ' . mysqli_connect_error());
}

mysqli_set_charset($connection, 'utf8mb4');
