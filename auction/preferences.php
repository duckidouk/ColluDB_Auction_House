<?php ob_start(); include_once("header.php") ?>

<?php
  $uid  = current_user_id();
  $role = user_role($connection, $uid);
  $shown = email_pref_types_for_role($role);          // which switches this role gets to see

  // Admins (and unknown users) have no preferences page: send them away.
  if (!$shown) { ob_end_clean(); header('Location: browse.php'); exit(); }
?>

<div class="container my-4">
  <h2 class="my-3">Email preferences</h2>
  <p class="text-muted">You will always see every notification under the bell. These switches only control whether we also <strong>email</strong> you.</p>

<?php
  // All notification types, keyed by name, then only the ones this role may see (in the role's order).
  $all = [];
  foreach (db_all($connection, 'SELECT notificationTypeId, name, description, isOptional FROM NotificationTypes') as $t) { $all[$t['name']] = $t; }
  $types = [];
  foreach ($shown as $name) { if (isset($all[$name])) { $types[] = $all[$name]; } }

  // Save: only OPTIONAL types that this role can see. A ticked box = emails ON.
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $on = array_map('intval', $_POST['enabled'] ?? []);
    foreach ($types as $t) {
      if (!$t['isOptional']) { continue; }
      $tid = (int) $t['notificationTypeId'];
      db_exec($connection,
        'INSERT INTO UserNotificationPreferences (userId, notificationTypeId, isEnabled) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE isEnabled = VALUES(isEnabled)',
        [$uid, $tid, in_array($tid, $on, true) ? 1 : 0]);
    }
    echo '<div class="alert alert-success">Email preferences saved.</div>';
  }

  // This user's saved switches (no row = ON)
  $saved = [];
  foreach (db_all($connection, 'SELECT notificationTypeId, isEnabled FROM UserNotificationPreferences WHERE userId = ?', [$uid]) as $p) {
    $saved[(int) $p['notificationTypeId']] = (int) $p['isEnabled'];
  }
?>
  <form method="post"><ul class="list-group mb-3">
<?php foreach ($types as $t): $tid = (int) $t['notificationTypeId']; $isOn = $saved[$tid] ?? 1; ?>
    <li class="list-group-item d-flex justify-content-between align-items-center">
      <div>
        <div class="font-weight-bold"><?php echo htmlspecialchars(ucfirst(strtolower(str_replace('_', ' ', $t['name']))), ENT_QUOTES); ?></div>
        <small class="text-muted"><?php echo htmlspecialchars((string) $t['description'], ENT_QUOTES); ?></small>
      </div>
<?php if (!$t['isOptional']): ?>
      <span class="badge badge-secondary">Always emailed</span>
<?php else: ?>
      <div class="custom-control custom-switch">
        <input type="checkbox" class="custom-control-input" id="t<?php echo $tid; ?>" name="enabled[]" value="<?php echo $tid; ?>"<?php echo $isOn ? ' checked' : ''; ?>>
        <label class="custom-control-label" for="t<?php echo $tid; ?>"><span class="sr-only">Email me this notification</span></label>
      </div>
<?php endif; ?>
    </li>
<?php endforeach; ?>
  </ul>
  <button class="btn btn-primary">Save email preferences</button></form>
</div>

<?php include_once("footer.php") ?>
