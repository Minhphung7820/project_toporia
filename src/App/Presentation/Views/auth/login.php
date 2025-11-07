<!doctype html><html><head><meta charset="utf-8"><title><?= htmlspecialchars($title) ?></title></head>
<body>
  <h1>Login</h1>
  <form method="post" action="/login">
    <label>Email: <input type="email" name="email" required></label>
    <button type="submit">Login</button>
  </form>
</body>
</html>
