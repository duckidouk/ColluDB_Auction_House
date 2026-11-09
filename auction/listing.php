<?php include_once("header.php")?>
<?php require("utilities.php")?>
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

  // Describe the auction information we want MySQL to retrieve
  $listing_query = "
    SELECT a.auctionId, a.itemId, a.startingPrice,
           a.startDateTime, a.endDateTime,
           i.title, i.description, i.sellerId,
           p.currentPrice, p.numBids
    FROM Auctions a
    JOIN Items i ON i.itemId = a.itemId
    JOIN AuctionCurrentPrice p ON p.auctionId = a.auctionId
    WHERE a.auctionId = ?
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

  // Calculate the minimum allowed bid.
  if ($num_bids === 0) {
    // No previous bid, use the starting price rounded up.
    $minimum_bid = max(1, ceil($current_price));
  } else {
    // Otherwise bid go up by 5% more and then round upward to a whole pound
    $minimum_bid = ceil($current_price * 1.05);
  }

  $end_time = new DateTime($listing['endDateTime']);

  // Describe the bids we want to retrieve for this auction
  $bid_history_query = "
    SELECT bidId, bidderId, bidAmount, bidTimestamp
    FROM Bids
    WHERE auctionId = ?
    ORDER BY bidAmount DESC, bidTimestamp ASC, bidId ASC
  ";

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

  // TODO: If the user has a session, use it to make a query to the database
  //       to determine if the user is already watching this item.
  //       For now, this is hardcoded.
  $has_session = true;
  $watching = false;
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

    <div class="itemDescription">
    <?php echo htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    </div>

  </div>

  <div class="col-sm-4"> <!-- Right col with bidding info -->

    <p>
<?php if ($now >= $end_time): ?>
     This auction ended <?php echo(date_format($end_time, 'j M H:i')) ?>
     <!-- TODO: Print the result of the auction here? -->
<?php else: ?>
     Auction ends <?php echo(date_format($end_time, 'j M H:i') . $time_remaining) ?></p>
    <p class="lead">Current bid: £<?php echo(number_format($current_price, 2)) ?></p>

    <!-- Bidding form -->
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
