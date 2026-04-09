<?php

namespace Jield\Http\Client\Adapter;

/**
 * An interface description for Jield\Http\Client\Adapter\Stream classes.
 *
 * This interface describes Jield\Http\Client\Adapter which supports streaming.
 */
interface StreamInterface
{
    /**
     * Set output stream
     *
     * This function sets output stream where the result will be stored.
     *
     * @param resource $stream Stream to write the output to
     * @return self
     */
    public function setOutputStream($stream): StreamInterface;
}
