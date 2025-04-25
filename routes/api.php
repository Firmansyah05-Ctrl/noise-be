<?php

use App\Http\Controllers\Api\LaeqController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::resource("/laeq", LaeqController::class);
Route::get('/laeq', [LaeqController::class, 'index']);