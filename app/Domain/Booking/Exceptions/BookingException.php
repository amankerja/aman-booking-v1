<?php

namespace App\Domain\Booking\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BookingException extends Exception
{
    protected string $errorCode;

    protected string $messageUser;

    protected ?string $messageDev;

    protected int $statusCode;

    public function __construct(
        string $errorCode,
        string $messageUser,
        int $statusCode = 422,
        ?string $messageDev = null,
        ?Exception $previous = null
    ) {
        $this->errorCode = $errorCode;
        $this->messageUser = $messageUser;
        $this->messageDev = $messageDev;
        $this->statusCode = $statusCode;

        parent::__construct($messageUser, $statusCode, $previous);
    }

    public static function slotTaken(?string $devMessage = null): self
    {
        return new self(
            'SLOT_TAKEN',
            'Slot tersebut baru saja dipesan customer lain. Silakan pilih waktu lain.',
            409,
            $devMessage
        );
    }

    public static function capacityFull(?string $devMessage = null): self
    {
        return new self(
            'CAPACITY_FULL',
            'Kuota untuk jadwal ini sudah penuh. Anda dapat bergabung ke daftar tunggu bila tersedia.',
            409,
            $devMessage
        );
    }

    public static function outsideBusinessHours(?string $devMessage = null): self
    {
        return new self(
            'OUTSIDE_BUSINESS_HOURS',
            'Waktu yang dipilih di luar jam operasional.',
            422,
            $devMessage
        );
    }

    public static function resourceUnavailable(?string $devMessage = null): self
    {
        return new self(
            'RESOURCE_UNAVAILABLE_FOR_FULL_DURATION',
            'Terapis/ruangan tidak tersedia untuk durasi penuh pada waktu tersebut.',
            422,
            $devMessage
        );
    }

    public static function minAdvanceNotMet(?string $devMessage = null): self
    {
        return new self(
            'MIN_ADVANCE_NOT_MET',
            'Booking minimal dilakukan sekian jam sebelum jadwal.',
            422,
            $devMessage
        );
    }

    public static function beyondHorizon(?string $devMessage = null): self
    {
        return new self(
            'BEYOND_BOOKING_HORIZON',
            'Jadwal yang dipilih terlalu jauh dari hari ini.',
            422,
            $devMessage
        );
    }

    public static function invalidTransition(string $from, string $to, ?string $devMessage = null): self
    {
        $dev = $devMessage ?? "Cannot transition booking from {$from} to {$to}";

        return new self(
            'INVALID_TRANSITION',
            'Status booking tidak dapat diubah ke tahap tersebut.',
            422,
            $dev
        );
    }

    public static function tenantUnavailable(?string $devMessage = null): self
    {
        return new self(
            'TENANT_UNAVAILABLE',
            'Layanan booking bisnis ini sedang tidak tersedia.',
            503,
            $devMessage
        );
    }

    public static function holdExpired(?string $devMessage = null): self
    {
        return new self(
            'HOLD_EXPIRED',
            'Waktu penahanan slot sudah habis. Silakan pilih jadwal kembali.',
            410,
            $devMessage
        );
    }

    public static function validationFailed(string $devMessage, ?string $userMessage = null): self
    {
        return new self(
            'VALIDATION_FAILED',
            $userMessage ?? 'Periksa kembali data yang ditandai pada formulir.',
            422,
            $devMessage
        );
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getMessageUser(): string
    {
        return $this->messageUser;
    }

    public function getMessageDev(): ?string
    {
        return $this->messageDev;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function render(Request $request): JsonResponse
    {
        $correlationId = $request->header('X-Correlation-Id') ?? (string) Str::uuid();

        return response()->json([
            'code' => $this->errorCode,
            'message_user' => $this->messageUser,
            'message_dev' => $this->messageDev ?? $this->messageUser,
            'correlation_id' => $correlationId,
        ], $this->statusCode);
    }
}
