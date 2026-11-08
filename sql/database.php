<?php
$config = require __DIR__ . '/../config.php';

$connection = mysqli_connect(
    $config['db_host'],
    $config['db_user'],
    $config['db_password'],
    $config['db_name'],
    $config['db_port']
);

if (!$connection) {
    die('Failed to connect to the MySQL server: ' . mysqli_connect_error());
}

mysqli_set_charset($connection, 'utf8mb4');
