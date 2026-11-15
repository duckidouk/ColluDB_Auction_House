<?php include_once("header.php")?>
<?php require_once __DIR__ . '/utilities.php'?>
<?php
// Duc: Connect to the MySQL database using the shared connection file
require_once __DIR__ . '/../sql/database.php';
?>

<?php
  // Get info from the URL:
  // Duc: changed from getting GET item_id to auction_id.
  // The listing should refer to an auction instead of an item directly.
  $auction_id = filter_input(INPUT_GET, 'auction_id', FILTER_VALIDATE_INT);

  // Stop if the URL does not contain a valid positive auction ID
  if ($auction_id === null || $auction_id === false || $auction_id < 1) {
    die('Invalid auction ID.');
  }

  // Describe the auction information we want SQL to retrieve
  $listing_query = "
    SELECT a.auctionId, a.itemId, a.startingPrice,
           a.startDateTime, a.endDateTime, a.reservePrice,
           a.buyNowPrice,
           i.title, i.description, i.sellerId,
           p.currentPrice, p.numBids,
           a.originalEndDateTime, o.outcomeId, ot.name AS outcomeType,
           ic.categoryID, c.name as category
    FROM Auctions a
    JOIN Items i ON i.itemId = a.itemId
    JOIN AuctionCurrentPrice p ON p.auctionId = a.auctionId
    JOIN ItemCategories ic ON ic.itemId = i.itemId
    JOIN Categories c ON ic.categoryId = c.categoryId
    LEFT JOIN Outcomes o ON o.auctionId = a.auctionId
    LEFT JOIN OutcomeTypes ot ON ot.outcomeTypeId = o.outcomeTypeId
    WHERE a.auctionId = ?
  ";
//sql query for images
  $image_query = "
    SELECT imageLocation, orderOfImage
    FROM ItemImages
    WHERE itemId = ?
    ORDER BY orderOfImage ASC
  ";

  // Prepare the SQL query so values can be added safely :)
  $listing_statement = mysqli_prepare($connection, $listing_query);

  // Put the validated auction ID into 'a.auctionId =?'
  mysqli_stmt_bind_param($listing_statement, 'i', $auction_id);

  // Run the prepared query against the DB
  mysqli_stmt_execute($listing_statement);

  // Collect the query result and read its first row
  $listing_result = mysqli_stmt_get_result($listing_statement);
  $listing = mysqli_fetch_assoc($listing_result);

  // Stop and die if no auction exists with this ID  ^ ^
  if (!$listing) {
    die('Auction not found.');
  }

  // Store the auction data returned by SQL in variables used by the webpage
  $item_id = (int) $listing['itemId'];
  $title = $listing['title'];
  $description = $listing['description'] ?? '';
  $current_price = (float) $listing['currentPrice'];
  $num_bids = (int) $listing['numBids'];
  $reserve_price = $listing['reservePrice'];
  $item_category = category_display_name($listing['category']);
  $buy_now_price = $listing['buyNowPrice'];

  // Calculate the minimum allowed bid.
  if ($num_bids === 0) {
    // No previous bid, use the starting price rounded up.
    $minimum_bid = max(1, ceil($current_price));
  } else {
    // Otherwise bid go up by 5% more and then round upward to a whole pound
    $minimum_bid = ceil($current_price * 1.05);
  }

  //Display null if there is no reserve_price
  if($reserve_price === null) {
      $reserve_price_display = 'No reserve';
  } else {
      $reserve_price = (float)$reserve_price;
      $reserve_price_display = '£' . number_format($reserve_price, 2);
  }

  //Display whether the reserve is met or not
  if ($reserve_price !== null) {
    if ($num_bids > 0 && $current_price >= $reserve_price) {
      $reserve_price_met_display = 'Reserve price met';
    } else {
      $reserve_price_met_display = 'Reserve price not met';
    }
  } else {
    $reserve_price_met_display = null;
  }

  // Display Buy Now price and button if available
  if ($buy_now_price === null) {
      $buy_now_price_display = 'Buy Now not available';
  } else {
      $buy_now_price = (float) $buy_now_price;
      $buy_now_price_display = '£' . number_format($buy_now_price, 2);
  }

  // Check whether place_bid.php requested Buy Now confirmation
  $show_buy_now_confirmation =
    isset($_SESSION['confirm_buy_now_auction']) &&
    (int) $_SESSION['confirm_buy_now_auction'] === $auction_id;

  // Make the confirmation appear only once
  unset($_SESSION['confirm_buy_now_auction']);

  $end_time = new DateTime($listing['endDateTime']);

  // Describe the bids we want to retrieve for this auction
  $bid_history_query = "
    SELECT bidId, bidderId, bidAmount, bidTimestamp
    FROM Bids
    WHERE auctionId = ?
    ORDER BY bidAmount DESC, bidTimestamp ASC, bidId ASC
  ";

  //image php retrieval.
  $image_statement = mysqli_prepare($connection, $image_query);
  mysqli_stmt_bind_param($image_statement, 'i', $item_id);
  mysqli_stmt_execute($image_statement);
  $image_result = mysqli_stmt_get_result($image_statement);

  // Prepare the bid-history query and supply the auction ID
  $bid_history_statement = mysqli_prepare($connection, $bid_history_query);
  mysqli_stmt_bind_param($bid_history_statement, 'i', $auction_id);

  // Run the query and collect all matching bids
  mysqli_stmt_execute($bid_history_statement);
  $bid_history_result = mysqli_stmt_get_result($bid_history_statement);

  // TODO: Note: Auctions that have ended may pull a different set of data,
  //       like whether the auction ended in a sale or was cancelled due
  //       to lack of high-enough bids. Or maybe not.

  // Calculate time to auction end:
  $now = new DateTime();

  if ($now < $end_time) {
    $time_to_end = date_diff($now, $end_time);
    $time_remaining = ' (in ' . display_time_remaining($time_to_end) . ')';
  }


  $has_session = true;
  $watching = false;

  // Used only to decide whether to show the Cancel / Shut down buttons below.
  $viewer_id      = current_user_id();
  $viewer_role    = $viewer_id === null ? null : user_role($connection, $viewer_id);   // null = logged out
  $already_closed = $listing['outcomeId'] !== null;   // the auction has a result (sold, cancelled, shut down...)

  // [OUTCOME CHANGE] Why did it close early? Label + colour for the badge shown next to the end time.
  $early_reasons = [
    'ITEM_SOLD_TO_BUY_NOW_BIDDER' => ['Buy Now purchase',     'info'],
    'CANCELLED_AUCTION'           => ['Cancelled by seller',  'warning'],
    'ADMIN_SHUT_DOWN'             => ['Shut down by admin',   'danger'],
  ];
  $early_reason = $early_reasons[$listing['outcomeType'] ?? ''] ?? null;   // null = ended normally
  $scheduled_end = $listing['originalEndDateTime'] !== null ? new DateTime($listing['originalEndDateTime']) : null;
  $can_cancel     = !$already_closed && $viewer_id !== null && ((int) $listing['sellerId'] === $viewer_id);   // the seller of THIS auction
  $can_shut_down  = !$already_closed && ($viewer_role === 'admin');                    // any admin
?>


<div class="container">

<div class="row"> <!-- Row #1 with auction title + watch button -->
  <div class="col-sm-8"> <!-- Left col -->
    <h2 class="my-3"><?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
  </div>
  <div class="col-sm-4 align-self-center"> <!-- Right col -->
<?php
  /* The following watchlist functionality uses JavaScript, but could
     just as easily use PHP as in other places in the code */
  if ($now < $end_time):
?>
    <div id="watch_nowatch" <?php if ($has_session && $watching) echo('style="display: none"');?> >
      <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addToWatchlist()">+ Add to watchlist</button>
    </div>
    <div id="watch_watching" <?php if (!$has_session || !$watching) echo('style="display: none"');?> >
      <button type="button" class="btn btn-success btn-sm" disabled>Watching</button>
      <button type="button" class="btn btn-danger btn-sm" onclick="removeFromWatchlist()">Remove watch</button>
    </div>
<?php endif /* Print nothing otherwise */ ?>
  </div>
</div>

<div class="row"> <!-- Row #2 with auction description + bidding info -->
  <div class="col-sm-8"> <!-- Left col with item info -->

<?php if (mysqli_num_rows($image_result) === 0): ?>
    <p class="text-muted">No images available.</p>
<?php else: ?>
    <div class="row mb-3">
<?php while ($image = mysqli_fetch_assoc($image_result)): ?>
<?php
      $image_location = ltrim($image['imageLocation'], '/');
      $image_source = str_starts_with($image_location, 'public/')
        ? '../' . $image_location
        : '../public/' . $image_location;
?>
      <div class="col-md-4 mb-3">
        <img
          src="<?php echo htmlspecialchars($image_source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
          class="img-fluid rounded"
          style="width: 100%; height: 220px; object-fit: cover;"
          alt="<?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
        >
      </div>
<?php endwhile; ?>
    </div>
<?php endif; ?>

    <div class="itemDescription">
    <?php echo htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    </div>
    <p class="text-muted mt-2">
      Category: <?php echo htmlspecialchars($item_category, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    </p>

  </div>

  <div class="col-sm-4"> <!-- Right col with bidding info -->

    <p>
<?php if ($now >= $end_time || $already_closed): /* [OUTCOME CHANGE] closed: ended on time, or closed early */ ?>
<?php if ($scheduled_end !== null): /* closed early while running: old end crossed out, new end shown */ ?>
     <del class="text-muted"><?php echo date_format($scheduled_end, 'j M H:i'); ?></del>
     <?php echo date_format($end_time, 'j M H:i'); ?>
     <small class="text-muted">(closed early)</small>
<?php elseif ($already_closed && $now < $end_time): /* closed before it started, so the dates were left alone */ ?>
     Closed before its scheduled end of <?php echo date_format($end_time, 'j M H:i'); ?>
<?php else: ?>
     This auction ended <?php echo(date_format($end_time, 'j M H:i')) ?>
<?php endif; ?>
<?php if ($early_reason): ?>
     <br><span class="badge badge-<?php echo $early_reason[1]; ?> mt-1"><?php echo $early_reason[0]; ?></span>
<?php endif; ?>
     <!-- TODO: Print the result of the auction here? -->
     </p>
<?php else: ?>
     Auction ends <?php echo(date_format($end_time, 'j M H:i') . $time_remaining) ?></p>
    <p class="lead">Current bid: £<?php echo(number_format($current_price, 2)) ?></p>
    <p>
      Reserve price: <?php echo $reserve_price_display; ?>
<?php if ($reserve_price_met_display !== null): ?>
      <small class="text-muted ml-2"><?php echo $reserve_price_met_display; ?></small>
<?php endif; ?>
    </p>

    <!-- Bidding form -->


    <!--buy now alert -->
    <?php if ($show_buy_now_confirmation): ?>
      <div class="alert alert-warning">
        <p>
          Your bid reached the Buy Now price of
          <?php echo $buy_now_price_display; ?>.
          Would you prefer to Buy Now?
        </p>

        <form method="POST" action="place_bid.php">
          <input
            type="hidden"
            name="auction_id"
            value="<?php echo (int) $auction_id; ?>"
          >
          <input type="hidden" name="action" value="buy_now">

          <button type="submit" class="btn btn-success">
            Yes, Buy Now
          </button>

          <a
            href="listing.php?auction_id=<?php echo (int) $auction_id; ?>"
            class="btn btn-secondary"
          >
            No, continue bidding
          </a>
        </form>
      </div>
    <?php endif; ?>

    <form method="POST" action="place_bid.php">
      <input type="hidden" name="auction_id" value="<?php echo (int) $auction_id; ?>">

      <div class="input-group">
        <div class="input-group-prepend">
          <span class="input-group-text">£</span>
        </div>
        <input
          type="number"
          class="form-control"
          id="bid"
          name="bid_amount"
          min="<?php echo (int) $minimum_bid; ?>"
          step="1"
          required
        >
        </div>

        <small class="form-text text-muted">
          Minimum bid: £<?php echo number_format($minimum_bid, 0); ?>
        </small>

      <button type="submit" class="btn btn-primary form-control">Place bid</button>
    </form>

<?php if ($viewer_role === 'buyer'): ?>
<?php if ($buy_now_price !== null): ?>
    <form method="POST" action="place_bid.php" class="mt-3"
          onsubmit="return confirm('Are you sure you want to Buy Now for <?php echo $buy_now_price_display; ?>?');">
      <input type="hidden" name="auction_id" value="<?php echo (int) $auction_id; ?>">
      <input type="hidden" name="action" value="buy_now">
      <button type="submit" class="btn btn-success form-control">
        Buy Now for <?php echo $buy_now_price_display; ?>
      </button>
    </form>
<?php else: ?>
    <button type="button" class="btn btn-secondary form-control mt-3" disabled>
      <?php echo $buy_now_price_display; ?>
    </button>
<?php endif; ?>
<?php endif; ?>

<?php if ($can_cancel): /* [OUTCOME CHANGE] seller cancels their own auction */ ?>
    <form method="POST" action="cancel_auction.php" class="mt-3"
          onsubmit="return confirm('Cancel this auction? Every bidder will be notified and their bids will not be taken.');">
      <input type="hidden" name="auction_id" value="<?php echo (int) $auction_id; ?>">
      <button type="submit" class="btn btn-outline-danger form-control">Cancel this auction</button>
    </form>
<?php endif; ?>

<?php if ($can_shut_down): /* [OUTCOME CHANGE] admin shuts down any auction */ ?>
    <form method="POST" action="admin_shutdown.php" class="mt-3"
          onsubmit="return confirm('Shut down this auction? The seller and every bidder will be notified.');">
      <input type="hidden" name="auction_id" value="<?php echo (int) $auction_id; ?>">
      <button type="submit" class="btn btn-danger form-control">Shut down this auction (admin)</button>
    </form>
<?php endif; ?>
<?php endif ?>


  </div> <!-- End of right col with bidding info -->

</div> <!-- End of row #2 -->

<div class="row mt-4">
  <div class="col-12">
    <h3>Bid history</h3>

<?php if (mysqli_num_rows($bid_history_result) === 0): ?>
    <p>No bids have been placed yet.</p>
<?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Bidder</th>
          <th>Amount</th>
          <th>Time</th>
        </tr>
      </thead>
      <tbody>
<?php while ($bid = mysqli_fetch_assoc($bid_history_result)): ?>
        <tr>
          <td>Bidder <?php echo (int) $bid['bidderId']; ?></td>
          <td>£<?php echo number_format((float) $bid['bidAmount'], 2); ?></td>
          <td><?php echo htmlspecialchars($bid['bidTimestamp'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
        </tr>
<?php endwhile; ?>
      </tbody>
    </table>
<?php endif; ?>

  </div>
</div>

</div> <!-- End of main container -->

<script>
// JavaScript functions: addToWatchlist and removeFromWatchlist.

function addToWatchlist(button) {
  console.log("These print statements are helpful for debugging btw");

  // This performs an asynchronous call to a PHP function using POST method.
  // Sends item ID as an argument to that function.
  $.ajax('watchlist_funcs.php', {
    type: "POST",
    data: {functionname: 'add_to_watchlist', arguments: [<?php echo($item_id);?>]},

    success:
      function (obj, textstatus) {
        // Callback function for when call is successful and returns obj
        console.log("Success");
        var objT = obj.trim();

        if (objT == "success") {
          $("#watch_nowatch").hide();
          $("#watch_watching").show();
        }
        else {
          var mydiv = document.getElementById("watch_nowatch");
          mydiv.appendChild(document.createElement("br"));
          mydiv.appendChild(document.createTextNode("Add to watch failed. Try again later."));
        }
      },

    error:
      function (obj, textstatus) {
        console.log("Error");
      }
  }); // End of AJAX call

} // End of addToWatchlist func

function removeFromWatchlist(button) {
  // This performs an asynchronous call to a PHP function using POST method.
  // Sends item ID as an argument to that function.
  $.ajax('watchlist_funcs.php', {
    type: "POST",
    data: {functionname: 'remove_from_watchlist', arguments: [<?php echo($item_id);?>]},

    success:
      function (obj, textstatus) {
        // Callback function for when call is successful and returns obj
        console.log("Success");
        var objT = obj.trim();

        if (objT == "success") {
          $("#watch_watching").hide();
          $("#watch_nowatch").show();
        }
        else {
          var mydiv = document.getElementById("watch_watching");
          mydiv.appendChild(document.createElement("br"));
          mydiv.appendChild(document.createTextNode("Watch removal failed. Try again later."));
        }
      },

    error:
      function (obj, textstatus) {
        console.log("Error");
      }
  }); // End of AJAX call

} // End of addToWatchlist func
</script>

<?php include_once("footer.php")?>
