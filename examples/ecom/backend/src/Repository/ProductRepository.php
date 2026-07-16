<?php

namespace Backend\Repository;

use Backend\Database;

final class ProductRepository
{
    public function __construct()
    {
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        Database::connection()->executeStatement(
            'CREATE TABLE IF NOT EXISTS products ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'name TEXT NOT NULL, '
            . 'price_cents INTEGER NOT NULL, '
            . 'description TEXT NOT NULL, '
            . 'image_url TEXT NOT NULL, '
            . 'stock INTEGER NOT NULL'
            . ')',
        );

        $count = (int) Database::connection()->fetchOne('SELECT COUNT(*) FROM products');
        if ($count === 0) {
            $this->seed();
        }
    }

    private function seed(): void
    {
        $products = [
            ['name' => 'Casque sans fil', 'price_cents' => 8990, 'description' => 'Réduction de bruit active, 30h d\'autonomie.', 'image_url' => 'https://picsum.photos/seed/headphones/600/600', 'stock' => 12],
            ['name' => 'Montre connectée', 'price_cents' => 14900, 'description' => 'Suivi santé, GPS intégré, étanche.', 'image_url' => 'https://picsum.photos/seed/watch/600/600', 'stock' => 7],
            ['name' => 'Enceinte portable', 'price_cents' => 5990, 'description' => 'Son 360°, résistante à l\'eau IPX7.', 'image_url' => 'https://picsum.photos/seed/speaker/600/600', 'stock' => 20],
            ['name' => 'Clavier mécanique', 'price_cents' => 11900, 'description' => 'Switches rouges, rétroéclairage RGB.', 'image_url' => 'https://picsum.photos/seed/keyboard/600/600', 'stock' => 5],
            ['name' => 'Sac à dos urbain', 'price_cents' => 6490, 'description' => 'Compartiment laptop 15", imperméable.', 'image_url' => 'https://picsum.photos/seed/backpack/600/600', 'stock' => 15],
            ['name' => 'Lampe de bureau LED', 'price_cents' => 3490, 'description' => 'Luminosité réglable, port USB-C.', 'image_url' => 'https://picsum.photos/seed/lamp/600/600', 'stock' => 30],
        ];

        foreach ($products as $product) {
            Database::connection()->insert('products', $product);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        return Database::connection()->fetchAllAssociative('SELECT * FROM products ORDER BY id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $row = Database::connection()->fetchAssociative('SELECT * FROM products WHERE id = ?', [$id]);

        return $row === false ? null : $row;
    }
}
