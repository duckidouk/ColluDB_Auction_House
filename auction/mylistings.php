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
    "SELECT a.auctionId, a.itemId, a.startDateTime, a.endDateTime,
            i.title, i.description, p.currentPrice, p.numBids
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

  function mylistings_h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
?>

<?php if (mysqli_num_rows($results) === 0): ?>
  <div class="alert alert-info">You have no auctions yet.</div>
<?php else: ?>
<ul class="list-group">
<?php
  $now = new DateTimeImmutable();
  while ($row = mysqli_fetch_assoc($results)):
    $start_time = new DateTimeImmutable($row['startDateTime']);
    $end_time = new DateTimeImmutable($row['endDateTime']);

    if ($start_time > $now) {
      $status = 'Scheduled';
      $status_class = 'info';
      $status_time = 'Starting in ' . display_time_remaining(date_diff($now, $start_time));
    } elseif ($end_time > $now) {
      $status = 'Live';
      $status_class = 'success';
      $status_time = 'Ending in ' . display_time_remaining(date_diff($now, $end_time));
    } else {
      $status = 'Ended';
      $status_class = 'secondary';
      $status_time = 'Ended ' . display_time_remaining(date_diff($end_time, $now)) . ' ago';
    }

    $description = $row['description'] ?? '';
    if (strlen($description) > 250) {
      $description = substr($description, 0, 250) . '...';
    }
    $num_bids = (int) $row['numBids'];
?>
  <li class="list-group-item d-flex justify-content-between">
    <div class="p-2 mr-5">
      <h5>
        <a href="listing.php?item_id=<?php echo (int) $row['itemId']; ?>">
          <?php echo mylistings_h($row['title']); ?>
        </a>
        <span class="badge badge-<?php echo $status_class; ?>"><?php echo $status; ?></span>
      </h5>
      <p class="mb-1"><?php echo mylistings_h($description); ?></p>
      <small class="text-muted"><?php echo mylistings_h($status_time); ?></small>
    </div>
    <div class="text-center text-nowrap">
      <span style="font-size: 1.5em">&pound;<?php echo number_format((float) $row['currentPrice'], 2); ?></span><br>
      <?php echo $num_bids . ($num_bids === 1 ? ' bid' : ' bids'); ?>
    </div>
  </li>
<?php endwhile; ?>
</ul>
<?php endif; ?>

<?php include_once("footer.php")?>