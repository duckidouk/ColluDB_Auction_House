Do we need diff entities for notification type? Should we not have just the name within the Notification entity. 
  Same question for Role 
Category parent-child thing


Do we seperate entity for each outcome (unsold, cancelled etc..), notification type 
Do we need home-address + bank details, credit card 


Features: 

  - Price History
  - Filter by price range 


Core Features:
  - User Registration
  - Create an item + link to auction: Nick Muffin DONE
  - Query for buyers to search items: Nickash DONE
  - Bidding + seeing other bids: Duc DONE
        - I am loading auction data and bid history from SQL, it add 5% bid validation, and save valid bids
        - Using buyer seed #5 to test bidding. Would need login features for full test
        - Buy-now is seperate from the main bidding, I cannot remember how we wish to implement buy-now feature.
  - Outcome bid notif + email: Saloni PENDING
    - notification feed --> Bell when not on listing page 
  - Finish listing page: DUC
    - Display buy now
    - Display reserve price
    - Buy now button 
    - Addind a max bid value for input 
    - Show seller on listing page, can follow profile (wait on nick for seller profile)
  - Buyer watchlist: Duc 
    - Have buyer be able to watch all his live auctions or scheduled auction 
    - Sorted watchlist -> By price, by 


Extra feature: 
  - Let the seller accept a winning bid under buy now price 
  - sort by: Nickash
    - price-range
    - live vs ended 
    -
