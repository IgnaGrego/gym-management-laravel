<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Actions\RegisterPayment;
use App\Filament\Resources\PaymentResource;
use App\Models\Payment;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    /**
     * Delegate the whole create to the RegisterPayment Action (SPEC-005
     * FR-004, FR-006).
     *
     * The Action authorizes (BR-011), re-validates full-payment equality and
     * the BR-007 field rules (ERR-001..ERR-005, ERR-010, ERR-011), persists
     * the confirmed payment with recorded_by, marks the cuota paid and invokes
     * Membership::activate() for a pending membership (FR-006, BR-008).
     */
    protected function handleRecordCreation(array $data): Payment
    {
        return app(RegisterPayment::class)->handle(
            (int) $data['cuota_id'],
            (string) $data['amount'],
            (string) $data['method'],
            (string) $data['payment_date'],
            $data['reference'] ?? null,
            $data['notes'] ?? null,
        );
    }
}
