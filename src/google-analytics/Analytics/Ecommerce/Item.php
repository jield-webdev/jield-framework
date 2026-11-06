<?php

declare(strict_types=1);

namespace Jield\GoogleAnalytics\Analytics\Ecommerce;

class Item
{
    public function __construct(
        protected int $sku,
        protected float $price,
        protected ?int $quantity = null,
        protected ?string $product = null,
        protected ?string $category = null,
    ) {}

    public function getSku(): int
    {
        return $this->sku;
    }

    public function setSku(int $sku): Item
    {
        $this->sku = $sku;
        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): Item
    {
        $this->price = $price;
        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): Item
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getProduct(): ?string
    {
        return $this->product;
    }

    public function setProduct(?string $product): Item
    {
        $this->product = $product;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): Item
    {
        $this->category = $category;
        return $this;
    }
}
