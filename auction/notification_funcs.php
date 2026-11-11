<?php
/*
 * RULE: every notification is ALWAYS saved and shown under the bell.
 *       User preferences only decide whether an EMAIL is sent as well.
 */
 
require_once __DIR__ . '/../sql/database.php';  

// Functions to Call the SQL queries in a more efficient manner (All rows vs First row vs Value vs to execute an INSERT, DELETE, UPDATE query)
function db_run(mysqli $db, string $sql, array $params = []) {
  $st = $db->prepare($sql);
  if ($params) {
    $types = '';
    foreach ($params as $p) { $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's'); } //determine the datatype of the Parameters
    $st->bind_param($types, ...$params);
  }
  $st->execute();
  $res = $st->get_result();          // True only for SELECT statements that give results
  $st->close();
  return $res;
}
function db_all(mysqli $db, string $sql, array $p = []): array { return db_run($db, $sql, $p)->fetch_all(MYSQLI_ASSOC); } 
function db_row(mysqli $db, string $sql, array $p = []): ?array { return db_run($db, $sql, $p)->fetch_assoc() ?: null; }
function db_val(mysqli $db, string $sql, array $p = []) { $r = db_run($db, $sql, $p)->fetch_row(); return $r ? $r[0] : null; }
function db_exec(mysqli $db, string $sql, array $params = []): int {
  $st = $db->prepare($sql);
  if ($params) {
    $types = '';
    foreach ($params as $p) { $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's'); }
    $st->bind_param($types, ...$params);
  }
  $st->execute();
  $n = $st->affected_rows;         
  $st->close();
  return $n;
}

// THIS PART NEEDS TO BE AMENDED ONCE THE LOGIN SECTION HAS BEEN DONE
// ---------------------------------------------------------------------------
const DEMO_USER_ID = 5;
// Set to false when login is finished: then ?as= is ignored and only the real logged-in user counts.
const ALLOW_DEMO_SWITCH = true;
function current_user_id(): int {
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (ALLOW_DEMO_SWITCH) {
    if (isset($_GET['as'])) { $_SESSION['demo_user_id'] = (int) $_GET['as']; }   // ?as=N picks a user to test as
    if (isset($_SESSION['demo_user_id'])) { return (int) $_SESSION['demo_user_id']; }   // the test user beats the login
  }
  return (int) ($_SESSION['user_id'] ?? DEMO_USER_ID);                              // otherwise: logged-in user, else user 5
}
function user_role(mysqli $db, int $userId): ?string {
  return db_val($db, 'SELECT r.name FROM UserAccounts u JOIN Roles r ON r.roleId = u.roleId WHERE u.userId = ?', [$userId]);
}

// Function to create a notification based on the notification types
// $auctionId may be null (NEW_FOLLOWER has no auction); $relatedUserId = who caused it (the follower).
function create_notification(mysqli $db, int $userId, string $typeName, ?int $auctionId, ?int $bidId = null, ?int $relatedUserId = null): void {
  db_exec($db,
    'INSERT INTO Notifications (receiverId, notificationTypeId, auctionId, bidId, relatedUserId)
     VALUES (?, (SELECT notificationTypeId FROM NotificationTypes WHERE name = ?), ?, ?, ?)',
    [$userId, $typeName, $auctionId, $bidId, $relatedUserId]);
}

// Which email-preference switches each role sees on preferences.php (also the order they are shown in).
// An admin has no preferences page.
function email_pref_types_for_role(?string $role): array {
  switch ($role) {
    case 'buyer':  return ['OUTBID_BY_ANOTHER_BIDDER', 'AUCTION_OUTCOME', 'FOLLOWED_SELLER_NEW_AUCTION',
                           'NEW_AUCTION_BASED_ON_PREFERENCES', 'AUCTION_START_REMINDER', 'AUCTION_END_REMINDER'];
    case 'seller': return ['AUCTION_OUTCOME', 'NEW_FOLLOWER', 'NEW_BID_ON_YOUR_AUCTION'];
  }
  return [];
}

// EMAIL preference gate.
//   mandatory type (isOptional = 0) -> always emailed, preference rows are ignored
//   optional type  (isOptional = 1) -> emailed unless the user switched it off
//   no preference row               -> treated as ON
function user_wants_email(mysqli $db, int $userId, string $typeName): bool {
  $r = db_row($db,
    'SELECT nt.isOptional, p.isEnabled
       FROM NotificationTypes nt
       LEFT JOIN UserNotificationPreferences p
              ON p.notificationTypeId = nt.notificationTypeId AND p.userId = ?
      WHERE nt.name = ?', [$userId, $typeName]);
  if (!$r)                return false;
  if (!$r['isOptional'])  return true;
  return $r['isEnabled'] === null || (bool) $r['isEnabled'];
}


function fetch_notifications(mysqli $db, int $userId, int $limit = 50): array {
  return db_all($db,
    'SELECT n.notifId, n.receiverId, n.auctionId, n.bidId, n.`timestamp`, n.readAt,
            nt.name AS typeName, nt.description AS typeDescription,
            i.title, i.sellerId, a.reservePrice,
            ot.name AS outcomeType,
            wb.bidAmount AS winAmount, wu.firstName AS winFirst, wu.lastName AS winLast,
            nb.bidAmount AS myBid,
            ru.firstName AS relFirst, ru.lastName AS relLast,
            (SELECT COUNT(*)       FROM Bids b WHERE b.auctionId = n.auctionId) AS bidCount,
            (SELECT MAX(bidAmount) FROM Bids b WHERE b.auctionId = n.auctionId) AS topBid
       FROM Notifications n
       JOIN NotificationTypes nt  ON nt.notificationTypeId = n.notificationTypeId
       LEFT JOIN Auctions a       ON a.auctionId = n.auctionId
       LEFT JOIN Items i          ON i.itemId = a.itemId
       LEFT JOIN Outcomes o       ON o.auctionId = a.auctionId
       LEFT JOIN OutcomeTypes ot  ON ot.outcomeTypeId = o.outcomeTypeId
       LEFT JOIN Bids wb          ON wb.bidId = o.winningBidId
       LEFT JOIN UserAccounts wu  ON wu.userId = wb.bidderId
       LEFT JOIN Bids nb          ON nb.bidId = n.bidId
       LEFT JOIN UserAccounts ru  ON ru.userId = n.relatedUserId
      WHERE n.receiverId = ?
      ORDER BY n.`timestamp` DESC, n.notifId DESC
      LIMIT ' . (int) $limit, [$userId]);
}

function unread_count(mysqli $db, int $userId): int {
  return (int) db_val($db, 'SELECT COUNT(*) FROM Notifications WHERE receiverId = ? AND readAt IS NULL', [$userId]);
}

// One notification, or all of them if $notifId is null. Only ever the user's OWN rows.
function mark_notifications_read(mysqli $db, int $userId, ?int $notifId = null): void {
  if ($notifId === null) {
    db_exec($db, 'UPDATE Notifications SET readAt = NOW() WHERE receiverId = ? AND readAt IS NULL', [$userId]);
  } else {
    db_exec($db, 'UPDATE Notifications SET readAt = NOW() WHERE receiverId = ? AND notifId = ? AND readAt IS NULL', [$userId, $notifId]);
  }
}

// ---------------------------------------------------------------------------
// Notification Wording - How it will show up on the Webpage
// ---------------------------------------------------------------------------
function gbp($v): string { return '£' . number_format((float) $v, 2); }

function notification_text(array $r): array {
  $title  = '"' . ($r['title'] ?? '') . '"';
  $seller = ((int) ($r['sellerId'] ?? 0) === (int) $r['receiverId']);
  $buyer  = trim(($r['winFirst'] ?? '') . ' ' . substr($r['winLast'] ?? '', 0, 1) . '.');

  if ($r['typeName'] === 'NEW_FOLLOWER') {
    $who = trim(($r['relFirst'] ?? 'Someone') . ' ' . substr($r['relLast'] ?? '', 0, 1) . '.');
    return ['headline' => 'New follower', 'body' => "$who started following you.",
            'style' => 'info', 'icon' => 'fa-user-plus'];
  }

  if ($r['typeName'] === 'NEW_BID_ON_YOUR_AUCTION') {
    $who = trim(($r['relFirst'] ?? 'Someone') . ' ' . substr($r['relLast'] ?? '', 0, 1) . '.');
    return ['headline' => 'New bid on your auction',
            'body' => "$who placed a bid of " . gbp($r['myBid']) . " on $title.",
            'style' => 'primary', 'icon' => 'fa-gbp'];
  }

  if ($r['typeName'] === 'OUTBID_BY_ANOTHER_BIDDER') {
    return ['headline' => 'You have been outbid',
            'body' => 'Someone placed a higher bid of ' . gbp($r['myBid']) . " on $title.",
            'style' => 'warning', 'icon' => 'fa-arrow-circle-up'];
  }

  if ($r['typeName'] !== 'AUCTION_OUTCOME' || $r['outcomeType'] === null) {
    // reminders / followed-seller / preference matches: description + item title
    return ['headline' => ucfirst(strtolower(str_replace('_', ' ', $r['typeName']))),
            'body' => $r['typeDescription'] . ': ' . $title . '.',
            'style' => 'secondary', 'icon' => 'fa-bell'];
  }

  switch ($r['outcomeType']) {
    case 'ITEM_SOLD_TO_HIGHEST_BIDDER':
      return $seller
        ? ['headline' => 'Your item sold', 'body' => "$title sold to $buyer for " . gbp($r['winAmount']) . '.',
           'style' => 'success', 'icon' => 'fa-check-circle']
        : ['headline' => 'You won the auction', 'body' => "You won $title with a bid of " . gbp($r['winAmount']) . '.',
           'style' => 'success', 'icon' => 'fa-trophy'];

    case 'ITEM_SOLD_TO_BUY_NOW_BIDDER':
      return $seller
        ? ['headline' => 'Your item sold (Buy Now)',
           'body' => "$title was bought now by $buyer for " . gbp($r['winAmount']) . '. The auction closed early.',
           'style' => 'success', 'icon' => 'fa-shopping-cart']
        : ['headline' => 'Purchase complete',
           'body' => "You bought $title at the Buy Now price of " . gbp($r['winAmount']) . '.',
           'style' => 'success', 'icon' => 'fa-shopping-cart'];

    case 'UNSOLD':
      return ((int) $r['bidCount'] === 0)
        ? ['headline' => 'No bids on your auction',
           'body' => "Your auction for $title ended with no bids. The item was not sold.",
           'style' => 'info', 'icon' => 'fa-info-circle']
        : ['headline' => 'Reserve price not met',
           'body' => "Your auction for $title ended. The highest bid was " . gbp($r['topBid'])
                   . ', below your reserve of ' . gbp($r['reservePrice']) . '. The item was not sold.',
           'style' => 'warning', 'icon' => 'fa-exclamation-triangle'];

    case 'CANCELLED_AUCTION':
      return ['headline' => 'Auction cancelled by seller',
              'body' => "The seller cancelled the auction for $title. The bids placed on it will not be taken.",
              'style' => 'danger', 'icon' => 'fa-ban'];

    case 'ADMIN_SHUT_DOWN':
      return $seller
        ? ['headline' => 'Your auction was shut down', 'body' => "An administrator ended your auction for $title.",
           'style' => 'danger', 'icon' => 'fa-gavel']
        : ['headline' => 'Auction shut down by an administrator',
           'body' => "The auction for $title was ended by an administrator. The bids placed on it will not be taken.",
           'style' => 'danger', 'icon' => 'fa-gavel'];
  }
  return ['headline' => 'Auction update', 'body' => "There is an update on $title.", 'style' => 'secondary', 'icon' => 'fa-bell'];
}

function time_ago(string $datetime): string {
  $s = max(0, time() - strtotime($datetime));
  if ($s < 60)    return 'just now';
  if ($s < 3600)  return floor($s / 60) . ' min ago';
  if ($s < 86400) return floor($s / 3600) . ' h ago';
  return floor($s / 86400) . ' d ago';
}

// HTML for ONE notification row - shared by the bell dropdown and notifications.php
function notification_item_html(array $r): string {
  $t      = notification_text($r);
  $unread = ($r['readAt'] === null);
  $e      = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  // where to come back to after "Mark as read" (just the file name + query string)
  $return = basename((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
  if (!empty($_SERVER['QUERY_STRING'])) { $return .= '?' . $_SERVER['QUERY_STRING']; }

  $h  = '<div class="d-flex align-items-start p-3 border-bottom' . ($unread ? ' bg-light' : '') . '">';
  $h .= '<span class="text-' . $t['style'] . ' mr-3 text-center flex-shrink-0" style="font-size:1.5em;width:1.4em"><i class="fa ' . $t['icon'] . '"></i></span>';
  $h .= '<div class="flex-grow-1">';
  $h .= '<a href="mark_read.php?open=' . (int) $r['notifId'] . '" class="' . ($unread ? 'font-weight-bold' : '') . '">' . $e($t['headline']) . '</a>';
  $h .= '<div class="small">' . $e($t['body']) . '</div>';
  $h .= '<small class="text-muted">' . $e(time_ago($r['timestamp']));
  if ($unread) {
    $h .= ' &middot; <form method="post" action="mark_read.php" class="d-inline">'
        . '<input type="hidden" name="notif_id" value="' . (int) $r['notifId'] . '">'
        . '<input type="hidden" name="return" value="' . $e($return) . '">'
        . '<button class="btn btn-link btn-sm p-0 align-baseline" style="font-size:inherit">Mark as read</button></form>';
  }
  $h .= '</small></div>';
  if ($unread) { $h .= '<span class="text-primary ml-2 align-self-center" title="Unread"><i class="fa fa-circle" style="font-size:.6em"></i></span>'; }
  return $h . '</div>';
}

// ---------------------------------------------------------------------------
// EMAIL (we are revisiting this later - this is the current behaviour).
// Run from cron_close_auctions.php. emailSentAt is "claimed" BEFORE sending so two
// runs can never send the same email twice. Optional types the user switched off are
// stamped but not sent (they still show under the bell).
// ---------------------------------------------------------------------------
function send_pending_emails(mysqli $db, int $limit = 100): int {
  $ids = array_column(db_all($db, 'SELECT notifId FROM Notifications WHERE emailSentAt IS NULL ORDER BY notifId LIMIT ' . (int) $limit), 'notifId');
  $sent = 0;
  foreach ($ids as $id) {
    if (db_exec($db, 'UPDATE Notifications SET emailSentAt = NOW() WHERE notifId = ? AND emailSentAt IS NULL', [(int) $id]) !== 1) { continue; }

    $uid = (int) db_val($db, 'SELECT receiverId FROM Notifications WHERE notifId = ?', [(int) $id]);
    $u   = db_row($db, 'SELECT firstName, emailAddress FROM UserAccounts WHERE userId = ?', [$uid]);
    $row = null;
    foreach (fetch_notifications($db, $uid, 200) as $r) { if ((int) $r['notifId'] === (int) $id) { $row = $r; break; } }
    if (!$row || !$u) { continue; }
    if (!user_wants_email($db, $uid, $row['typeName'])) { continue; }

    $t = notification_text($row);
    $link = $row['auctionId'] ? "\n\nView it: http://localhost/listing.php?auction_id=" . (int) $row['auctionId'] : '';
    if (send_email($u['emailAddress'], '[Auction Site] ' . $t['headline'], "Hi {$u['firstName']},\n\n" . $t['body'] . $link . "\n")) {
      $sent++;
    } else {
      db_exec($db, 'UPDATE Notifications SET emailSentAt = NULL WHERE notifId = ?', [(int) $id]);   // retry next run
    }
  }
  return $sent;
}

// Uses PHP mail(). For local testing set the MAIL_LOG_FILE environment variable to write emails to a file instead.
function send_email(string $to, string $subject, string $body): bool {
  if (($log = getenv('MAIL_LOG_FILE'))) {
    return file_put_contents($log, "TO: $to\nSUBJECT: $subject\n\n$body\n----\n", FILE_APPEND) !== false;
  }
  return @mail($to, $subject, $body, 'From: noreply@auctionsite.example');
}
