<?php
/* admin_shutdown.php - receives a POST from a button on listing.php, runs the action, goes back to the listing. */
require_once __DIR__ . '/outcomes.php';

$userId = current_user_id();
$id     = (int) ($_POST['auction_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
  header('Location: browse.php'); exit();
}

$res = admin_shut_down_auction($connection, $id, $userId);
header('Location: listing.php?auction_id=' . $id . '&msg=' . urlencode($res['message']) . '&ok=' . (int) $res['ok']);
exit();
