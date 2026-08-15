<?php

namespace App\Actions;

use App\Models\Cuota;
use App\Models\Membership;
use App\Models\Payment;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RegisterPayment
{
    /**
     * Register a manual payment against a pending cuota (SPEC-005 FR-004,
     * FR-006; BR-004, BR-005, BR-007, BR-008, BR-014).
     *
     * Full payment only (NC-01, BR-014): the amount must equal the cuota
     * amount; the single matching confirmed payment satisfies the cuota
     * (BR-003) and, when the cuota's membership is `pending`, invokes the
     * SPEC-004 activation contract Membership::activate() (FR-006, BR-008).
     * A late payment on an expired membership (or a pending membership whose
     * end date has passed) never reactivates it: the activate() DomainException
     * is swallowed and the payment + paid cuota stand (AF-005, ERR-009,
     * AM-10, NC-04).
     *
     * @param  int  $cuotaId  the pending cuota id
     * @param  string  $amount  the amount received (must equal the cuota amount)
     * @param  string  $method  cash | transfer (BR-004)
     * @param  string  $paymentDate  a date not in the future (PY-03)
     * @param  string|null  $reference  required for transfers (PY-04)
     * @param  string|null  $notes  optional free text
     *
     * @throws ValidationException when the cuota is missing/non-payable or any
     *                             BR-007 field rule is violated (ERR-001..005,
     *                             ERR-010, ERR-011)
     */
    public function handle(
        int $cuotaId,
        string $amount,
        string $method,
        string $paymentDate,
        ?string $reference = null,
        ?string $notes = null,
    ): Payment {
        $this->authorize();

        $cuota = Cuota::find($cuotaId);

        $this->validate($cuota, $amount, $method, $paymentDate, $reference, $notes);

        return DB::transaction(function () use ($cuota, $amount, $method, $paymentDate, $reference, $notes): Payment {
            $payment = new Payment([
                'cuota_id' => $cuota->id,
                'amount' => $amount,
                'method' => $method,
                'payment_date' => $paymentDate,
                'reference' => $reference,
                'notes' => $notes,
            ]);

            // recorded_by is never mass-assigned (PY-06): it is set to the
            // authenticated staff User here (the Action runs in an
            // authenticated ADMIN/TRAINER context via the Filament form).
            $payment->recorded_by = auth()->id();
            $payment->save();

            $cuota->markPaid();

            $membership = $cuota->membership;

            if ($membership !== null && $membership->status === Membership::STATUS_PENDING) {
                try {
                    $membership->activate();
                } catch (DomainException) {
                    // AF-005, ERR-009, AM-10: a late payment on a membership
                    // whose end date has passed cannot activate it. The
                    // payment and the paid cuota stand; the expired membership
                    // is never reactivated.
                }
            }

            return $payment;
        });
    }

    /**
     * Server-side enforcement (SPEC-005 BR-011, §9; AGENTS.md §17): only
     * ADMIN/TRAINER may register a payment (D-15 option 1). The Filament page
     * is already gated by the panel; this is defense in depth so the Action
     * stays safe outside the UI.
     */
    protected function authorize(): void
    {
        Gate::authorize('create', Payment::class);
    }

    /**
     * Validate payment input (SPEC-005 ERR-001..ERR-005, ERR-010, ERR-011;
     * NC-01, BR-004, BR-007, BR-014).
     */
    protected function validate(?Cuota $cuota, string $amount, string $method, string $paymentDate, ?string $reference, ?string $notes): void
    {
        if ($cuota === null) {
            throw ValidationException::withMessages([
                'cuota_id' => 'The selected cuota does not exist.',
            ]);
        }

        if ($cuota->status !== Cuota::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'cuota_id' => 'Only a pending cuota can be paid.',
            ]);
        }

        Validator::make([
            'amount' => $amount,
            'method' => $method,
            'reference' => $reference,
            'payment_date' => $paymentDate,
            'notes' => $notes,
        ], [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:'.Payment::METHOD_CASH.','.Payment::METHOD_TRANSFER],
            'reference' => ['nullable', 'string', 'required_if:method,'.Payment::METHOD_TRANSFER],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        // NC-01, BR-014, ERR-010: full payment only. Compare normalized
        // two-decimal strings to avoid floating-point drift (ADR-003).
        if (number_format((float) $amount, 2, '.', '') !== number_format((float) $cuota->amount, 2, '.', '')) {
            throw ValidationException::withMessages([
                'amount' => 'The payment amount must equal the cuota amount (full payment only).',
            ]);
        }
    }
}
