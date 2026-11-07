<?php

declare(strict_types=1);

namespace JieldTest\Doctrine\TestAsset;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class PaginationEntity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    public function __construct(#[ORM\Column] public string $name)
    {
    }
}
