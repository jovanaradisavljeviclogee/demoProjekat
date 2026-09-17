<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jedini red konfiguracije veze ka provajderu.
 *
 * Tabela `connections` drzi tacno jedan red i taj red kreira migracija
 * 2026_09_17_000007_create_connections_table.php. Aplikacija ga samo azurira:
 * u ovoj klasi namerno nema create(), firstOrCreate(), updateOrCreate() ni
 * delete() putanje.
 *
 * api_secret stoji u $hidden, pa ga toArray() i toJson() izostavljaju - nikad
 * ne stize do klijenta ni u API odgovoru ni kroz prosledjivanje modela u view.
 * $hidden pokriva samo serijalizaciju: direktno citanje atributa i dalje radi,
 * pa ga niko ne sme ispisati u sablonu, logu ni poruci izuzetka.
 */
class Connection extends Model
{
    /**
     * Tabela nema timestamp kolone.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'api_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_connected' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Jedini red iz `connections`.
     *
     * Vraca null kada reda nema. To nije stanje koje treba zakrpiti nego
     * pokvarena migracija, i mora da ostane vidljivo. Namerno se ne koristi
     * firstOrCreate(): on bi tiho napravio drugi red sa praznim kredencijalima
     * i time istovremeno sakrio kvar i prekrsio invarijantu o jednom redu.
     */
    public static function current(): ?self
    {
        return static::query()->first();
    }
}
