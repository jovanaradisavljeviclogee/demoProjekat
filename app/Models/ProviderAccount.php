<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Nalog koji provajder smatra validnim.
 *
 * Tabelu `provider_accounts` puni seeder i ona je read-only za aplikaciju
 * (invarijanta 3 iz data.md). Zato ova klasa namerno nema nijednu listu za
 * mass assignment - ni dozvoljenu (fillable) ni zabranjenu (guarded). Bez
 * dozvoljene liste Eloquent mass assignment odbija sam po sebi, a pisanje
 * zabranjene bi znacilo da je upis predvidjen. Nije: nijedan poziv u `app/`
 * ne sme da kreira, azurira ni brise red ove tabele.
 *
 * Pristup ide iskljucivo kroz App\Repositories\ProviderAccountRepository.
 * Razlog je faza 2 (ADR-14): tada tabela izlazi iz ove baze u zaseban provider
 * servis, repozitorijum menja telo metoda a potpisi i pozivaoci ostaju. Upiti
 * razasuti po kodu bi tu promenu pretvorili u prepisivanje, pa je pravilo
 * merljivo (N11): svaki upit nad `provider_accounts` stoji u tacno jednom
 * fajlu, u repozitorijumu. Ova klasa je samo Eloquent mapiranje tabele i sama
 * ne pokrece nijedan upit.
 *
 * `api_key` i `api_secret` stoje u `$hidden` i nikad se ne serijalizuju ka
 * klijentu; ovo su tudje tajne, ne podaci prodavca.
 */
class ProviderAccount extends Model
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
        'api_key',
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
            'is_active' => 'boolean',
        ];
    }
}
