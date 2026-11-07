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
class FormFileSessionProgress extends FormFileUploadProgress
{
    protected function getName(): string
    {
        $name = ini_get(option: 'session.upload_progress.name');
        return $name ? $name : '';
    }
}
