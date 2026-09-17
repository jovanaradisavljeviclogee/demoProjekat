<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Par kljuc-vrednost iz tabele `settings`.
 *
 * Kolona `key` je rezervisana rec u MySQL-u (RK9). Eloquent i query builder
 * citiraju identifikatore backtick-ovima sami, pa `where('key', ...)` radi bez
 * dodatnog posla. Rizik postoji samo za rucno pisan sirov SQL - `DB::statement`,
 * `DB::raw`, `whereRaw` - gde se kolona mora pisati kao `` `key` ``; bez toga
 * MySQL vraca sintaksnu gresku.
 *
 * Podrazumevane vrednosti kljuceva upisace seeder, koji jos ne postoji. Dok ga
 * nema, fallback daje pozivalac kroz drugi argument metode `get()`.
 */
class Setting extends Model
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
        'key',
        'value',
    ];

    /**
     * Vraca vrednost za dati kljuc, ili `$default` ako kljuc ne postoji.
     *
     * Upit ide po unique indeksu nad `key` - tabela se nikad ne ucitava cela.
     *
     * Pazi: ova metoda zaklanja Eloquent-ov staticki `Model::get()`. Za sve
     * redove koristi `Setting::all()` ili `Setting::query()->get()`.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    /**
     * Upisuje vrednost za dati kljuc, kreirajuci red ako ga jos nema.
     */
    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}
