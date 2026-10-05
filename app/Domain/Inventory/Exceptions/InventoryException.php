<?php

namespace App\Domain\Inventory\Exceptions;

use Exception;

class InventoryException extends Exception
{
    public static function insufficientStock(string $itemName, int $available, int $needed): self
    {
        return new self("Stok item '{$itemName}' tidak mencukupi. Tersedia: {$available}, dibutuhkan: {$needed}.");
    }

    public static function moduleDisabled(): self
    {
        return new self('Modul inventori belum diaktifkan pada pengaturan bisnis Anda.');
    }

    public static function itemNotFound(): self
    {
        return new self('Item inventori tidak ditemukan.');
    }

    public static function invalidQuantity(): self
    {
        return new self('Jumlah mutasi stok harus lebih besar dari 0.');
    }

    public static function negativeStockNotAllowed(string $itemName, int $resultingStock): self
    {
        return new self("Stok item '{$itemName}' tidak boleh negatif (Hasil: {$resultingStock}).");
    }
}
