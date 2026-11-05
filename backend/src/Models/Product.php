<?php

namespace Models;

use JsonSerializable;

class Product implements JsonSerializable {
    // promoción de propiedades de PHP 8 (declara y asigna en el propio constructor)
    public function __construct(
        public ?int $id,
        public string $name,
        public string $brand,
        public ?string $description,
        public float $price,
        public float $size,
        public int $stock,
        public ?string $imageUrl,
        public ?string $createdAt = null
    ) {}

    /**
     * Define cómo se serializa este objeto a JSON.
     */
    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'brand' => $this->brand,
            'description' => $this->description,
            'price' => $this->price,
            'size' => $this->size,
            'stock' => $this->stock,
            'image_url' => $this->imageUrl,
            'created_at' => $this->createdAt
        ];
    }
}