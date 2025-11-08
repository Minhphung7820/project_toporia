<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Middleware;

use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Add Security Headers Middleware
 *
 * Adds security-related HTTP headers to prevent XSS, clickjacking,
 * and other common web vulnerabilities.
 */
final class AddSecurityHeaders extends AbstractMiddleware
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    protected function after(Request $request, Response $response, mixed $result): void
    {
        // X-Content-Type-Options: Prevent MIME sniffing
        if ($this->config['x_content_type_options']) {
            $response->header('X-Content-Type-Options', 'nosniff');
        }

        // X-Frame-Options: Prevent clickjacking
        if ($this->config['x_frame_options']) {
            $response->header('X-Frame-Options', $this->config['x_frame_options']);
        }

        // X-XSS-Protection: Enable browser XSS filter
        if ($this->config['x_xss_protection']) {
            $response->header('X-XSS-Protection', '1; mode=block');
        }

        // Strict-Transport-Security: Force HTTPS
        if ($this->config['hsts'] && $this->config['hsts_max_age']) {
            $hsts = 'max-age=' . $this->config['hsts_max_age'];
            if ($this->config['hsts_include_subdomains']) {
                $hsts .= '; includeSubDomains';
            }
            if ($this->config['hsts_preload']) {
                $hsts .= '; preload';
            }
            $response->header('Strict-Transport-Security', $hsts);
        }

        // Content-Security-Policy
        if ($this->config['csp']) {
            $response->header('Content-Security-Policy', $this->config['csp']);
        }

        // Referrer-Policy
        if ($this->config['referrer_policy']) {
            $response->header('Referrer-Policy', $this->config['referrer_policy']);
        }

        // Permissions-Policy (formerly Feature-Policy)
        if ($this->config['permissions_policy']) {
            $response->header('Permissions-Policy', $this->config['permissions_policy']);
        }
    }

    /**
     * Get default security headers configuration
     *
     * @return array
     */
    private function getDefaultConfig(): array
    {
        return [
            'x_content_type_options' => true,
            'x_frame_options' => 'SAMEORIGIN', // DENY, SAMEORIGIN, or false to disable
            'x_xss_protection' => true,
            'hsts' => false, // Enable for production with HTTPS
            'hsts_max_age' => 31536000, // 1 year
            'hsts_include_subdomains' => false,
            'hsts_preload' => false,
            'csp' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';",
            'referrer_policy' => 'strict-origin-when-cross-origin',
            'permissions_policy' => 'geolocation=(), microphone=(), camera=()',
        ];
    }
}
