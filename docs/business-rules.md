# ColluDB Auction House Business Rules

This file records the agreed and unresolved rules that affect the relational schema.

## Confirmed rules

| ID | Business rule | Schema effect |
|---|---|---|
| BR-01 | An unsold item may be relisted after its previous auction ends. | Each relisting creates a new `AUCTION` row. One `ITEM` may have many historical auctions. |
| BR-02 | The same account may act as both buyer and seller. | Buyer and seller are participation roles. `USER_ACCOUNTS` does not store a single buyer-or-seller `Role`. |
| BR-03 | A Buy Now outcome may exist without a bid. | Its buyer is stored in `BUY_NOW_OUTCOME`; no `BID_WIN_OUTCOME` row is required. An unsold result has neither subtype row. |
| BR-04 | The same buyer may place several bids in one auction. Every bid is retained. | `Bid_ID` identifies each bid. `(Buyer_ID, Auction_ID)` is not unique. |
| BR-05 | An active auction may have no outcome; a completed auction has exactly one. | `OUTCOME.Auction_ID` is unique and is a candidate key. |
| BR-06 | Store only the current relationship between a user and a watched auction. | `WATCHED_AUCTIONS` uses the composite key `(Auction_ID, User_ID)`. Both attributes are also foreign keys. |
| BR-07 | Store only the current relationship between a follower and a favourite seller. | `FAVOURITE_SELLERS` uses the composite key `(Follower_ID, Seller_ID)`. Both attributes are also foreign keys to `USER_ACCOUNTS`. |
| BR-08 | Each user may have at most one current preference for each notification type. | `USER_NOTIFICATION_PREFERENCE` uses the composite key `(User_ID, Type_ID)`. |
| BR-09 | Each image position must be unique within an item. | `ITEM_IMAGE` has candidate keys `Image_ID` and `(Item_ID, Order_of_Images)`; the composite key receives a unique constraint. |
| BR-10 | A user may have multiple sessions over time. | Each session is identified by `Session_ID`; `User_ID` is not unique in `USER_SESSION`. |
| BR-11 | Every activity type has a unique name. | `ACTIVITY_TYPES` has candidate keys `ActivityType_ID` and `Name`; `Name` receives a unique constraint. |
| BR-12 | An outcome is an auction win, a Buy Now purchase or an unsold result. | Common data remains in `OUTCOME`; bid-win and Buy Now details are stored in `BID_WIN_OUTCOME` and `BUY_NOW_OUTCOME`. An unsold result needs no subtype row. |
| BR-13 | Every activity belongs to one session, and each session belongs to one user. | `ACTIVITY_LOG` stores `Session_ID` but not the transitively dependent `User_ID`. |
| BR-14 | A notification may concern at most one bid, outcome or auction. | Target references are stored separately in `BID_NOTIFICATION`, `OUTCOME_NOTIFICATION` or `AUCTION_NOTIFICATION`; a notification appears in at most one of these relations. |

## Rules awaiting group agreement

| ID | Question | Possible schema effect |
|---|---|---|
| UBR-01 | Does each item have one category or several? | One category uses `ITEM.Sub_Category_ID`; several require `ITEM_CATEGORY(Item_ID, Category_ID)`. |
| UBR-03 | Which descriptive values must be controlled? | Use `CHECK` constraints or lookup relations for agreed lists. |
| UBR-04 | Does the system need administrative permissions? | If required, use `ROLE` and `USER_ROLE`; do not confuse permissions with buying and selling. |
| UBR-05 | May one item appear in several active auctions at once? | If not, enforce one active auction without preventing historical relisting. |
| UBR-06 | May sellers bid on their own items, and is a minimum bid increment required? | Enforce agreed restrictions through transaction-safe application logic or database constraints and triggers. |
