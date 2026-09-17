<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jedan zapis iz tabele `logs`.
 *
 * Tabela nema `updated_at` - log se upisuje jednom i vise se ne menja - pa
 * `const UPDATED_AT = null` sprecava Eloquent da pise kolonu koje nema.
 *
 * Sudar imena: ova klasa je `App\Models\Log`, a Laravel isporucuje i
 * `Illuminate\Support\Facades\Log`, globalno aliasovanu kao `\Log`. Fajl kome
 * trebaju oba simbola pise `use App\Models\Log;` i fasadu zove kao
 * `\Log::info(...)` ili, bolje, kroz `logger()` helper koji sudara nema. Bez
 * toga `Log::info(...)` tiho pogadja ovaj model i puca tek pri pozivu. Puna
 * odluka: docs/decisions/ADR-15-model-log-zadrzava-ime.md.
 *
 * `context` nikad ne sme da sadrzi kredencijale: ni `api_key`, ni `api_secret`,
 * ni lozinku, ni sirov payload zahteva koji ih nosi. Vrednosti se maskiraju pre
 * upisa - invarijanta 8 iz data.md. Model to ne proverava: izvrsni filter nije u
 * opsegu ove iteracije jer jos ne postoji kod koji pise logove, pa pravilo za
 * sada stoji samo zapisano (rizik RK7).
 *
 * Kad se RK7 bude zatvarao, maskiranje ide na *jedno* mesto kroz koje prolazi
 * svaki upis - mutator nad `context`, custom cast ili jedna write metoda - a ne
 * u svako pozivno mesto. Spisak kljuceva koji se maskiraju je pravilo koje se
 * menja; ponovljen na N mesta, dovoljno je da ga jedno novo pozivno mesto
 * preskoci pa da invarijanta padne, i to tiho.
 */
class Log extends Model
{
    /**
     * Tabela nema `updated_at` kolonu, pa Eloquent ne sme da je pise.
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'level',
        'message',
        'context',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    /**
     * Korisnik na koga se zapis odnosi, ako ga ima.
     *
     * Vraca `null` kad je `user_id` null i kad je korisnik u medjuvremenu
     * obrisan - strani kljuc tada ponistava vezu umesto da obrise log
     * (invarijanta 6). `user_id` je nullable namerno: neuspesna prijava se
     * upisuje pre nego sto je iko autentifikovan.
     *
     * Zato kod koji cita log mora da podnese odsustvo korisnika. Nikad
     * `$log->user->name` bez provere - uvek provera (`$log->user?->name`) ili
     * fallback.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
