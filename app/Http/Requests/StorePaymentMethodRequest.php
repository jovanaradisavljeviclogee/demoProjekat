<?php

namespace App\Http\Requests;

/**
 * Kreiranje: nijedna postojeća metoda se ne izuzima iz provere jedinstvenosti koda.
 */
class StorePaymentMethodRequest extends PaymentMethodRequest
{
    protected function ignoredId(): ?int
    {
        return null;
    }
}
