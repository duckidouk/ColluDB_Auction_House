<?php include_once("header.php")?>

<div class="container">
<!-- TODO: implement redirect for non seller roles -->
<div style="max-width: 800px; margin: 10px auto">
  <h2 class="my-3">Create new auction</h2>
  <div class="alert alert-info">
    Demo seller mode: choose one of the reusable items belonging to seeded seller account 2.
  </div>

<?php
require_once __DIR__ . '/../sql/database.php';
$seller_id = 2;
$item_query = mysqli_prepare(
  $connection,
  "SELECT i.itemId, i.title
   FROM Items i
   WHERE i.sellerId = ?
     AND NOT EXISTS (
       SELECT 1
       FROM Auctions a
       WHERE a.itemId = i.itemId AND a.endDateTime > NOW()
     )
     AND NOT EXISTS (
       SELECT 1
       FROM Auctions a
       JOIN Outcomes o ON o.auctionId = a.auctionId
       JOIN OutcomeTypes ot ON ot.outcomeTypeId = o.outcomeTypeId
       WHERE a.itemId = i.itemId
         AND ot.name IN ('ITEM_SOLD_TO_HIGHEST_BIDDER', 'ITEM_SOLD_TO_BUY_NOW_BIDDER')
     )
   ORDER BY i.title"
);
mysqli_stmt_bind_param($item_query, 'i', $seller_id);
mysqli_stmt_execute($item_query);
$items = mysqli_stmt_get_result($item_query);
$selected_item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
?>

<?php if (mysqli_num_rows($items) === 0): ?>
  <div class="alert alert-info">
    You have no items available to auction. <a href="create_item.php">Add an item</a> first.
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-body">
      <form method="post" action="create_auction_result.php">
        <div class="form-group">
          <label for="itemId">Item to auction</label>
          <select class="form-control" id="itemId" name="item_id" required>
            <option value="">Choose an item...</option>
<?php while ($item = mysqli_fetch_assoc($items)): ?>
            <option value="<?php echo (int) $item['itemId']; ?>" <?php echo ($selected_item_id === (int) $item['itemId']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
            </option>
<?php endwhile; ?>
          </select>
          <small class="form-text text-muted">
            Only your items without a current or scheduled auction and without a sold outcome are listed.
          </small>
        </div>

        <div class="form-group">
          <label for="startingPrice">Starting price (£)</label>
          <input type="number" class="form-control" id="startingPrice" name="starting_price" min="0" max="99999999.99" step="0.01">
          <small class="form-text text-muted">Optional. Leave blank to start at £0.00.</small>
        </div>

        <div class="form-group">
          <label for="reservePrice">Reserve price (£)</label>
          <input type="number" class="form-control" id="reservePrice" name="reserve_price" min="0" max="99999999.99" step="0.01">
          <small class="form-text text-muted">Optional; must be at least the starting price.</small>
        </div>

        <div class="form-group">
          <label for="buyNowPrice">Buy-now price (£)</label>
          <input type="number" class="form-control" id="buyNowPrice" name="buy_now_price" min="0" max="99999999.99" step="0.01">
          <small class="form-text text-muted">Optional; must be at least the starting and reserve prices.</small>
        </div>

        <div class="form-group">
          <label for="endDateTime">End date and time</label>
          <input type="datetime-local" class="form-control" id="endDateTime" name="end_datetime" required>
          <small class="form-text text-muted">The auction starts immediately and ends at this time.</small>
        </div>

        <button type="submit" class="btn btn-primary form-control">Create Auction</button>
      </form>
    </div>
  </div>
<?php endif; ?>

  <p class="mt-3"><a href="create_item.php">Add another item</a></p>
</div>

</div>

<?php include_once("footer.php")?>
