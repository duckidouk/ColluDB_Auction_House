<?php
require_once __DIR__ . '/utilities.php';
ensure_session_started();
$seller_id = current_user_id();
if ($seller_id === null || ($_SESSION['account_type'] ?? null) !== 'seller') {
  header('Location: browse.php');
  exit();
}
require_once __DIR__ . '/../sql/database.php';

$errors = [];
$auction_id = null;
$item_id = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT);
$transaction_started = false;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $errors[] = 'Submit the auction form to continue.';
} else {
  $starting_input = trim((string) ($_POST['starting_price'] ?? ''));
  $reserve_input = trim((string) ($_POST['reserve_price'] ?? ''));
  $buy_now_input = trim((string) ($_POST['buy_now_price'] ?? ''));
  $start_input = (string) ($_POST['start_datetime'] ?? '');
  $end_input = (string) ($_POST['end_datetime'] ?? '');
  $money_pattern = '/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/D';
  $starting_price = $starting_input === ''
    ? 0.0
    : (preg_match($money_pattern, $starting_input) ? (float) $starting_input : false);
  $reserve_price = $reserve_input === ''
    ? null
    : (preg_match($money_pattern, $reserve_input) ? (float) $reserve_input : false);
  $buy_now_price = $buy_now_input === ''
    ? null
    : (preg_match($money_pattern, $buy_now_input) ? (float) $buy_now_input : false);
  $start_date = DateTime::createFromFormat('!Y-m-d\TH:i', $start_input);
  $start_date_errors = DateTime::getLastErrors();
  $end_date = DateTime::createFromFormat('!Y-m-d\TH:i', $end_input);
  $end_date_errors = DateTime::getLastErrors();

  if ($item_id === false || $item_id === null || $item_id < 1) {
    $errors[] = 'Choose a valid item.';
  }
  if ($starting_price === false) {
    $errors[] = 'Enter a starting price from £0.00 to £99,999,999.99 with at most two decimal places, or leave it blank for £0.00.';
  }
  if ($reserve_price === false) {
    $errors[] = 'Enter a valid reserve price or leave it blank.';
  } elseif ($reserve_price !== null && $starting_price !== false && $reserve_price < $starting_price) {
    $errors[] = 'The reserve price must be at least the starting price.';
  }
  if ($buy_now_price === false) {
    $errors[] = 'Enter a valid buy-now price or leave it blank.';
  } elseif ($buy_now_price !== null && $starting_price !== false && $buy_now_price < $starting_price) {
    $errors[] = 'The buy-now price must be at least the starting price.';
  } elseif ($buy_now_price !== null && $reserve_price !== false && $reserve_price !== null && $buy_now_price < $reserve_price) {
    $errors[] = 'The buy-now price must be at least the reserve price.';
  }
  if (
    !$start_date ||
    $start_date->format('Y-m-d\TH:i') !== $start_input ||
    ($start_date_errors !== false && ($start_date_errors['warning_count'] > 0 || $start_date_errors['error_count'] > 0))
  ) {
    $errors[] = 'Choose a valid start date and time.';
  }
  if (
    !$end_date ||
    $end_date->format('Y-m-d\TH:i') !== $end_input ||
    ($end_date_errors !== false && ($end_date_errors['warning_count'] > 0 || $end_date_errors['error_count'] > 0)) ||
    $end_date <= new DateTime()
  ) {
    $errors[] = 'Choose a valid end date and time in the future.';
  }
  if ($start_date && $end_date && $start_date >= $end_date) {
    $errors[] = 'The start time must be before the end time.';
  }

  if (!$errors) {
    try {
      mysqli_begin_transaction($connection);
      $transaction_started = true;

      $owned_item = mysqli_prepare(
        $connection,
        'SELECT itemId FROM Items WHERE itemId = ? AND sellerId = ? FOR UPDATE'
      );
      mysqli_stmt_bind_param($owned_item, 'ii', $item_id, $seller_id);
      mysqli_stmt_execute($owned_item);
      if (!mysqli_fetch_assoc(mysqli_stmt_get_result($owned_item))) {
        $errors[] = 'That item does not belong to the demo seller.';
      } else {
        $available_item = mysqli_prepare(
          $connection,
          "SELECT i.itemId
           FROM Items i
           WHERE i.itemId = ? AND i.sellerId = ?
             AND NOT EXISTS (
               SELECT 1 FROM Auctions a
               WHERE a.itemId = i.itemId AND a.endDateTime > NOW()
             )
             AND NOT EXISTS (
               SELECT 1
               FROM Auctions a
               JOIN Outcomes o ON o.auctionId = a.auctionId
               JOIN OutcomeTypes ot ON ot.outcomeTypeId = o.outcomeTypeId
               WHERE a.itemId = i.itemId
                 AND ot.name IN ('ITEM_SOLD_TO_HIGHEST_BIDDER', 'ITEM_SOLD_TO_BUY_NOW_BIDDER')
             )"
        );
        mysqli_stmt_bind_param($available_item, 'ii', $item_id, $seller_id);
        mysqli_stmt_execute($available_item);

        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($available_item))) {
          $errors[] = 'That item is not available for a new auction. Select one of your unsold items without an active auction.';
        } else {
          $auction_insert = mysqli_prepare(
            $connection,
            'INSERT INTO Auctions (itemId, startingPrice, reservePrice, buyNowPrice, startDateTime, endDateTime)
             VALUES (?, ?, ?, ?, ?, ?)'
          );
          $start_datetime = $start_date->format('Y-m-d H:i:s');
          $end_datetime = $end_date->format('Y-m-d H:i:s');
          mysqli_stmt_bind_param(
            $auction_insert,
            'idddss',
            $item_id,
            $starting_price,
            $reserve_price,
            $buy_now_price,
            $start_datetime,
            $end_datetime
          );
          mysqli_stmt_execute($auction_insert);
          $auction_id = mysqli_insert_id($connection);
        }
      }

      if ($errors) {
        mysqli_rollback($connection);
      } else {
        mysqli_commit($connection);
      }
      $transaction_started = false;
    } catch (mysqli_sql_exception $exception) {
      if ($transaction_started) {
        mysqli_rollback($connection);
      }
      error_log('Auction creation failed: ' . $exception->getMessage());
      $errors[] = 'The auction could not be saved. Please try again.';
      $auction_id = null;
    }
  }
}
?>

<?php include_once __DIR__ . '/header.php'; ?>
<div class="container my-5">
<?php if ($auction_id !== null): ?>
  <div class="alert alert-success">Auction successfully created.</div>
  <a class="btn btn-primary" href="listing.php?auction_id=<?php echo (int) $auction_id; ?>">View auction</a>
<?php else: ?>
  <div class="alert alert-danger">
    <p>The auction was not created:</p>
    <ul class="mb-0">
<?php foreach ($errors as $error): ?>
      <li><?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
<?php endforeach; ?>
    </ul>
  </div>
  <a href="create_auction.php">Return to create auction</a>
<?php endif; ?>
</div>

<?php include_once("footer.php")?>