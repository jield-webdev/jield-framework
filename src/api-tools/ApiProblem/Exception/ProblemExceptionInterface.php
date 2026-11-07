<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Exception;

use Throwable;

/**
 * Interface for exceptions that can provide additional API Problem details.
 */
interface ProblemExceptionInterface extends Throwable
{
    public function getAdditionalDetails(): ?iterable;

    public function getType(): string;

    public function getTitle(): string;
}
