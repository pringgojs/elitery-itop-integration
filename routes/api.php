<?php

use App\Http\Controllers\Api\V1\ItopEliteryReciverController;
use App\Http\Controllers\Api\V1\ItopExternalReciverController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    // endpoint for itop elitery
    Route::post('ticket-update-private-log', [ItopEliteryReciverController::class, 'ticketUpdatePrivateLog']);
    Route::post('ticket-state-change', [ItopEliteryReciverController::class, 'ticketStateChange']);
    
    // endpoint for itop external
    Route::post('create-attachment', [ItopExternalReciverController::class, 'createAttachment']);
    Route::post('create-ticket', [ItopExternalReciverController::class, 'createTicket']);
    Route::post('update-ticket', [ItopExternalReciverController::class, 'updateTicket']);
    Route::post('update-private-log', [ItopExternalReciverController::class, 'ticketUpdatePrivateLog']);
});
