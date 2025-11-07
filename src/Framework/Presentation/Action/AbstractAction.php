<?php
namespace Framework\Presentation\Action;

use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Base Action for ADR style. Child classes implement handle().
 * You may override before()/after() hooks.
 */
abstract class AbstractAction
{
    final public function __invoke(Request $request, Response $response, ...$vars)
    {
        $this->before($request, $response);
        $result = $this->handle($request, $response, ...$vars);
        $this->after($request, $response, $result);
        return $result;
    }

    protected function before(Request $request, Response $response): void {}
    protected function after(Request $request, Response $response, mixed $result): void {}

    /** @return mixed */
    abstract protected function handle(Request $request, Response $response, ...$vars);
}
