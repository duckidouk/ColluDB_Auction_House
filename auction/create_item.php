<?php include_once("header.php")?>
<!-- TODO: allow for adding images -->
<div class="container my-4">
  <h2 class="my-3">Add an item</h2>
  <div class="alert alert-info">
    Demo seller mode: this item will be saved under seeded seller account 2.
  </div>

<?php
  require_once __DIR__ . '/../sql/database.php';
  $categories = mysqli_query($connection, 'SELECT categoryId, name FROM Categories ORDER BY name');
  $category_labels = [
    'AUDIO' => 'Audio',
    'BABIES_AND_KIDS' => 'Babies and Kids',
    'BEAUTY_AND_PERSONAL_CARE' => 'Beauty and Personal Care',
    'CAR_ACCESSORIES' => 'Car Accessories',
    'CARS' => 'Cars',
    'COMPUTER_AND_TECH' => 'Computer and Tech',
    'CLOTHING' => 'Clothing',
    'ACCESSORIES_AND_JEWELRY' => 'Accessories and Jewelry',
    'FOOD_AND_DRINKS' => 'Food and Drinks',
    'FURNITURE_AND_HOME_LIVING' => 'Furniture and Home Living',
    'HEALTH_AND_NUTRITION' => 'Health and Nutrition',
    'HOBBIES_AND_LIFESTYLE' => 'Hobbies and Lifestyle',
    'MOBILE_PHONES_AND_GADGETS' => 'Mobile Phones and Gadgets',
    'PET_SUPPLIES' => 'Pet Supplies',
    'SPORTS_EQUIPMENT' => 'Sports Equipment',
    'TV_AND_HOME_APPLIANCES' => 'TV and Home Appliances',
    'TOYS_AND_COLLECTIBLES' => 'Toys and Collectibles',
    'OTHER' => 'Other',
    'BOOKS' => 'Books',
    'ART' => 'Art',
  ];
?>
  <div class="card">
    <div class="card-body">
      <form method="post" action="create_item_result.php">
        <div class="form-group">
          <label for="itemTitle">Title</label>
          <input type="text" class="form-control" id="itemTitle" name="title" maxlength="255" required>
        </div>
        <div class="form-group">
          <label for="itemDescription">Description</label>
          <textarea class="form-control" id="itemDescription" name="description" rows="4"></textarea>
        </div>
        <div class="form-group">
          <label for="itemCondition">Condition</label>
          <select class="form-control" id="itemCondition" name="condition" required>
            <option value="">Choose...</option>
            <option value="new">New</option>
            <option value="like_new">Like new</option>
            <option value="used">Used</option>
            <option value="heavily_used">Heavily used</option>
          </select>
        </div>
        <fieldset class="form-group">
          <legend class="col-form-label">Categories</legend>
          <small class="form-text text-muted mb-2">Choose one or more categories.</small>
<?php while ($category = mysqli_fetch_assoc($categories)): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="category_ids[]" id="category<?php echo (int) $category['categoryId']; ?>" value="<?php echo (int) $category['categoryId']; ?>">
            <label class="form-check-label" for="category<?php echo (int) $category['categoryId']; ?>"><?php echo htmlspecialchars($category_labels[$category['name']] ?? $category['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></label>
          </div>
<?php endwhile; ?>
        </fieldset>
        <button type="submit" class="btn btn-primary">Save item</button>
      </form>
    </div>
  </div>
</div>

<?php include_once("footer.php")?>
