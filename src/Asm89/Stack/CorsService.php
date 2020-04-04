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
        if ($options['allowedOrigins'] === array('*')) {
            $options['allowedOrigins'] = true;
        }
        if ($options['allowedHeaders'] === array('*')) {
            $options['allowedHeaders'] = true;
        } else {
            $options['allowedHeaders'] = array_map('strtolower', $options['allowedHeaders']);
        }
        if ($options['allowedMethods'] === array('*')) {
            $options['allowedMethods'] = true;
        } else {
            $options['allowedMethods'] = array_map('strtoupper', $options['allowedMethods']);
        }
        if ($options['exposedHeaders'] === array('*')) {
            $options['exposedHeaders'] = true;
        } elseif (is_array($options['exposedHeaders'])) {
            $options['exposedHeaders'] = array_map('strtolower', $options['exposedHeaders']);
        }

        return $options;
    }

    public function isPreflightRequest(Request $request)
    {
        return $request->getMethod() === 'OPTIONS'
            && $request->headers->has('Access-Control-Request-Method');
    }

    public function addActualRequestHeaders(Response $response, Request $request)
    {
        $this->addOriginHeader($response, $request);
        $this->addCredentialsHeader($response);

        if ($this->options['supportsCredentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($this->options['exposedHeaders']) {
            if ($this->options['supportsCredentials'] === true && $this->options['exposedHeaders'] === true) {
                // This is not allowed.
                // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Access-Control-Expose-Headers
                throw new \LogicException("Cannot set supportsCredentials to true and exposedHeaders to true or ['*']");
            }

            $exposedHeaders = $this->options['exposedHeaders'] === true
                ? '*'
                : implode(', ', $this->options['exposedHeaders']);
            $response->headers->set('Access-Control-Expose-Headers', $exposedHeaders);
        }

        return $response;
    }

    public function handlePreflightRequest(Request $request)
    {
        return $this->buildPreflightCheckResponse($request);
    }

    private function buildPreflightCheckResponse(Request $request)
    {
        $response = new Response('', Response::HTTP_NO_CONTENT);

        $this->addOriginHeader($response, $request);
        $this->addCredentialsHeader($response);

        if ($this->options['allowedMethods']) {
            // If credentials are supported, and all methods are allowed, the request must be varied by the header.
            if ($this->options['supportsCredentials'] === true && $this->options['allowedMethods'] === true) {
                $this->addVary($response, 'Access-Control-Request-Method');
                $allowMethods = strtoupper($request->headers->get('Access-Control-Request-Method'));
            } else {
                $allowMethods = $this->options['allowedMethods'] === true
                    ? '*'
                    : implode(', ', $this->options['allowedMethods']);
            }
            $response->headers->set('Access-Control-Allow-Methods', $allowMethods);
        }

        if ($this->options['allowedHeaders']) {
            // If credentials are supported, and all headers are allowed, the request must be varied by the header.
            if ($this->options['supportsCredentials'] === true && $this->options['allowedHeaders'] === true) {
                $this->addVary($response, 'Access-Control-Request-Headers');
                $allowHeaders = strtoupper($request->headers->get('Access-Control-Request-Headers'));
            } else {
                $allowHeaders = $this->options['allowedHeaders'] === true
                    ? '*'
                    : implode(', ', $this->options['allowedHeaders']);
            }
            $response->headers->set('Access-Control-Allow-Headers', $allowHeaders);
        }

        if ($this->options['maxAge'] !== null) {
            $response->headers->set('Access-Control-Max-Age', $this->options['maxAge']);
        }

        return $response;
    }

    private function checkOrigin(Request $request)
    {
        if ($this->options['allowedOrigins'] === true) {
            // allow all '*' flag
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

    private function addVary(Response $response, $header)
    {
        if (!$response->headers->has('Vary')) {
            $response->headers->set('Vary', $header);
        } else {
            $vary = explode(', ', $response->headers->get('Vary'));
            $response->headers->set('Vary', implode(', ', array_unique(array_merge($vary, array($header)))));
        }
    }

    private function addOriginHeader(Response $response, Request $request)
    {
        if ($this->options['allowedOrigins'] || $this->options['allowedOriginsPatterns']) {
            if ($this->options['supportsCredentials'] === false && $this->options['allowedOrigins'] === true) {
                // If any Origin is allowed, set the response for all requests.
                // (not supported if supportsCredentials is true)
                $response->headers->set('Access-Control-Allow-Origin', '*');
            } elseif (!$this->options['allowedOriginsPatterns'] && count($this->options['allowedOrigins']) === 1) {
                // If there is only one allowed origin, set the response for all requests,
                // since the requesting Origin is irrelevant.
                $response->headers->set('Access-Control-Allow-Origin', $this->options['allowedOrigins'][0]);
            } else {
                // Regardless if the origin is valid or not, the request needs to be varied by the Origin.
                $this->addVary($response, 'Origin');

                if ($this->checkOrigin($request)) {
                    $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin'));
                }
            }
        }

        return $response;
    }

    private function addCredentialsHeader(Response $response)
    {
        if ($this->options['supportsCredentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
