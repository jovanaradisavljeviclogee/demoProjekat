<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->id();

            // Veza ka korisniku je opciona i ponistava se na brisanju naloga
            // (invarijanta 6): log nadzivljava korisnika o kome govori.
            //
            // nullOnDelete() je ogranicenje same baze, ne Eloquent observer.
            // Observer se aktivira samo kad brisanje ide kroz model i tiho se
            // zaobilazi TRUNCATE-om ili DELETE-om iz mysql klijenta; strani
            // kljuc vazi za svaki od tih puteva.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // `level` je string, ne enum. data.md u koloni `tip` propisuje
            // varchar, a cetiri nivoa (debug, info, warning, error) nabraja u
            // koloni `pravila`. Prati se navedeni tip, ne nabrajanje: odluka D8
            // u design.md, otvoreno pitanje Q1 za narucioca.
            $table->string('level');
            $table->string('message');
            $table->json('context')->nullable();

            // Log se upisuje jednom i vise se ne menja, pa nosi samo vreme
            // nastanka. Zato nema helper-a koji pravi obe timestamp kolone -
            // kolona za izmenu ne postoji (invarijanta 9 iz data.md).
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
