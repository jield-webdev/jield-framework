<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Exception;

use Override;

class DomainException extends \DomainException implements ExceptionInterface, ProblemExceptionInterface
{
    protected string $type;

    protected array $details = [];

    protected string $title;

    public function setAdditionalDetails(array $details): static
    {
        $this->details = $details;
        return $this;
    }

    public function setType(string $uri): static
    {
        $this->type = $uri;
        return $this;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    #[Override]
    public function getAdditionalDetails(): array
    {
        return $this->details;
    }

    #[Override]
    public function getType(): string
    {
        return $this->type;
    }

    #[Override]
    public function getTitle(): string
    {
        return $this->title;
    }
}
