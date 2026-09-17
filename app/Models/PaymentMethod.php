<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Payment metoda iz tabele `payment_methods`.
 *
 * Tabela nema timestamp kolone, kao ni obe pivot tabele koje je vezuju za
 * `currencies` i `countries`. Pivot tabele nemaju ni sopstveni `id` - kljuc je
 * slozen, nad parom stranih kljuceva, sto usput sprecava duplu vezu iste
 * metode i iste valute odnosno zemlje.
 */
class PaymentMethod extends Model
{
    /**
     * Tabela nema timestamp kolone.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'type',
        'is_active',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Valute koje ova metoda podrzava.
     *
     * Ime pivot tabele se navodi eksplicitno: Laravel bi po konvenciji trazio
     * `currency_payment_method` (abecedni redosled), a tabela se zove
     * `payment_method_currency`. Bez drugog argumenta relacija puca.
     *
     * @return BelongsToMany<Currency, $this>
     */
    public function currencies(): BelongsToMany
    {
        return $this->belongsToMany(Currency::class, 'payment_method_currency');
    }

    /**
     * Zemlje u kojima je ova metoda dostupna.
     *
     * Ime pivot tabele je eksplicitno iz istog razloga kao kod `currencies()`:
     * konvencija bi dala `country_payment_method`, a tabela je
     * `payment_method_country`.
     *
     * @return BelongsToMany<Country, $this>
     */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'payment_method_country');
    }
}
