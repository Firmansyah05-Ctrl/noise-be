<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\LaeqDataController;
use App\Http\Controllers\Api\LaeqLminLmaxController;
use App\Http\Controllers\Api\LaeqMetricsController;
use App\Http\Controllers\Api\MqttStatusController;
use App\Http\Controllers\Api\LaeqController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LaeqHourlyController;
use App\Http\Controllers\Api\ExportController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/laeq-data', [LaeqDataController::class, 'index']);
Route::get('/laeq-lmin-lmax', [LaeqLminLmaxController::class, 'index']);
Route::get('/laeq-metrics', [LaeqMetricsController::class, 'index']);
Route::get('/mqtt-status', [MqttStatusController::class, 'index']);
Route::get('/laeq', [LaeqController::class, 'index']);
Route::get('/dashboard-summary', [DashboardController::class, 'index']);
Route::get('/laeq-hourly', [LaeqHourlyController::class, 'index']);

Route::prefix('export')->group(function () {
    Route::get('/', [ExportController::class, 'index']);
    Route::get('/export', [ExportController::class, 'export']);
});
