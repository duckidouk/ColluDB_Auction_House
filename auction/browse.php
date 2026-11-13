<?php include_once("header.php")?>
<?php require_once __DIR__ . '/utilities.php'?>
<?php
/*
 * browse.php — Capability 3: buyers search for items being auctioned,
 * filter by category and price range, and re-arrange (sort) the listings.
 *
 * Flow: 1) read + validate the URL inputs, 2) build the WHERE clause from the
 * filters actually set, 3) count matches for pagination, 4) fetch one page.
 * All user values go through prepared-statement placeholders (?); the sort
 * order uses a whitelist because ORDER BY cannot take placeholders.
 */
require_once __DIR__ . '/../sql/database.php';

// ---------- 1. Read and validate the inputs from the URL (GET) ----------
$keyword = trim($_GET['keyword'] ?? '');

// 'all' (or anything that is not a positive integer) means no category filter.
$category_id = filter_var($_GET['cat'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($category_id === false) {
  $category_id = null;
}

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

// Whitelist: form value => fixed ORDER BY text. auctionId is a tie-breaker so
// the order is deterministic and pagination never repeats or skips a row.
// 'date' puts live auctions first (soonest ending on top), ended ones after.
$sort_options = [
  'pricelow'  => 'p.currentPrice ASC, a.auctionId ASC',
  'pricehigh' => 'p.currentPrice DESC, a.auctionId ASC',
  'date'      => '(a.endDateTime < NOW()) ASC, a.endDateTime ASC, a.auctionId ASC',
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
if ($category_id !== null) {
  // EXISTS keeps the auction if its item has the chosen category, without
  // joining ItemCategories into the main query (so no duplicate rows).
  $conditions[] = 'EXISTS (SELECT 1 FROM ItemCategories ic2
                            WHERE ic2.itemId = i.itemId AND ic2.categoryId = ?)';
  $types .= 'i';
  $params[] = $category_id;
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

// Categories for the dropdown (shows the name, sends the id).
$categories = mysqli_query($connection, 'SELECT categoryId, name FROM Categories ORDER BY name');

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
        <label for="cat" class="sr-only">Search within:</label>
        <select class="form-control" id="cat" name="cat">
          <option value="all"<?php echo $category_id === null ? ' selected' : ''; ?>>All categories</option>
<?php while ($category = mysqli_fetch_assoc($categories)): ?>
          <option value="<?php echo (int) $category['categoryId']; ?>"<?php echo $category_id === (int) $category['categoryId'] ? ' selected' : ''; ?>><?php echo browse_h(category_display_name($category['name'])); ?></option>
<?php endwhile; ?>
        </select>
      </div>
    </div>
    <div class="col-md-3 pr-0">
      <div class="form-inline">
        <label class="mx-2" for="order_by">Sort by:</label>
        <select class="form-control" id="order_by" name="order_by">
          <option value="pricelow"<?php echo $ordering === 'pricelow' ? ' selected' : ''; ?>>Price (low to high)</option>
          <option value="pricehigh"<?php echo $ordering === 'pricehigh' ? ' selected' : ''; ?>>Price (high to low)</option>
          <option value="date"<?php echo $ordering === 'date' ? ' selected' : ''; ?>>Soonest expiry</option>
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
  </div>
</form>
</div> <!-- end search specs bar -->

</div>

<div class="container mt-5">

<?php if ($num_results > 0): ?>
  <p class="text-muted"><?php echo (int) $num_results; ?> auction<?php echo $num_results == 1 ? '' : 's'; ?> found</p>
<?php endif; ?>
<?php
  $auction_list_empty_message = 'No auctions match your search. Try a different keyword, category or price range.';
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
