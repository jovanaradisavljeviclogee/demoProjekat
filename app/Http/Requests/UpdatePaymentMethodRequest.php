<?php

namespace App\Http\Requests;

/**
 * Izmena: metoda koja se menja se izuzima, inače ne bi mogla da sačuva sopstveni kod
 * (PM-FR-52, PM-AC-10).
 */
class UpdatePaymentMethodRequest extends PaymentMethodRequest
{
    protected function ignoredId(): ?int
    {
        return (int) $this->route('id');
    }
}
