<?php

// Start or continue the user's PHP session
session_start();

// make database errors throw exceptions that we can catch safely
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Connect to the MySQL database.
require_once __DIR__ . '/../sql/database.php';

// Only allow this file to process submitted forms
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: browse.php');
  exit();
}


// Read + validate the auction ID and whole £ bid amount
$auction_id = filter_input(INPUT_POST, 'auction_id', FILTER_VALIDATE_INT);
$bid_amount = filter_input(INPUT_POST, 'bid_amount', FILTER_VALIDATE_INT);

// if the submitted auction ID is invalid you die
if ($auction_id === null || $auction_id === false || $auction_id < 1) {
  die('Please provide a valid auction ID.');
}

// if the submitted bid is not a positive whole amount you die
if ($bid_amount === null || $bid_amount === false || $bid_amount < 1) {
  die('Please provide a valid whole-pound bid amount.');
}

// TO CHANGE: demo the buyer until the login feature stores real user ID
$bidder_id = 5;


$transaction_started = false;

try {
    // start a transaction so the auction can be checked and updated safely
    mysqli_begin_transaction($connection);
    $transaction_started = true;

    // retrieve and lock this auction while the bid is being checked

    $auction_query = "
    SELECT a.auctionId, a.startingPrice, a.buyNowPrice,
           a.startDateTime, a.endDateTime,
           NOW() AS databaseNow,
           i.sellerId, o.outcomeId
    FROM Auctions a
      JOIN Items i ON i.itemId = a.itemId
      LEFT JOIN Outcomes o ON o.auctionId = a.auctionId
      WHERE a.auctionId = ?
      FOR UPDATE
    ";

    // Prepare the query and put the auction ID into the '?'
    $auction_statement = mysqli_prepare($connection, $auction_query);
    mysqli_stmt_bind_param($auction_statement, 'i', $auction_id);

    // run the query and read the auction row
    mysqli_stmt_execute($auction_statement);
    $auction_result = mysqli_stmt_get_result($auction_statement);
    $auction = mysqli_fetch_assoc($auction_result);

    // I. check that the auction exists.
    if (!$auction) {
      mysqli_rollback($connection);
      die('Auction not found.');
    }

    // Retrieve the demo buyer's account type and active-ban status.
    $buyer_query = "
      SELECT u.userId, r.name AS roleName,
             EXISTS (
               SELECT 1
               FROM UserBans ub
               WHERE ub.userId = u.userId
                 AND ub.bannedSince <= NOW()
                 AND (ub.bannedUntil IS NULL OR ub.bannedUntil > NOW())
             ) AS isBanned
      FROM UserAccounts u
      JOIN Roles r ON r.roleId = u.roleId
      WHERE u.userId = ?
    ";

    $buyer_statement = mysqli_prepare($connection, $buyer_query);
    mysqli_stmt_bind_param($buyer_statement, 'i', $bidder_id);
    mysqli_stmt_execute($buyer_statement);

    $buyer_result = mysqli_stmt_get_result($buyer_statement);
    $buyer = mysqli_fetch_assoc($buyer_result);

    // II. checks that the buyer exists, checks for buyer role
    if (!$buyer || $buyer['roleName'] !== 'buyer') {
      mysqli_rollback($connection);
      die('Only a valid buyer account can place a bid.');
    }

    // III. reject a banned user trying to buy
    if ((int) $buyer['isBanned'] === 1) {
      mysqli_rollback($connection);
      die('This buyer account is currently banned from bidding.');
    }

    // IV. dies if the seller bids on their own item
    if ((int) $auction['sellerId'] === $bidder_id) {
      mysqli_rollback($connection);
      die('You cannot bid on your own item.');
    }

    // Use the database clock for auction-time checks
    $database_now = new DateTime($auction['databaseNow']);
    $start_time = new DateTime($auction['startDateTime']);
    $end_time = new DateTime($auction['endDateTime']);

    // V. reject bids before the auction starts
    if ($database_now < $start_time) {
      mysqli_rollback($connection);
      die('This auction has not started yet.');
    }

    // VI. reject bids at or after the auction end time
    if ($database_now >= $end_time) {
      mysqli_rollback($connection);
      die('This auction has already ended.');
    }
    // VII. reject an auction that already has a final outcome
    if ($auction['outcomeId'] !== null) {
      mysqli_rollback($connection);
      die('This auction is already closed.');
    }

    // read the latest price after locking the auction
    $price_query = "
      SELECT currentPrice, numBids,
             CASE
               WHEN numBids = 0 THEN GREATEST(1, CEIL(currentPrice))
               ELSE CEIL(currentPrice * 1.05)
             END AS minimumBid
      FROM AuctionCurrentPrice
      WHERE auctionId = ?
    ";

    $price_statement = mysqli_prepare($connection, $price_query);
    mysqli_stmt_bind_param($price_statement, 'i', $auction_id);
    mysqli_stmt_execute($price_statement);

    $price_result = mysqli_stmt_get_result($price_statement);
    $price = mysqli_fetch_assoc($price_result);

    // VIII. recalculate the minimum using the latest database price
    $minimum_bid = (int) $price['minimumBid'];

    // reject a bid below the required 5% whole-pound increase.
    if ($bid_amount < $minimum_bid) {
      mysqli_rollback($connection);
      die(
        'Your bid is too low. The minimum bid is £' .
        number_format($minimum_bid, 0) .
        '.'
      );
    }


    // Keep Buy Now separate from ordinary bidding.
    if (
      $auction['buyNowPrice'] !== null &&
      $bid_amount >= (float) $auction['buyNowPrice']
    ) {
      mysqli_rollback($connection);
      die('This amount reaches the Buy Now price. Use the Buy Now option instead.');
    }

    // insert the valid bid as a new database row
    $insert_query = "
      INSERT INTO Bids (auctionId, bidderId, bidAmount)
      VALUES (?, ?, ?)
    ";

    $insert_statement = mysqli_prepare($connection, $insert_query);
    mysqli_stmt_bind_param(
      $insert_statement,
      'iid',
      $auction_id,
      $bidder_id,
      $bid_amount
    );

    mysqli_stmt_execute($insert_statement);

    // make the new bid permanent and release the auction lock
    mysqli_commit($connection);
    $transaction_started = false;

} catch (mysqli_sql_exception $exception) {
    // cancel unfinished database work if an unexpected SQL error occurs.
    if ($transaction_started) {
      mysqli_rollback($connection);
    }

    // record technical details privately in the server log
    error_log('Bid placement failed: ' . $exception->getMessage());

    // show the buyer a safe message without exposing database details
    die('The bid could not be saved. Please try again.');
}

// return to the auction so the new price and history appear
header('Location: listing.php?auction_id=' . $auction_id);
exit();
