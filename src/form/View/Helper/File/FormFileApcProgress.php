<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper\File;

use function ini_get;

/**
 * A view helper to render the hidden input with a Session progress id
 * for file uploads progress tracking.
 *
 * @final
 */
class FormFileApcProgress extends FormFileUploadProgress
{
    protected function getName(): string
    {
        return ini_get(option: 'apc.rfc1867_name') ?: '';
    }
}
