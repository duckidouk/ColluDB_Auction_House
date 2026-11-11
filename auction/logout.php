<?php

require_once __DIR__ . '/utilities.php';
require_once __DIR__ . '/../sql/database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ensure_session_started();

$userId = current_user_id();
$userSessionId = current_user_session();
if ($userId !== null && $userSessionId !== null) {
  $statement = mysqli_prepare(
    $connection,
    'UPDATE UserSessions SET endTime = NOW() WHERE sessionId = ? AND userId = ? AND endTime IS NULL'
  );
  mysqli_stmt_bind_param($statement, 'ii', $userSessionId, $userId);
  mysqli_stmt_execute($statement);
  mysqli_stmt_close($statement);
}

$_SESSION = [];
$cookie = session_get_cookie_params();
if (ini_get('session.use_cookies')) {
  setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
}
session_destroy();

header('Location: browse.php');
exit();
