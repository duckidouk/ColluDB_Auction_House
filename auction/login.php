<?php include_once __DIR__ . '/header.php'; ?>

<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <h2 class="my-3">Login</h2>
      <form method="POST" action="login_result.php">
        <div class="form-group">
          <label for="loginEmail">Email</label>
          <input type="email" class="form-control" id="loginEmail" name="email" placeholder="Email" required>
        </div>
        <div class="form-group">
          <label for="loginPassword">Password</label>
          <input type="password" class="form-control" id="loginPassword" name="password" placeholder="Password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign in</button>
      </form>
      <div class="text-center my-3">Don't have an account? <a href="register.php">Register</a></div>
    </div>
  </div>
</div>

<?php include_once __DIR__ . '/footer.php'; ?>
