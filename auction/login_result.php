<?php

require_once __DIR__ . '/utilities.php';
require_once __DIR__ . '/../sql/database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ensure_session_started();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: browse.php');
  exit();
}

$email = $_POST['email'] ?? null;
$password = $_POST['password'] ?? null;
if (!is_string($email) || !is_string($password)) {
  $_SESSION['auth_message'] = ['type' => 'danger', 'text' => 'Enter your email address and password.'];
  header('Location: browse.php');
  exit();
}
$email = trim($email);
if ($email === '' || $password === '') {
  $_SESSION['auth_message'] = ['type' => 'danger', 'text' => 'Enter your email address and password.'];
  header('Location: browse.php');
  exit();
}

$query = mysqli_prepare(
  $connection,
  'SELECT u.userId, u.passwordHash, r.name AS accountType
     FROM UserAccounts u
     JOIN Roles r ON r.roleId = u.roleId
    WHERE u.emailAddress = ?'
);
mysqli_stmt_bind_param($query, 's', $email);
mysqli_stmt_execute($query);
$user = mysqli_stmt_get_result($query)->fetch_assoc();
mysqli_stmt_close($query);

if (!$user || !password_verify($password, $user['passwordHash'])) {
  $_SESSION['auth_message'] = ['type' => 'danger', 'text' => 'The email address or password is incorrect.'];
  header('Location: browse.php');
  exit();
}

$userId = (int) $user['userId'];
start_authenticated_session($connection, $userId, $user['accountType']);

header('Location: browse.php');
exit();
