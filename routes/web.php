<?php

use App\Http\Controllers\Admin\PaymentMethodController;
use Illuminate\Support\Facades\Route;
use Domain\Hello;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/hello', function () {
    return 'Hello world';
});

Route::get('/hello2', function () {
    $hello = new Hello();
    return $hello->message();
});

/*
 * JSON rute stoje u `web.php`, a ne u `api.php`, zbog sesije i CSRF-a (ADR-17, PM-N-03).
 *
 * Registruju se PRE shell rute (PM-R-01). Pri današnjim prefiksima `{any?}` ionako ne
 * može da uhvati `/admin/api/...`, ali čim se prefiksi preklope prvi upisani obrazac
 * pobeđuje — a tada bi svaki JSON poziv tiho vratio HTML. Redosled je zato ovde jedina
 * zaštita i ne sme se menjati.
 */
Route::controller(PaymentMethodController::class)
    ->prefix('admin/api/payment-methods')
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show')->whereNumber('id');
        Route::post('/', 'store');
        Route::put('/{id}', 'update')->whereNumber('id');
        Route::patch('/{id}/active', 'toggleActive')->whereNumber('id');
        Route::delete('/{id}', 'destroy')->whereNumber('id');
    });

// Jedan Blade shell za sva tri ekrana; `.*` hvata i deep link i osvežavanje (PM-FR-82).
Route::get('/admin/payment-methods/{any?}', function () {
    return view('admin.payment-methods');
})->where('any', '.*');
