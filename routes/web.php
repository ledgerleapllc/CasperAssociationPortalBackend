<?php

use App\Http\Controllers\Api\V1\InstallController;
use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('install', [InstallController::class, 'install']);
Route::get('/install-emailer', [InstallController::class, 'installEmailer']);
Route::get('test-hash', [AuthController::class, 'testHash']);