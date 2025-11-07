<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <title>Home</title>
</head>

<body>
  <p>Home. <a href="/login">Login</a> | <a href="/products/create">Create Product</a> | <a href="/dashboard">Dashboard</a></p>
  <?php if (!empty($user)): ?>
    <p>Hello, <?= htmlspecialchars($user['email']) ?>!</p>
    <p><a href="/logout">Logout</a></p>
  <?php else: ?>
    <p>You are not logged in</p>
  <?php endif; ?>
</body>

</html>