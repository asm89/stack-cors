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

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsService
{
    private $options;

    public function __construct(array $options = array())
    {
        $this->options = $this->normalizeOptions($options);
    }

    private function normalizeOptions(array $options = array())
    {
        $options += array(
            'allowedOrigins' => array(),
            'allowedOriginsPatterns' => array(),
            'supportsCredentials' => false,
            'allowedHeaders' => array(),
            'exposedHeaders' => array(),
            'allowedMethods' => array(),
            'maxAge' => 0,
        );

        // normalize array('*') to true
        if (in_array('*', $options['allowedOrigins'])) {
            $options['allowedOrigins'] = true;
        }
        if (in_array('*', $options['allowedHeaders'])) {
            $options['allowedHeaders'] = true;
        } else {
            $options['allowedHeaders'] = array_map('strtolower', $options['allowedHeaders']);
        }

        if (in_array('*', $options['allowedMethods'])) {
            $options['allowedMethods'] = true;
        } else {
            $options['allowedMethods'] = array_map('strtoupper', $options['allowedMethods']);
        }

        return $options;
    }

    public function isActualRequestAllowed(Request $request)
    {
        return $this->checkOrigin($request);
    }

    public function isCorsRequest(Request $request)
    {
        return $request->headers->has('Origin') && !$this->isSameHost($request);
    }

    public function isPreflightRequest(Request $request)
    {
        return $this->isCorsRequest($request)
            && $request->getMethod() === 'OPTIONS'
            && $request->headers->has('Access-Control-Request-Method');
    }

    public function addActualRequestHeaders(Response $response, Request $request)
    {
        $this->addAllowedOrigin($response, $request);

        if ($this->options['supportsCredentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($this->options['exposedHeaders']) {
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $this->options['exposedHeaders']));
        }

        return $response;
    }

    private function addAllowedOrigin(Response $response, Request $request)
    {
        if ($this->options['allowedOrigins'] === true && !$this->options['supportsCredentials']) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        } elseif ($this->options['allowedOrigins'] !== true &&
            count($this->options['allowedOrigins']) === 1 &&
            empty($this->options['allowedOriginsPatterns'])) {
            $response->headers->set('Access-Control-Allow-Origin', array_values($this->options['allowedOrigins'])[0]);
        } else {
            if ($this->checkOrigin($request)) {
                $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin'));
            }

            $this->varyHeader($response, 'Origin');
        }
    }

    private function varyHeader(Response $response, $header)
    {
        if (!$response->headers->has('Vary')) {
            $response->headers->set('Vary', $header);
        } else {
            $response->headers->set('Vary', $response->headers->get('Vary') . ', ' . $header);
        }
    }
    public function handlePreflightRequest(Request $request)
    {
        if (true !== $check = $this->checkPreflightRequestConditions($request)) {
            return $check;
        }

        return $this->buildPreflightCheckResponse($request);
    }

    private function buildPreflightCheckResponse(Request $request)
    {
        $response = new Response();

        if ($this->options['supportsCredentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        $this->addAllowedOrigin($response, $request);

        if ($this->options['maxAge']) {
            $response->headers->set('Access-Control-Max-Age', $this->options['maxAge']);
        }

        if ($this->options['allowedMethods'] === true) {
            if ($this->options['supportsCredentials']) {
                $allowMethods = strtoupper($request->headers->get('Access-Control-Request-Method'));
                $this->varyHeader('Access-Control-Request-Method');
            } else {
                $allowMethods = '*';
            }
        } else {
            $allowMethods = implode(', ', $this->options['allowedMethods']);
        }
        $response->headers->set('Access-Control-Allow-Methods', $allowMethods);

        if ($this->options['allowedHeaders'] === true) {
            if ($this->options['supportsCredentials']) {
                $allowHeaders = strtoupper($request->headers->get('Access-Control-Request-Headers'));
                $this->varyHeader('Access-Control-Request-Headers');
            } else {
                $allowHeaders = '*';
            }
        } else {
            $allowHeaders = implode(', ', $this->options['allowedHeaders']);
        }
        $response->headers->set('Access-Control-Allow-Headers', $allowHeaders);

        $response->setStatusCode(204);

        return $response;
    }

    private function checkPreflightRequestConditions(Request $request)
    {
        if (!$this->checkOrigin($request)) {
            return $this->createBadRequestResponse(403, 'Origin not allowed');
        }

        if (!$this->checkMethod($request)) {
            return $this->createBadRequestResponse(405, 'Method not allowed');
        }

        $requestHeaders = array();
        // if allowedHeaders has been set to true ('*' allow all flag) just skip this check
        if ($this->options['allowedHeaders'] !== true && $request->headers->has('Access-Control-Request-Headers')) {
            $headers        = strtolower($request->headers->get('Access-Control-Request-Headers'));
            $requestHeaders = array_filter(explode(',', $headers));

            foreach ($requestHeaders as $header) {
                if (!in_array(trim($header), $this->options['allowedHeaders'])) {
                    return $this->createBadRequestResponse(403, 'Header not allowed');
                }
            }
        }

        return true;
    }

    private function createBadRequestResponse($code, $reason = '')
    {
        return new Response($reason, $code);
    }

    private function isSameHost(Request $request)
    {
        return $request->headers->get('Origin') === $request->getSchemeAndHttpHost();
    }

    private function checkOrigin(Request $request)
    {
        if ($this->options['allowedOrigins'] === true) {
            return true;
        }

        $origin = $request->headers->get('Origin');

        if (in_array($origin, $this->options['allowedOrigins'])) {
            return true;
        }

        foreach ($this->options['allowedOriginsPatterns'] as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        return false;
    }

    private function checkMethod(Request $request)
    {
        if ($this->options['allowedMethods'] === true) {
            // allow all '*' flag
            return true;
        }

        $requestMethod = strtoupper($request->headers->get('Access-Control-Request-Method'));
        return in_array($requestMethod, $this->options['allowedMethods']);
    }
}
