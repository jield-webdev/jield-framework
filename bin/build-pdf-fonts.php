<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    $autoload = dirname(__DIR__, 3) . '/autoload.php';
}
require $autoload;

$package = \Composer\InstalledVersions::getInstallPath('tecnickcom/tc-lib-pdf-font');
if ($package === null) {
    fwrite(STDERR, "The tecnickcom/tc-lib-pdf-font dependency is not installed.\n");
    exit(1);
}
if (is_file($package . '/target/fonts/core/helvetica.json')) {
    exit(0);
}
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($package . '/util/build_fonts.php'), $result);
exit($result);
