<?php
require_once __DIR__ . '/utilities.php';
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
  require_once __DIR__ . '/../sql/database.php';

  $stmt = mysqli_prepare(
    $connection,
    "SELECT a.auctionId, a.startDateTime, a.endDateTime,
            i.title, i.description, p.currentPrice, p.numBids,
            (SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ')
               FROM ItemCategories ic
               JOIN Categories c ON c.categoryId = ic.categoryId
              WHERE ic.itemId = i.itemId) AS categories
       FROM Auctions a
       JOIN Items i ON i.itemId = a.itemId
       JOIN AuctionCurrentPrice p ON p.auctionId = a.auctionId
      WHERE i.sellerId = ?
      ORDER BY
        CASE
          WHEN a.startDateTime <= NOW() AND a.endDateTime > NOW() THEN 0
          WHEN a.startDateTime > NOW() THEN 1
          ELSE 2
        END,
        CASE
          WHEN a.startDateTime <= NOW() AND a.endDateTime > NOW() THEN a.endDateTime
          WHEN a.startDateTime > NOW() THEN a.startDateTime
        END ASC,
        CASE WHEN a.endDateTime <= NOW() THEN a.endDateTime END DESC,
        a.auctionId DESC"
  );
  mysqli_stmt_bind_param($stmt, 'i', $seller_id);
  mysqli_stmt_execute($stmt);
  $results = mysqli_stmt_get_result($stmt);

?>

<?php
  $auction_list_empty_message = 'You have no auctions yet.';
  require __DIR__ . '/auction_list.php';
?>

<?php include_once("footer.php")?>