<?php

function fetch_seller_auctions(mysqli $connection, int $sellerId): mysqli_result {
  $statement = mysqli_prepare(
    $connection,
    "SELECT a.auctionId, a.startDateTime, a.endDateTime,
            i.title, i.description, p.currentPrice, p.numBids,
            (SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ')
               FROM ItemCategories ic
               JOIN Categories c ON c.categoryId = ic.categoryId
              WHERE ic.itemId = i.itemId) AS categories
       FROM Auctions a
       JOIN Items i ON i.itemId = a.itemId
       JOIN AuctionCurrentPrice p ON p.auctionId = a.auctionId
      WHERE i.sellerId = ?
      ORDER BY
        CASE
          WHEN a.startDateTime <= NOW() AND a.endDateTime > NOW() THEN 0
          WHEN a.startDateTime > NOW() THEN 1
          ELSE 2
        END,
        CASE
          WHEN a.startDateTime <= NOW() AND a.endDateTime > NOW() THEN a.endDateTime
          WHEN a.startDateTime > NOW() THEN a.startDateTime
        END ASC,
        CASE WHEN a.endDateTime <= NOW() THEN a.endDateTime END DESC,
        a.auctionId DESC"
  );
  mysqli_stmt_bind_param($statement, 'i', $sellerId);
  mysqli_stmt_execute($statement);
  $results = mysqli_stmt_get_result($statement);
  mysqli_stmt_close($statement);

  if ($results === false) {
    throw new RuntimeException('Failed to retrieve seller auctions.');
  }

  return $results;
}
