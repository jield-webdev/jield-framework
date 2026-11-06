<?php

/**
 * Jield\GoogleAnalytics Configuration
 *
 * If you have a ./configs/autoload/ directory set up for your project, you can
 * drop this config file in it and change the values as you wish.
 */
$googleAnalytics = [
    /**
     * Measurement ID (something like G-xxxxx-x)
     */
    'id' => '',

    /**
     * Disable/enable page tracking
     *
     * It is advised to turn off tracking in a development/staging environment. Put this
     * configuration option in your local.php in the autoload folder and set "enable" to
     * false.
     */
    // 'enable' => false,
];

/**
 * You do not need to edit below this line
 */
return ['google_analytics' => $googleAnalytics];
