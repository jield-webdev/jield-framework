<?php

declare(strict_types=1);

$package = dirname(__DIR__) . '/vendor/tecnickcom/tc-lib-pdf-font';
if (is_file($package . '/target/fonts/core/helvetica.json')) {
    exit(0);
}
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($package . '/util/build_fonts.php'), $result);
exit($result);
