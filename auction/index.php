<?php
require_once __DIR__ . '/lib/utilities.php';
require_once __DIR__ . '/../sql/database.php';
ensure_session_started();
validate_authenticated_session($connection);

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$destination = current_user_id() === null
  ? $basePath . '/login.php'
  : (($_SESSION['account_type'] ?? null) === 'seller'
    ? $basePath . '/mylistings.php'
    : $basePath . '/browse.php');

header('Location: ' . $destination);
exit();
?>