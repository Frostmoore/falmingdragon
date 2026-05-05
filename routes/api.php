<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\MemoryController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\ToolController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Middleware\TelegramAuthMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Telegram Webhook
|--------------------------------------------------------------------------
| Auth (chat ID check) handled by TelegramAuthMiddleware.
| Throttle to mitigate webhook flooding.
*/
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->middleware([TelegramAuthMiddleware::class, 'throttle:60,1'])
    ->name('telegram.webhook');

/*
|--------------------------------------------------------------------------
| FlamingDragon REST API
|--------------------------------------------------------------------------
| No authentication — secured at infrastructure level (Nginx, VPN, firewall).
*/
Route::prefix('fd')->name('api.fd.')->group(function () {

    // Sessions
    Route::get('/sessions',                  [AgentController::class, 'index'])->name('sessions.index');
    Route::get('/sessions/{uuid}',           [AgentController::class, 'show'])->name('sessions.show');
    Route::post('/sessions/{uuid}/cancel',   [AgentController::class, 'cancel'])->name('sessions.cancel');

    // Commands (allow-list)
    Route::get('/commands',         [ConfigController::class, 'commandsIndex'])->name('commands.index');
    Route::post('/commands',        [ConfigController::class, 'commandsStore'])->name('commands.store');
    Route::put('/commands/{id}',    [ConfigController::class, 'commandsUpdate'])->name('commands.update');
    Route::delete('/commands/{id}', [ConfigController::class, 'commandsDestroy'])->name('commands.destroy');

    // Skills
    Route::get('/skills',                    [SkillController::class, 'index'])->name('skills.index');
    Route::post('/skills/install',           [SkillController::class, 'install'])->name('skills.install');
    Route::post('/skills/{id}/toggle',       [SkillController::class, 'toggle'])->name('skills.toggle');
    Route::delete('/skills/{id}',            [SkillController::class, 'destroy'])->name('skills.destroy');

    // Tools
    Route::get('/tools',             [ToolController::class, 'index'])->name('tools.index');
    Route::post('/tools/{id}/toggle', [ToolController::class, 'toggle'])->name('tools.toggle');

    // Memory
    Route::get('/memory',              [MemoryController::class, 'index'])->name('memory.index');
    Route::post('/memory',             [MemoryController::class, 'store'])->name('memory.store');
    Route::patch('/memory/{id}',       [MemoryController::class, 'update'])->name('memory.update');
    Route::delete('/memory/{id}',      [MemoryController::class, 'destroy'])->name('memory.destroy');
    Route::delete('/memory',           [MemoryController::class, 'destroyNamespace'])->name('memory.destroy-namespace');
    Route::post('/memory/backfill',    [MemoryController::class, 'backfillEmbeddings'])->name('memory.backfill');

    // LLM Providers
    Route::get('/providers',                    [ConfigController::class, 'providersIndex'])->name('providers.index');
    Route::put('/providers/{id}',               [ConfigController::class, 'providersUpdate'])->name('providers.update');
    Route::post('/providers/{id}/set-default',  [ConfigController::class, 'providersSetDefault'])->name('providers.set-default');

    // Stats & Health
    Route::get('/stats',  [ConfigController::class, 'stats'])->name('stats');
    Route::get('/health', [ConfigController::class, 'health'])->name('health');
});
