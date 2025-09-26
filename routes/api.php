<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BlastController;

Route::post('/blasts', [BlastController::class, 'store']);
