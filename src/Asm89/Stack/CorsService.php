<?php

namespace Asm89\Stack;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsService
{
    private $options;

    public function __construct(array $options = array())
    {
        $this->options = $options;
    }

    public function isCorsRequest(Request $request)
    {
        return $request->headers->has('Origin');
    }

    public function isPreflightRequest(Request $request)
    {
        return $this->isCorsRequest($request)
            &&$request->getMethod() === 'OPTIONS'
            && $request->headers->has('Access-Control-Request-Method');
    }

    public function addActualRequestHeaders(Response $response, $origin)
    {
        if ( ! in_array($origin, $this->options['allowedOrigins'])) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);

        if ( ! $response->headers->has('Vary')) {
            $response->headers->set('Vary', 'Origin');
        } else {
            $response->headers->set('Vary', $response->headers->get('Vary') . ', Origin');
        }

        if ($this->options['supportsCredentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($this->options['exposedHeaders']) {
            $response->headers->set('Access-Control-Exposed-Headers', implode(', ', $this->options['exposedHeaders']));
        }

        return $response;
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

        $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin'));

        if ($this->options['maxAge']) {
            $response->headers->set('Access-Control-Max-Age', $this->options['maxAge']);
        }

        $response->headers->set('Access-Control-Allow-Methods', implode(', ', $this->options['allowedMethods']));

        $response->headers->set('Access-Control-Allow-Headers', $request->headers->get('Access-Control-Request-Headers'));

        return $response;
    }

    private function checkPreflightRequestConditions(Request $request)
    {
        $origin = $request->headers->get('Origin');
        if ( ! in_array($origin, $this->options['allowedOrigins'])) {
            return $this->createBadRequestResponse(403, 'Origin not allowed');
        }

        $method = $request->headers->get('Access-Control-Request-Method');
        if ( ! in_array($method, $this->options['allowedMethods'])) {
            return $this->createBadRequestResponse(405, 'Method not allowed');
        }

        $requestHeaders = array();
        if ($request->headers->has('Access-Control-Request-Headers')) {
            $headers        = strtolower($request->headers->get('Access-Control-Request-Headers'));
            $requestHeaders = explode(',', $headers);

            foreach ($requestHeaders as $header) {
                if ( ! in_array(trim($header), $this->options['allowedHeaders'])) {
                    return $this->createBadRequestResponse(403, 'Header not allowed');
                }
            }
        }

        return true;
    }

    private function createBadRequestResponse($code, $reason = '')
    {
        $response = new Response($reason);

        $response->setStatusCode($code);

        return $response;
    }
}
