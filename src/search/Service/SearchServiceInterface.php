<?php

declare(strict_types=1);

namespace Jield\Search\Service;

use Jield\Search\Entity\HasSearchInterface;
use Solarium\Client;
use Solarium\QueryType\Update\Result;

/**
 * Interface SearchServiceInterface
 */
interface SearchServiceInterface
{
    public function getSolrClient(): Client;

    // Preserve the existing parameter contract used by callers and inherited interfaces.
    // @mago-expect lint:no-boolean-flag-parameter(1)
    public function clearIndex(bool $optimize = true): Result;

    // Preserve the existing parameter contract used by callers and inherited interfaces.
    // @mago-expect lint:no-boolean-flag-parameter(1)
    public function deleteDocument(HasSearchInterface $entity, bool $optimize = true): Result;
}
