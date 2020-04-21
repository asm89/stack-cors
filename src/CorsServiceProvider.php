<?php

/**
 * This file is part of the Makise-Co Framework
 *
 * World line: 0.571024a
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace Asm89\Stack;

use DI\Container;
use MakiseCo\Config\ConfigRepositoryInterface;
use MakiseCo\Providers\ServiceProviderInterface;

class CorsServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->set(CorsService::class, static function (Container $container) {
            $config = $container->get(ConfigRepositoryInterface::class);

            $corsConfig = $config->get('cors', []);

            return new CorsService($corsConfig);
        });
    }
}
