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

use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class MethodNotAllowedException extends MethodNotAllowedHttpException
{
    /**
     * Constructor.
     *
     * @param array      $allow    An array of allowed methods
     */
    public function __construct(array $allow)
    {
        parent::__construct($allow, 'Method not allowed');
    }
}
