<?php
require_once __DIR__ . '/lib/utilities.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../sql/database.php';
require_once __DIR__ . '/lib/notification_funcs.php';
ensure_session_started();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method not allowed.');
}

$seller_id = filter_input(INPUT_POST, 'seller_id', FILTER_VALIDATE_INT, [
  'options' => ['min_range' => 1],
]);
if ($seller_id === null || $seller_id === false) {
  $_SESSION['auth_message'] = ['type' => 'danger', 'text' => 'Invalid seller.'];
  header('Location: browse.php');
  exit();
}

$return_to_seller = 'seller.php?seller_id=' . $seller_id;
$csrf_token = $_POST['csrf_token'] ?? null;
if (
  !is_string($csrf_token)
  || !isset($_SESSION['seller_follow_csrf'])
  || !is_string($_SESSION['seller_follow_csrf'])
  || !hash_equals($_SESSION['seller_follow_csrf'], $csrf_token)
) {
  $_SESSION['auth_message'] = ['type' => 'danger', 'text' => 'Your request could not be verified. Please try again.'];
  header('Location: ' . $return_to_seller);
  exit();
}

$follower_id = current_user_id();
if ($follower_id === null || user_role($connection, $follower_id) !== 'buyer') {
  $_SESSION['auth_message'] = ['type' => 'danger', 'text' => 'Only buyers can follow sellers.'];
  header('Location: ' . $return_to_seller);
  exit();
}

if (user_role($connection, $seller_id) !== 'seller' || $follower_id === $seller_id) {
  $_SESSION['auth_message'] = ['type' => 'danger', 'text' => 'This seller cannot be followed.'];
  header('Location: browse.php');
  exit();
}

$action = $_POST['action'] ?? '';
if (!in_array($action, ['follow', 'unfollow'], true)) {
  $_SESSION['auth_message'] = ['type' => 'danger', 'text' => 'Invalid follow action.'];
  header('Location: ' . $return_to_seller);
  exit();
}

if ($action === 'follow') {
  mysqli_begin_transaction($connection);
  try {
    $follow_statement = mysqli_prepare(
      $connection,
      'INSERT INTO FavouriteSellers (followerId, sellerId)
       VALUES (?, ?)
       ON DUPLICATE KEY UPDATE followedAt = FavouriteSellers.followedAt'
    );
    mysqli_stmt_bind_param($follow_statement, 'ii', $follower_id, $seller_id);
    mysqli_stmt_execute($follow_statement);
    $created = mysqli_stmt_affected_rows($follow_statement) === 1;
    mysqli_stmt_close($follow_statement);

    if ($created) {
      create_notification($connection, $seller_id, 'NEW_FOLLOWER', null, null, $follower_id);
    }

    mysqli_commit($connection);
  } catch (mysqli_sql_exception $exception) {
    mysqli_rollback($connection);
    throw $exception;
  }

  $_SESSION['auth_message'] = $created
    ? ['type' => 'success', 'text' => 'You are now following this seller.']
    : ['type' => 'info', 'text' => 'You are already following this seller.'];
} else {
  $unfollow_statement = mysqli_prepare(
    $connection,
    'DELETE FROM FavouriteSellers WHERE followerId = ? AND sellerId = ?'
  );
  mysqli_stmt_bind_param($unfollow_statement, 'ii', $follower_id, $seller_id);
  mysqli_stmt_execute($unfollow_statement);
  $removed = mysqli_stmt_affected_rows($unfollow_statement) === 1;
  mysqli_stmt_close($unfollow_statement);

  $_SESSION['auth_message'] = $removed
    ? ['type' => 'success', 'text' => 'You have unfollowed this seller.']
    : ['type' => 'info', 'text' => 'You were not following this seller.'];
}

header('Location: ' . $return_to_seller);
exit();
