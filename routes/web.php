<?php

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
