<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Exception;

use Override;

class DomainException extends \DomainException implements ExceptionInterface, ProblemExceptionInterface
{
    /** @var string */
    protected string $type;

    /** @var array */
    protected array $details = [];

    /** @var string */
    protected string $title;

    /**
     * @param array $details
     * @return static
     */
    public function setAdditionalDetails(array $details): static
    {
        $this->details = $details;
        return $this;
    }

    /**
     * @param string $uri
     * @return static
     */
    public function setType(string $uri): static
    {
        $this->type = $uri;
        return $this;
    }

    /**
     * @param string $title
     * @return static
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return array
     */
    #[Override]
    public function getAdditionalDetails(): array
    {
        return $this->details;
    }

    /**
     * @return string
     */
    #[Override]
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    #[Override]
    public function getTitle(): string
    {
        return $this->title;
    }
}
