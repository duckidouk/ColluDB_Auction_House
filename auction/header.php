<?php
  require_once __DIR__ . '/lib/utilities.php';
  ensure_session_started();

  // [OUTCOME/NOTIFICATION CHANGE] database connection + notification functions (used by the bell)
  require_once __DIR__ . '/../sql/database.php';
  require_once __DIR__ . '/lib/notification_funcs.php';
  validate_authenticated_session($connection);
  $authPages = ['login.php', 'register.php'];
  $currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
  if (in_array($currentPage, $authPages, true)) {
    if (current_user_id() !== null) {
      header('Location: index.php');
      exit();
    }
  } else {
    require_authenticated_session($connection);
  }
  $loggedIn = current_user_id() !== null;
?>


<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  
  <!-- Bootstrap and FontAwesome CSS -->
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

  <!-- Custom CSS file -->
  <link rel="stylesheet" href="css/custom.css">

  <title>Auction House</title>
</head>


<body>

<!-- Navbars -->
<nav class="navbar navbar-expand-lg navbar-light bg-light mx-2">
  <a class="navbar-brand" href="index.php">Auction House</a>
  <ul class="navbar-nav ml-auto align-items-center">
<?php include __DIR__ . '/notification_bell.php'; /* [OUTCOME/NOTIFICATION CHANGE] the bell + floating list */ ?>
    <li class="nav-item">
<?php
  if ($loggedIn) {
    echo '<a class="nav-link" href="logout.php">Logout</a>';
  }
  else {
    echo '<a class="nav-link" href="login.php">Login</a>';
  }
?>
    </li>
  </ul>
</nav>
<?php if ($loggedIn): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <ul class="navbar-nav align-middle">
	<li class="nav-item mx-1">
      <a class="nav-link" href="browse.php">Browse</a>
    </li>
<?php
  if ($loggedIn && ($_SESSION['account_type'] ?? null) === 'buyer') {
  echo('
	<li class="nav-item mx-1">
      <a class="nav-link" href="mybids.php">My Bids</a>
    </li>
	<li class="nav-item mx-1">
      <a class="nav-link" href="recommendations.php">Recommended</a>
    </li>');
  }
  if ($loggedIn && ($_SESSION['account_type'] ?? null) === 'seller') {
  echo('
	<li class="nav-item mx-1">
      <a class="nav-link" href="mylistings.php">My Listings</a>
    </li>
	<li class="nav-item ml-3">
      <a class="nav-link btn border-light" href="create_auction.php">+ Create auction</a>
    </li>');
  }
?>
  </ul>
</nav>
<?php endif; ?>

<?php
  if (isset($_SESSION['auth_message']) && is_array($_SESSION['auth_message'])) {
    $authMessage = $_SESSION['auth_message'];
    unset($_SESSION['auth_message']);
    $messageType = in_array($authMessage['type'] ?? '', ['success', 'danger'], true)
      ? $authMessage['type']
      : 'info';
    echo '<div class="container mt-3"><div class="alert alert-' . $messageType . '">'
      . htmlspecialchars((string) ($authMessage['text'] ?? ''), ENT_QUOTES, 'UTF-8')
      . '</div></div>';
  }
?>
