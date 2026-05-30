<?php

declare(strict_types=1);

namespace App\Search\Application\Service;

final class ProductPayloadBuilder
{
    public function build(
        string $name,
        string $category,
        string $brand,
        int $priceAmount,
        string $priceCurrency,
        int $stock,
        array $attributes,
        string $enrichedText = '',
    ): array {
        $payload = [
            'name'           => $name,
            'category'       => $category,
            'brand'          => $brand,
            'price_amount'   => $priceAmount,
            'price_currency' => $priceCurrency,
            'in_stock'       => $stock > 0,
            'stock_quantity' => $stock,
            'enriched_text'  => $enrichedText,
        ];

        foreach ($attributes as $key => $value) {
            $payload[$key] = $value;
        }

        return $payload;
    }
}
