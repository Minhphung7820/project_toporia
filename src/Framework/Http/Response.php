<?php
namespace Framework\Http;

final class Response
{
    private int $status = 200;
    private array $headers = ['Content-Type' => 'text/html; charset=UTF-8'];

    public function setStatus(int $code): void { $this->status = $code; http_response_code($code); }
    public function header(string $name, string $value): void { $this->headers[$name] = $value; header($name . ': ' . $value, replace: true); }

    public function html(string $content, int $status=200): void
    {
        $this->setStatus($status);
        $this->header('Content-Type', 'text/html; charset=UTF-8');
        echo $content;
    }
    public function json(array $data, int $status=200): void
    {
        $this->setStatus($status);
        $this->header('Content-Type', 'application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
