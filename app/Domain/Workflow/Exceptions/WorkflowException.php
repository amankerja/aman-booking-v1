<?php

namespace App\Domain\Workflow\Exceptions;

use Exception;

class WorkflowException extends Exception
{
    public static function maxDepthExceeded(int $depth): self
    {
        return new self("Batas kedalaman eksekusi alur terlampaui (maksimal {$depth} langkah). Terdeteksi potensi loop berulang.");
    }

    public static function infiniteLoopDetected(string $nodeId): self
    {
        return new self("Terdeteksi perulangan siklus tak terbatas pada simpul '{$nodeId}'.");
    }

    public static function nodeNotFound(string $nodeId): self
    {
        return new self("Simpul alur kerja dengan ID '{$nodeId}' tidak ditemukan dalam graf versi aktif.");
    }

    public static function executionFailed(string $message): self
    {
        return new self("Eksekusi alur kerja gagal: {$message}");
    }
}
