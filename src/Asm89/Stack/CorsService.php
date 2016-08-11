<?php

namespace Asm89\Stack;

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
            'allowedOrigins'      => array(),
            'supportsCredentials' => false,
            'allowedHeaders'      => array(),
            'exposedHeaders'      => array(),
            'allowedMethods'      => array(),
            'maxAge'              => 0,
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
        return $request->headers->has('Origin');
    }

    public function isPreflightRequest(Request $request)
    {
        return $this->isCorsRequest($request)
        && $request->getMethod() === 'OPTIONS'
        && $request->headers->has('Access-Control-Request-Method');
    }

    public function addActualRequestHeaders(Response $response, Request $request)
    {
        if (!$this->checkOrigin($request)) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin'));

        if (!$response->headers->has('Vary')) {
            $response->headers->set('Vary', 'Origin');
        } else {
            $response->headers->set('Vary', $response->headers->get('Vary') . ', Origin');
        }

        if ($this->options['supportsCredentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($this->options['exposedHeaders']) {
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $this->options['exposedHeaders']));
        }

        return $response;
    }

    public function handlePreflightRequest(Request $request, Response $response)
    {
        if (true !== $check = $this->checkPreflightRequestConditions($request, $response)) {
            return $check;
        }

        return $this->buildPreflightCheckResponse($request, $response);
    }

    private function buildPreflightCheckResponse(Request $request, Response $response)
    {
        if ($this->options['supportsCredentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin'));

        if ($this->options['maxAge']) {
            $response->headers->set('Access-Control-Max-Age', $this->options['maxAge']);
        }

        $allowMethods = $this->options['allowedMethods'] === true
            ? strtoupper($request->headers->get('Access-Control-Request-Method'))
            : implode(', ', $this->options['allowedMethods']);
        $response->headers->set('Access-Control-Allow-Methods', $allowMethods);

        $allowHeaders = $this->options['allowedHeaders'] === true
            ? strtoupper($request->headers->get('Access-Control-Request-Headers'))
            : implode(', ', $this->options['allowedHeaders']);
        $response->headers->set('Access-Control-Allow-Headers', $allowHeaders);

        return $response;
    }

    private function checkPreflightRequestConditions(Request $request, Response $response)
    {
        if (!$this->checkOrigin($request)) {
            return $this->createBadRequestResponse($response, 403, 'Origin not allowed');
        }

        if (!$this->checkMethod($request)) {
            return $this->createBadRequestResponse($response, 405, 'Method not allowed');
        }

        $requestHeaders = array();
        // if allowedHeaders has been set to true ('*' allow all flag) just skip this check
        if ($this->options['allowedHeaders'] !== true && $request->headers->has('Access-Control-Request-Headers')) {
            $headers = strtolower($request->headers->get('Access-Control-Request-Headers'));
            $requestHeaders = explode(',', $headers);

            foreach ($requestHeaders as $header) {
                if (!in_array(trim($header), $this->options['allowedHeaders'])) {
                    return $this->createBadRequestResponse(403, 'Header not allowed');
                }
            }
        }

        return true;
    }

    private function createBadRequestResponse($response, $code, $reason = '')
    {
        return $response->setContent($reason)->setStatusCode($code);

    }

    private function checkOrigin(Request $request)
    {
        if ($this->options['allowedOrigins'] === true) {
            // allow all '*' flag
            return true;
        }
        $origin = $request->headers->get('Origin');

        return in_array($origin, $this->options['allowedOrigins']);
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
