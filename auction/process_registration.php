<?php

require_once __DIR__ . '/utilities.php';
require_once __DIR__ . '/../sql/database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ensure_session_started();

function registration_error(string $message): void {
  $_SESSION['auth_message'] = ['type' => 'danger', 'text' => $message];
  header('Location: register.php');
  exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: register.php');
  exit();
}

foreach (['firstName', 'lastName', 'email', 'password', 'passwordConfirmation', 'accountType'] as $field) {
  if (!isset($_POST[$field]) || !is_string($_POST[$field])) {
    registration_error('Complete all registration fields with valid values.');
  }
}

$firstName = trim((string) ($_POST['firstName'] ?? ''));
$lastName = trim((string) ($_POST['lastName'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$passwordConfirmation = (string) ($_POST['passwordConfirmation'] ?? '');
$accountType = (string) ($_POST['accountType'] ?? '');

if ($firstName === '' || strlen($firstName) > 50 || $lastName === '' || strlen($lastName) > 50) {
  registration_error('Enter a first and last name of no more than 50 characters each.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
  registration_error('Enter a valid email address.');
}
if ($password === '' || $password !== $passwordConfirmation) {
  registration_error('Enter a password and make sure both password fields match.');
}
if (!in_array($accountType, ['buyer', 'seller'], true)) {
  registration_error('Choose a valid account type.');
}

$emailCheck = mysqli_prepare($connection, 'SELECT userId FROM UserAccounts WHERE emailAddress = ?');
mysqli_stmt_bind_param($emailCheck, 's', $email);
mysqli_stmt_execute($emailCheck);
$emailExists = mysqli_stmt_get_result($emailCheck)->fetch_assoc() !== null;
mysqli_stmt_close($emailCheck);
if ($emailExists) {
  registration_error('An account with that email address already exists.');
}

$roleQuery = mysqli_prepare($connection, 'SELECT roleId FROM Roles WHERE name = ?');
mysqli_stmt_bind_param($roleQuery, 's', $accountType);
mysqli_stmt_execute($roleQuery);
$role = mysqli_stmt_get_result($roleQuery)->fetch_assoc();
mysqli_stmt_close($roleQuery);
if (!$role) {
  registration_error('That account type is not available.');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$insert = mysqli_prepare(
  $connection,
  'INSERT INTO UserAccounts (firstName, lastName, emailAddress, passwordHash, roleId) VALUES (?, ?, ?, ?, ?)'
);
$roleId = (int) $role['roleId'];
mysqli_stmt_bind_param($insert, 'ssssi', $firstName, $lastName, $email, $passwordHash, $roleId);

try {
  mysqli_stmt_execute($insert);
} catch (mysqli_sql_exception $exception) {
  mysqli_stmt_close($insert);
  if ($exception->getCode() === 1062) {
    registration_error('An account with that email address already exists.');
  }
  throw $exception;
}
mysqli_stmt_close($insert);

$userId = mysqli_insert_id($connection);
start_authenticated_session($connection, $userId, $accountType);
$_SESSION['auth_message'] = ['type' => 'success', 'text' => 'Your account has been created and you are now logged in.'];
header('Location: browse.php');
exit();