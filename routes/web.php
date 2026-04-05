<?php

declare(strict_types=1);

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\LogViewerController;
use App\Http\Controllers\Dashboard\SettingsController;
use App\Http\Controllers\Dashboard\SkillEditorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard Web Routes
|--------------------------------------------------------------------------
| No application-level auth — access control is handled at infrastructure level
| (Nginx IP whitelist, Cloudflare Access, or VPN).
*/

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Logs
Route::prefix('logs')->name('logs.')->group(function () {
    Route::get('/',              [LogViewerController::class, 'index'])->name('index');
    Route::get('/{uuid}',        [LogViewerController::class, 'show'])->name('show');
    Route::get('/{uuid}/stream', [LogViewerController::class, 'stream'])->name('stream');
});

// Skills
Route::prefix('skills')->name('skills.')->group(function () {
    Route::get('/',           [SkillEditorController::class, 'index'])->name('index');
    Route::get('/{id}/edit',  [SkillEditorController::class, 'edit'])->name('edit');
    Route::post('/{id}/edit', [SkillEditorController::class, 'update'])->name('update');
    Route::post('/install',   [SkillEditorController::class, 'install'])->name('install');
});

// Commands, Tools, Memory, Settings
Route::get('/commands', [SettingsController::class, 'commands'])->name('commands.index');
Route::get('/tools',    [SettingsController::class, 'tools'])->name('tools.index');
Route::get('/memory',   [SettingsController::class, 'memory'])->name('memory.index');
Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');

// Setup Wizard
Route::prefix('wizard')->name('wizard.')->group(function () {
    Route::get('/',                  [\App\Http\Controllers\Dashboard\WizardController::class, 'index'])->name('index');
    Route::post('/save-env',         [\App\Http\Controllers\Dashboard\WizardController::class, 'saveEnv'])->name('save-env');
    Route::post('/test-telegram',    [\App\Http\Controllers\Dashboard\WizardController::class, 'testTelegram'])->name('test-telegram');
    Route::post('/register-webhook', [\App\Http\Controllers\Dashboard\WizardController::class, 'registerWebhook'])->name('register-webhook');
    Route::post('/test-llm',         [\App\Http\Controllers\Dashboard\WizardController::class, 'testLlm'])->name('test-llm');
    Route::post('/send-test-message', [\App\Http\Controllers\Dashboard\WizardController::class, 'sendTestMessage'])->name('send-test-message');
});
