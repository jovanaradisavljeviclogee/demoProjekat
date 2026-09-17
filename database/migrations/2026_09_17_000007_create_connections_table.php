<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->string('pspid')->nullable();
            $table->string('api_key')->nullable();
            $table->string('api_secret')->nullable();
            $table->enum('environment', ['test', 'live'])->default('test');
            $table->boolean('is_connected')->default(false);
            $table->timestamp('verified_at')->nullable();
        });

        // Tabela drzi tacno jedan red i taj red kreira migracija, prazan.
        // Aplikacija ga samo azurira - nema create ni delete putanje.
        //
        // Upis ide kroz DB::table(), ne kroz App\Models\Connection: migracija je
        // istorijski zapis sheme i ne sme da zavisi od klase koja se u buducnosti
        // moze preimenovati ili obrisati.
        DB::table('connections')->insert(['environment' => 'test']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
