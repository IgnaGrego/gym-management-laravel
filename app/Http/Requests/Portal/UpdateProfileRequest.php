<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * The controller authorizes ClientPolicy::update on the resolved own
     * Client record; this request only whitelists the editable fields.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The final CLIENT-editable contact set (SPEC-013 FR-011, BR-008, NC-01,
     * CP-01): only email / phone / emergency_contact are accepted.
     *
     * full_name, dni (identity, SPEC-002 BR-005), status (SPEC-012) and the
     * health notes (injuries_notes / medical_conditions_notes, NC-02) are NOT
     * present here, so they can never be changed through the portal even
     * though they are fillable on Client (ERR-011).
     *
     * The email is format-validated only (max:255) and is NOT unique-validated:
     * clients.email is independent of the login users.email (SPEC-002 OQ-07).
     * The phone keeps the exact SPEC-002 constraint (string + max:255) — no
     * regex is invented (SPEC-002 ERR-006).
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
        ];
    }
}
