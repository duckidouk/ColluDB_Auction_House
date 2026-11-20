<?php
require_once __DIR__ . '/lib/utilities.php';
require_once __DIR__ . '/../sql/database.php';
require_once __DIR__ . '/lib/auction_queries.php';
require_once __DIR__ . '/lib/notification_funcs.php';
ensure_session_started();
require_authenticated_session($connection);

$profile_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT, [
  'options' => ['min_range' => 1],
]);
if ($profile_id === null || $profile_id === false) {
  http_response_code(404);
  exit('User not found.');
}

$profile_statement = mysqli_prepare(
  $connection,
  'SELECT u.firstName, u.lastName, r.name AS roleName,
          EXISTS (
            SELECT 1 FROM UserBans ub
             WHERE ub.userId = u.userId AND ub.bannedSince <= NOW()
               AND (ub.bannedUntil IS NULL OR ub.bannedUntil > NOW())
          ) AS isBanned,
          EXISTS (
            SELECT 1 FROM UserBans ub
             WHERE ub.userId = u.userId AND ub.bannedSince <= NOW()
               AND ub.bannedUntil IS NULL
          ) AS isPermanentlyBanned,
          (SELECT MAX(ub.bannedUntil)
             FROM UserBans ub
            WHERE ub.userId = u.userId AND ub.bannedSince <= NOW()
              AND ub.bannedUntil > NOW()) AS activeBanUntil
     FROM UserAccounts u
     JOIN Roles r ON r.roleId = u.roleId
    WHERE u.userId = ?'
);
mysqli_stmt_bind_param($profile_statement, 'i', $profile_id);
mysqli_stmt_execute($profile_statement);
$profile = mysqli_stmt_get_result($profile_statement)->fetch_assoc();
mysqli_stmt_close($profile_statement);

if (!$profile) {
  http_response_code(404);
  exit('User not found.');
}

$profile_name = trim($profile['firstName'] . ' ' . $profile['lastName']);
$is_seller_profile = $profile['roleName'] === 'seller';
$is_admin_profile = $profile['roleName'] === 'admin';
$viewer_id = current_user_id();
$viewer_role = $viewer_id === null ? null : user_role($connection, $viewer_id);
$is_buyer_viewer = $viewer_role === 'buyer';
$is_admin_viewer = $viewer_role === 'admin';
$is_viewer_following_seller_profile = false;

if ($is_buyer_viewer && $is_seller_profile) {
  $follow_statement = mysqli_prepare(
    $connection,
    'SELECT 1 FROM FavouriteSellers WHERE followerId = ? AND sellerId = ?'
  );
  mysqli_stmt_bind_param($follow_statement, 'ii', $viewer_id, $profile_id);
  mysqli_stmt_execute($follow_statement);
  $is_viewer_following_seller_profile = mysqli_stmt_get_result($follow_statement)->num_rows > 0;
  mysqli_stmt_close($follow_statement);
  if (!isset($_SESSION['seller_follow_csrf']) || !is_string($_SESSION['seller_follow_csrf'])) {
    $_SESSION['seller_follow_csrf'] = bin2hex(random_bytes(32));
  }
}

$can_admin_viewer_ban_profile = $is_admin_viewer && !$is_admin_profile;
$is_profile_banned = (bool) $profile['isBanned'];
$is_profile_permanently_banned = (bool) $profile['isPermanentlyBanned'];
if (
  $can_admin_viewer_ban_profile
  && !$is_profile_banned
  && (!isset($_SESSION['admin_ban_csrf']) || !is_string($_SESSION['admin_ban_csrf']))
) {
  $_SESSION['admin_ban_csrf'] = bin2hex(random_bytes(32));
}

$results = $is_seller_profile ? fetch_seller_auctions($connection, $profile_id) : null;

include __DIR__ . '/header.php';
?>

<div class="container">
  <div class="d-flex justify-content-between align-items-center my-3">
    <h2 class="mb-0"><?php echo htmlspecialchars($profile_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
    <div class="d-flex align-items-center">
      <?php if ($is_buyer_viewer && $is_seller_profile): ?>
        <?php if ($is_viewer_following_seller_profile): ?>
          <button type="button" class="btn btn-success mr-2" data-toggle="modal" data-target="#unfollowSellerModal">
            Following
          </button>
        <?php else: ?>
          <form method="POST" action="seller_follow.php" class="mr-2">
            <input type="hidden" name="seller_id" value="<?php echo (int) $profile_id; ?>">
            <input type="hidden" name="action" value="follow">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['seller_follow_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="btn btn-outline-primary">Follow</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($can_admin_viewer_ban_profile && $is_profile_banned): ?>
        <span class="badge badge-danger mr-2">
          <?php if ($is_profile_permanently_banned): ?>
            Banned Permanently
          <?php else: ?>
            Banned Until <?php echo htmlspecialchars((new DateTime($profile['activeBanUntil']))->format('j M Y H:i'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
          <?php endif; ?>
        </span>
      <?php elseif ($can_admin_viewer_ban_profile): ?>
        <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#banUserModal">
          Ban user
        </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($is_seller_profile): ?>
    <h3 class="mt-4">Auctions</h3>
    <?php
      $auction_list_empty_message = 'This seller has no auctions yet.';
      require __DIR__ . '/auction_list.php';
    ?>
  <?php endif; ?>
</div>

<?php if ($is_buyer_viewer && $is_seller_profile && $is_viewer_following_seller_profile): ?>
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
        Stop following <?php echo htmlspecialchars($profile_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <form method="POST" action="seller_follow.php">
          <input type="hidden" name="seller_id" value="<?php echo (int) $profile_id; ?>">
          <input type="hidden" name="action" value="unfollow">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['seller_follow_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
          <button type="submit" class="btn btn-danger">Unfollow</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($can_admin_viewer_ban_profile && !$is_profile_banned): ?>
<div class="modal fade" id="banUserModal" tabindex="-1" role="dialog" aria-labelledby="banUserModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="POST" action="admin_ban.php">
        <div class="modal-header">
          <h5 class="modal-title" id="banUserModalLabel">Ban <?php echo htmlspecialchars($profile_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="user_id" value="<?php echo (int) $profile_id; ?>">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_ban_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
          <div class="form-group">
            <label for="banDuration">Duration</label>
            <select class="form-control" id="banDuration" name="duration" required>
              <option value="1">1 day</option>
              <option value="7">7 days</option>
              <option value="30">30 days</option>
              <option value="permanent">Permanent</option>
            </select>
          </div>
          <div class="form-group mb-0">
            <label for="banReason">Reason (optional)</label>
            <textarea class="form-control" id="banReason" name="reason" maxlength="255" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Apply ban</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
