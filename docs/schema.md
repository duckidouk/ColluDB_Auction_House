User_Accounts(
  userID       PK,
  emailAddress UK,
  roleID       FK -> Role.Role_ID,
  firstName,
  lastName,
  passwordHash,
)

Role(
  roleID      PK,
  roleDescription,
  name
)

User_Ban(
  banID      PK,
  userID     FK -> UserAccount.userID
  bannedSince,
  bannedUntil,
  reason
)

User_Session(
  sessionID  PK,
  userID     FK --> UserAccount.userID,
  startTime,
  endTime,
)

Activity_Log(
  activityID            PK,
  activityTypeID        FK --> Activity_Types.activityTypeID,
  auctionID (Nullable)  FK --> Auction.auctionID,
  categoryID (Nullable) FK --> Categories.categoryID,
  sessionID             FK --> User_Session.sessionID
  searchQuery (Nullable),
  timestamp,
)

Activity_Types(
  activityTypeID    PK,
  name
  description
)
