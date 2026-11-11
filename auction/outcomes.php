<?php
require_once __DIR__ . '/../sql/database.php';
require_once __DIR__ . '/notification_funcs.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);  

const OUT_HIGHEST   = 'ITEM_SOLD_TO_HIGHEST_BIDDER';
const OUT_BUY_NOW   = 'ITEM_SOLD_TO_BUY_NOW_BIDDER';
const OUT_UNSOLD    = 'UNSOLD';
const OUT_CANCELLED = 'CANCELLED_AUCTION';
const OUT_ADMIN     = 'ADMIN_SHUT_DOWN';

function outcome_type_id(mysqli $db, string $name): int {
  return (int) db_val($db, 'SELECT outcomeTypeId FROM OutcomeTypes WHERE name = ?', [$name]);
}

// Lock the auction row: if two requests hit the same auction at the same time
function lock_auction(mysqli $db, int $auctionId): ?array {
  return db_row($db,
    'SELECT a.*, i.sellerId, i.title
       FROM Auctions a JOIN Items i ON i.itemId = a.itemId
      WHERE a.auctionId = ? FOR UPDATE', [$auctionId]);
}

// Check to see if the outcome already exists (so as to not to overwrite or duplicate)
function outcome_exists(mysqli $db, int $auctionId): bool {
  return db_val($db, 'SELECT 1 FROM Outcomes WHERE auctionId = ? FOR UPDATE', [$auctionId]) !== null;
}

// Ensure that the auction is still live
function auction_is_live(mysqli $db, int $auctionId): bool {
  return (bool) db_val($db, 'SELECT startDateTime <= NOW() AND endDateTime > NOW() FROM Auctions WHERE auctionId = ?', [$auctionId]);
}

// Error control
function fail(mysqli $db, string $msg): array {
  $db->rollback();
  return ['ok' => false, 'message' => $msg];
}

// ---------------------------------------------------------------------------
// Outcomes row + notifications. Call INSIDE a transaction.
// ---------------------------------------------------------------------------
function record_outcome(mysqli $db, array $auction, string $type, ?int $winningBidId): void {
  db_exec($db, 'INSERT INTO Outcomes (auctionId, winningBidId, outcomeTypeId) VALUES (?, ?, ?)',
          [(int) $auction['auctionId'], $winningBidId, outcome_type_id($db, $type)]);

  // Everyone involved always gets the in-app notification (the bell).
  // Preferences only decide whether an EMAIL is also sent (send_pending_emails).
  foreach (outcome_recipients($db, $auction, $type, $winningBidId) as [$userId, $bidId]) {
    create_notification($db, $userId, 'AUCTION_OUTCOME', (int) $auction['auctionId'], $bidId);
  }
}

// Who is told, per outcome type. Returns [[userId, bidId|null], ...]
function outcome_recipients(mysqli $db, array $auction, string $type, ?int $winningBidId): array {
  $seller = (int) $auction['sellerId'];
  switch ($type) {
    case OUT_HIGHEST:
    case OUT_BUY_NOW:      // seller + buyer
      $buyer = (int) db_val($db, 'SELECT bidderId FROM Bids WHERE bidId = ?', [$winningBidId]);
      return [[$seller, $winningBidId], [$buyer, $winningBidId]];
    case OUT_UNSOLD:       // seller only ("no bids" or "reserve not met")
      return [[$seller, null]];
    case OUT_CANCELLED:    // every buyer who bid
      return bidder_recipients($db, (int) $auction['auctionId']);
    case OUT_ADMIN:        // seller + every buyer who bid
      return array_merge([[$seller, null]], bidder_recipients($db, (int) $auction['auctionId']));
  }
  return [];
}

// Everyone who placed at least one bid, once each. [userId, null]: the message does not need a bid.
function bidder_recipients(mysqli $db, int $auctionId): array {
  $ids = array_column(db_all($db, 'SELECT DISTINCT bidderId FROM Bids WHERE auctionId = ?', [$auctionId]), 'bidderId');
  return array_map(fn($id) => [(int) $id, null], $ids);
}

// ---------------------------------------------------------------------------
// TIME RAN OUT (called by cron_close_auctions.php). Outcome types 1 and 3.
// Four plain SQL statements, run together in one transaction:
//   1. every ended auction with no outcome whose top bid meets the reserve -> SOLD TO HIGHEST BIDDER
//   2. every ended auction with no outcome that is still left               -> UNSOLD (no bids, or reserve not met)
//   3. tell the seller of each of those     4. tell the winning buyer of each sale
// Outcomes.auctionId is UNIQUE, so an auction can never get two outcomes, even if two things collide.
// Returns how many auctions were closed.
// ---------------------------------------------------------------------------
function close_ended_auctions(mysqli $db): int {
  $db->begin_transaction();
  try {
    // 1. sold: the top bid = highest amount, ties go to the earliest bid
    $sold = db_exec($db,
      "INSERT INTO Outcomes (auctionId, winningBidId, outcomeTypeId)
       SELECT a.auctionId, b.bidId, (SELECT outcomeTypeId FROM OutcomeTypes WHERE name = 'ITEM_SOLD_TO_HIGHEST_BIDDER')
         FROM Auctions a
         JOIN (SELECT bidId, auctionId, bidAmount,
                      ROW_NUMBER() OVER (PARTITION BY auctionId ORDER BY bidAmount DESC, bidTimestamp ASC, bidId ASC) AS rownumber
                 FROM Bids) b ON b.auctionId = a.auctionId AND b.rownumber = 1
        WHERE a.endDateTime <= NOW()
          AND (a.reservePrice IS NULL OR b.bidAmount >= a.reservePrice)
          AND NOT EXISTS (SELECT 1 FROM Outcomes o WHERE o.auctionId = a.auctionId)");

    // 2. whatever is still ended and has no outcome did not sell
    $unsold = db_exec($db,
      "INSERT INTO Outcomes (auctionId, winningBidId, outcomeTypeId)
       SELECT a.auctionId, NULL, (SELECT outcomeTypeId FROM OutcomeTypes WHERE name = 'UNSOLD')
         FROM Auctions a
        WHERE a.endDateTime <= NOW()
          AND NOT EXISTS (SELECT 1 FROM Outcomes o WHERE o.auctionId = a.auctionId)");

    // 3 + 4. notifications (only for outcomes that do not have one yet, so it is safe to run twice)
    $typeSql = "(SELECT notificationTypeId FROM NotificationTypes WHERE name = 'AUCTION_OUTCOME')";
    db_exec($db,   // the seller hears about both a sale and an unsold auction
      "INSERT INTO Notifications (receiverId, notificationTypeId, auctionId, bidId)
       SELECT i.sellerId, $typeSql, o.auctionId, o.winningBidId
         FROM Outcomes o
         JOIN OutcomeTypes ot ON ot.outcomeTypeId = o.outcomeTypeId AND ot.name IN ('ITEM_SOLD_TO_HIGHEST_BIDDER', 'UNSOLD')
         JOIN Auctions a ON a.auctionId = o.auctionId
         JOIN Items i ON i.itemId = a.itemId
        WHERE NOT EXISTS (SELECT 1 FROM Notifications n
                           WHERE n.auctionId = o.auctionId AND n.receiverId = i.sellerId AND n.notificationTypeId = $typeSql)");
    db_exec($db,   // the winning buyer hears about a sale
      "INSERT INTO Notifications (receiverId, notificationTypeId, auctionId, bidId)
       SELECT b.bidderId, $typeSql, o.auctionId, o.winningBidId
         FROM Outcomes o
         JOIN OutcomeTypes ot ON ot.outcomeTypeId = o.outcomeTypeId AND ot.name = 'ITEM_SOLD_TO_HIGHEST_BIDDER'
         JOIN Bids b ON b.bidId = o.winningBidId
        WHERE NOT EXISTS (SELECT 1 FROM Notifications n
                           WHERE n.auctionId = o.auctionId AND n.receiverId = b.bidderId AND n.notificationTypeId = $typeSql)");

    $db->commit();
    return $sold + $unsold;
  } catch (Throwable $e) {
    $db->rollback();
    error_log('close_ended_auctions failed: ' . $e->getMessage());
    return 0;
  }
}

function top_bid(mysqli $db, int $auctionId): ?array {
  return db_row($db, 'SELECT bidId, bidderId, bidAmount FROM Bids WHERE auctionId = ?
                       ORDER BY bidAmount DESC, bidTimestamp ASC, bidId ASC LIMIT 1', [$auctionId]);
}

// BUY NOW (Outcome = 2). Only called from place_bid.php, AFTER it has checked the buyer, the auction and the bid.
// (The 'Buy now' button just submits a bid at the Buy Now price to place_bid.php, so there is one door in.)
function execute_buy_now(mysqli $db, array $a, int $buyerId): array {
  $auctionId = (int) $a['auctionId'];

  db_exec($db, 'INSERT INTO Bids (auctionId, bidderId, bidAmount, bidTimestamp) VALUES (?, ?, ?, NOW())',
          [$auctionId, $buyerId, $a['buyNowPrice']]);
  $bidId = (int) $db->insert_id;

  record_outcome($db, $a, OUT_BUY_NOW, $bidId);

  db_exec($db, 'UPDATE Auctions SET originalEndDateTime = endDateTime, endDateTime = NOW() WHERE auctionId = ?', [$auctionId]);

  return ['ok' => true, 'message' => 'You bought "' . $a['title'] . '" for £' . number_format((float) $a['buyNowPrice'], 2) . '.'];
}

// FUNCTION TO NOTIFY THE BIDDERS THAT HAVE HEEN OUTBID
function notify_outbid(mysqli $db, ?array $previousTop, int $newBidderId, int $auctionId, int $newBidId): void {
  if ($previousTop && (int) $previousTop['bidderId'] !== $newBidderId) {
    create_notification($db, (int) $previousTop['bidderId'], 'OUTBID_BY_ANOTHER_BIDDER', $auctionId, $newBidId);
  }
}

// Tell the seller that someone bid on their auction (optional email, always shown under the bell)
function notify_seller_of_bid(mysqli $db, int $sellerId, int $bidderId, int $auctionId, int $bidId): void {
  create_notification($db, $sellerId, 'NEW_BID_ON_YOUR_AUCTION', $auctionId, $bidId, $bidderId);
}

// SELLER CANCELS THEIR AUCTION (Outcome = 4)
function cancel_auction_by_seller(mysqli $db, int $auctionId, int $sellerId): array {
  $db->begin_transaction();
  try {
    $a = lock_auction($db, $auctionId);
    if (!$a || (int) $a['sellerId'] !== $sellerId) return fail($db, 'You can only cancel your own auctions.');
    if (outcome_exists($db, $auctionId))           return fail($db, 'This auction is already closed.');
    if (db_val($db, 'SELECT endDateTime <= NOW() FROM Auctions WHERE auctionId = ?', [$auctionId]))
                                                   return fail($db, 'This auction has already ended.');

    record_outcome($db, $a, OUT_CANCELLED, null);
    $db->commit();
    return ['ok' => true, 'message' => 'Auction cancelled. Bidders have been notified.'];
  } catch (Throwable $e) { $db->rollback(); throw $e; }
}


// ADMIN SHUTS IT DOWN ANY AUCTION (Outcome = 5)
function admin_shut_down_auction(mysqli $db, int $auctionId, int $adminId): array {
  $db->begin_transaction();
  try {
    if (user_role($db, $adminId) !== 'admin') return fail($db, 'Administrators only.');
    $a = lock_auction($db, $auctionId);
    if (!$a)                                  return fail($db, 'Auction not found.');
    if (outcome_exists($db, $auctionId))      return fail($db, 'This auction is already closed.');

    record_outcome($db, $a, OUT_ADMIN, null);
    $db->commit();
    return ['ok' => true, 'message' => 'Auction shut down. Seller and bidders have been notified.'];
  } catch (Throwable $e) { $db->rollback(); throw $e; }
}
