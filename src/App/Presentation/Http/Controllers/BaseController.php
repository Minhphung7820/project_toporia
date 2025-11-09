<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Base Controller
 *
 * Provides helper methods for controllers via ControllerHelpers trait.
 *
 * You can choose between two approaches:
 * 1. Extend BaseController (traditional MVC)
 * 2. Use ControllerHelpers trait directly (modern, more flexible)
 *
 * Both approaches are valid and supported.
 *
 * Example 1 - Extending BaseController:
 * ```php
 * final class MyController extends BaseController
 * {
 *     public function index()
 *     {
 *         return $this->view('products/index');
 *     }
 * }
 * ```
 *
 * Example 2 - Using trait directly:
 * ```php
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
