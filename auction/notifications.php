<?php include_once("header.php") ?>

<div class="container my-4">
  <h2 class="my-3">Notifications</h2>
<?php
  $userId = current_user_id();
  if ($userId === null) {
    echo '<div class="alert alert-info">Log in to view your notifications.</div>';
  } else {
    $rows = fetch_notifications($connection, $userId, 100);
    if (!$rows) {
      echo '<div class="alert alert-secondary">You have no notifications yet.</div>';
    } else {
      echo '<div class="border rounded">';
      foreach ($rows as $row) {
        echo notification_item_html($row);
      }
      echo '</div>';
    }
  }
?>
</div>

<?php include_once("footer.php") ?>
