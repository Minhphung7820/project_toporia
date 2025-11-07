<?php
namespace Framework\Http\Problem;

use Exception;

class HttpProblem extends Exception
{
    public function __construct(public int $status, string $title, public ?string $detail = null, public array $extra = [])
    {
        parent::__construct($title, $status);
    }
}
