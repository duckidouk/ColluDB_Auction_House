-- why the character set andd collate, is it necessary?

DROP DATABASE IF EXISTS auction_db;
CREATE DATABASE auction_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE auction_db;

-- ---------------------------------------------------------------------
-- Users, roles, bans, sessions
-- ---------------------------------------------------------------------
CREATE TABLE Roles (
    roleId          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name ENUM('buyer','seller','admin') NOT NULL UNIQUE KEY,
    roleDescription VARCHAR(255) NULL
);

CREATE TABLE UserAccounts (
    userId        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    firstName     VARCHAR(50)  NOT NULL,
    lastName      VARCHAR(50)  NOT NULL,
    emailAddress  VARCHAR(255) NOT NULL UNIQUE KEY,
    passwordHash  VARCHAR(255) NOT NULL,
    roleId        INT UNSIGNED NOT NULL,
    CONSTRAINT fk_user_role FOREIGN KEY (roleId) REFERENCES Roles (roleId)
);
-- could we move to having an admin entity, if yes can remove userID for 3nf
CREATE TABLE UserBans (
    banId       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    userId      INT UNSIGNED NOT NULL,
    bannedSince DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    bannedUntil DATETIME     NULL,                -- NULL means permanent ban
    reason      VARCHAR(255) NULL,
    KEY idx_ban_user (userId),
    CONSTRAINT fk_ban_user FOREIGN KEY (userId) REFERENCES UserAccounts (userId)
        ON DELETE CASCADE,
    CONSTRAINT chk_ban_dates CHECK (bannedUntil IS NULL OR bannedUntil > bannedSince)
);

CREATE TABLE UserSessions (
    sessionId INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    userId    INT UNSIGNED NOT NULL,
    startTime DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    endTime   DATETIME     NULL,                  -- NULL means still active
    KEY idx_session_user (userId),
    CONSTRAINT fk_session_user FOREIGN KEY (userId) REFERENCES UserAccounts (userId)
        ON DELETE CASCADE,
    CONSTRAINT chk_session_times CHECK (endTime IS NULL OR endTime > startTime)

);

-- ---------------------------------------------------------------------
-- Items, categories, images
-- ---------------------------------------------------------------------
CREATE TABLE Categories (
    categoryId INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name       ENUM(
        'AUDIO',
        'BABIES_AND_KIDS',
        'BEAUTY_AND_PERSONAL_CARE',
        'CAR_ACCESSORIES',
        'CARS',
        'COMPUTER_AND_TECH',
        'CLOTHING',
        'ACCESSORIES_AND_JEWELRY',
        'FOOD_AND_DRINKS',
        'FURNITURE_AND_HOME_LIVING',
        'HEALTH_AND_NUTRITION',
        'HOBBIES_AND_LIFESTYLE',
        'MOBILE_PHONES_AND_GADGETS',
        'PET_SUPPLIES',
        'SPORTS_EQUIPMENT',
        'TV_AND_HOME_APPLIANCES',
        'TOYS_AND_COLLECTIBLES',
        'OTHER',
        'BOOKS',
        'ART'
    ) NOT NULL UNIQUE
);

CREATE TABLE Items (
    itemId        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sellerId      INT UNSIGNED NOT NULL,
    title         VARCHAR(255) NOT NULL,
    description   TEXT         NULL,
    itemCondition ENUM('new','like_new','used', 'heavily_used') NOT NULL,
    KEY idx_item_seller (sellerId),
    FULLTEXT KEY ft_item_search (title, description),
    CONSTRAINT fk_item_seller FOREIGN KEY (sellerId) REFERENCES UserAccounts (userId)
);

CREATE TABLE ItemCategories (                      -- many-to-many Items <-> Categories
    categoryId INT UNSIGNED NOT NULL,
    itemId     INT UNSIGNED NOT NULL,
    PRIMARY KEY (categoryId, itemId),
    KEY idx_ic_item (itemId),
    CONSTRAINT fk_ic_category FOREIGN KEY (categoryId) REFERENCES Categories (categoryId)
        ON DELETE CASCADE,
    CONSTRAINT fk_ic_item FOREIGN KEY (itemId) REFERENCES Items (itemId)
        ON DELETE CASCADE
);

CREATE TABLE ItemImages (
    imageId       INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    itemId        INT UNSIGNED  NOT NULL,
    orderOfImage  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    imageLocation VARCHAR(255)  NOT NULL,          -- relative path, e.g. uploads/items/1_1.jpg
    UNIQUE KEY uq_image_order (itemId, orderOfImage),
    CONSTRAINT fk_image_item FOREIGN KEY (itemId) REFERENCES Items (itemId)
        ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Auctions and bids
-- ---------------------------------------------------------------------
CREATE TABLE Auctions (
    auctionId     INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    itemId        INT UNSIGNED  NOT NULL,
    startingPrice DECIMAL(10,2) NOT NULL,
    reservePrice  DECIMAL(10,2) NULL,
    buyNowPrice   DECIMAL(10,2) NULL,
    startDateTime DATETIME      NOT NULL,
    endDateTime   DATETIME      NOT NULL,
    originalEndDateTime DATETIME NULL,
    KEY idx_auction_item (itemId),
    KEY idx_auction_end (endDateTime),
    CONSTRAINT fk_auction_item FOREIGN KEY (itemId) REFERENCES Items (itemId),
    CONSTRAINT chk_auction_dates  CHECK (endDateTime > startDateTime),
    CONSTRAINT chk_auction_orig_end CHECK (originalEndDateTime IS NULL OR originalEndDateTime > endDateTime),
    CONSTRAINT chk_auction_nonneg_price  CHECK (startingPrice >= 0 AND (reservePrice IS NULL OR reservePrice >= 0) AND (buyNowPrice IS NULL OR buyNowPrice >= 0)),
    CONSTRAINT chk_auction_reserve CHECK (reservePrice IS NULL OR reservePrice >= startingPrice),
    CONSTRAINT chk_auction_buynow  CHECK (buyNowPrice  IS NULL OR buyNowPrice  >= startingPrice),
    CONSTRAINT chk_auction_buynow_reserve
    CHECK (buyNowPrice IS NULL OR reservePrice IS NULL OR buyNowPrice >= reservePrice)
);

CREATE TABLE Bids (
    bidId        INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    auctionId    INT UNSIGNED  NOT NULL,
    bidderId     INT UNSIGNED  NOT NULL,
    bidAmount    DECIMAL(10,2) NOT NULL,
    bidTimestamp DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_bid_bidder (bidderId),
    UNIQUE KEY uq_bid_id_auction (bidId, auctionId),
    CONSTRAINT fk_bid_auction FOREIGN KEY (auctionId) REFERENCES Auctions (auctionId),
    CONSTRAINT fk_bid_bidder   FOREIGN KEY (bidderId) REFERENCES UserAccounts (userId),
    CONSTRAINT chk_bid_amount CHECK (bidAmount > 0)
);

-- ---------------------------------------------------------------------
-- Outcomes
-- ---------------------------------------------------------------------
CREATE TABLE OutcomeTypes (
    outcomeTypeId INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name          ENUM(
        'ITEM_SOLD_TO_HIGHEST_BIDDER',
        'ITEM_SOLD_TO_BUY_NOW_BIDDER',
        'UNSOLD',
        'CANCELLED_AUCTION',
        'ADMIN_SHUT_DOWN'
    ) NOT NULL UNIQUE,
    description   VARCHAR(255) NULL
);

CREATE TABLE Outcomes (
    outcomeId     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    auctionId     INT UNSIGNED NOT NULL UNIQUE,
    winningBidId  INT UNSIGNED NULL,               -- NULL when nobody won
    outcomeTypeId INT UNSIGNED NOT NULL,
    CONSTRAINT fk_outcome_auction FOREIGN KEY (auctionId) REFERENCES Auctions (auctionId),
    CONSTRAINT fk_outcome_type    FOREIGN KEY (outcomeTypeId) REFERENCES OutcomeTypes (outcomeTypeId),
    -- composite FK: winning bid must be a bid on THIS auction
    CONSTRAINT fk_outcome_bid FOREIGN KEY (winningBidId, auctionId)
        REFERENCES Bids (bidId, auctionId)
);

-- ---------------------------------------------------------------------
-- Watchlists and favourite sellers
-- ---------------------------------------------------------------------
CREATE TABLE WatchLists (
    auctionId INT UNSIGNED NOT NULL,
    userId    INT UNSIGNED NOT NULL,
    timestamp DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (auctionId, userId),
    KEY idx_watch_user (userId),
    CONSTRAINT fk_watch_auction FOREIGN KEY (auctionId) REFERENCES Auctions (auctionId)
        ON DELETE CASCADE,
    CONSTRAINT fk_watch_user FOREIGN KEY (userId) REFERENCES UserAccounts (userId)
        ON DELETE CASCADE
);

CREATE TABLE FavouriteSellers (
    followerId INT UNSIGNED NOT NULL,
    sellerId   INT UNSIGNED NOT NULL,
    followedAt DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (followerId, sellerId),
    KEY idx_fav_seller (sellerId),
    CONSTRAINT fk_follower FOREIGN KEY (followerId) REFERENCES UserAccounts (userId)
        ON DELETE CASCADE,
    CONSTRAINT fk_seller FOREIGN KEY (sellerId) REFERENCES UserAccounts (userId)
        ON DELETE CASCADE
    -- cant do (followerId <> sellerId) on FK columns that use
    -- ON DELETE CASCADE, so enforce "can't follow yourself" in PHP biz logic.
);

-- ---------------------------------------------------------------------
-- Notifications
-- ---------------------------------------------------------------------
CREATE TABLE NotificationTypes (
    notificationTypeId INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name               ENUM(
        'OUTBID_BY_ANOTHER_BIDDER',
        'FOLLOWED_SELLER_NEW_AUCTION',
        'NEW_AUCTION_BASED_ON_PREFERENCES',
        'AUCTION_START_REMINDER',
        'AUCTION_END_REMINDER',
        'AUCTION_OUTCOME',
        'NEW_FOLLOWER'
    ) NOT NULL UNIQUE,
    description        VARCHAR(255) NULL,
   isOptional         BOOLEAN      NOT NULL DEFAULT TRUE
);

CREATE TABLE UserNotificationPreferences (
    userId             INT UNSIGNED NOT NULL,
    notificationTypeId INT UNSIGNED NOT NULL,
    isEnabled          BOOLEAN      NOT NULL DEFAULT TRUE,   -- email on/off; only rows for OPTIONAL types are used
    PRIMARY KEY (userId, notificationTypeId),
    CONSTRAINT fk_pref_user FOREIGN KEY (userId) REFERENCES UserAccounts (userId)
        ON DELETE CASCADE,
    CONSTRAINT fk_pref_type FOREIGN KEY (notificationTypeId)
        REFERENCES NotificationTypes (notificationTypeId)
        ON DELETE CASCADE
);

CREATE TABLE Notifications (
    notifId            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    receiverId         INT UNSIGNED NOT NULL,
    notificationTypeId INT UNSIGNED NOT NULL,
    auctionId          INT UNSIGNED NULL,          -- [CHANGE] NULL allowed: NEW_FOLLOWER is not about an auction
    bidId              INT UNSIGNED NULL,          -- only set for bid-related notifications (e.g. outbid)
    relatedUserId      INT UNSIGNED NULL,          -- [CHANGE] the other person (e.g. who followed you)
    timestamp          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,   -- when the notification was created
    readAt             DATETIME     NULL,          -- [CHANGE] NULL = unread (drives the bell badge)
    emailSentAt        DATETIME     NULL,          -- [CHANGE] NULL = email not sent yet; set when the email goes out
    KEY idx_notif_receiver (receiverId, timestamp),
    KEY idx_notif_unread (receiverId, readAt),
    KEY idx_notif_email (emailSentAt),
    CONSTRAINT fk_notif_receiver FOREIGN KEY (receiverId) REFERENCES UserAccounts (userId)
        ON DELETE CASCADE,
    CONSTRAINT fk_notif_type FOREIGN KEY (notificationTypeId)
        REFERENCES NotificationTypes (notificationTypeId),
    CONSTRAINT fk_notif_auction FOREIGN KEY (auctionId) REFERENCES Auctions (auctionId),
    CONSTRAINT fk_notif_bid FOREIGN KEY (bidId) REFERENCES Bids (bidId),
    CONSTRAINT fk_notif_related FOREIGN KEY (relatedUserId) REFERENCES UserAccounts (userId)
        ON DELETE SET NULL
);

-- ---------------------------------------------------------------------
-- Activity tracking (for recommendations)
-- ---------------------------------------------------------------------
CREATE TABLE ActivityTypes (
    activityTypeId INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name           ENUM(
        'AUCTION_SEARCH',
        'VIEW_AUCTION',
        'BROWSE_CATEGORY',
        'ADD_TO_WATCHLIST'
    ) NOT NULL UNIQUE,
    description    VARCHAR(255) NULL
);

CREATE TABLE ActivityLogs (
    activityId     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sessionId      INT UNSIGNED NOT NULL,
    activityTypeId INT UNSIGNED NOT NULL,
    auctionId      INT UNSIGNED NULL,              -- set for view_auction
    categoryId     INT UNSIGNED NULL,              -- set for browse_category
    searchQuery    VARCHAR(255) NULL,              -- set for search
    timestamp      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activity_session (sessionId),
    CONSTRAINT fk_activity_session  FOREIGN KEY (sessionId) REFERENCES UserSessions (sessionId)
        ON DELETE CASCADE,
    CONSTRAINT fk_activity_type     FOREIGN KEY (activityTypeId) REFERENCES ActivityTypes (activityTypeId),
    CONSTRAINT fk_activity_auction  FOREIGN KEY (auctionId)  REFERENCES Auctions (auctionId)
        ON DELETE SET NULL,
    CONSTRAINT fk_activity_category FOREIGN KEY (categoryId) REFERENCES Categories (categoryId)
        ON DELETE SET NULL
);

-- ---------------------------------------------------------------------
-- Convenience view: current price per auction (highest bid or starting price)
-- ---------------------------------------------------------------------
CREATE VIEW AuctionCurrentPrice AS
SELECT a.auctionId,
       COALESCE(MAX(b.bidAmount), a.startingPrice) AS currentPrice,
       COUNT(b.bidId)                              AS numBids
FROM Auctions a
LEFT JOIN Bids b ON b.auctionId = a.auctionId
GROUP BY a.auctionId, a.startingPrice;