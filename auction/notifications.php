<?php include_once("header.php") ?>

<div class="container my-4">
  <h2 class="my-3">Notifications</h2>
<?php
  // Every notification the user has ever received (the bell only shows the latest 8).
  $rows = fetch_notifications($connection, current_user_id(), 100); //found in nofitification_funcs php
  if (!$rows) {
    echo '<div class="alert alert-secondary">You have no notifications yet.</div>';
  } else {
    echo '<div class="border rounded">';
    foreach ($rows as $r) { echo notification_item_html($r); } //found in nofitification_funcs php
    echo '</div>';
  }
?>
</div>

<?php include_once("footer.php") ?>
