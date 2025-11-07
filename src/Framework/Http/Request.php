<?php
namespace Framework\Http;

final class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $body;

    public static function capture(): self
    {
        $r = new self();
        $r->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $r->path = rtrim($uri, '/') ?: '/';
        $r->query = $_GET ?? [];
        $raw = file_get_contents('php://input') ?: '';
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($ct, 'application/json')) {
            $r->body = json_decode($raw, true) ?: [];
        } else {
            $r->body = $_POST ?? [];
        }
        return $r;
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }
    public function query(?string $k=null, $d=null) { return $k===null ? $this->query : ($this->query[$k] ?? $d); }
    public function input(?string $k=null, $d=null) { return $k===null ? $this->body : ($this->body[$k] ?? $d); }
}
