# Elasticsearch Integration

Toporia cung cấp module Search riêng, tuân thủ Clean Architecture/SOLID và có thể tái sử dụng ở mọi dự án.

## 1. Khởi chạy Elasticsearch

```bash
docker run -d --name es \
  -p 9200:9200 -p 9300:9300 \
  -e discovery.type=single-node \
  -e xpack.security.enabled=false \
  docker.elastic.co/elasticsearch/elasticsearch:8.14.0
```

## 2. Cấu hình

`config/search.php`:

```php
'default' => 'elasticsearch',
'connections' => [
    'elasticsearch' => [
        'hosts' => explode(',', env('ELASTICSEARCH_HOSTS', 'http://localhost:9200')),
        'username' => env('ELASTICSEARCH_USERNAME'),
        'password' => env('ELASTICSEARCH_PASSWORD'),
        'api_key' => env('ELASTICSEARCH_API_KEY'),
        'retries' => 2,
        'request_timeout' => 2.0,
    ],
],
```

`.env`:
```
ELASTICSEARCH_HOSTS=http://localhost:9200
SEARCH_QUEUE_ENABLED=false
```

## 3. Searchable Model

```php
use Toporia\Framework\Search\Contracts\SearchableModelInterface;
use Toporia\Framework\Search\Searchable;

class Product extends Model implements SearchableModelInterface
{
    use Searchable;

    public static function searchIndexName(): string
    {
        return 'products';
    }

    public function getSearchDocumentId(): string|int
    {
        return $this->getKey();
    }

    public function toSearchDocument(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'status' => $this->status,
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
```

Trait `Searchable` tự động hook vào `created/updated/deleted` để đồng bộ documento.

## 4. Bulk Reindex

```bash
php console search:reindex App\\Domain\\Product\\ProductModel --chunk=1000
```

Command dùng `SearchIndexerInterface` để đẩy dữ liệu theo batch.

## 5. Truy vấn

```php
$query = search()->query()
    ->match('title', $keyword)
    ->term('status', 'published')
    ->range('price', ['gte' => 10000])
    ->sort('price', 'asc')
    ->paginate(1, 20)
    ->toArray();

$results = search()->search('products', $query);
```

## 6. Queue Sync (tuỳ chọn)

Khi bật `SEARCH_QUEUE_ENABLED=true`, job sync sẽ đưa vào queue `search-sync`, cho phép xử lý bất đồng bộ (phù hợp khi ghi DB lớn).

## 7. Hiệu năng

- Connection pool + retry + gzip (từ `elasticsearch/elasticsearch` v8).
- Bulk buffer (`SEARCH_BULK_BATCH_SIZE`, `SEARCH_BULK_FLUSH_INTERVAL_MS`).
- Circuit breaker: nếu ES lỗi, job queue sẽ retry theo backoff.

## 8. Clean Architecture

- `SearchClientInterface`, `SearchIndexerInterface`, `SearchableModelInterface` giúp tách framework khỏi Elasticsearch cụ thể (có thể swap sang OpenSearch).
- `SearchServiceProvider` đăng ký mọi binding, `SearchManager` đóng vai trò facade.
- Trait + command chỉ phụ thuộc interface, đảm bảo dễ tái sử dụng/test.***

