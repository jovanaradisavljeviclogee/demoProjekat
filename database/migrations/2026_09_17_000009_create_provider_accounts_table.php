<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `provider_accounts` drzi kredencijale koje provajder smatra validnim. To
     * nisu podaci prodavca: tabelu puni seeder, a aplikacija je samo cita
     * (invarijanta 3 iz data.md). Zato nema ni jedne kolone viska - bez
     * timestamps(), bez stranih kljuceva, bez veze ka `connections`.
     *
     * Odvojenost od `connections` je namerna i opisana u ADR-14: u fazi 2 ova
     * tabela izlazi iz baze u zaseban provider servis.
     */
    public function up(): void
    {
        Schema::create('provider_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('pspid');
            $table->string('api_key');
            $table->string('api_secret');
            $table->enum('environment', ['test', 'live']);
            $table->boolean('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_accounts');
    }
};
