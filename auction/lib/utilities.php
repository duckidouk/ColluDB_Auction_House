<?php

function ensure_session_started(): void {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
}

function current_user_id(): ?int {
  ensure_session_started();
  return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_user_session(): ?int {
  ensure_session_started();
  return isset($_SESSION['user_session_id']) ? (int) $_SESSION['user_session_id'] : null;
}

function validate_authenticated_session(mysqli $connection): void {
  static $validated = false;
  if ($validated) {
    return;
  }
  $validated = true;

  $userId = current_user_id();
  $userSessionId = current_user_session();
  if ($userId === null && $userSessionId === null) {
    return;
  }

  $isValid = false;
  if ($userId !== null && $userSessionId !== null) {
    $statement = mysqli_prepare(
      $connection,
      'SELECT EXISTS (
         SELECT 1 FROM UserSessions
          WHERE sessionId = ? AND userId = ? AND endTime IS NULL
       ) AND NOT EXISTS (
         SELECT 1 FROM UserBans
          WHERE userId = ? AND bannedSince <= NOW()
            AND (bannedUntil IS NULL OR bannedUntil > NOW())
       )'
    );
    mysqli_stmt_bind_param($statement, 'iii', $userSessionId, $userId, $userId);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement)->fetch_row();
    $isValid = $result !== false && (bool) $result[0];
    mysqli_stmt_close($statement);
  }

  if ($isValid) {
    return;
  }

  unset($_SESSION['user_id'], $_SESSION['user_session_id'], $_SESSION['account_type']);
  session_regenerate_id(true);
  $_SESSION['auth_message'] = [
    'type' => 'danger',
    'text' => 'Your session has ended. Please sign in again.',
  ];
}

function start_authenticated_session(mysqli $connection, int $userId, string $accountType): bool {
  ensure_session_started();

  mysqli_begin_transaction($connection);
  try {
    $account = mysqli_prepare(
      $connection,
      'SELECT r.name AS accountType,
              EXISTS (
                SELECT 1 FROM UserBans
                 WHERE userId = u.userId AND bannedSince <= NOW()
                   AND (bannedUntil IS NULL OR bannedUntil > NOW())
              ) AS isBanned
         FROM UserAccounts u
         JOIN Roles r ON r.roleId = u.roleId
        WHERE u.userId = ?
        FOR UPDATE'
    );
    mysqli_stmt_bind_param($account, 'i', $userId);
    mysqli_stmt_execute($account);
    $accountDetails = mysqli_stmt_get_result($account)->fetch_assoc();
    mysqli_stmt_close($account);

    if (!$accountDetails || (int) $accountDetails['isBanned'] === 1 || $accountDetails['accountType'] !== $accountType) {
      mysqli_rollback($connection);
      return false;
    }

    $statement = mysqli_prepare($connection, 'INSERT INTO UserSessions (userId) VALUES (?)');
    mysqli_stmt_bind_param($statement, 'i', $userId);
    mysqli_stmt_execute($statement);
    $userSessionId = mysqli_insert_id($connection);
    mysqli_stmt_close($statement);
    mysqli_commit($connection);
  } catch (Throwable $exception) {
    mysqli_rollback($connection);
    throw $exception;
  }

  session_regenerate_id(true);

  $_SESSION['user_id'] = $userId;
  $_SESSION['user_session_id'] = $userSessionId;
  $_SESSION['account_type'] = $accountType;
  unset($_SESSION['demo_user_id']);
  return true;
}

// display_time_remaining:
// Helper function to help figure out what time to display
function display_time_remaining($interval) {

    if ($interval->days == 0 && $interval->h == 0) {
      // Less than one hour remaining: print mins + seconds:
      $time_remaining = $interval->format('%im %Ss');
    }
    else if ($interval->days == 0) {
      // Less than one day remaining: print hrs + mins:
      $time_remaining = $interval->format('%hh %im');
    }
    else {
      // At least one day remaining: print days + hrs:
      $time_remaining = $interval->format('%ad %hh');
    }

  return $time_remaining;

}

const CATEGORY_DISPLAY_NAMES = [
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

function category_display_name(string $name): string {
  return CATEGORY_DISPLAY_NAMES[$name] ?? ucwords(strtolower(str_replace('_', ' ', $name)));
}

?>