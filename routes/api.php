<?php

use App\Http\Controllers\Api\GithubWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhooks/github/{connection}', GithubWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('api.webhooks.github');
