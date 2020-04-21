<?php

/*
 * This file is part of asm89/stack-cors.
 *
 * (c) Alexander <iam.asm89@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Asm89\Stack;

use MakiseCo\Http\Request;
use MakiseCo\Http\Response;

use function array_map;
use function array_values;
use function explode;
use function implode;
use function in_array;
use function preg_match;

class CorsService
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $this->normalizeOptions($options);
    }

    private function normalizeOptions(array $options = []): array
    {
        $options += [
            'allowedOrigins' => [],
            'allowedOriginsPatterns' => [],
            'supportsCredentials' => false,
            'allowedHeaders' => [],
            'exposedHeaders' => [],
            'allowedMethods' => [],
            'maxAge' => 0,
        ];

        // normalize array('*') to true
        if (in_array('*', $options['allowedOrigins'], true)) {
            $options['allowedOrigins'] = true;
        }
        if (in_array('*', $options['allowedHeaders'], true)) {
            $options['allowedHeaders'] = true;
        } else {
            $options['allowedHeaders'] = array_map('strtolower', $options['allowedHeaders']);
        }

        if (in_array('*', $options['allowedMethods'], true)) {
            $options['allowedMethods'] = true;
        } else {
            $options['allowedMethods'] = array_map('strtoupper', $options['allowedMethods']);
        }

        return $options;
    }

    public function isCorsRequest(Request $request): bool
    {
        return $request->headers->has('Origin') && !$this->isSameHost($request);
    }

    public function isPreflightRequest(Request $request): bool
    {
        return $request->getMethod() === 'OPTIONS' && $request->headers->has('Access-Control-Request-Method');
    }

    public function handlePreflightRequest(Request $request): Response
    {
        $response = new Response();

        $response->setStatusCode(204);

        return $this->addPreflightRequestHeaders($response, $request);
    }

    public function addPreflightRequestHeaders(Response $response, Request $request): Response
    {
        $this->configureAllowedOrigin($response, $request);

        if ($response->headers->has('Access-Control-Allow-Origin')) {
            $this->configureAllowCredentials($response, $request);

            $this->configureAllowedMethods($response, $request);

            $this->configureAllowedHeaders($response, $request);

            $this->configureMaxAge($response, $request);
        }

        return $response;
    }

    public function isOriginAllowed(Request $request): bool
    {
        if ($this->options['allowedOrigins'] === true) {
            return true;
        }

        if (!$request->headers->has('Origin')) {
            return false;
        }

        $origin = $request->headers->get('Origin');

        if (in_array($origin, $this->options['allowedOrigins'], true)) {
            return true;
        }

        foreach ($this->options['allowedOriginsPatterns'] as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        return false;
    }

    public function addActualRequestHeaders(Response $response, Request $request): Response
    {
        $this->configureAllowedOrigin($response, $request);

        if ($response->headers->has('Access-Control-Allow-Origin')) {
            $this->configureAllowCredentials($response, $request);

            $this->configureExposedHeaders($response, $request);
        }

        return $response;
    }

    private function configureAllowedOrigin(Response $response, Request $request): void
    {
        if ($this->options['allowedOrigins'] === true && !$this->options['supportsCredentials']) {
            // Safe+cacheable, allow everything
            $response->headers->set('Access-Control-Allow-Origin', '*');
        } elseif ($this->isSingleOriginAllowed()) {
            // Single origins can be safely set
            $response->headers->set('Access-Control-Allow-Origin', array_values($this->options['allowedOrigins'])[0]);
        } else {
            // For dynamic headers, check the origin first
            if ($this->isOriginAllowed($request)) {
                $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin'));
            }

            $this->varyHeader($response, 'Origin');
        }
    }

    private function isSingleOriginAllowed(): bool
    {
        if ($this->options['allowedOrigins'] === true || !empty($this->options['allowedOriginsPatterns'])) {
            return false;
        }

        return count($this->options['allowedOrigins']) === 1;
    }

    private function configureAllowedMethods(Response $response, Request $request): void
    {
        if ($this->options['allowedMethods'] === true) {
            if ($this->options['supportsCredentials']) {
                $allowMethods = strtoupper($request->headers->get('Access-Control-Request-Method'));
                $this->varyHeader($response, 'Access-Control-Request-Method');
            } else {
                $allowMethods = '*';
            }
        } else {
            $allowMethods = implode(', ', $this->options['allowedMethods']);
        }

        $response->headers->set('Access-Control-Allow-Methods', $allowMethods);
    }

    private function configureAllowedHeaders(Response $response, Request $request): void
    {
        if ($this->options['allowedHeaders'] === true) {
            if ($this->options['supportsCredentials']) {
                $allowHeaders = $request->headers->get('Access-Control-Request-Headers');
                $this->varyHeader($response, 'Access-Control-Request-Headers');
            } else {
                $allowHeaders = '*';
            }
        } else {
            $allowHeaders = implode(', ', $this->options['allowedHeaders']);
        }
        $response->headers->set('Access-Control-Allow-Headers', $allowHeaders);
    }

    private function configureAllowCredentials(Response $response, Request $request): void
    {
        if ($this->options['supportsCredentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
    }

    private function configureExposedHeaders(Response $response, Request $request): void
    {
        if ($this->options['exposedHeaders']) {
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $this->options['exposedHeaders']));
        }
    }

    private function configureMaxAge(Response $response, Request $request): void
    {
        if ($this->options['maxAge'] !== null) {
            $response->headers->set('Access-Control-Max-Age', (int)$this->options['maxAge']);
        }
    }

    public function varyHeader(Response $response, $header): Response
    {
        if (!$response->headers->has('Vary')) {
            $response->headers->set('Vary', $header);
        } elseif (!in_array($header, explode(', ', $response->headers->get('Vary')), true)) {
            $response->headers->set('Vary', $response->headers->get('Vary') . ', ' . $header);
        }

        return $response;
    }

    private function isSameHost(Request $request): bool
    {
        return $request->headers->get('Origin') === $request->getSchemeAndHttpHost();
    }
}
