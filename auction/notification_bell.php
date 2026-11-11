<?php
/*
// Notification bell icon done in HTML. Calls upon the fetch_notifications function (SQL code that is in notification funcs)
// Also calls upon unread_count (SQL query for all unread notifications in notification _funcs file).
// 
 */
require_once __DIR__ . '/notification_funcs.php';

$bell_user   = current_user_id();
$bell_rows   = fetch_notifications($connection, $bell_user, 8);
$bell_unread = unread_count($connection, $bell_user);
$bell_role   = user_role($connection, $bell_user);   // admins have no email preferences page
$bell_return = basename((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH))
             . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);
?>
<li class="nav-item dropdown mr-2">
  <a class="nav-link position-relative" href="#" id="bellDropdown" role="button"
     data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Notifications">
    <i class="fa fa-bell fa-lg"></i>
<?php if ($bell_unread > 0): ?>
    <span class="badge badge-warning badge-pill" style="position:absolute;top:0;right:-4px;font-size:.7rem"><?php echo $bell_unread > 9 ? '9+' : $bell_unread; ?></span>
<?php endif; ?>
  </a>

  <div class="dropdown-menu dropdown-menu-right p-0 shadow" aria-labelledby="bellDropdown" style="width:420px;max-width:92vw">
    <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
      <h6 class="mb-0">Notifications</h6>
<?php if ($bell_unread > 0): ?>
      <form method="post" action="mark_read.php" class="mb-0">
        <input type="hidden" name="all" value="1">
        <input type="hidden" name="return" value="<?php echo htmlspecialchars($bell_return, ENT_QUOTES); ?>">
        <button class="btn btn-link btn-sm p-0">Mark all as read</button>
      </form>
<?php endif; ?>
    </div>

    <div style="max-height:60vh;overflow-y:auto">
<?php if (!$bell_rows): ?>
      <div class="p-3 text-muted">You have no notifications yet.</div>
<?php else: foreach ($bell_rows as $bell_row) { echo notification_item_html($bell_row); } endif; ?>
    </div>

    <div class="d-flex justify-content-between p-2 small">
      <a class="dropdown-item w-auto" href="notifications.php">See all notifications</a>
<?php if ($bell_role !== 'admin'): ?>
      <a class="dropdown-item w-auto" href="preferences.php">Email preferences</a>
<?php endif; ?>
    </div>
  </div>
</li>
