<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

use App\Http\Controllers\AuthController;
Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'authenticate']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/api/dashboard/data', [DashboardController::class, 'apiData']);
    Route::post('/api/dashboard/jobs/{id}/cancel', [DashboardController::class, 'cancelJob']);
    Route::post('/api/dashboard/jobs/{id}/delete', [DashboardController::class, 'deleteJob']);
    Route::post('/api/dashboard/reset-cooldowns', [DashboardController::class, 'resetCooldowns']);
    Route::get('/api/dashboard/settings', [DashboardController::class, 'getSettings']);
    Route::post('/api/dashboard/settings', [DashboardController::class, 'updateSettings']);
});
