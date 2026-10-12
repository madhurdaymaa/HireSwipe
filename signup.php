<?php
require 'db.php';
if (isset($_SESSION['user_id'])) { header('Location: home.php'); exit; }

$countries = ['India','United States','United Kingdom','Canada','Australia','Germany','France','Singapore','United Arab Emirates','Nepal','Bangladesh','Sri Lanka','Other'];
$errors = [];
$v = ['user_type'=>'employee','name'=>'','mobile'=>'','country'=>'India','description'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) { $v[$k] = trim($_POST[$k] ?? $v[$k]); }
    $pass = $_POST['password'] ?? '';

    if (!in_array($v['user_type'], ['employee','employer'], true)) $errors[] = 'Choose employee or employer.';
    if ($v['name'] === '' || mb_strlen($v['name']) > 100)           $errors[] = 'Enter your name.';
    if (!preg_match('/^\+?[0-9]{7,15}$/', $v['mobile']))             $errors[] = 'Enter a valid mobile number (digits only, 7–15 long).';
    if (!in_array($v['country'], $countries, true))                  $errors[] = 'Choose your country.';
    if (mb_strlen($v['description']) < 10)                           $errors[] = 'Write at least a short description (10+ characters).';
    if (strlen($pass) < 6)                                           $errors[] = 'Password must be at least 6 characters.';

    if (!$errors) {
        $st = $pdo->prepare('SELECT 1 FROM users WHERE mobile = ?');
        $st->execute([$v['mobile']]);
        if ($st->fetch()) $errors[] = 'That mobile number already has an account. Try logging in.';
    }
    if (!$errors) {
        $st = $pdo->prepare('INSERT INTO users (user_type,name,mobile,country,description,password_hash) VALUES (?,?,?,?,?,?)');
        $st->execute([$v['user_type'],$v['name'],$v['mobile'],$v['country'],$v['description'],password_hash($pass, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$pdo->lastInsertId();
        header('Location: home.php'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign up · JobSwipe</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main class="auth">
  <section class="auth-art">
    <div class="brand"><i></i>JobSwipe</div>
    <div>
      <h1>Two sides. One swipe.</h1>
      <p>Tell us who you are, add a few details, and start swiping in under a minute.</p>
    </div>
    <div></div>
  </section>

  <section class="auth-form">
    <div class="panel">
      <h2>Create your account</h2>
      <p class="sub">First, tell us what you're here to do.</p>
      <?php foreach ($errors as $e): ?><div class="error" role="alert"><?= h($e) ?></div><?php endforeach; ?>

      <form method="post" novalidate>
        <div class="roles">
          <label class="role">
            <input type="radio" name="user_type" value="employee" <?= $v['user_type']==='employee'?'checked':'' ?>>
            <span class="box"><strong>Employee</strong><span>I'm looking for a job</span></span>
          </label>
          <label class="role">
            <input type="radio" name="user_type" value="employer" <?= $v['user_type']==='employer'?'checked':'' ?>>
            <span class="box"><strong>Employer</strong><span>I'm hiring people</span></span>
          </label>
        </div>

        <label class="f" for="name" id="nameLabel">Full name</label>
        <input type="text" id="name" name="name" value="<?= h($v['name']) ?>" maxlength="100" required>

        <label class="f" for="mobile">Mobile number</label>
        <input type="tel" id="mobile" name="mobile" value="<?= h($v['mobile']) ?>" placeholder="+91 98765 43210" required>

        <label class="f" for="country">Country</label>
        <select id="country" name="country">
          <?php foreach ($countries as $c): ?>
            <option <?= $v['country']===$c?'selected':'' ?>><?= h($c) ?></option>
          <?php endforeach; ?>
        </select>

        <label class="f" for="description" id="descLabel">About you</label>
        <textarea id="description" name="description" required><?= h($v['description']) ?></textarea>
        <p class="hint" id="descHint">Your skills, experience and the work you want.</p>

        <label class="f" for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="new-password" required>
        <p class="hint">At least 6 characters.</p>

        <button class="btn" type="submit">Create account</button>
      </form>
      <p class="alt">Already have an account? <a href="login.php">Log in</a></p>
    </div>
  </section>
</main>

<script>
// Change the wording of the form to match the chosen type
const copy = {
  employee: {name:'Full name',    desc:'About you', hint:'Your skills, experience and the work you want.'},
  employer: {name:'Company name', desc:'About your company', hint:'What you do and the roles you are hiring for.'}
};
function apply(){
  const t = document.querySelector('input[name=user_type]:checked').value;
  nameLabel.textContent = copy[t].name;
  descLabel.textContent = copy[t].desc;
  descHint.textContent  = copy[t].hint;
}
document.querySelectorAll('input[name=user_type]').forEach(r => r.addEventListener('change', apply));
apply();
</script>
</body>
</html>
