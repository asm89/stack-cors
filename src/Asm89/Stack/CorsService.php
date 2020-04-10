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

    public function handlePreflightRequest(Request $request)
    {
        $response = new Response();

        $response->setStatusCode(204);

        return $this->addPreflightRequestHeaders($response, $request);
    }

    public function addPreflightRequestHeaders(Response $response, Request $request)
    {
        $this->configureAllowedOrigin($response, $request);

        $this->configureAllowCredentials($response, $request);

        $this->configureAllowedMethods($response, $request);

        $this->configureAllowedHeaders($response, $request);

        $this->configureMaxAge($response, $request);

        return $response;
    }

    public function isActualRequestAllowed(Request $request)
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

    public function addActualRequestHeaders(Response $response, Request $request)
    {
        $this->configureAllowedOrigin($response, $request);

        $this->configureAllowCredentials($response, $request);

        $this->configureExposedHeaders($response, $request);

        return $response;
    }

    private function configureAllowedOrigin(Response $response, Request $request)
    {
        if ($this->options['allowedOrigins'] === true && !$this->options['supportsCredentials']) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        } elseif ($this->options['allowedOrigins'] !== true &&
            count($this->options['allowedOrigins']) === 1 &&
            empty($this->options['allowedOriginsPatterns'])) {
            $response->headers->set('Access-Control-Allow-Origin', array_values($this->options['allowedOrigins'])[0]);
        } else {
            if ($this->isActualRequestAllowed($request)) {
                $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin'));
            }

            $this->varyHeader($response, 'Origin');
        }
    }

    private function configureAllowedMethods(Response $response, Request $request)
    {
        if ($this->options['allowedMethods'] === true) {
            if ($this->options['supportsCredentials']) {
                $allowMethods = 'GET, HEAD, PUT, PATCH, POST, DELETE';
            } else {
                $allowMethods = '*';
            }
        } else {
            $allowMethods = implode(', ', $this->options['allowedMethods']);
        }

        $response->headers->set('Access-Control-Allow-Methods', $allowMethods);
    }

    private function configureAllowedHeaders(Response $response, Request $request)
    {
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
    }

    private function configureAllowCredentials(Response $response, Request $request){
        if ($this->options['supportsCredentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
    }

    private function configureExposedHeaders(Response $response, Request $request)
    {
        if ($this->options['exposedHeaders']) {
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $this->options['exposedHeaders']));
        }
    }

    private function configureMaxAge(Response $response, Request $request){
        if ($this->options['maxAge'] !== null) {
            $response->headers->set('Access-Control-Max-Age', (int) $this->options['maxAge']);
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

    private function isSameHost(Request $request)
    {
        return $request->headers->get('Origin') === $request->getSchemeAndHttpHost();
    }
}
