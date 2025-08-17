<?php

use App\Http\Controllers\MigrateController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MigrateController::class, 'index'])->name('home');
Route::post('/', [MigrateController::class, 'import'])->name('import');
