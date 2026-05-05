<?php

declare(strict_types=1);

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\GeneratorController;
use App\Http\Controllers\Dashboard\LogViewerController;
use App\Http\Controllers\Dashboard\SettingsController;
use App\Http\Controllers\Dashboard\SkillEditorController;
use App\Http\Controllers\Dashboard\ToolDetailController;
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
    Route::get('/',              [SkillEditorController::class, 'index'])->name('index');
    Route::get('/{id}',          [SkillEditorController::class, 'show'])->name('show');
    Route::get('/{id}/edit',     [SkillEditorController::class, 'edit'])->name('edit');
    Route::post('/{id}/edit',    [SkillEditorController::class, 'update'])->name('update');
    Route::post('/{id}/ai-suggest', [SkillEditorController::class, 'aiSuggest'])->name('ai-suggest');
    Route::post('/{id}/ai-apply',   [SkillEditorController::class, 'aiApply'])->name('ai-apply');
    Route::post('/{id}/config',  [SkillEditorController::class, 'saveConfig'])->name('save-config');
    Route::post('/install',      [SkillEditorController::class, 'install'])->name('install');
});

// Commands, Tools, Memory, Settings, Prompts
Route::get('/commands', [SettingsController::class, 'commands'])->name('commands.index');
Route::prefix('tools')->name('tools.')->group(function () {
    Route::get('/',                  [SettingsController::class, 'tools'])->name('index');
    Route::get('/{id}',              [ToolDetailController::class, 'show'])->name('show');
    Route::post('/{id}/ai-suggest',  [ToolDetailController::class, 'aiSuggest'])->name('ai-suggest');
    Route::post('/{id}/ai-apply',    [ToolDetailController::class, 'aiApply'])->name('ai-apply');
    Route::post('/{id}/config',      [ToolDetailController::class, 'saveConfig'])->name('save-config');
    Route::post('/{id}/config-keys', [ToolDetailController::class, 'saveConfigKeys'])->name('save-config-keys');
});
Route::get('/memory',   [SettingsController::class, 'memory'])->name('memory.index');
Route::get('/settings',          [SettingsController::class, 'index'])->name('settings.index');
Route::post('/settings/update-webhook', [SettingsController::class, 'updateWebhook'])->name('settings.update-webhook');
Route::get('/prompts',  [SettingsController::class, 'prompts'])->name('prompts.index');
Route::post('/prompts/global',  [SettingsController::class, 'saveGlobalPrompt'])->name('prompts.save-global');
Route::post('/prompts/command/{id}', [SettingsController::class, 'saveCommandPrompt'])->name('prompts.save-command');

// AI Generator
Route::prefix('generator')->name('generator.')->group(function () {
    Route::post('/run',        [GeneratorController::class, 'run'])->name('run');
    Route::get('/tools-list',  [GeneratorController::class, 'toolsList'])->name('tools-list');
});

// Setup Wizard
Route::prefix('wizard')->name('wizard.')->group(function () {
    Route::get('/',                  [\App\Http\Controllers\Dashboard\WizardController::class, 'index'])->name('index');
    Route::post('/save-env',         [\App\Http\Controllers\Dashboard\WizardController::class, 'saveEnv'])->name('save-env');
    Route::post('/test-telegram',    [\App\Http\Controllers\Dashboard\WizardController::class, 'testTelegram'])->name('test-telegram');
    Route::post('/register-webhook', [\App\Http\Controllers\Dashboard\WizardController::class, 'registerWebhook'])->name('register-webhook');
    Route::post('/test-llm',         [\App\Http\Controllers\Dashboard\WizardController::class, 'testLlm'])->name('test-llm');
    Route::post('/send-test-message', [\App\Http\Controllers\Dashboard\WizardController::class, 'sendTestMessage'])->name('send-test-message');
});
