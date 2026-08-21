<?php

namespace App\Controllers;

/**
 * Products Controller
 *
 * Handles product listing and single product display
 * Uses Aimeos headless e-commerce JSON API
 *
 * @package App\Controllers
 */
class Products extends WebController
{
    /**
     * Display all products in grid format
     *
     * @return string
     */
    public function index(): string
    {
        // Fetch products from Aimeos
        $products = $this->aimeos->getProductsCached();

        $data = [
            'title' => 'Products',
            'products' => $products,
        ];

        return $this->render('products.index', $data);
    }

    /**
     * Display a single product
     *
     * @param string $id Product ID
     * @return string
     */
    public function show(string $id): string
    {
        // Fetch single product by ID
        $product = $this->aimeos->getProductCached($id);

        // Check if product exists
        if ($product === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Product not found');
        }

        $data = [
            'title' => $product['label'] ?? 'Product',
            'product' => $product,
        ];

        return $this->render('products.show', $data);
    }
}
