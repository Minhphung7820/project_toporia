<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Base Controller (Legacy Support)
 *
 * @deprecated Use ControllerHelpers trait instead for better flexibility.
 *
 * This class is kept for backward compatibility with existing controllers.
 * New controllers should use method injection or ControllerHelpers trait.
 *
 * Migration guide:
 * ```php
 * // Old way (still works):
 * final class MyController extends BaseController
 * {
 *     public function index()
 *     {
 *         return $this->view('products/index');
 *     }
 * }
 *
 * // New way (recommended):
 * final class MyController
 * {
 *     use ControllerHelpers;
 *
 *     public function index(Request $request, Response $response)
 *     {
 *         return $this->view('products/index');
 *     }
 * }
 * ```
 */
abstract class BaseController
{
    use ControllerHelpers;

    /**
     * Constructor with Request/Response injection.
     *
     * @param Request $request HTTP request instance
     * @param Response $response HTTP response instance
     */
    public function __construct(
        protected Request $request,
        protected Response $response
    ) {}
}
