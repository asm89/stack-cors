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
        if (is_array($options['allowedOrigins']) && in_array('*', $options['allowedOrigins'])) {
            $options['allowedOrigins'] = true;
        }
        if (is_array($options['allowedHeaders'])) {
            if (in_array('*', $options['allowedHeaders'])) {
                $options['allowedHeaders'] = true;
            } else {
                $options['allowedHeaders'] = array_map('strtolower', $options['allowedHeaders']);
            }
        }
        if (is_array($options['allowedMethods'])) {
            if (in_array('*', $options['allowedMethods'])) {
                $options['allowedMethods'] = true;
            } else {
                $options['allowedMethods'] = array_map('strtoupper', $options['allowedMethods']);
            }
        }
        if (is_array($options['exposedHeaders'])) {
            if (in_array('*', $options['exposedHeaders'])) {
                $options['exposedHeaders'] = true;
            } else {
                $options['exposedHeaders'] = array_map('strtolower', $options['exposedHeaders']);
            }
        }

        if ($options['supportsCredentials'] === true && $options['exposedHeaders'] === true) {
            // This is not allowed.
            // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Access-Control-Expose-Headers
            throw new \LogicException("Cannot set supportsCredentials to true and exposedHeaders to true or ['*']");
        }

        return $options;
    }

    /**
     * @deprecated
     *
     * @todo throw a deprecation notice?
     */
    public function isActualRequestAllowed(Request $request)
    {
        return $this->checkOrigin($request);
    }

    /**
     * @deprecated
     *
     * @todo throw a deprecation notice?
     */
    public function isCorsRequest(Request $request)
    {
        return $request->headers->has('Origin') && !$this->isSameHost($request);
    }

    /**
     * @deprecated
     *
     * @todo throw a deprecation notice?
     */
    public function isPreflightRequest(Request $request)
    {
        return $this->isCorsRequest($request)
            && $request->getMethod() === 'OPTIONS'
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
            $exposedHeaders = $this->options['exposedHeaders'] === true
                ? '*'
                : implode(', ', $this->options['exposedHeaders']);
            $response->headers->set('Access-Control-Expose-Headers', $exposedHeaders);
        }

        return $response;
    }

    public function handlePreflightRequest(Request $request)
    {
        return $this->buildPreflightCheckResponse(new Response('', Response::HTTP_NO_CONTENT), $request);
    }

    private function buildPreflightCheckResponse(Response $response, Request $request)
    {
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

    public function addPreflightRequestHeader(Response $response, Request $request) {
        return $this->buildPreflightCheckResponse($response, $request);
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

        return $response;
    }

    private function addOriginHeader(Response $response, Request $request)
    {
        if ($this->options['allowedOrigins'] || $this->options['allowedOriginsPatterns']) {
            if ($this->options['supportsCredentials'] === false && $this->options['allowedOrigins'] === true) {
                // If any Origin is allowed, set the response for all requests.
                // (not supported if supportsCredentials is true)
                $response->headers->set('Access-Control-Allow-Origin', '*');
            } elseif (empty($this->options['allowedOriginsPatterns'])
                && is_array($this->options['allowedOrigins'])
                && count($this->options['allowedOrigins']) === 1
            ) {
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
