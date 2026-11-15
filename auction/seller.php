<?php
require_once __DIR__ . '/utilities.php';
require_once __DIR__ . '/../sql/database.php';
require_once __DIR__ . '/auction_queries.php';
require_once __DIR__ . '/notification_funcs.php';
ensure_session_started();

$seller_id = filter_input(INPUT_GET, 'seller_id', FILTER_VALIDATE_INT, [
  'options' => ['min_range' => 1],
]);
if ($seller_id === null || $seller_id === false) {
  http_response_code(404);
  exit('Seller not found.');
}

$seller_statement = mysqli_prepare(
  $connection,
  "SELECT u.firstName, u.lastName
     FROM UserAccounts u
     JOIN Roles r ON r.roleId = u.roleId
    WHERE u.userId = ? AND r.name = 'seller'"
);
mysqli_stmt_bind_param($seller_statement, 'i', $seller_id);
mysqli_stmt_execute($seller_statement);
$seller = mysqli_stmt_get_result($seller_statement)->fetch_assoc();
mysqli_stmt_close($seller_statement);

if (!$seller) {
  http_response_code(404);
  exit('Seller not found.');
}

$seller_name = trim($seller['firstName'] . ' ' . $seller['lastName']);
$viewer_id = current_user_id();
$is_buyer = $viewer_id !== null && user_role($connection, $viewer_id) === 'buyer';
$is_following = false;

if ($is_buyer) {
  $follow_statement = mysqli_prepare(
    $connection,
    'SELECT 1 FROM FavouriteSellers WHERE followerId = ? AND sellerId = ?'
  );
  mysqli_stmt_bind_param($follow_statement, 'ii', $viewer_id, $seller_id);
  mysqli_stmt_execute($follow_statement);
  $is_following = mysqli_stmt_get_result($follow_statement)->num_rows > 0;
  mysqli_stmt_close($follow_statement);
  if (!isset($_SESSION['seller_follow_csrf']) || !is_string($_SESSION['seller_follow_csrf'])) {
    $_SESSION['seller_follow_csrf'] = bin2hex(random_bytes(32));
  }
}

$results = fetch_seller_auctions($connection, $seller_id);

include __DIR__ . '/header.php';
?>

<div class="container">
  <div class="d-flex justify-content-between align-items-center my-3">
    <h2 class="mb-0"><?php echo htmlspecialchars($seller_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
    <?php if ($is_buyer): ?>
      <?php if ($is_following): ?>
        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#unfollowSellerModal">
          Following
        </button>
      <?php else: ?>
        <form method="POST" action="seller_follow.php">
          <input type="hidden" name="seller_id" value="<?php echo (int) $seller_id; ?>">
          <input type="hidden" name="action" value="follow">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['seller_follow_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
          <button type="submit" class="btn btn-outline-primary">Follow</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <h3 class="mt-4">Auctions</h3>
  <?php
    $auction_list_empty_message = 'This seller has no auctions yet.';
    require __DIR__ . '/auction_list.php';
  ?>
</div>

<?php if ($is_buyer && $is_following): ?>
<div class="modal fade" id="unfollowSellerModal" tabindex="-1" role="dialog" aria-labelledby="unfollowSellerModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="unfollowSellerModalLabel">Unfollow seller?</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        Stop following <?php echo htmlspecialchars($seller_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <form method="POST" action="seller_follow.php">
          <input type="hidden" name="seller_id" value="<?php echo (int) $seller_id; ?>">
          <input type="hidden" name="action" value="unfollow">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['seller_follow_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
          <button type="submit" class="btn btn-danger">Unfollow</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
include __DIR__ . '/footer.php';
?>
