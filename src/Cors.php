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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;

class Cors implements HttpKernelInterface
{
    /**
     * @var \Symfony\Component\HttpKernel\HttpKernelInterface
     */
    private $app;

    /**
     * @var \Asm89\Stack\CorsService
     */
    private $cors;

    private $defaultOptions = [
        'allowedHeaders'         => [],
        'allowedMethods'         => [],
        'allowedOrigins'         => [],
        'allowedOriginsPatterns' => [],
        'exposedHeaders'         => [],
        'maxAge'                 => 0,
        'supportsCredentials'    => false,
    ];

    public function __construct(HttpKernelInterface $app, array $options = [])
    {
        $this->app  = $app;
        $this->cors = new CorsService(array_merge($this->defaultOptions, $options));
    }

    public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): Response
    {
        if ($this->cors->isPreflightRequest($request)) {
            $response = $this->cors->handlePreflightRequest($request);
            return $this->cors->varyHeader($response, 'Access-Control-Request-Method');
        }

        $response = $this->app->handle($request, $type, $catch);

        if ($request->getMethod() === 'OPTIONS') {
            $this->cors->varyHeader($response, 'Access-Control-Request-Method');
        }

        return $this->cors->addActualRequestHeaders($response, $request);
    }
}
