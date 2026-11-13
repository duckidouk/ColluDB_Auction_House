<?php include_once("header.php")?>
<?php require_once __DIR__ . '/utilities.php'?>
<?php
/*
 * browse.php — Capability 3: buyers search for items being auctioned,
 * filter by one or more categories, a price range, whether the item has a
 * photo and the auction's status, and re-arrange (sort) the listings.
 *
 * Flow: 1) read + validate the URL inputs, 2) build the WHERE clause from the
 * filters actually set, 3) count matches for pagination, 4) fetch one page.
 * All user values go through prepared-statement placeholders (?); the sort
 * order uses a whitelist because ORDER BY cannot take placeholders.
 */
require_once __DIR__ . '/../sql/database.php';

// ---------- 1. Read and validate the inputs from the URL (GET) ----------
$keyword = trim($_GET['keyword'] ?? '');

// All categories, for the tick boxes (shows the name, sends the id). Read here,
// before the filters, so ids that are not real categories can be dropped below.
$categories = mysqli_fetch_all(
  mysqli_query($connection, 'SELECT categoryId, name FROM Categories ORDER BY name'),
  MYSQLI_ASSOC
);
$known_category_ids = array_map('intval', array_column($categories, 'categoryId'));

// Categories: zero or more ids, sent as cat[]=6&cat[]=12 by the tick boxes.
// Each must be a real category id; anything else ('all', 'abc', 999) is
// dropped, like a bad price. An empty list means no category filter.
// Old single links (cat=12) still work.
$raw_categories = $_GET['cat'] ?? [];
if (!is_array($raw_categories)) {
  $raw_categories = [$raw_categories];
}
$category_ids = [];
foreach ($raw_categories as $raw_category) {
  $id = filter_var($raw_category, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
  if ($id !== false && in_array($id, $known_category_ids, true)) {
    $category_ids[] = $id;
  }
}
$category_ids = array_values(array_unique($category_ids));  // the same id twice counts once

// Price range on the CURRENT price (highest bid, else starting price), in £.
// Both ends are optional and inclusive. Each must be a number >= 0; blank or
// invalid (text, negative) means that end is ignored, like 'all' for cat.
$min_price = filter_var($_GET['min_price'] ?? '', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
if ($min_price === false) {
  $min_price = null;
}
$max_price = filter_var($_GET['max_price'] ?? '', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
if ($max_price === false) {
  $max_price = null;
}
// Typed the wrong way round (e.g. 100 to 30)? Swap instead of showing nothing.
if ($min_price !== null && $max_price !== null && $min_price > $max_price) {
  [$min_price, $max_price] = [$max_price, $min_price];
}

// Has a photo: one tick box. Only the exact value '1' (what the box sends)
// switches the filter on; anything else, or nothing, means off.
$has_picture = ($_GET['has_picture'] ?? '') === '1';

// Status tick boxes. Each status maps to a fixed piece of SQL (a whitelist, like
// the sort), using MySQL's clock. An auction with a row in Outcomes (sold,
// unsold, bought now, cancelled, shut down) is closed whatever its end time
// says, the same rule as place_bid.php: cancelling or shutting down does not
// move the end time, so the dates alone would still call it Live. Nothing
// ticked, or nothing valid, means the default agreed at the 10 Nov meeting:
// Live and Scheduled, but not Ended. Old single links (status=ended) still work.
$closed_sql = 'EXISTS (SELECT 1 FROM Outcomes o WHERE o.auctionId = a.auctionId)';
$status_sql = [
  'live'      => "(a.startDateTime <= NOW() AND a.endDateTime > NOW() AND NOT $closed_sql)",
  'scheduled' => "(a.startDateTime > NOW() AND NOT $closed_sql)",
  'ended'     => "(a.endDateTime <= NOW() OR $closed_sql)",
];
$raw_statuses = $_GET['status'] ?? [];
if (!is_array($raw_statuses)) {
  $raw_statuses = [$raw_statuses];
}
$raw_statuses = array_filter($raw_statuses, 'is_string');  // drop lists inside the list
// Keep the known names only, once each, in a fixed order.
$statuses = array_values(array_intersect(array_keys($status_sql), $raw_statuses));
if (!$statuses) {
  $statuses = ['live', 'scheduled'];
}

// Whitelist: form value => fixed ORDER BY text. auctionId is a tie-breaker so
// the order is deterministic and pagination never repeats or skips a row.
// 'date' puts live auctions first (soonest ending on top), ended ones after.
// 'status' groups Live, then Scheduled, then Ended (built from the same rules
// as the status filter above), cheapest first within each group.
$sort_options = [
  'pricelow'  => 'p.currentPrice ASC, a.auctionId ASC',
  'pricehigh' => 'p.currentPrice DESC, a.auctionId ASC',
  'date'      => '(a.endDateTime < NOW()) ASC, a.endDateTime ASC, a.auctionId ASC',
  'status'    => "CASE WHEN {$status_sql['live']} THEN 0 WHEN {$status_sql['scheduled']} THEN 1 ELSE 2 END ASC,
                  p.currentPrice ASC, a.auctionId ASC",
];
$ordering = $_GET['order_by'] ?? 'pricelow';
if (!array_key_exists($ordering, $sort_options)) {
  $ordering = 'pricelow';
}
$order_sql = $sort_options[$ordering];

$curr_page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($curr_page === false) {
  $curr_page = 1;
}
$results_per_page = 3;  // deliberately small so pagination is visible with the seed data (8 auctions); raise (e.g. 10) once there is more data

// ---------- 2. Build the WHERE clause from the filters the user set ----------
$conditions = [];
$types = '';      // one letter per placeholder: s = string, i = integer, d = double (decimal number)
$params = [];

if ($keyword !== '') {
  // Keyword matches the title, the description, OR one of the item's category
  // names (underscores swapped for spaces, so "home living" finds
  // FURNITURE_AND_HOME_LIVING). EXISTS again avoids duplicate rows.
  $conditions[] = "(i.title LIKE ? OR i.description LIKE ?
                    OR EXISTS (SELECT 1 FROM ItemCategories ic3
                                 JOIN Categories c3 ON c3.categoryId = ic3.categoryId
                                WHERE ic3.itemId = i.itemId
                                  AND REPLACE(c3.name, '_', ' ') LIKE ?))";
  $like = '%' . $keyword . '%';
  $types .= 'sss';
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}
if ($category_ids) {
  // ANY of the ticked categories (OR). EXISTS keeps the auction if its item has
  // at least one of them, without joining ItemCategories into the main query,
  // so an item in two ticked categories still appears once.
  // One ? per id: only question marks are built into the SQL text, never the ids.
  $placeholders = implode(', ', array_fill(0, count($category_ids), '?'));
  $conditions[] = "EXISTS (SELECT 1 FROM ItemCategories ic2
                            WHERE ic2.itemId = i.itemId AND ic2.categoryId IN ($placeholders))";
  $types .= str_repeat('i', count($category_ids));
  array_push($params, ...$category_ids);
}
if ($min_price !== null) {
  // p = the AuctionCurrentPrice view (one row per auction).
  $conditions[] = 'p.currentPrice >= ?';
  $types .= 'd';
  $params[] = $min_price;
}
if ($max_price !== null) {
  $conditions[] = 'p.currentPrice <= ?';
  $types .= 'd';
  $params[] = $max_price;
}
if ($has_picture) {
  // EXISTS keeps the auction if its item has at least one row in ItemImages,
  // without joining ItemImages (one row per image would repeat the card).
  // No ? here: the buyer only decides WHETHER this fixed text is added.
  $conditions[] = 'EXISTS (SELECT 1 FROM ItemImages im WHERE im.itemId = i.itemId)';
}
if (count($statuses) < count($status_sql)) {
  // OR between the ticked statuses, inside brackets so they stay together when
  // ANDed with the other filters (SQL does AND before OR). All three ticked
  // means every auction, so then no condition is added at all. No ? here: only
  // fixed text from the whitelist goes into the SQL.
  $status_parts = [];
  foreach ($statuses as $status_name) {
    $status_parts[] = $status_sql[$status_name];
  }
  $conditions[] = '(' . implode(' OR ', $status_parts) . ')';
}
$where_sql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// ---------- 3. Count matching auctions (for pagination) ----------
// The count must join every table the WHERE can mention. The price conditions
// use p.currentPrice, so the view is joined here too ("Unknown column"
// otherwise). The view has exactly one row per auction, so the join adds no rows.
$count_stmt = mysqli_prepare($connection,
  "SELECT COUNT(*)
     FROM Auctions a
     JOIN Items i               ON a.itemId = i.itemId
     JOIN AuctionCurrentPrice p ON a.auctionId = p.auctionId
   $where_sql");
if ($types !== '') {
  mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
mysqli_stmt_bind_result($count_stmt, $num_results);
mysqli_stmt_fetch($count_stmt);
mysqli_stmt_close($count_stmt);

$max_page = max(1, (int) ceil($num_results / $results_per_page));
if ($curr_page > $max_page) {
  $curr_page = $max_page;
}
$offset = ($curr_page - 1) * $results_per_page;

// ---------- 4. Fetch one page of results ----------
$sql = "SELECT a.auctionId, i.title, i.description, p.currentPrice, p.numBids,
               a.startDateTime, a.endDateTime,
               (SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ')
                  FROM ItemCategories ic
                  JOIN Categories c ON c.categoryId = ic.categoryId
                 WHERE ic.itemId = i.itemId) AS categories
          FROM Auctions a
          JOIN Items i               ON a.itemId = i.itemId
          JOIN AuctionCurrentPrice p ON a.auctionId = p.auctionId
          $where_sql
         ORDER BY $order_sql
         LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($connection, $sql);
$page_types  = $types . 'ii';
$page_params = array_merge($params, [$results_per_page, $offset]);
mysqli_stmt_bind_param($stmt, $page_types, ...$page_params);
mysqli_stmt_execute($stmt);
$results = mysqli_stmt_get_result($stmt);

// Escape anything printed into HTML (stops stored text being run as code).
function browse_h($value) {
  return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>

<div class="container">

<h2 class="my-3">Browse listings</h2>

<div id="searchSpecs">
<!-- The form submits to this same page with GET, so the choices appear in the
     URL (browse.php?keyword=...&cat=...&order_by=...). Each input needs a
     name attribute or the browser does not send it. -->
<form method="get" action="browse.php">
  <div class="row">
    <div class="col-md-5 pr-0">
      <div class="form-group">
        <label for="keyword" class="sr-only">Search keyword:</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text bg-transparent pr-0 text-muted">
              <i class="fa fa-search"></i>
            </span>
          </div>
          <input type="text" class="form-control border-left-0" id="keyword" name="keyword"
                 placeholder="Search for anything" value="<?php echo browse_h($keyword); ?>">
        </div>
      </div>
    </div>
    <div class="col-md-3 pr-0">
      <div class="form-group">
<?php
  // Button text: nothing ticked = all; one = its name; more = how many.
  $ticked_names = [];
  foreach ($categories as $category) {
    if (in_array((int) $category['categoryId'], $category_ids, true)) {
      $ticked_names[] = category_display_name($category['name']);
    }
  }
  if (!$ticked_names) {
    $category_button_text = 'All categories';
  } elseif (count($ticked_names) === 1) {
    $category_button_text = $ticked_names[0];
  } else {
    $category_button_text = count($ticked_names) . ' categories';
  }
?>
        <!-- Tick-box dropdown: each ticked box sends cat[]=<id>, so PHP receives
             a list. Nothing ticked sends nothing, which means all categories. -->
        <div class="dropdown">
          <button type="button" class="form-control dropdown-toggle d-flex justify-content-between align-items-center"
                  id="categoryButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <span class="text-truncate"><span class="sr-only">Categories: </span><?php echo browse_h($category_button_text); ?></span>
          </button>
          <div class="dropdown-menu" id="categoryMenu" aria-labelledby="categoryButton"
               style="min-width: 100%; max-height: 300px; overflow-y: auto;">
<?php foreach ($categories as $category): ?>
<?php   $cid = (int) $category['categoryId']; ?>
            <label class="dropdown-item mb-0">
              <input type="checkbox" class="mr-2" name="cat[]" value="<?php echo $cid; ?>"<?php echo in_array($cid, $category_ids, true) ? ' checked' : ''; ?>>
              <?php echo browse_h(category_display_name($category['name'])); ?>
            </label>
<?php endforeach; ?>
          </div>
        </div>
        <script>
          // Bootstrap 4 closes a dropdown on any click. Stop clicks inside the
          // list from reaching the page, so several boxes can be ticked in one go.
          document.getElementById('categoryMenu').addEventListener('click', function (event) {
            event.stopPropagation();
          });
        </script>
      </div>
    </div>
    <div class="col-md-3 pr-0">
      <div class="form-inline">
        <label class="mx-2" for="order_by">Sort by:</label>
        <select class="form-control" id="order_by" name="order_by">
          <option value="pricelow"<?php echo $ordering === 'pricelow' ? ' selected' : ''; ?>>Price (low to high)</option>
          <option value="pricehigh"<?php echo $ordering === 'pricehigh' ? ' selected' : ''; ?>>Price (high to low)</option>
          <option value="date"<?php echo $ordering === 'date' ? ' selected' : ''; ?>>Soonest expiry</option>
          <option value="status"<?php echo $ordering === 'status' ? ' selected' : ''; ?>>Status (live first)</option>
        </select>
      </div>
    </div>
    <div class="col-md-1 px-0">
      <button type="submit" class="btn btn-primary">Search</button>
    </div>
  </div>
  <div class="row">
    <div class="col-md-5 pr-0">
      <!-- Price range. type="number" and min="0" stop most bad input in the
           browser, but anyone can edit the URL, so PHP checks the values again. -->
      <div class="form-group">
        <label for="min_price" class="sr-only">Minimum price in pounds:</label>
        <label for="max_price" class="sr-only">Maximum price in pounds:</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text">Price &pound;</span>
          </div>
          <input type="number" class="form-control" id="min_price" name="min_price"
                 min="0" step="0.01" placeholder="Min"
                 value="<?php echo $min_price === null ? '' : browse_h($min_price); ?>">
          <div class="input-group-append">
            <span class="input-group-text">to &pound;</span>
          </div>
          <input type="number" class="form-control" id="max_price" name="max_price"
                 min="0" step="0.01" placeholder="Max"
                 value="<?php echo $max_price === null ? '' : browse_h($max_price); ?>">
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <!-- Has a photo: ticked sends has_picture=1; unticked sends nothing. -->
      <div class="form-check mt-2 ml-md-3">
        <input type="checkbox" class="form-check-input" id="has_picture" name="has_picture" value="1"<?php echo $has_picture ? ' checked' : ''; ?>>
        <label class="form-check-label" for="has_picture">Only items with photos</label>
      </div>
    </div>
    <div class="col-md-4">
      <!-- Status: each ticked box sends status[]=<name>. Nothing ticked falls back
           to the default (Live + Scheduled). Colours match the badges on the cards. -->
      <fieldset class="mt-2">
        <legend class="sr-only">Auction status</legend>
<?php foreach (['live' => ['Live', 'success'], 'scheduled' => ['Scheduled', 'info'], 'ended' => ['Ended', 'secondary']] as $status_name => [$status_label, $badge_colour]): ?>
        <div class="form-check form-check-inline">
          <input type="checkbox" class="form-check-input" id="status_<?php echo $status_name; ?>" name="status[]" value="<?php echo $status_name; ?>"<?php echo in_array($status_name, $statuses, true) ? ' checked' : ''; ?>>
          <label class="form-check-label" for="status_<?php echo $status_name; ?>"><span class="badge badge-<?php echo $badge_colour; ?>"><?php echo $status_label; ?></span></label>
        </div>
<?php endforeach; ?>
      </fieldset>
    </div>
  </div>
</form>
</div> <!-- end search specs bar -->

</div>

<div class="container mt-5">

<?php if ($num_results > 0): ?>
  <p class="text-muted"><?php echo (int) $num_results; ?> auction<?php echo $num_results == 1 ? '' : 's'; ?> found</p>
<?php endif; ?>
<?php
  $auction_list_empty_message = 'No auctions match your search. Try a different keyword or fewer filters.';
  require __DIR__ . '/auction_list.php';
?>

<!-- Pagination: keep the current search in every page link, change only page= -->
<?php if ($max_page > 1): ?>
<nav aria-label="Search results pages" class="mt-5">
  <ul class="pagination justify-content-center">
<?php
  $query_args = $_GET;
  unset($query_args['page']);
  $querystring = browse_h(http_build_query($query_args));
  $querystring = $querystring === '' ? '' : $querystring . '&amp;';

  $high_page_boost = max(3 - $curr_page, 0);
  $low_page_boost = max(2 - ($max_page - $curr_page), 0);
  $low_page = max(1, $curr_page - 2 - $low_page_boost);
  $high_page = min($max_page, $curr_page + 2 + $high_page_boost);

  if ($curr_page != 1) {
    echo('
    <li class="page-item">
      <a class="page-link" href="browse.php?' . $querystring . 'page=' . ($curr_page - 1) . '" aria-label="Previous">
        <span aria-hidden="true"><i class="fa fa-arrow-left"></i></span>
        <span class="sr-only">Previous</span>
      </a>
    </li>');
  }

  for ($i = $low_page; $i <= $high_page; $i++) {
    echo($i == $curr_page ? '
    <li class="page-item active">' : '
    <li class="page-item">');
    echo('
      <a class="page-link" href="browse.php?' . $querystring . 'page=' . $i . '">' . $i . '</a>
    </li>');
  }

  if ($curr_page != $max_page) {
    echo('
    <li class="page-item">
      <a class="page-link" href="browse.php?' . $querystring . 'page=' . ($curr_page + 1) . '" aria-label="Next">
        <span aria-hidden="true"><i class="fa fa-arrow-right"></i></span>
        <span class="sr-only">Next</span>
      </a>
    </li>');
  }
?>
  </ul>
</nav>
<?php endif; ?>

</div>

<?php include_once("footer.php")?>
