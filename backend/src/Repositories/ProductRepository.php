<?php

namespace Repositories;

use PDO;
use Models\Product;

class ProductRepository {
    
    public function __construct(
        private PDO $db
    ) {}

    /**
     * Obtiene todos los productos del catálogo.
     * 
     * @return Product[] Array de objetos Product
     */
    public function findAll(): array {
        // Consulta preparada por seguridad
        $stmt = $this->db->prepare("SELECT * FROM products ORDER BY id ASC");
        $stmt->execute();
        
        $products = [];
        while ($row = $stmt->fetch()) {
            $products[] = $this->mapRowToProduct($row);
        }
        
        return $products;
    }

    /**
     * Obtiene un producto específico por su ID.
     * 
     * @param int $id
     * @return Product|null Devuelve el producto o null si no existe
     */
    public function findById(int $id): ?Product {
        // Consulta parametrizada obligatoria para prevenir Inyección SQL
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute(['id' => $id]);
        
        $row = $stmt->fetch();
        $product = null;

        if ($row) {
            $product = $this->mapRowToProduct($row);
        }
        
        return $product;
    }

    /**
     * Función auxiliar para convertir una fila de PostgreSQL en un objeto Product.
     */
    private function mapRowToProduct(array $row): Product {
        return new Product(
            $row['id'],
            $row['name'],
            $row['brand'],
            $row['description'],
            (float) $row['price'],
            (float) $row['size'],
            (int) $row['stock'],
            $row['image_url'],
            $row['created_at']
        );
    }
}