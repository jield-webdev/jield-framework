<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Exception;

/**
 * Interface for exceptions that can provide additional API Problem details.
 */
interface ProblemExceptionInterface
{
    public function getAdditionalDetails(): ?iterable;

    public function getType(): string;

    public function getTitle(): string;
}
