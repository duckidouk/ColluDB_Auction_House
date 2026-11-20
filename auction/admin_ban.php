<?php
require_once __DIR__ . '/lib/utilities.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../sql/database.php';
require_once __DIR__ . '/lib/ban_funcs.php';
ensure_session_started();
require_authenticated_session($connection);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method not allowed.');
}

$target_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT, [
  'options' => ['min_range' => 1],
]);
if ($target_id === null || $target_id === false) {
  $_SESSION['auth_message'] = ['type' => 'danger', 'text' => 'Choose a valid user profile.'];
  header('Location: browse.php');
  exit();
}

$return_to_profile = 'profile.php?user_id=' . (int) $target_id;
$csrf_token = $_POST['csrf_token'] ?? null;
if (
  !is_string($csrf_token)
  || !isset($_SESSION['admin_ban_csrf'])
  || !is_string($_SESSION['admin_ban_csrf'])
  || !hash_equals($_SESSION['admin_ban_csrf'], $csrf_token)
) {
  $_SESSION['auth_message'] = ['type' => 'danger', 'text' => 'Your request could not be verified. Please try again.'];
  header('Location: ' . $return_to_profile);
  exit();
}

$durations = ['1' => 1, '7' => 7, '30' => 30, 'permanent' => null];
$duration_input = $_POST['duration'] ?? null;
$reason_input = $_POST['reason'] ?? '';
if (!is_string($duration_input) || !array_key_exists($duration_input, $durations) || !is_string($reason_input)) {
  $_SESSION['auth_message'] = ['type' => 'danger', 'text' => 'Choose a valid ban duration and reason.'];
  header('Location: ' . $return_to_profile);
  exit();
}

$reason = trim($reason_input);
if ($reason !== '' && preg_match('/^.{0,255}$/us', $reason) !== 1) {
  $_SESSION['auth_message'] = ['type' => 'danger', 'text' => 'The ban reason must be 255 characters or fewer.'];
  header('Location: ' . $return_to_profile);
  exit();
}

$admin_id = current_user_id();
if ($admin_id === null) {
  $_SESSION['auth_message'] = ['type' => 'danger', 'text' => 'Please sign in as an administrator.'];
  header('Location: browse.php');
  exit();
}

try {
  $result = admin_ban_user(
    $connection,
    $admin_id,
    (int) $target_id,
    $durations[$duration_input],
    $reason === '' ? null : $reason
  );
  $_SESSION['auth_message'] = [
    'type' => $result['ok'] ? 'success' : 'danger',
    'text' => $result['message'],
  ];
  if ($result['ok']) {
    unset($_SESSION['admin_ban_csrf']);
  }
} catch (InvalidArgumentException $exception) {
  $_SESSION['auth_message'] = ['type' => 'danger', 'text' => $exception->getMessage()];
} catch (Throwable $exception) {
  error_log('User ban failed: ' . $exception->getMessage());
  $_SESSION['auth_message'] = ['type' => 'danger', 'text' => 'The ban could not be applied. Please try again.'];
}

header('Location: ' . $return_to_profile);
exit();
