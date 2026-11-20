<?php

require_once __DIR__ . '/../outcomes.php';

function admin_ban_user(
  mysqli $db,
  int $adminId,
  int $targetId,
  ?int $durationDays,
  ?string $reason
): array {
  if ($durationDays !== null && !in_array($durationDays, [1, 7, 30], true)) {
    throw new InvalidArgumentException('Choose a supported ban duration.');
  }
  if ($reason !== null && preg_match('/^.{0,255}$/us', $reason) !== 1) {
    throw new InvalidArgumentException('The ban reason must be 255 characters or fewer.');
  }

  $db->begin_transaction();
  try {
    $users = db_all(
      $db,
      'SELECT u.userId, r.name AS roleName
         FROM UserAccounts u
         JOIN Roles r ON r.roleId = u.roleId
        WHERE u.userId IN (?, ?)
        ORDER BY u.userId
        FOR UPDATE',
      [$adminId, $targetId]
    );
    $roles = [];
    foreach ($users as $user) {
      $roles[(int) $user['userId']] = $user['roleName'];
    }

    if (($roles[$adminId] ?? null) !== 'admin') {
      $db->rollback();
      return ['ok' => false, 'message' => 'Administrators only.'];
    }
    if (!isset($roles[$targetId]) || $roles[$targetId] === 'admin') {
      $db->rollback();
      return ['ok' => false, 'message' => 'This account cannot be banned.'];
    }

    if ($durationDays === null) {
      db_exec(
        $db,
        'INSERT INTO UserBans (userId, bannedUntil, reason) VALUES (?, NULL, ?)',
        [$targetId, $reason]
      );
    } else {
      db_exec(
        $db,
        'INSERT INTO UserBans (userId, bannedUntil, reason)
         VALUES (?, DATE_ADD(NOW(), INTERVAL ' . $durationDays . ' DAY), ?)',
        [$targetId, $reason]
      );
    }

    db_exec(
      $db,
      'UPDATE UserSessions
          SET endTime = GREATEST(NOW(), DATE_ADD(startTime, INTERVAL 1 SECOND))
        WHERE userId = ? AND endTime IS NULL',
      [$targetId]
    );

    $shutdownCount = 0;
    if ($roles[$targetId] === 'buyer') {
      $liveAuctions = db_all(
        $db,
        'SELECT a.auctionId
           FROM Auctions a
          WHERE a.startDateTime <= NOW() AND a.endDateTime > NOW()
            AND EXISTS (
              SELECT 1 FROM Bids b
               WHERE b.auctionId = a.auctionId AND b.bidderId = ? AND b.isInvalid = FALSE
            )
            AND NOT EXISTS (
              SELECT 1 FROM Outcomes o WHERE o.auctionId = a.auctionId
            )
          ORDER BY a.auctionId
          FOR UPDATE',
        [$targetId]
      );
      foreach ($liveAuctions as $auction) {
        db_exec(
          $db,
          'UPDATE Bids SET isInvalid = TRUE
            WHERE auctionId = ? AND bidderId = ? AND isInvalid = FALSE',
          [(int) $auction['auctionId'], $targetId]
        );
      }
    } elseif ($roles[$targetId] === 'seller') {
      $auctions = db_all(
        $db,
        'SELECT a.auctionId
           FROM Auctions a
           JOIN Items i ON i.itemId = a.itemId
          WHERE i.sellerId = ? AND a.endDateTime > NOW()
            AND NOT EXISTS (
              SELECT 1 FROM Outcomes o WHERE o.auctionId = a.auctionId
            )
          ORDER BY a.auctionId
          FOR UPDATE',
        [$targetId]
      );
      foreach ($auctions as $auction) {
        $result = admin_shut_down_auction_in_transaction(
          $db,
          (int) $auction['auctionId'],
          $adminId
        );
        if (!$result['ok']) {
          throw new RuntimeException('A seller auction could not be shut down: ' . $result['message']);
        }
        $shutdownCount++;
      }
    }

    $db->commit();
    return [
      'ok' => true,
      'message' => $roles[$targetId] === 'seller'
        ? 'The account was banned and ' . $shutdownCount . ' auction(s) were shut down.'
        : 'The account was banned and outstanding bids were invalidated.',
    ];
  } catch (Throwable $exception) {
    $db->rollback();
    throw $exception;
  }
}
