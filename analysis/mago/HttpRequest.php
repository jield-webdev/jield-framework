<?php

namespace Laminas\Http;

// The zero-argument forms return collections; the vendor unions lose this
// relationship and incorrectly treat collection operations as dynamic calls.
class Request
{
    /** @return ($name is null ? \Laminas\Stdlib\ParametersInterface : mixed) */
    public function getQuery($name = null, $default = null) {}

    /** @return ($name is null ? \Laminas\Stdlib\ParametersInterface : mixed) */
    public function getPost($name = null, $default = null) {}

    /** @return ($name is null ? \Laminas\Stdlib\ParametersInterface : mixed) */
    public function getFiles($name = null, $default = null) {}

    /** @return ($name is null ? Headers : \Laminas\Http\Header\HeaderInterface|\ArrayIterator|false) */
    public function getHeaders($name = null, $default = false) {}
}
