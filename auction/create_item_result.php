<?php
include_once("header.php");
require_once __DIR__ . '/../sql/database.php';

$errors = [];
$item_id = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $errors[] = 'Submit the item form to continue.';
} else {
  $title = trim((string) ($_POST['title'] ?? ''));
  $description = trim((string) ($_POST['description'] ?? ''));
  $condition = (string) ($_POST['condition'] ?? '');
  $category_input = $_POST['category_ids'] ?? [];
  $valid_conditions = ['new', 'like_new', 'used', 'heavily_used'];
  $category_ids = [];

  if (!preg_match('/^.{1,255}$/us', $title)) {
    $errors[] = 'Enter a title of 1 to 255 characters.';
  }
  if (strlen($description) > 65535) {
    $errors[] = 'The description is too long.';
  }
  if (!in_array($condition, $valid_conditions, true)) {
    $errors[] = 'Choose a valid item condition.';
  }
  if (!is_array($category_input)) {
    $errors[] = 'Choose one or more valid categories.';
  } else {
    foreach ($category_input as $category_value) {
      $category_id = filter_var($category_value, FILTER_VALIDATE_INT);
      if ($category_id === false || $category_id === null || $category_id < 1) {
        $errors[] = 'Choose only valid categories.';
        break;
      }
      $category_ids[] = (int) $category_id;
    }
    $category_ids = array_values(array_unique($category_ids));
    if (!$category_ids) {
      $errors[] = 'Choose at least one category.';
    }
  }

  if (!$errors) {
    $category_check = mysqli_prepare($connection, 'SELECT categoryId FROM Categories WHERE categoryId = ?');
    foreach ($category_ids as $category_id) {
      mysqli_stmt_bind_param($category_check, 'i', $category_id);
      mysqli_stmt_execute($category_check);
      if (!mysqli_fetch_assoc(mysqli_stmt_get_result($category_check))) {
        $errors[] = 'One of the selected categories no longer exists. Please choose again.';
        break;
      }
    }
  }

  if (!$errors) {
    $transaction_started = false;
    try {
      mysqli_begin_transaction($connection);
      $transaction_started = true;
      $seller_id = 2;
      $item_insert = mysqli_prepare(
        $connection,
        'INSERT INTO Items (sellerId, title, description, itemCondition) VALUES (?, ?, ?, ?)'
      );
      mysqli_stmt_bind_param($item_insert, 'isss', $seller_id, $title, $description, $condition);
      mysqli_stmt_execute($item_insert);
      $item_id = mysqli_insert_id($connection);

      $category_insert = mysqli_prepare(
        $connection,
        'INSERT INTO ItemCategories (categoryId, itemId) VALUES (?, ?)'
      );
      foreach ($category_ids as $category_id) {
        mysqli_stmt_bind_param($category_insert, 'ii', $category_id, $item_id);
        mysqli_stmt_execute($category_insert);
      }
      mysqli_commit($connection);
      $transaction_started = false;
    } catch (mysqli_sql_exception $exception) {
      if ($transaction_started) {
        mysqli_rollback($connection);
      }
      error_log('Item creation failed: ' . $exception->getMessage());
      $errors[] = 'The item could not be saved. Please try again.';
      $item_id = null;
    }
  }
}
?>

<div class="container my-5">
<?php if ($item_id !== null): ?>
  <div class="alert alert-success">Item saved. You can now set up an auction for it.</div>
  <a class="btn btn-primary" href="create_auction.php?item_id=<?php echo (int) $item_id; ?>">Create auction for this item</a>
<?php else: ?>
  <div class="alert alert-danger">
    <p>The item was not created:</p>
    <ul class="mb-0">
<?php foreach ($errors as $error): ?>
      <li><?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
<?php endforeach; ?>
    </ul>
  </div>
  <a href="create_item.php">Return to add item</a>
<?php endif; ?>
</div>

<?php include_once("footer.php")?>
