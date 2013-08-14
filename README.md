# Stack/Cors

Library and middleware enabling cross-origin resource sharing for your
http-{foundation,kernel} using application. It attempts to implement the
[W3C Candidate Recommendation] for cross-origin resource sharing.

[W3C Candidate Recommendation]: http://www.w3.org/TR/cors/

Master: [![Build Status](https://secure.travis-ci.org/asm89/stack-cors.png?branch=master)](http://travis-ci.org/asm89/stack-cors)
Develop [![Build Status](https://secure.travis-ci.org/asm89/stack-cors.png?branch=develop)](http://travis-ci.org/asm89/stack-cors)

## Installation

Require `asm89/stack-cors` using composer.

## Usage

```php
<?php

use Asm89\Stack\Cors;

$app = new Cors($app, array(
    'allowedHeaders'      => array('x-allowed-header', 'x-other-allowed-header'),
    'allowedMethods'      => array('DELETE', 'GET', 'POST', 'PUT'),
    'allowedOrigins'      => array('localhost'),
    'exposedHeaders'      => false,
    'maxAge'              => false,
    'supportsCredentials' => false,
));
```
