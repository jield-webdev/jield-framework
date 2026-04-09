<?php

namespace Jield\Http\Header;

/**
 * Content Security Policy Level 3 Header
 *
 * @link http://www.w3.org/TR/CSP/
 */
class ContentSecurityPolicyReportOnly extends ContentSecurityPolicy
{
    /**
     * Get the header name
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return 'Content-Security-Policy-Report-Only';
    }
}
