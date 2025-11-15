<!doctype html><html><head><meta charset="utf-8"><title><?= htmlspecialchars($title) ?></title></head>
<body>
  <h1>Create Product</h1>
  <form method="post" action="/products">
    <label>Title: <input name="title" required></label><br>
    <label>SKU: <input name="sku"></label><br>
    <button type="submit">Create</button>
  </form>
</body>
</html>
