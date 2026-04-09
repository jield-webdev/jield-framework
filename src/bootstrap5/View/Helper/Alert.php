<?php

declare(strict_types=1);

namespace Jield\Bootstrap5\View\Helper;

use function sprintf;
use function trim;

final class Alert
{
    private string $format = '<div class="alert alert-%s %s" role="alert">%s%s</div>';

    public function info(string $alert, bool $isDismissible = false): string
    {
        return $this->render(alert: $alert, class: 'info', isDismissible: $isDismissible);
    }

    public function render(string $alert, string $class = '', bool $isDismissible = false): string
    {
        $closeButton = '';
        $dismissibleClass = '';
        if ($isDismissible) {
            $closeButton = '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            $dismissibleClass = 'alert-dismissible fade show';
        }
        $class = trim(string: $class);

        return sprintf(
            $this->format,
            $class,
            $dismissibleClass,
            $closeButton,
            $alert
        );
    }

    public function danger(string $alert, bool $isDismissible = false): string
    {
        return $this->render(alert: $alert, class: 'danger', isDismissible: $isDismissible);
    }

    public function success(string $alert, bool $isDismissible = false): string
    {
        return $this->render(alert: $alert, class: 'success', isDismissible: $isDismissible);
    }

    public function warning(string $alert, bool $isDismissible = false): string
    {
        return $this->render(alert: $alert, class: 'warning', isDismissible: $isDismissible);
    }

    public function primary(string $alert, bool $isDismissible = false): string
    {
        return $this->render(alert: $alert, class: 'primary', isDismissible: $isDismissible);
    }

    public function secondary(string $alert, bool $isDismissible = false): string
    {
        return $this->render(alert: $alert, class: 'secondary', isDismissible: $isDismissible);
    }

    public function light(string $alert, bool $isDismissible = false): string
    {
        return $this->render(alert: $alert, class: 'light', isDismissible: $isDismissible);
    }

    public function dark(string $alert, bool $isDismissible = false): string
    {
        return $this->render(alert: $alert, class: 'dark', isDismissible: $isDismissible);
    }

    public function __invoke(?string $alert = null, string $class = 'info', bool $isDismissible = false): string|static
    {
        if (null !== $alert) {
            return $this->render(alert: $alert, class: $class, isDismissible: $isDismissible);
        }

        return $this;
    }
}
