<?php
/*
 * mark_read.php - marks notifications as read, then sends the user back.
 *   POST notif_id=N   -> mark that one read, go back
 *   POST all=1        -> mark all read, go back
 *   GET  open=N       -> mark that one read, open its auction (or the notifications page)
 */
require_once __DIR__ . '/notification_funcs.php';

$uid = current_user_id();

// only redirect to our own simple page names, never to another website
function safe_return(string $r): string {
  return preg_match('/^[A-Za-z0-9_\-]+\.php(\?[A-Za-z0-9_=&\-\.%]*)?$/', $r) ? $r : 'notifications.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!empty($_POST['all']))          { mark_notifications_read($connection, $uid); }
  elseif (!empty($_POST['notif_id'])) { mark_notifications_read($connection, $uid, (int) $_POST['notif_id']); }
  header('Location: ' . safe_return((string) ($_POST['return'] ?? ''))); exit();
}

if (!empty($_GET['open'])) {
  $id  = (int) $_GET['open'];
  $row = db_row($connection, 'SELECT auctionId FROM Notifications WHERE notifId = ? AND receiverId = ?', [$id, $uid]);
  if ($row) {
    mark_notifications_read($connection, $uid, $id);
    header('Location: ' . ($row['auctionId'] ? 'listing.php?auction_id=' . (int) $row['auctionId'] : 'notifications.php')); exit();
  }
}
header('Location: notifications.php');
