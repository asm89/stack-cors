<?php

/*
 * This file is part of asm89/stack-cors.
 *
 * (c) Alexander <iam.asm89@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Asm89\Stack\Exception;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RequestNotAllowedException extends AccessDeniedHttpException
{
    public function __construct()
    {
        parent::__construct('Not allowed.');
    }
}
