<?php

namespace App\Services;

use App\Models\Product;

class ProductUnitConverter
{
    /**
     * Convert quantity to base unit (eceran)
     */
    public function convertToEceran(float|int|string $quantity, int|string|null $unitId, Product $product): float|int
    {
        if ($unitId == $product->unit_dus) {
            return $quantity * $product->dus_to_eceran;
        } elseif ($unitId == $product->unit_pak) {
            return $quantity * $product->pak_to_eceran;
        } elseif ($unitId == $product->unit_eceran) {
            return $quantity;
        }

        return 0;
    }

    /**
     * Convert from base unit (eceran) to specific unit
     */
    public function convertFromEceran(float|int|string $quantityEceran, int|string|null $unitId, Product $product): float|int
    {
        if ($unitId == $product->unit_dus && $product->dus_to_eceran > 0) {
            return $quantityEceran / $product->dus_to_eceran;
        } elseif ($unitId == $product->unit_pak && $product->pak_to_eceran > 0) {
            return $quantityEceran / $product->pak_to_eceran;
        } elseif ($unitId == $product->unit_eceran) {
            return $quantityEceran;
        }

        return 0;
    }

    /**
     * Convert price to base unit (eceran) price
     */
    public function convertPriceToEceran(float|int|string $price, int|string|null $unitId, Product $product): float|int
    {
        if ($unitId == $product->unit_dus) {
            return $price / $product->dus_to_eceran;
        } elseif ($unitId == $product->unit_pak) {
            return $price / $product->pak_to_eceran;
        } elseif ($unitId == $product->unit_eceran) {
            return $price;
        }

        return 0;
    }

    /**
     * Convert price from base unit (eceran) to specific unit
     */
    public function convertPriceFromEceran(float|int|string $eceranPrice, int|string|null $unitId, Product $product): float|int
    {
        if ($unitId == $product->unit_dus) {
            return $eceranPrice * $product->dus_to_eceran;
        } elseif ($unitId == $product->unit_pak) {
            return $eceranPrice * $product->pak_to_eceran;
        } elseif ($unitId == $product->unit_eceran) {
            return $eceranPrice;
        }

        return 0;
    }
}
