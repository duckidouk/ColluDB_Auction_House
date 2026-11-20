<?php
require_once __DIR__ . '/lib/utilities.php';
require_once __DIR__ . '/../sql/database.php';
ensure_session_started();
require_authenticated_session($connection);
$seller_id = current_user_id();
if ($seller_id === null) {
  header('Location: browse.php');
  exit();
}
include_once __DIR__ . '/header.php';
?>

<div class="container">

<h2 class="my-3">My listings</h2>

<?php
  require_once __DIR__ . '/lib/auction_queries.php';
  $results = fetch_seller_auctions($connection, $seller_id);
?>

<?php
  $auction_list_empty_message = 'You have no auctions yet.';
  require __DIR__ . '/auction_list.php';
?>

<?php include_once("footer.php")?>