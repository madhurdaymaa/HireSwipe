<?php
require 'db.php';
if (isset($_SESSION['user_id'])) { header('Location: home.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile = trim($_POST['mobile'] ?? '');
    $pass   = $_POST['password'] ?? '';
    $st = $pdo->prepare('SELECT id, password_hash FROM users WHERE mobile = ?');
    $st->execute([$mobile]);
    $u = $st->fetch();
    if ($u && password_verify($pass, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $u['id'];
        header('Location: home.php'); exit;
    }
    $error = 'Mobile number or password is incorrect.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log in · JobSwipe</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main class="auth">
  <section class="auth-art">
    <div class="brand"><i></i>JobSwipe</div>
    <div>
      <div class="fan" aria-hidden="true">
        <span>Priya S.<small>Frontend developer</small></span>
        <span>Northwind Labs<small>Hiring 3 engineers</small></span>
        <span>It's a match<small>Say hello</small></span>
      </div>
      <h1>Swipe your way to the right job.</h1>
      <p>Employees find roles. Employers find people. Both sides swipe right to connect.</p>
    </div>
    <div></div>
  </section>

  <section class="auth-form">
    <div class="panel">
      <h2>Welcome back</h2>
      <p class="sub">Log in with your mobile number.</p>
      <?php if ($error): ?><div class="error" role="alert"><?= h($error) ?></div><?php endif; ?>
      <form method="post" novalidate>
        <label class="f" for="mobile">Mobile number</label>
        <input type="tel" id="mobile" name="mobile" value="<?= h($_POST['mobile'] ?? '') ?>" autocomplete="tel" required>
        <label class="f" for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <button class="btn" type="submit">Log in</button>
      </form>
      <p class="alt">New here? <a href="signup.php">Create an account</a></p>
    </div>
  </section>
</main>
</body>
</html>
