<?php
namespace Framework\Presentation\Responder;

use Framework\Http\Response;

abstract class AbstractResponder
{
    public function json(Response $res, array $data, int $status = 200): void
    {
        $res->json($data, $status);
    }

    public function html(Response $res, string $html, int $status = 200): void
    {
        $res->html($html, $status);
    }

    public function jsonCreated(Response $res, array $data): void
    {
        $this->json($res, $data, 201);
    }

    public function problem(Response $res, int $status, string $title, ?string $detail = null, array $extra = []): void
    {
        $payload = array_merge([
            "type" => "about:blank",
            "title" => $title,
            "status" => $status,
            "detail" => $detail,
        ], $extra);
        $this->json($res, $payload, $status);
    }
}
