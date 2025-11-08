<?php

namespace App\Presentation\Http\Controllers;

use Toporia\Framework\Support\Accessors\Auth;
use Toporia\Framework\Support\Accessors\DB;
use Toporia\Framework\Support\Result;
use Toporia\Framework\Support\Str;

final class HomeController extends BaseController
{
    public function index()
    {
        // ====================================
        // COLLECTION EXAMPLES
        // ====================================

        // Basic collection operations
        $numbers = collect([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        $doubled = $numbers->map(fn($n) => $n * 2);
        $evens = $numbers->filter(fn($n) => $n % 2 === 0);
        $sum = $numbers->sum();
        $avg = $numbers->avg();

        // Advanced: Sliding window (beyond Laravel!)
        $pairs = $numbers->pairs(); // [[1,2], [2,3], [3,4], ...]
        $windows = $numbers->window(3); // [[1,2,3], [2,3,4], [3,4,5], ...]

        // Advanced: Moving average
        $movingAvg = $numbers->movingAverage(3);

        // Advanced: Frequency analysis
        $data = collect(['a', 'b', 'a', 'c', 'a', 'b']);
        $frequencies = $data->frequencies();

        // Advanced: Partition
        [$passed, $failed] = $numbers->partition(fn($n) => $n > 5);

        // Advanced: Cross join (cartesian product)
        $colors = collect(['red', 'green']);
        $sizes = collect(['S', 'M', 'L']);
        $combinations = $colors->crossJoin($sizes);

        // Statistical functions
        $median = $numbers->median();
        $mode = collect([1, 2, 2, 3, 3, 3, 4])->mode();

        // ====================================
        // STRING EXAMPLES
        // ====================================

        // Fluent string operations
        $text = str('Hello World')
            ->upper()
            ->append('!')
            ->kebab()
            ->value(); // "HELLO-WORLD!"

        // Vietnamese slug (supports tiếng Việt!)
        $slug = str('Xin chào Việt Nam')->slug(); // "xin-chao-viet-nam"

        // Advanced: Fuzzy matching
        $similarity = Str::similarity('hello', 'hallo'); // ~80%
        $soundsLike = Str::soundsLike('hello', 'helo'); // true

        // Advanced: Truncate middle (great for long filenames!)
        $long = 'very_long_filename_that_needs_truncation.pdf';
        $short = Str::truncateMiddle($long, 20); // "very_long...ation.pdf"

        // Advanced: Excerpt around keyword (for search results)
        $article = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. PHP is awesome. Sed do eiusmod tempor.';
        $excerpt = Str::excerpt($article, 'PHP', 20); // "...elit. PHP is awesome. Sed..."

        // Advanced: Template parsing
        $template = 'Hello {name}, you have {count} messages';
        $rendered = Str::template($template, ['name' => 'John', 'count' => 5]);

        // Mask sensitive data
        $email = Str::mask('john@example.com', '*', 4); // "john*************"

        // Generate IDs
        $uuid = Str::uuid(); // "550e8400-e29b-41d4-a716-446655440000"
        $ulid = Str::ulid(); // "01ARZ3NDEKTSV4RRFFQ69G5FAV"

        // ====================================
        // HELPER FUNCTIONS
        // ====================================

        // Retry operation
        $retryResult = retry(3, function ($attempt) {
            // Will retry 3 times if exception thrown
            return "Attempt $attempt succeeded";
        });

        // Data get/set with dot notation
        $dataArray = ['user' => ['name' => 'John', 'email' => 'john@example.com']];
        $name = data_get($dataArray, 'user.name'); // "John"

        // Transform if filled
        $output = transform('  hello  ', fn($v) => trim($v)); // "hello"
        $output2 = transform('', fn($v) => trim($v), 'default'); // "default"

        // ====================================
        // RESULT TYPE (Functional Error Handling)
        // ====================================

        $result = Result::all([
            Result::ok(12),
            Result::ok(1),
            Result::ok(3)
        ])->toArray();

        // Return demo data as JSON
        return $this->response->json([
            'auth' => Auth::check(),
            'title' => 'Collection & String Utilities Demo',
            'examples' => [
                'numbers' => $numbers->all(),
                'doubled' => $doubled->all(),
                'evens' => $evens->all(),
                'sum' => $sum,
                'avg' => $avg,
                'median' => $median,
                'mode' => $mode,
                'moving_average' => $movingAvg->all(),
                'frequencies' => $frequencies->all(),
                'passed' => $passed->all(),
                'failed' => $failed->all(),
                'combinations' => $combinations->map(fn($c) => $c->all())->all(),
                'text' => $text,
                'slug' => $slug,
                'similarity' => $similarity,
                'short_filename' => $short,
                'excerpt' => $excerpt,
                'rendered' => $rendered,
                'masked_email' => $email,
                'uuid' => $uuid,
                'ulid' => $ulid,
                'retry_result' => $retryResult,
                'data_get' => $name,
                'transform_output' => $output,
                'transform_default' => $output2,
                'result_type' => $result,
            ]
        ]);
    }

    public function dashboard(): string
    {
        return $this->view('home/index', ['user' => auth()->user()]);
    }
}
