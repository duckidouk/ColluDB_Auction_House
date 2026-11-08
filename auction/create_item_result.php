<?php
include_once("header.php");
require_once __DIR__ . '/../sql/database.php';

$errors = [];
$item_id = null;
$saved_image_paths = [];
$upload_directory = __DIR__ . '/../public/uploads/items';
$max_image_size = 5 * 1024 * 1024;
$max_image_pixels = 20000000;
$allowed_image_types = [
  'image/jpeg' => 'jpg',
  'image/png' => 'png',
  'image/webp' => 'webp',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $errors[] = 'Submit the item form to continue.';
} else {
  $title = trim((string) ($_POST['title'] ?? ''));
  $description = trim((string) ($_POST['description'] ?? ''));
  $condition = (string) ($_POST['condition'] ?? '');
  $category_input = $_POST['category_ids'] ?? [];
  $image_input = $_FILES['images'] ?? [];
  $valid_conditions = ['new', 'like_new', 'used', 'heavily_used'];
  $category_ids = [];
  $images_to_upload = [];

  if (!preg_match('/^.{1,255}$/us', $title)) {
    $errors[] = 'Enter a title of 1 to 255 characters.';
  }
  if (strlen($description) > 65535) {
    $errors[] = 'The description is too long.';
  }
  if (!in_array($condition, $valid_conditions, true)) {
    $errors[] = 'Choose a valid item condition.';
  }

  if ($image_input && !is_array($image_input['error'] ?? null)) {
    $errors[] = 'The uploaded images could not be processed.';
  } elseif ($image_input) {
    for ($order = 1; $order <= 5; $order++) {
      $error = $image_input['error'][$order] ?? UPLOAD_ERR_NO_FILE;
      if ($error === UPLOAD_ERR_NO_FILE) {
        continue;
      }
      if ($error !== UPLOAD_ERR_OK) {
        $errors[] = 'Image ' . $order . ' could not be uploaded.';
        continue;
      }

      $temporary_path = $image_input['tmp_name'][$order] ?? '';
      $file_size = $image_input['size'][$order] ?? 0;
      if (!is_uploaded_file($temporary_path) || $file_size < 1 || $file_size > $max_image_size) {
        $errors[] = 'Image ' . $order . ' must be a valid file no larger than 5 MB.';
        continue;
      }

      $file_info = new finfo(FILEINFO_MIME_TYPE);
      $mime_type = $file_info->file($temporary_path);
      $image_dimensions = getimagesize($temporary_path);
      if (
        !isset($allowed_image_types[$mime_type]) ||
        $image_dimensions === false ||
        ($image_dimensions[0] * $image_dimensions[1]) > $max_image_pixels
      ) {
        $errors[] = 'Image ' . $order . ' must be a valid JPEG, PNG, or WebP image with reasonable dimensions.';
        continue;
      }

      $images_to_upload[$order] = [
        'temporary_path' => $temporary_path,
        'extension' => $allowed_image_types[$mime_type],
      ];
    }
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
      if (!is_dir($upload_directory) || !is_writable($upload_directory)) {
        throw new RuntimeException('The item image directory is missing or not writable.');
      }

      foreach ($images_to_upload as $order => $image) {
        $filename = bin2hex(random_bytes(16)) . '.' . $image['extension'];
        $destination = $upload_directory . '/' . $filename;
        if (!move_uploaded_file($image['temporary_path'], $destination)) {
          throw new RuntimeException('An uploaded image could not be moved into storage.');
        }
        $saved_image_paths[$order] = [
          'filesystem_path' => $destination,
          'database_path' => 'public/uploads/items/' . $filename,
        ];
      }

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

      $image_insert = mysqli_prepare(
        $connection,
        'INSERT INTO ItemImages (itemId, orderOfImage, imageLocation) VALUES (?, ?, ?)'
      );
      foreach ($saved_image_paths as $order => $image) {
        $image_location = $image['database_path'];
        mysqli_stmt_bind_param($image_insert, 'iis', $item_id, $order, $image_location);
        mysqli_stmt_execute($image_insert);
      }

      mysqli_commit($connection);
      $transaction_started = false;
    } catch (Throwable $exception) {
      if ($transaction_started) {
        mysqli_rollback($connection);
      }
      foreach ($saved_image_paths as $image) {
        if (is_file($image['filesystem_path']) && !unlink($image['filesystem_path'])) {
          error_log('Failed to remove an image after item creation failed: ' . $image['filesystem_path']);
        }
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
