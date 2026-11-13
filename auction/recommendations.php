<?php include_once("header.php")?>
<?php require_once __DIR__ . '/utilities.php'?>

<div class="container">

<h2 class="my-3">Recommendations for you</h2>

<?php
  // This page is for showing a buyer recommended items based on their bid 
  // history. It will be pretty similar to browse.php, except there is no 
  // search bar. This can be started after browse.php is working with a database.
  // Feel free to extract out useful functions from browse.php and put them in
  // the shared "utilities.php" where they can be shared by multiple files.
  
  
  // TODO: Check user's credentials (cookie/session).
  
  // TODO: Perform a query to pull up auctions they might be interested in.
  
  // TODO: Loop through results and print them out as list items.
'''
  SELECT a.auctionId, i.title, p.currentPrice, p.numBids,
       COUNT(DISTINCT other.bidderId) AS similarBidders
FROM Bids mine
JOIN Bids other ON other.auctionId = mine.auctionId AND other.bidderId <> mine.bidderId
JOIN Bids rec   ON rec.bidderId = other.bidderId
JOIN Auctions a ON a.auctionId = rec.auctionId
JOIN Items i    ON i.itemId = a.itemId
JOIN AuctionCurrentPrice p ON p.auctionId = a.auctionId
LEFT JOIN Outcomes o ON o.auctionId = a.auctionId
WHERE mine.bidderId = 7
  AND a.startDateTime <= NOW() AND a.endDateTime > NOW()
  AND o.outcomeId IS NULL
  AND i.sellerId <> 7
  AND NOT EXISTS (SELECT 1 FROM Bids b2
                  WHERE b2.auctionId = a.auctionId AND b2.bidderId = 7)
GROUP BY a.auctionId, i.title, p.currentPrice, p.numBids, a.endDateTime
ORDER BY similarBidders DESC, p.numBids DESC, a.endDateTime ASC
LIMIT 6;

select distinct b.bidderId,i.itemId, a.auctionId, i.title, m.bidderId
from Bids b, Auctions a, Items i, Bids m
where b.auctionId = a.auctionId
and a.itemId = i.itemId
and (b.auctionId = m.auctionId and b.bidderId <> m.bidderId)
AND a.startDateTime <= NOW() AND a.endDateTime > NOW()
order by b.bidderId;
  
?>

'''