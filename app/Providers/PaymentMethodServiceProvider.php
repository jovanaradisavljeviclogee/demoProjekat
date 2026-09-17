<?php

namespace App\Providers;

use App\Repositories\ArrayPaymentMethodRepository;
use App\Repositories\PaymentMethodRepositoryInterface;
use Illuminate\Support\ServiceProvider;

/**
 * Vezuje ugovor za njegovu jedinu implementaciju (PM-FR-05).
 *
 * Zaseban provider, a ne `AppServiceProvider`: kad statički niz zameni baza,
 * izmena je jedna linija u fajlu koji postoji isključivo zbog ovog vezivanja.
 */
class PaymentMethodServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PaymentMethodRepositoryInterface::class,
            ArrayPaymentMethodRepository::class,
        );
    }
}
