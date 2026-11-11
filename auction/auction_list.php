<?php
$emptyMessage = $auction_list_empty_message ?? 'No auctions found.';
if (mysqli_num_rows($results) === 0):
?>
  <div class="alert alert-info"><?php echo htmlspecialchars($emptyMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
<?php else: ?>
<ul class="list-group">
<?php
$now = new DateTimeImmutable();
while ($row = mysqli_fetch_assoc($results)):
  $startTime = new DateTimeImmutable($row['startDateTime']);
  $endTime = new DateTimeImmutable($row['endDateTime']);

  if ($startTime > $now) {
    $status = 'Scheduled';
    $statusClass = 'info';
    $statusTime = 'Starting in ' . display_time_remaining($now->diff($startTime));
  } elseif ($endTime > $now) {
    $status = 'Live';
    $statusClass = 'success';
    $statusTime = 'Ending in ' . display_time_remaining($now->diff($endTime));
  } else {
    $status = 'Ended';
    $statusClass = 'secondary';
    $statusTime = 'Ended ' . display_time_remaining($endTime->diff($now)) . ' ago';
  }

  $description = $row['description'] ?? '';
  if (strlen($description) > 250) {
    $description = substr($description, 0, 250) . '...';
  }
  $categories = $row['categories'] ? explode(', ', $row['categories']) : [];
?>
  <li class="list-group-item d-flex justify-content-between">
    <div class="p-2 mr-5">
      <h5>
        <a href="listing.php?auction_id=<?php echo (int) $row['auctionId']; ?>">
          <?php echo htmlspecialchars($row['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </a>
        <span class="badge badge-<?php echo $statusClass; ?>"><?php echo $status; ?></span>
      </h5>
      <p class="mb-1"><?php echo htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php if ($categories): ?>
      <div class="mt-2">
        <?php foreach ($categories as $category): ?>
        <span class="badge badge-secondary"><?php echo htmlspecialchars(category_display_name($category), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <small class="text-muted"><?php echo htmlspecialchars($statusTime, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></small>
    </div>
    <div class="text-center text-nowrap">
      <span style="font-size: 1.5em">&pound;<?php echo number_format((float) $row['currentPrice'], 2); ?></span><br>
      <?php echo (int) $row['numBids'] . ((int) $row['numBids'] === 1 ? ' bid' : ' bids'); ?>
    </div>
  </li>
<?php endwhile; ?>
</ul>
<?php endif; ?>
