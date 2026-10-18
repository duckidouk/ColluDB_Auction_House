# ColluDB Auction House - Relational Schema

This document records the logical relational translation of the current ER diagram. It is the input to normalization and does not, by itself, establish that every relation is in Third Normal Form (3NF).

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
    Role,
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
    Buyer_ID FK -> USER_ACCOUNTS.User_ID NULL,
    Auction_ID FK -> AUCTION.Auction_ID,
    Winning_Bid_ID FK -> BIDS.Bid_ID NULL,
    Type
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
    User_ID FK -> USER_ACCOUNTS.User_ID,
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
    Timestamp,
    Outcome_ID FK -> OUTCOME.Outcome_ID NULL,
    Bid_ID FK -> BIDS.Bid_ID NULL,
    Auction_ID FK -> AUCTION.Auction_ID NULL
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

## Business rules still to confirm

1. Whether an unsold item can be relisted in multiple auctions.
2. Whether one user account can act as both buyer and seller.
3. Whether `OUTCOME.Buyer_ID` should be retained when `Winning_Bid_ID` already determines the buyer.
4. Which descriptive values should be controlled by lookup relations rather than unrestricted text.

