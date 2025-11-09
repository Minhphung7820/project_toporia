<?php

declare(strict_types=1);

namespace Toporia\Framework\Error;

use Throwable;

/**
 * HTML Error Renderer
 *
 * Beautiful error pages inspired by Whoops/Ignition.
 *
 * Features:
 * - Syntax-highlighted code context
 * - Full stack trace with expandable frames
 * - Request/Server information
 * - Clean, modern UI
 *
 * Performance: O(N) where N = stack frames (acceptable for debugging)
 *
 * @package Toporia\Framework\Error
 */
final class HtmlErrorRenderer implements ErrorRendererInterface
{
    public function __construct(
        private bool $debug = true
    ) {}

    /**
     * {@inheritdoc}
     */
    public function render(Throwable $exception): void
    {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');

        if ($this->debug) {
            echo $this->renderDebugPage($exception);
        } else {
            echo $this->renderProductionPage($exception);
        }
    }

    /**
     * Render beautiful debug error page.
     *
     * @param Throwable $exception
     * @return string
     */
    private function renderDebugPage(Throwable $exception): string
    {
        $class = get_class($exception);
        $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
        $file = $exception->getFile();
        $line = $exception->getLine();

        $codeContext = $this->getCodeContext($file, $line);
        $stackTrace = $this->renderStackTrace($exception);
        $requestInfo = $this->renderRequestInfo();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$class}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #1a1a2e;
            color: #e0e0e0;
            line-height: 1.6;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .exception-class {
            font-size: 16px;
            color: #ffd700;
            font-weight: 600;
            margin-bottom: 10px;
            font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
        }
        .exception-message {
            font-size: 24px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 15px;
        }
        .exception-location {
            font-size: 14px;
            color: rgba(255,255,255,0.8);
            font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
        }
        .exception-location a {
            color: #ffd700;
            text-decoration: none;
        }
        .code-context {
            background: #16213e;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            overflow-x: auto;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .code-context pre {
            font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
            font-size: 13px;
            line-height: 1.5;
        }
        .code-line {
            padding: 4px 12px;
            border-left: 3px solid transparent;
        }
        .code-line.error {
            background: rgba(239, 68, 68, 0.2);
            border-left-color: #ef4444;
        }
        .code-line .line-number {
            display: inline-block;
            width: 50px;
            color: #6b7280;
            user-select: none;
            text-align: right;
            margin-right: 15px;
        }
        .code-line.error .line-number {
            color: #ef4444;
            font-weight: bold;
        }
        .code-line code {
            color: #e0e0e0;
        }
        .stack-trace {
            background: #16213e;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .stack-trace h3 {
            color: #ffd700;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .stack-frame {
            background: #0f3460;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .stack-frame:hover {
            background: #1a4d7a;
            transform: translateX(5px);
        }
        .frame-header {
            font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
            font-size: 13px;
            color: #e0e0e0;
        }
        .frame-location {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 5px;
        }
        .frame-location a {
            color: #ffd700;
            text-decoration: none;
        }
        .request-info {
            background: #16213e;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .request-info h3 {
            color: #ffd700;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .info-table {
            width: 100%;
            font-size: 13px;
            font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
        }
        .info-table tr {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .info-table td {
            padding: 10px;
        }
        .info-table td:first-child {
            color: #ffd700;
            width: 200px;
            font-weight: 600;
        }
        .info-table td:last-child {
            color: #e0e0e0;
        }
        .keyword { color: #c792ea; }
        .string { color: #c3e88d; }
        .number { color: #f78c6c; }
        .comment { color: #676e95; }
        .function { color: #82aaff; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="exception-class">{$class}</div>
            <div class="exception-message">{$message}</div>
            <div class="exception-location">
                at <a href="#">{$file}:{$line}</a>
            </div>
        </div>

        <div class="code-context">
            <pre>{$codeContext}</pre>
        </div>

        {$stackTrace}

        {$requestInfo}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render production error page (simple, no details).
     *
     * @param Throwable $exception
     * @return string
     */
    private function renderProductionPage(Throwable $exception): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: #fff;
        }
        .container {
            text-align: center;
            padding: 40px;
        }
        h1 {
            font-size: 120px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        h2 {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        p {
            font-size: 18px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>500</h1>
        <h2>Server Error</h2>
        <p>Oops! Something went wrong on our end.</p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get code context around the error line.
     *
     * @param string $file File path
     * @param int $line Line number
     * @param int $contextLines Number of lines before/after (default: 10)
     * @return string Formatted HTML
     */
    private function getCodeContext(string $file, int $line, int $contextLines = 10): string
    {
        if (!file_exists($file)) {
            return '<div class="code-line error">Could not read file</div>';
        }

        $lines = file($file);
        $start = max(0, $line - $contextLines - 1);
        $end = min(count($lines), $line + $contextLines);

        $html = '';
        for ($i = $start; $i < $end; $i++) {
            $lineNumber = $i + 1;
            $code = htmlspecialchars($lines[$i], ENT_QUOTES, 'UTF-8');
            $code = $this->highlightSyntax($code);

            $isError = $lineNumber === $line;
            $class = $isError ? 'code-line error' : 'code-line';

            $html .= sprintf(
                '<div class="%s"><span class="line-number">%d</span><code>%s</code></div>',
                $class,
                $lineNumber,
                $code
            );
        }

        return $html;
    }

    /**
     * Simple PHP syntax highlighting.
     *
     * @param string $code Code to highlight
     * @return string Highlighted code
     */
    private function highlightSyntax(string $code): string
    {
        // Simple highlighting (can be enhanced)
        $code = preg_replace('/\b(function|class|public|private|protected|static|return|if|else|foreach|for|while|new|use|namespace|extends|implements)\b/', '<span class="keyword">$1</span>', $code);
        $code = preg_replace('/(\'[^\']*\'|"[^"]*")/', '<span class="string">$1</span>', $code);
        $code = preg_replace('/\/\/.*$/', '<span class="comment">$0</span>', $code);
        $code = preg_replace('/\b(\d+)\b/', '<span class="number">$1</span>', $code);

        return $code;
    }

    /**
     * Render stack trace.
     *
     * @param Throwable $exception
     * @return string HTML
     */
    private function renderStackTrace(Throwable $exception): string
    {
        $trace = $exception->getTrace();
        $frames = '';

        foreach ($trace as $index => $frame) {
            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? 0;
            $class = $frame['class'] ?? '';
            $function = $frame['function'] ?? '';
            $type = $frame['type'] ?? '';

            $call = $class ? "{$class}{$type}{$function}()" : "{$function}()";

            $frames .= sprintf(
                '<div class="stack-frame">
                    <div class="frame-header">%d. %s</div>
                    <div class="frame-location"><a href="#">%s:%d</a></div>
                </div>',
                $index + 1,
                htmlspecialchars($call, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($file, ENT_QUOTES, 'UTF-8'),
                $line
            );
        }

        return <<<HTML
<div class="stack-trace">
    <h3>Stack Trace</h3>
    {$frames}
</div>
HTML;
    }

    /**
     * Render request information.
     *
     * @return string HTML
     */
    private function renderRequestInfo(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        return <<<HTML
<div class="request-info">
    <h3>Request Information</h3>
    <table class="info-table">
        <tr>
            <td>Method</td>
            <td>{$method}</td>
        </tr>
        <tr>
            <td>URI</td>
            <td>{$uri}</td>
        </tr>
        <tr>
            <td>Protocol</td>
            <td>{$protocol}</td>
        </tr>
        <tr>
            <td>IP Address</td>
            <td>{$ip}</td>
        </tr>
        <tr>
            <td>User Agent</td>
            <td>{$userAgent}</td>
        </tr>
    </table>
</div>
HTML;
    }
}
