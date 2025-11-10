# Query Builder - Complete Examples

Comprehensive guide for all QueryBuilder methods with real-world examples.

## Table of Contents
- [Basic Queries](#basic-queries)
- [WHERE Clauses](#where-clauses)
- [JOIN Operations](#join-operations)
- [Grouping & Aggregation](#grouping--aggregation)
- [Ordering & Limiting](#ordering--limiting)
- [Advanced Queries](#advanced-queries)

---

## Basic Queries

### SELECT with specific columns

```php
// Get all products
$products = DB::table('products')->get();

// Select specific columns
$products = DB::table('products')
    ->select(['id', 'title', 'price'])
    ->get();

// Select varargs
$products = DB::table('products')
    ->select('id', 'title', 'price')
    ->get();

// DISTINCT - Get unique values
$categories = DB::table('products')
    ->select('category')
    ->distinct()
    ->get();
```

### Raw SELECT expressions

```php
// COUNT with alias
$query = DB::table('products')
    ->selectRaw('COUNT(*) as total_products')
    ->first();

// CONCAT columns
$users = DB::table('users')
    ->selectRaw("CONCAT(first_name, ' ', last_name) as full_name")
    ->get();
```

---

## WHERE Clauses

### Basic WHERE

```php
// Simple equality
$products = DB::table('products')
    ->where('is_active', 1)
    ->get();

// With operator
$products = DB::table('products')
    ->where('price', '>', 100)
    ->get();

// Multiple conditions (AND)
$products = DB::table('products')
    ->where('is_active', 1)
    ->where('stock', '>', 0)
    ->get();

// OR conditions
$products = DB::table('products')
    ->where('category', 'electronics')
    ->orWhere('category', 'computers')
    ->get();
```

### Advanced WHERE

```php
// WHERE IN
$products = DB::table('products')
    ->whereIn('category', ['electronics', 'computers', 'phones'])
    ->get();

// WHERE NULL / NOT NULL
$products = DB::table('products')
    ->whereNull('deleted_at')
    ->whereNotNull('sku')
    ->get();

// Raw WHERE
$products = DB::table('products')
    ->whereRaw('price BETWEEN ? AND ?', [10, 100])
    ->get();
```

### Nested WHERE (Laravel-style)

```php
// Complex conditions with closures
$products = DB::table('products')
    ->where('is_active', 1)
    ->where(function($q) {
        $q->where('price', '>', 100)
          ->orWhere('featured', 1);
    })
    ->get();

// Generated SQL:
// WHERE is_active = 1 AND (price > 100 OR featured = 1)

// Nested OR groups
$products = DB::table('products')
    ->where('status', 'active')
    ->orWhere(function($q) {
        $q->where('role', 'admin')
          ->where('verified', 1);
    })
    ->get();

// Generated SQL:
// WHERE status = 'active' OR (role = 'admin' AND verified = 1)
```

---

## JOIN Operations

### Basic JOINs

```php
// INNER JOIN
$orders = DB::table('orders')
    ->join('users', 'orders.user_id', '=', 'users.id')
    ->select(['orders.*', 'users.name'])
    ->get();

// LEFT JOIN
$products = DB::table('products')
    ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
    ->select(['products.*', 'categories.name as category_name'])
    ->get();

// RIGHT JOIN
$orders = DB::table('orders')
    ->rightJoin('users', 'orders.user_id', '=', 'users.id')
    ->get();
```

### Multiple JOINs

```php
$orders = DB::table('orders')
    ->join('users', 'orders.user_id', '=', 'users.id')
    ->leftJoin('products', 'orders.product_id', '=', 'products.id')
    ->select([
        'orders.*',
        'users.name as customer_name',
        'products.title as product_title'
    ])
    ->get();
```

---

## Grouping & Aggregation

### GROUP BY

```php
// Group by single column
$stats = DB::table('products')
    ->select(['category', 'COUNT(*) as count'])
    ->groupBy('category')
    ->get();

// Group by multiple columns
$stats = DB::table('orders')
    ->select(['user_id', 'status', 'COUNT(*) as count'])
    ->groupBy('user_id', 'status')
    ->get();

// Or array syntax
$stats = DB::table('orders')
    ->select(['user_id', 'status', 'COUNT(*) as count'])
    ->groupBy(['user_id', 'status'])
    ->get();
```

### HAVING Clause

```php
// Filter aggregated results
$popularCategories = DB::table('products')
    ->select(['category', 'COUNT(*) as count'])
    ->groupBy('category')
    ->having('count', '>', 10)
    ->get();

// With complex aggregates
$richUsers = DB::table('orders')
    ->select(['user_id', 'SUM(total) as total_spent'])
    ->groupBy('user_id')
    ->having('total_spent', '>=', 1000)
    ->get();

// Multiple HAVING conditions
$stats = DB::table('products')
    ->select(['category', 'AVG(price) as avg_price', 'COUNT(*) as count'])
    ->groupBy('category')
    ->having('count', '>', 5)
    ->having('avg_price', '<', 500)
    ->get();

// OR HAVING
$stats = DB::table('products')
    ->select(['category', 'COUNT(*) as count'])
    ->groupBy('category')
    ->having('count', '>', 100)
    ->orHaving('count', '<', 5)
    ->get();
```

---

## Ordering & Limiting

### ORDER BY

```php
// Single column ascending
$products = DB::table('products')
    ->orderBy('price', 'ASC')
    ->get();

// Single column descending
$products = DB::table('products')
    ->orderBy('created_at', 'DESC')
    ->get();

// Multiple columns
$products = DB::table('products')
    ->orderBy('category', 'ASC')
    ->orderBy('price', 'DESC')
    ->get();
```

### Shortcut Methods

```php
// Latest (newest first) - orderBy('created_at', 'DESC')
$products = DB::table('products')
    ->latest()
    ->get();

// Latest by custom column
$products = DB::table('products')
    ->latest('updated_at')
    ->get();

// Oldest (oldest first) - orderBy('created_at', 'ASC')
$products = DB::table('products')
    ->oldest()
    ->get();

// Random order
$products = DB::table('products')
    ->inRandomOrder()
    ->limit(10)
    ->get();
```

### LIMIT & OFFSET

```php
// Get first 10
$products = DB::table('products')
    ->limit(10)
    ->get();

// Skip 20, take 10 (pagination)
$products = DB::table('products')
    ->offset(20)
    ->limit(10)
    ->get();

// Shortcut methods
$products = DB::table('products')
    ->skip(20)
    ->take(10)
    ->get();
```

---

## Advanced Queries

### Complex Aggregation

```php
// Category statistics
$stats = DB::table('products')
    ->select([
        'category',
        'COUNT(*) as total_products',
        'AVG(price) as average_price',
        'MIN(price) as min_price',
        'MAX(price) as max_price',
        'SUM(stock) as total_stock'
    ])
    ->groupBy('category')
    ->having('total_products', '>', 5)
    ->orderBy('average_price', 'DESC')
    ->get();
```

### Sales Report Example

```php
// Monthly sales report
$monthlySales = DB::table('orders')
    ->select([
        'YEAR(created_at) as year',
        'MONTH(created_at) as month',
        'COUNT(*) as total_orders',
        'SUM(total) as revenue'
    ])
    ->whereRaw('created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)')
    ->groupBy(['year', 'month'])
    ->orderBy('year', 'DESC')
    ->orderBy('month', 'DESC')
    ->get();
```

### Top Customers

```php
// Top 10 customers by total spent
$topCustomers = DB::table('orders')
    ->join('users', 'orders.user_id', '=', 'users.id')
    ->select([
        'users.id',
        'users.name',
        'COUNT(orders.id) as total_orders',
        'SUM(orders.total) as total_spent'
    ])
    ->groupBy('users.id', 'users.name')
    ->orderBy('total_spent', 'DESC')
    ->limit(10)
    ->get();
```

### Active Products with Stock

```php
$products = DB::table('products')
    ->where('is_active', 1)
    ->where(function($q) {
        $q->where('stock', '>', 0)
          ->orWhere('allow_backorder', 1);
    })
    ->latest()
    ->paginate(20);
```

### Search with Filters

```php
$query = DB::table('products')
    ->select(['id', 'title', 'price', 'category']);

// Apply filters
if ($searchTerm) {
    $query->whereRaw('title LIKE ?', ["%{$searchTerm}%"]);
}

if ($category) {
    $query->where('category', $category);
}

if ($minPrice) {
    $query->where('price', '>=', $minPrice);
}

if ($maxPrice) {
    $query->where('price', '<=', $maxPrice);
}

$products = $query
    ->where('is_active', 1)
    ->orderBy('title', 'ASC')
    ->paginate(20);
```

---

## Performance Tips

### ✅ Good Practices

```php
// ✅ Use indexes on WHERE columns
$products = DB::table('products')
    ->where('category_id', 5)  // Make sure category_id has index
    ->where('is_active', 1)    // is_active should have index
    ->get();

// ✅ Select only needed columns
$products = DB::table('products')
    ->select(['id', 'title', 'price'])  // Don't select unnecessary columns
    ->get();

// ✅ Use LIMIT for large datasets
$recentProducts = DB::table('products')
    ->latest()
    ->limit(100)  // Limit results
    ->get();

// ✅ Use pagination for UI
$products = DB::table('products')
    ->paginate(20);  // Much better than get() for large tables
```

### ❌ Bad Practices

```php
// ❌ Don't load all data then filter in PHP
$allProducts = DB::table('products')->get();
$filtered = $allProducts->filter(fn($p) => $p['price'] > 100);  // BAD!

// ✅ Filter in database instead
$filtered = DB::table('products')->where('price', '>', 100)->get();

// ❌ Don't use random order on large tables without LIMIT
$products = DB::table('products')->inRandomOrder()->get();  // SLOW!

// ✅ Use LIMIT with random order
$products = DB::table('products')->inRandomOrder()->limit(10)->get();
```

---

## Comparison with Laravel

All methods are **100% compatible with Laravel** syntax:

```php
// Laravel
$products = DB::table('products')
    ->where('is_active', 1)
    ->latest()
    ->paginate(15);

// This framework - IDENTICAL
$products = DB::table('products')
    ->where('is_active', 1)
    ->latest()
    ->paginate(15);
```

**New methods added:**
- ✅ `groupBy()` - GROUP BY clause
- ✅ `having()` / `orHaving()` - HAVING clause
- ✅ `distinct()` - SELECT DISTINCT
- ✅ `latest()` / `oldest()` - Shortcut for orderBy
- ✅ `inRandomOrder()` - Random order
- ✅ `take()` / `skip()` - Aliases for limit/offset

---

## Summary

**Query Builder now supports:**

✅ All basic SQL operations (SELECT, INSERT, UPDATE, DELETE)
✅ Complex WHERE with nested closures
✅ All JOIN types (INNER, LEFT, RIGHT)
✅ **GROUP BY with HAVING** (NEW!)
✅ **DISTINCT queries** (NEW!)
✅ **Latest/Oldest shortcuts** (NEW!)
✅ **Random ordering** (NEW!)
✅ Pagination with Paginator class
✅ Raw SQL expressions
✅ Parameter binding (SQL injection protection)

**Performance characteristics:**
- O(1) for most operations (just building query)
- O(N) for result iteration where N = rows returned
- Efficient SQL compilation with minimal overhead
- Database-level filtering (not PHP filtering)
