# ColluDB Auction House - Normalisation Record

This document records the normalisation of the logical schema to Third Normal Form (3NF). Business rules are maintained separately in [`business-rules.md`](business-rules.md).

## Method

For each relation:

1. identify all candidate keys and select a primary key;
2. state the functional dependencies;
3. test 1NF, 2NF and 3NF;
4. decompose any relation that fails, preserving a lossless join.

## Final 3NF relations

All attributes are atomic, so every final relation satisfies 1NF. Any composite candidate key determines all non-key attributes, so there are no partial dependencies and every relation satisfies 2NF. The functional dependencies below have only candidate-key determinants, so the final relations satisfy 3NF.

| Relation | Candidate key(s) | Primary key | Functional dependency or dependencies | Result |
|---|---|---|---|---|
| `USER_ACCOUNTS` | `User_ID`; `Email_Address` | `User_ID` | `User_ID -> First_Name, Last_Name, Password, Email_Address`; `Email_Address -> User_ID, First_Name, Last_Name, Password` | 3NF |
| `ITEM` | `Item_ID` | `Item_ID` | `Item_ID -> Seller_ID, Sub_Category_ID, Title, Description, Condition, Availability` | 3NF |
| `ITEM_IMAGE` | `Image_ID`; (`Item_ID`, `Order_of_Images`) | `Image_ID` | `Image_ID -> Item_ID, Order_of_Images, Image_Location`; (`Item_ID`, `Order_of_Images`) `-> Image_ID, Image_Location` | 3NF |
| `CATEGORIES` | `Category_ID` | `Category_ID` | `Category_ID -> Parent_ID, Name` | 3NF |
| `AUCTION` | `Auction_ID` | `Auction_ID` | `Auction_ID -> Item_ID, Starting_Price, Start_Datetime, End_Datetime, Reserve_Price, Buy_Now_Price` | 3NF |
| `BIDS` | `Bid_ID` | `Bid_ID` | `Bid_ID -> Buyer_ID, Auction_ID, Bid_Amount, Bid_Timestamp` | 3NF |
| `OUTCOME` | `Outcome_ID`; `Auction_ID` | `Outcome_ID` | `Outcome_ID -> Auction_ID, Type`; `Auction_ID -> Outcome_ID, Type` | 3NF |
| `BID_WIN_OUTCOME` | `Outcome_ID`; `Winning_Bid_ID` | `Outcome_ID` | `Outcome_ID -> Winning_Bid_ID`; `Winning_Bid_ID -> Outcome_ID` | 3NF |
| `BUY_NOW_OUTCOME` | `Outcome_ID` | `Outcome_ID` | `Outcome_ID -> Buyer_ID` | 3NF |
| `WATCHED_AUCTIONS` | (`Auction_ID`, `User_ID`) | (`Auction_ID`, `User_ID`) | (`Auction_ID`, `User_ID`) `-> Timestamp` | 3NF |
| `FAVOURITE_SELLERS` | (`Follower_ID`, `Seller_ID`) | (`Follower_ID`, `Seller_ID`) | (`Follower_ID`, `Seller_ID`) `-> Followed_At` | 3NF |
| `USER_SESSION` | `Session_ID` | `Session_ID` | `Session_ID -> User_ID, Start_Time, End_Time` | 3NF |
| `ACTIVITY_TYPES` | `ActivityType_ID`; `Name` | `ActivityType_ID` | `ActivityType_ID -> Name, Description`; `Name -> ActivityType_ID, Description` | 3NF |
| `ACTIVITY_LOG` | `Activity_ID` | `Activity_ID` | `Activity_ID -> Session_ID, ActivityType_ID, Auction_ID, Category_ID, Search_Query, Timestamp` | 3NF |
| `NOTIFICATION_TYPE` | `Type_ID` | `Type_ID` | `Type_ID -> Name, Description, Is_Optional` | 3NF |
| `USER_NOTIFICATION_PREFERENCE` | (`User_ID`, `Type_ID`) | (`User_ID`, `Type_ID`) | (`User_ID`, `Type_ID`) `-> Is_Enabled` | 3NF |
| `NOTIFICATIONS` | `Notif_ID` | `Notif_ID` | `Notif_ID -> Receiver_ID, Type_ID, Timestamp` | 3NF |
| `BID_NOTIFICATION` | `Notif_ID` | `Notif_ID` | `Notif_ID -> Bid_ID` | 3NF |
| `OUTCOME_NOTIFICATION` | `Notif_ID` | `Notif_ID` | `Notif_ID -> Outcome_ID` | 3NF |
| `AUCTION_NOTIFICATION` | `Notif_ID` | `Notif_ID` | `Notif_ID -> Auction_ID` | 3NF |
| `COMPLAINTS` | `Complaint_ID` | `Complaint_ID` | `Complaint_ID -> Complainant_ID, Auction_ID, Complaint_Type, Description, Status, Created_At` | 3NF |
| `FEEDBACK` | `Feedback_ID` | `Feedback_ID` | `Feedback_ID -> Outcome_ID, Rater_ID, Rating, Comments, Timestamp` | 3NF |

## Decomposition 1: OUTCOME

### Original relation

```text
OUTCOME(Outcome_ID, Buyer_ID, Auction_ID, Winning_Bid_ID, Type)
```

Relevant dependencies included:

```text
Outcome_ID -> Buyer_ID, Auction_ID, Winning_Bid_ID, Type
Auction_ID -> Outcome_ID, Buyer_ID, Winning_Bid_ID, Type
Winning_Bid_ID -> Buyer_ID, Auction_ID
```

For a bid-winning outcome, `Buyer_ID` could be obtained from `Winning_Bid_ID`. Buy Now outcomes still require a buyer without a winning bid. Mixing these cases produced a conditional transitive dependency and avoidable nulls.

### 3NF decomposition

```text
OUTCOME(Outcome_ID, Auction_ID, Type)
BID_WIN_OUTCOME(Outcome_ID, Winning_Bid_ID)
BUY_NOW_OUTCOME(Outcome_ID, Buyer_ID)
```

`Outcome_ID` is the primary key of each subtype and a foreign key to `OUTCOME`. `Winning_Bid_ID` is unique in `BID_WIN_OUTCOME`. An unsold result requires only an `OUTCOME` row. Joining a subtype to `OUTCOME` on `Outcome_ID` reconstructs the applicable outcome without duplicating the buyer stored against a bid.

## Decomposition 2: ACTIVITY_LOG

### Original relation

```text
ACTIVITY_LOG(Activity_ID, Session_ID, ActivityType_ID, User_ID,
             Auction_ID, Category_ID, Search_Query, Timestamp)
```

The dependencies included:

```text
Activity_ID -> Session_ID, ActivityType_ID, User_ID,
               Auction_ID, Category_ID, Search_Query, Timestamp
Session_ID -> User_ID
```

This created the transitive dependency:

```text
Activity_ID -> Session_ID -> User_ID
```

### 3NF decomposition

```text
USER_SESSION(Session_ID, User_ID, Start_Time, End_Time)
ACTIVITY_LOG(Activity_ID, Session_ID, ActivityType_ID,
             Auction_ID, Category_ID, Search_Query, Timestamp)
```

`User_ID` is obtained through `USER_SESSION`. The join is lossless because every `ACTIVITY_LOG.Session_ID` references exactly one `USER_SESSION` row.

## Decomposition 3: NOTIFICATIONS

### Original relation

```text
NOTIFICATIONS(Notif_ID, Receiver_ID, Type_ID, Timestamp,
              Outcome_ID, Bid_ID, Auction_ID)
```

When identifiers were stored together, the dependencies included:

```text
Bid_ID -> Auction_ID
Outcome_ID -> Auction_ID
```

This allowed `Auction_ID` to depend transitively on `Bid_ID` or `Outcome_ID`, and allowed inconsistent target combinations.

### 3NF decomposition

```text
NOTIFICATIONS(Notif_ID, Receiver_ID, Type_ID, Timestamp)
BID_NOTIFICATION(Notif_ID, Bid_ID)
OUTCOME_NOTIFICATION(Notif_ID, Outcome_ID)
AUCTION_NOTIFICATION(Notif_ID, Auction_ID)
```

Each subtype uses `Notif_ID` as both its primary key and a foreign key to `NOTIFICATIONS`. A notification may appear in at most one target subtype. Joining on `Notif_ID` reconstructs its target without storing redundant auction identifiers.



```text
                         BID_NOTIFICATION
                        /
NOTIFICATIONS -------- OUTCOME_NOTIFICATION
                        \
                         AUCTION_NOTIFICATION
```


Example

An outbid notification would be stored as:

``` text
NOTIFICATIONS
Notif_ID  Receiver_ID  Type_ID  Timestamp
N01       U07          T02      2026-10-19 10:00

BID_NOTIFICATION
Notif_ID  Bid_ID
N01       B17
```
Joining the two rows using Notif_ID gives the complete notification:

```text
N01 → receiver U07, type T02, time 10:00, concerning bid B17
```

If we need its auction, we follow:

```text
BID_NOTIFICATION.Bid_ID
        ↓
BIDS.Bid_ID
        ↓
BIDS.Auction_ID
```

Therefore, Auction_ID does not need to be repeated.
## Conclusion

The final logical schema is in 3NF under the confirmed business rules. The unresolved questions in `business-rules.md` must be agreed before the physical SQL schema is created.
