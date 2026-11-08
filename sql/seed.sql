-- =====================================================================
-- 02_seed.sql  -  Sample data. Run AFTER 01_schema.sql
-- Run:  mysql -u root -p auction_db < sql/02_seed.sql
--
-- All sample users have the password:  password
-- (bcrypt hash below - in real code always generate with password_hash())
-- Dates are relative to NOW() so you always have live, ended and upcoming auctions.
-- =====================================================================
USE auction_db;

-- ---------- Roles ----------
INSERT INTO Roles (roleId, name, roleDescription) VALUES
(1, 'admin',  'Can manage users, bans and all auctions'),
(2, 'seller', 'Can list items and create auctions'),
(3, 'buyer',  'Can bid on auctions and keep a watchlist');

-- ---------- Users ----------
INSERT INTO UserAccounts (userId, firstName, lastName, emailAddress, passwordHash, roleId) VALUES
(1, 'Ada',    'Admin',   'admin@auctionhouse.test',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(2, 'Priya',  'Shah',    'priya.shah@example.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2),
(3, 'Marcus', 'Webb',    'marcus.webb@example.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2),
(4, 'Elena',  'Rossi',   'elena.rossi@example.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2),
(5, 'Tom',    'Baker',   'tom.baker@example.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3),
(6, 'Aisha',  'Khan',    'aisha.khan@example.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3),
(7, 'Liam',   'Murphy',  'liam.murphy@example.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3),
(8, 'Chloe',  'Dubois',  'chloe.dubois@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3),
(9, 'Raj',    'Patel',   'raj.patel@example.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3);

-- ---------- Bans (one active, one expired) ----------
INSERT INTO UserBans (banId, userId, bannedSince, bannedUntil, reason) VALUES
(1, 9, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_ADD(NOW(), INTERVAL 20 DAY), 'Repeated non-payment after winning auctions'),
(2, 8, DATE_SUB(NOW(), INTERVAL 60 DAY), DATE_SUB(NOW(), INTERVAL 45 DAY), 'Spam messages to sellers');

-- ---------- Categories ----------
INSERT INTO Categories (categoryId, name) VALUES
(1, 'AUDIO'),
(2, 'BABIES_AND_KIDS'),
(3, 'BEAUTY_AND_PERSONAL_CARE'),
(4, 'CAR_ACCESSORIES'),
(5, 'CARS'),
(6, 'COMPUTER_AND_TECH'),
(7, 'CLOTHING'),
(8, 'ACCESSORIES_AND_JEWELRY'),
(9, 'FOOD_AND_DRINKS'),
(10, 'FURNITURE_AND_HOME_LIVING'),
(11, 'HEALTH_AND_NUTRITION'),
(12, 'HOBBIES_AND_LIFESTYLE'),
(13, 'MOBILE_PHONES_AND_GADGETS'),
(14, 'PET_SUPPLIES'),
(15, 'SPORTS_EQUIPMENT'),
(16, 'TV_AND_HOME_APPLIANCES'),
(17, 'TOYS_AND_COLLECTIBLES'),
(18, 'OTHER'),
(19, 'BOOKS'),
(20, 'ART');

-- ---------- Items ----------
INSERT INTO Items (itemId, sellerId, title, description, itemCondition) VALUES
(1, 2, 'Vintage Canon AE-1 Film Camera',        '35mm SLR with 50mm f/1.8 lens. Light meter works, minor cosmetic wear.',          'used'),
(2, 2, 'Sony WH-1000XM4 Headphones',            'Noise cancelling wireless headphones. Used a handful of times, boxed.',          'like_new'),
(3, 3, 'Paperback Book Bundle',                 'A mixed set of popular paperback novels with light shelf wear.',                 'used'),
(4, 3, 'Wooden Dining Chair',                   'Solid wood dining chair in good condition with a few signs of use.',             'used'),
(5, 4, 'Leather Messenger Bag',                 'Full-grain brown leather, brass fittings, laptop sleeve. Brand new with tags.',   'new'),
(6, 4, 'Framed Abstract Oil Painting',          'Original oil on canvas, 60x80cm, signed lower right. Black wooden frame.',        'like_new'),
(7, 2, 'Nintendo Game Boy Color (Teal)',        'Fully working, new screen protector, no battery cover scratches.',                'used'),
(8, 3, 'Vintage Omega Seamaster Watch',         '1970s automatic, recently serviced. Case shows signs of age.',                    'heavily_used'),
(9, 4, 'Mechanical Keyboard (Cherry MX Brown)', 'Tenkeyless, PBT keycaps, USB-C. Not listed in an auction yet.',                   'new'),
(10, 2, 'Nikon FM2 35mm Film Camera',            'Classic manual SLR with a working light meter and Nikkor 50mm lens.',             'used'),
(11, 2, 'Bose QuietComfort 35 Headphones',        'Wireless noise-cancelling headphones with carrying case and charging cable.',     'used'),
(12, 3, 'Cookbook Set',                           'Four everyday cookbooks with clean pages and lightly worn covers.',              'used'),
(13, 3, 'Oak Coffee Table',                      'Solid oak table with a lower shelf and a few small marks from normal use.',      'used'),
(14, 4, 'Canvas Backpack',                        'Everyday canvas backpack with adjustable shoulder straps and a front pocket.',   'like_new'),
(15, 4, 'Original Landscape Watercolour',         'Small signed landscape painting in a simple dark wood frame.',                   'like_new'),
(16, 2, 'Super Nintendo Console Bundle',          'Working console with two controllers and a selection of classic games.',         'used'),
(17, 3, 'Seiko Automatic Dress Watch',            'Stainless steel automatic watch with a black dial and a recently replaced strap.', 'used'),
(18, 4, 'Handmade Ceramic Table Lamp',             'Glazed stoneware base with a neutral linen shade; tested and working.',           'like_new'),
(19, 3, 'Classic Rock Vinyl Record Collection',    'A curated set of twelve LPs with sleeves; condition ranges from good to very good.', 'used'),
(20, 2, 'Handwoven Wool Area Rug',                'Traditional geometric pattern in blue and red, professionally cleaned.',          'used');

INSERT INTO ItemCategories (categoryId, itemId) VALUES
(6,1),(12,1), (1,2),(6,2), (19,3), (10,4), (7,5),(8,5), (20,6), (6,7),(17,7),
(8,8),(12,8), (6,9), (6,10),(12,10), (1,11),(6,11), (9,12),(19,12), (10,13),
(7,14),(12,14), (20,15), (6,16),(17,16), (8,17), (10,18), (1,19),(17,19), (10,20);

INSERT INTO ItemImages (itemId, orderOfImage, imageLocation) VALUES
(1, 1, 'uploads/items/1-vintage-canon-ae-1-film-camera-1.jpg'),
(1, 2, 'uploads/items/1-vintage-canon-ae-1-film-camera-2.jpg'),
(1, 3, 'uploads/items/1-vintage-canon-ae-1-film-camera-3.jpg'),
(2, 1, 'uploads/items/2-sony-wh-1000xm4-headphones-1.jpg'),
(2, 2, 'uploads/items/2-sony-wh-1000xm4-headphones-2.jpg'),
(3, 1, 'uploads/items/3-paperback-book-bundle-1.jpg'),
(3, 2, 'uploads/items/3-paperback-book-bundle-2.jpg'),
(4, 1, 'uploads/items/4-wooden-dining-chair-1.jpg'),
(4, 2, 'uploads/items/4-wooden-dining-chair-2.jpg'),
(4, 3, 'uploads/items/4-wooden-dining-chair-3.jpg'),
(5, 1, 'uploads/items/5-leather-messenger-bag-1.jpg'),
(5, 2, 'uploads/items/5-leather-messenger-bag-2.jpg'),
(6, 1, 'uploads/items/6-framed-abstract-oil-painting-1.png'),
(7, 1, 'uploads/items/7-nintendo-game-boy-color-teal-1.jpg'),
(7, 2, 'uploads/items/7-nintendo-game-boy-color-teal-2.jpg'),
(8, 1, 'uploads/items/8-vintage-omega-seamaster-watch-1.jpg'),
(8, 2, 'uploads/items/8-vintage-omega-seamaster-watch-2.jpg'),
(9, 1, 'uploads/items/9-mechanical-keyboard-cherry-mx-brown-1.jpg'),
(9, 2, 'uploads/items/9-mechanical-keyboard-cherry-mx-brown-2.jpg'),
(10, 1, 'uploads/items/10-nikon-fm2-35mm-film-camera-1.jpg'),
(10, 2, 'uploads/items/10-nikon-fm2-35mm-film-camera-2.jpg'),
(10, 3, 'uploads/items/10-nikon-fm2-35mm-film-camera-3.jpg'),
(11, 1, 'uploads/items/11-bose-quietcomfort-35-headphones-1.jpg'),
(11, 2, 'uploads/items/11-bose-quietcomfort-35-headphones-2.jpg'),
(13, 1, 'uploads/items/13-oak-coffee-table-1.jpg'),
(14, 1, 'uploads/items/14-canvas-backpack-1.jpg'),
(14, 2, 'uploads/items/14-canvas-backpack-2.jpg'),
(15, 1, 'uploads/items/15-original-landscape-watercolour-1.jpg'),
(16, 1, 'uploads/items/16-super-nintendo-console-bundle-1.jpg'),
(16, 3, 'uploads/items/16-super-nintendo-console-bundle-3.jpg'),
(17, 1, 'uploads/items/17-seiko-automatic-dress-watch-1.jpg'),
(17, 2, 'uploads/items/17-seiko-automatic-dress-watch-2.jpg'),
(18, 2, 'uploads/items/18-handmade-ceramic-table-lamp-2.jpg'),
(19, 1, 'uploads/items/19-classic-rock-vinyl-record-collection-1.jpg'),
(19, 2, 'uploads/items/19-classic-rock-vinyl-record-collection-2.jpg'),
(20, 3, 'uploads/items/20-handwoven-wool-area-rug-3.jpg');

-- ---------- Auctions ----------
-- 1 ended (sold) | 2,3,6,8 live | 4 and 7 ended unsold | 5 upcoming
INSERT INTO Auctions (auctionId, itemId, startingPrice, reservePrice, buyNowPrice, startDateTime, endDateTime) VALUES
(1, 1,  20.00,  60.00, NULL,   DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 2,  50.00, 120.00, 200.00, DATE_SUB(NOW(), INTERVAL 2 DAY),  DATE_ADD(NOW(), INTERVAL 3 DAY)),
(3, 3,  15.00,  NULL,   60.00, DATE_SUB(NOW(), INTERVAL 1 DAY),  DATE_ADD(NOW(), INTERVAL 5 DAY)),
(4, 4, 100.00, 300.00, NULL,   DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY)),
(5, 5,  40.00,  NULL,   90.00, DATE_ADD(NOW(), INTERVAL 1 DAY),  DATE_ADD(NOW(), INTERVAL 8 DAY)),
(6, 6, 200.00, 400.00, NULL,   DATE_SUB(NOW(), INTERVAL 6 DAY),  DATE_ADD(NOW(), INTERVAL 6 HOUR)),
(7, 7,  25.00,  NULL,   NULL,  DATE_SUB(NOW(), INTERVAL 8 DAY),  DATE_SUB(NOW(), INTERVAL 5 DAY)),
(8, 8, 250.00, 500.00, 900.00, DATE_SUB(NOW(), INTERVAL 3 DAY),  DATE_ADD(NOW(), INTERVAL 4 DAY));

-- ---------- Bids ----------
INSERT INTO Bids (bidId, auctionId, bidderId, bidAmount, bidTimestamp) VALUES
-- auction 1 (won by Liam @ 70, reserve 60 met)
(1,  1, 5,  25.00, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(2,  1, 6,  30.00, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(3,  1, 5,  45.00, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(4,  1, 7,  70.00, DATE_SUB(NOW(), INTERVAL 4 DAY)),
-- auction 2 (live)
(5,  2, 6,  55.00, DATE_SUB(NOW(), INTERVAL 40 HOUR)),
(6,  2, 7,  60.00, DATE_SUB(NOW(), INTERVAL 30 HOUR)),
(7,  2, 5,  75.00, DATE_SUB(NOW(), INTERVAL 20 HOUR)),
(8,  2, 6,  90.00, DATE_SUB(NOW(), INTERVAL 5 HOUR)),
-- auction 3 (live)
(9,  3, 8,  16.00, DATE_SUB(NOW(), INTERVAL 20 HOUR)),
(10, 3, 5,  20.00, DATE_SUB(NOW(), INTERVAL 10 HOUR)),
-- auction 4 (ended, highest 150 < reserve 300)
(11, 4, 7, 110.00, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(12, 4, 8, 150.00, DATE_SUB(NOW(), INTERVAL 10 DAY)),
-- auction 6 (live, ends in 6 hours)
(13, 6, 5, 210.00, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(14, 6, 8, 260.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(15, 6, 6, 300.00, DATE_SUB(NOW(), INTERVAL 1 DAY)),
-- auction 8 (live)
(16, 8, 7, 260.00, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(17, 8, 5, 310.00, DATE_SUB(NOW(), INTERVAL 1 DAY));

-- ---------- Outcomes ----------
INSERT INTO OutcomeTypes (outcomeTypeId, name, description) VALUES
(1, 'ITEM_SOLD_TO_HIGHEST_BIDDER', 'The highest bid met the reserve price'),
(2, 'ITEM_SOLD_TO_BUY_NOW_BIDDER', 'A bidder purchased the item at its buy-now price'),
(3, 'UNSOLD',                      'The auction ended without a qualifying sale'),
(4, 'CANCELLED_AUCTION',           'The seller cancelled the auction'),
(5, 'ADMIN_SHUT_DOWN',             'An administrator ended the auction');

INSERT INTO Outcomes (outcomeId, auctionId, winningBidId, outcomeTypeId) VALUES
(1, 1, 4,    1),
(2, 4, NULL, 3),
(3, 7, NULL, 3);

-- ---------- Watchlists / favourite sellers ----------
INSERT INTO WatchLists (auctionId, userId, timestamp) VALUES
(2, 6, DATE_SUB(NOW(), INTERVAL 45 HOUR)),
(2, 7, DATE_SUB(NOW(), INTERVAL 35 HOUR)),
(6, 8, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(5, 5, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(8, 5, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(8, 6, DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO FavouriteSellers (followerId, sellerId, followedAt) VALUES
(5, 2, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(5, 4, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(6, 3, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(7, 2, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(8, 4, DATE_SUB(NOW(), INTERVAL 6 DAY));

-- ---------- Notification types, preferences, notifications ----------
INSERT INTO NotificationTypes (notificationTypeId, name, description, isOptional) VALUES
(1, 'OUTBID_BY_ANOTHER_BIDDER', 'Someone placed a higher bid than yours', TRUE),
(2, 'FOLLOWED_SELLER_NEW_AUCTION', 'A seller you follow listed an item for auction', TRUE),
(3, 'NEW_AUCTION_BASED_ON_PREFERENCES', 'A new auction matches your preferences', TRUE),
(4, 'AUCTION_START_REMINDER', 'A watched auction is starting soon', TRUE),
(5, 'AUCTION_END_REMINDER', 'An auction you bid on or watched is ending soon', TRUE),
(6, 'AUCTION_OUTCOME', 'An auction you bid on or watched has ended', FALSE),
(7, 'NEW_FOLLOWER', 'Someone started following you', TRUE);

INSERT INTO UserNotificationPreferences (userId, notificationTypeId, isEnabled) VALUES
(5, 1, TRUE), (5, 2, TRUE), (5, 3, TRUE), (5, 4, TRUE), (5, 5, TRUE),
(5, 6, TRUE), (6, 1, TRUE), (6, 4, FALSE), (6, 5, FALSE),
(6, 6, TRUE), (7, 1, FALSE), (7, 4, TRUE), (7, 5, TRUE),
(7, 6, TRUE), (2, 6, TRUE), (3, 6, TRUE), (4, 6, TRUE);

INSERT INTO Notifications (notifId, receiverId, notificationTypeId, auctionId, bidId, timestamp) VALUES
(1, 5, 1, 1, 2, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(2, 6, 1, 1, 3, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(3, 5, 1, 1, 4, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(4, 7, 6, 1, 4, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(5, 6, 1, 2, 7, DATE_SUB(NOW(), INTERVAL 20 HOUR)),
(6, 5, 1, 2, 8, DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(7, 8, 1, 6, 15, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(8, 5, 2, 5, NULL, DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- ---------- Sessions & activity ----------
INSERT INTO ActivityTypes (activityTypeId, name, description) VALUES
(1, 'AUCTION_SEARCH',   'User ran an auction search, optionally with filters'),
(2, 'VIEW_AUCTION',      'User opened an auction page'),
(3, 'BROWSE_CATEGORY',   'User browsed a category'),
(4, 'ADD_TO_WATCHLIST',  'User added an auction to their watchlist');

INSERT INTO UserSessions (sessionId, userId, startTime, endTime) VALUES
(1, 5, DATE_SUB(NOW(), INTERVAL 2 DAY),   DATE_SUB(NOW(), INTERVAL 47 HOUR)),
(2, 6, DATE_SUB(NOW(), INTERVAL 1 DAY),   DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(3, 7, DATE_SUB(NOW(), INTERVAL 2 HOUR),  NULL),
(4, 8, DATE_SUB(NOW(), INTERVAL 5 HOUR),  DATE_SUB(NOW(), INTERVAL 4 HOUR));

INSERT INTO ActivityLogs (activityId, sessionId, activityTypeId, auctionId, categoryId, searchQuery, timestamp) VALUES
(1, 1, 1, NULL, NULL, 'vintage camera', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 1, 2, 1,    NULL, NULL,             DATE_SUB(NOW(), INTERVAL 47 HOUR)),
(3, 2, 3, NULL, 1,    NULL,             DATE_SUB(NOW(), INTERVAL 24 HOUR)),
(4, 2, 2, 2,    NULL, NULL,             DATE_SUB(NOW(), INTERVAL 24 HOUR)),
(6, 3, 1, NULL, NULL, 'omega watch',    DATE_SUB(NOW(), INTERVAL 110 MINUTE)),
(7, 3, 2, 8,    NULL, NULL,             DATE_SUB(NOW(), INTERVAL 100 MINUTE)),
(8, 4, 3, NULL, 6,    NULL,             DATE_SUB(NOW(), INTERVAL 5 HOUR));