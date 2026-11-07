<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Link;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Jield\ApiTools\ApiProblem\Exception;
use Override;
use Psr\Link\LinkInterface;
use ReturnTypeWillChange;

use function array_diff;
use function array_intersect;
use function array_key_exists;
use function array_keys;
use function count;
use function in_array;
use function is_array;

/**
 * Object describing a collection of link relations
 *
 *
 * @implements \IteratorAggregate<array-key, mixed>
 */
class LinkCollection implements Countable, IteratorAggregate
{
    /** @var array<array-key, LinkInterface|array<array-key, LinkInterface>> */
    protected array $links = [];

    /**
     * Return a count of link relations
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function count(): int
    {
        return count(value: $this->links);
    }

    /**
     * Retrieve internal iterator
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator(array: $this->links);
    }

    /**
     * Add a link
     *
     * @throws Exception\DomainException
     *@deprecated Since 1.5.0; use idempotentAdd() for PSR-13 and RFC 5988 compliance.
     */
    // Preserve the existing parameter contract used by callers and inherited interfaces.
    // @mago-expect lint:no-boolean-flag-parameter(1)
    public function add(Link $link, bool $overwrite = false): static
    {
        $relation = $link->getRelation();
        if (($this->links[$relation] ?? null) === null || $overwrite || 'self' === $relation) {
            $this->links[$relation] = $link;
            return $this;
        }

        $existing = $this->links[$relation];
        $links = $existing instanceof LinkInterface ? [$existing] : $existing;
        $links[] = $link;
        $this->links[$relation] = $links;
        return $this;
    }

    /**
     * Add a link to the collection and update the collection's relations according to RFC 5988.
     *
     * @todo Rename to "add" after deprecating the current "add" implementation
     */
    public function idempotentAdd(LinkInterface $link): void
    {
        $existingRels = array_keys(array: $this->links);
        $linkRels = $link->getRels();

        // update existing rels
        $intersection = array_intersect($linkRels, $existingRels);
        foreach ($intersection as $relation) {
            $relationLinks = $this->links[$relation];
            if (!is_array(value: $relationLinks)) {
                $relationLinks = [$relationLinks];
            }

            if (!in_array(needle: $link, haystack: $relationLinks, strict: true)) {
                $relationLinks[] = $link;
                $this->links[$relation] = $relationLinks; // inside the if, otherwise it's not really idempotent
            }
        }

        // add missing rels
        $diff = array_diff($linkRels, $existingRels);
        foreach ($diff as $relation) {
            $this->links[$relation] = $link;
        }
    }

    /**
     * Retrieve a link relation
     *
     * @return LinkInterface|Link|array<mixed>|null
     */
    public function get(string $relation): Link|array|LinkInterface|null
    {
        if (!$this->has(relation: $relation)) {
            return null;
        }

        return $this->links[$relation];
    }

    /**
     * Does a given link relation exist?
     */
    public function has(string $relation): bool
    {
        return array_key_exists(key: $relation, array: $this->links);
    }

    /**
     * Remove a given link relation
     */
    public function remove(string $relation): bool
    {
        if (!$this->has(relation: $relation)) {
            return false;
        }

        unset($this->links[$relation]);
        return true;
    }
}
