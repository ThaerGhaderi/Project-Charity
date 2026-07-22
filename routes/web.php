<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
    
});
Route::get('/payment/success', function () {
    return view('payment.success');
})->name('payment.success');

Route::get('/payment/cancel', function () {
    return view('payment.cancel');
})->name('payment.cancel');