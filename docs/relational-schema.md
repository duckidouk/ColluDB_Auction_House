# ColluDB Auction House - Relational Schema

This document records the logical relational schema after normalisation to Third Normal Form (3NF) under the confirmed business rules. Unresolved rules may require later revisions.

## Notation

- `PK`: primary key
- `FK`: foreign key
- `UQ`: unique constraint
- `NULL`: optional attribute

## Core relations

```text
USER_ACCOUNTS(
    User_ID PK,
    First_Name,
    Last_Name,
    Password,
    Email_Address UQ
)

ITEM(
    Item_ID PK,
    Seller_ID FK -> USER_ACCOUNTS.User_ID,
    Sub_Category_ID FK -> CATEGORIES.Category_ID,
    Title,
    Description,
    Condition,
    Availability
)

ITEM_IMAGE(
    Image_ID PK,
    Item_ID FK -> ITEM.Item_ID,
    Order_of_Images,
    Image_Location
)

CATEGORIES(
    Category_ID PK,
    Parent_ID FK -> CATEGORIES.Category_ID NULL,
    Name
)

AUCTION(
    Auction_ID PK,
    Item_ID FK -> ITEM.Item_ID,
    Starting_Price,
    Start_Datetime,
    End_Datetime,
    Reserve_Price,
    Buy_Now_Price
)

BIDS(
    Bid_ID PK,
    Buyer_ID FK -> USER_ACCOUNTS.User_ID,
    Auction_ID FK -> AUCTION.Auction_ID,
    Bid_Amount,
    Bid_Timestamp
)

OUTCOME(
    Outcome_ID PK,
    Auction_ID FK -> AUCTION.Auction_ID UQ,
    Type
)

BID_WIN_OUTCOME(
    Outcome_ID PK, FK -> OUTCOME.Outcome_ID,
    Winning_Bid_ID FK -> BIDS.Bid_ID UQ
)

BUY_NOW_OUTCOME(
    Outcome_ID PK, FK -> OUTCOME.Outcome_ID,
    Buyer_ID FK -> USER_ACCOUNTS.User_ID
)
```

## Watching and following

```text
WATCHED_AUCTIONS(
    Auction_ID PK, FK -> AUCTION.Auction_ID,
    User_ID PK, FK -> USER_ACCOUNTS.User_ID,
    Timestamp
)

FAVOURITE_SELLERS(
    Follower_ID PK, FK -> USER_ACCOUNTS.User_ID,
    Seller_ID PK, FK -> USER_ACCOUNTS.User_ID,
    Followed_At
)
```

## Activity tracking

```text
USER_SESSION(
    Session_ID PK,
    User_ID FK -> USER_ACCOUNTS.User_ID,
    Start_Time,
    End_Time
)

ACTIVITY_TYPES(
    ActivityType_ID PK,
    Name,
    Description
)

ACTIVITY_LOG(
    Activity_ID PK,
    Session_ID FK -> USER_SESSION.Session_ID,
    ActivityType_ID FK -> ACTIVITY_TYPES.ActivityType_ID,
    Auction_ID FK -> AUCTION.Auction_ID NULL,
    Category_ID FK -> CATEGORIES.Category_ID NULL,
    Search_Query NULL,
    Timestamp
)
```

## Notifications

```text
NOTIFICATION_TYPE(
    Type_ID PK,
    Name,
    Description,
    Is_Optional
)

USER_NOTIFICATION_PREFERENCE(
    User_ID PK, FK -> USER_ACCOUNTS.User_ID,
    Type_ID PK, FK -> NOTIFICATION_TYPE.Type_ID,
    Is_Enabled
)

NOTIFICATIONS(
    Notif_ID PK,
    Receiver_ID FK -> USER_ACCOUNTS.User_ID,
    Type_ID FK -> NOTIFICATION_TYPE.Type_ID,
    Timestamp
)

BID_NOTIFICATION(
    Notif_ID PK, FK -> NOTIFICATIONS.Notif_ID,
    Bid_ID FK -> BIDS.Bid_ID
)

OUTCOME_NOTIFICATION(
    Notif_ID PK, FK -> NOTIFICATIONS.Notif_ID,
    Outcome_ID FK -> OUTCOME.Outcome_ID
)

AUCTION_NOTIFICATION(
    Notif_ID PK, FK -> NOTIFICATIONS.Notif_ID,
    Auction_ID FK -> AUCTION.Auction_ID
)
```

## Complaints and feedback

```text
COMPLAINTS(
    Complaint_ID PK,
    Complainant_ID FK -> USER_ACCOUNTS.User_ID,
    Auction_ID FK -> AUCTION.Auction_ID,
    Complaint_Type,
    Description,
    Status,
    Created_At
)

FEEDBACK(
    Feedback_ID PK,
    Outcome_ID FK -> OUTCOME.Outcome_ID,
    Rater_ID FK -> USER_ACCOUNTS.User_ID,
    Rating,
    Comments,
    Timestamp
)
```

## Business rules

Confirmed and unresolved rules are maintained in [`business-rules.md`](business-rules.md).

