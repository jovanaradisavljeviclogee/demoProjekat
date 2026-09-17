<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Jedino pravilo prekidača iz PM-FR-13: telo mora nositi `active` kao boolean.
 *
 * Bez ovoga `$request->boolean('active')` čita izostanak polja i svaku neprepoznatu
 * vrednost kao `false`, pa pokvaren ili prazan zahtev **gasi** metodu i vraća `200`.
 * Prekidač koji na besmislen ulaz odgovara isključivanjem je gori od prekidača koji
 * odbije zahtev, jer poziv izgleda kao da je uspeo.
 *
 * Validacija stoji u Form Requestu, kao i za formu (PM-FR-50).
 */
class ToggleActiveRequest extends FormRequest
{
    /** Autorizacija administratora je van opsega ovog tiketa (spec 2.2). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
        ];
    }
}
