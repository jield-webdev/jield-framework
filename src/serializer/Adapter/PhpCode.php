<?php

declare(strict_types=1);

namespace Jield\Serializer\Adapter;

use Jield\Serializer\Exception;
use Jield\Stdlib\ErrorHandler;

use function error_get_last;
use function var_export;

use const E_ALL;

final class PhpCode extends AbstractAdapter
{
    /**
     * Serialize PHP using var_export
     */
    public function serialize(mixed $value): string
    {
        return var_export(value: $value, return: true);
    }

    /**
     * Deserialize PHP string
     *
     * Warning: this uses eval(), and should likely be avoided.
     *
     * @throws Exception\RuntimeException On eval error.
     */
    public function unserialize(string $serialized): mixed
    {
        ErrorHandler::start(errorLevel: E_ALL);
        $ret = null;
        // This suppression is due to the fact that the ErrorHandler cannot
        // catch syntax errors, and is intentionally left in place.
        $eval = @eval('$ret=' . $serialized . ';');
        $err  = ErrorHandler::stop();

        if ($eval === false || $err) {
            $msg = 'eval failed';

            // Error handler doesn't catch syntax errors
            if ($eval === false) {
                $lastErr = error_get_last();
                if (isset($lastErr['message'])) {
                    $msg .= ': ' . $lastErr['message'];
                }
            }

            throw new Exception\RuntimeException(message: $msg, code: 0, previous: $err);
        }

        return $ret;
    }
}
