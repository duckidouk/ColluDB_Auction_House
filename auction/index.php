<?php
require_once __DIR__ . '/lib/utilities.php';
ensure_session_started();

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$destination = ($_SESSION['account_type'] ?? null) === 'seller'
  ? $basePath . '/mylistings.php'
  : $basePath . '/browse.php';

header('Location: ' . $destination);
exit();
?>