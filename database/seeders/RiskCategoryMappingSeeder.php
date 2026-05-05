<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RiskCategory;
use App\Models\Tool;
use Illuminate\Database\Seeder;

/**
 * Assigns risk_category values (and corrects requires_confirmation where needed)
 * according to the PLAN-v2 audit table (§G12+B3).
 *
 * Idempotent: uses updateOrCreate by name.
 */
class RiskCategoryMappingSeeder extends Seeder
{
    public function run(): void
    {
        $mappings = [
            // --- BASH / SHELL ---
            'bash'               => [RiskCategory::Bash,              true],
            'laravel_artisan'    => [RiskCategory::Bash,              true],
            'composer_operation' => [RiskCategory::Bash,              true],   // promoted
            'npm_operation'      => [RiskCategory::Bash,              true],   // promoted

            // --- FILE DELETION ---
            'file_delete'           => [RiskCategory::FileDelete, true],
            'gmail_trash'           => [RiskCategory::FileDelete, true],
            'google_calendar_delete' => [RiskCategory::FileDelete, true],

            // --- DATABASE DESTRUCTIVE ---
            // Accepted false-positive: all db_query calls require confirmation.
            // Future improvement: argument-level inspection.
            'db_query' => [RiskCategory::DatabaseDestructive, true],   // promoted

            // --- GIT PUSH ---
            // Accepted false-positive: git status/log also asks confirmation.
            'git_operation' => [RiskCategory::GitPush, true],   // promoted

            // --- MESSAGES TO THIRD PARTIES ---
            'send_email'     => [RiskCategory::MessageThirdParty, true],
            'gmail_send'     => [RiskCategory::MessageThirdParty, true],
            'whatsapp_send'  => [RiskCategory::MessageThirdParty, true],
            'facebook_post'  => [RiskCategory::MessageThirdParty, true],
            'instagram_post' => [RiskCategory::MessageThirdParty, true],
            'telegram_send'  => [RiskCategory::MessageThirdParty, true],   // promoted
        ];

        foreach ($mappings as $toolName => [$category, $requiresConfirmation]) {
            Tool::where('name', $toolName)->update([
                'risk_category'        => $category->value,
                'requires_confirmation' => $requiresConfirmation,
            ]);
        }

        $this->command->info('RiskCategoryMappingSeeder: ' . count($mappings) . ' tool aggiornati.');
    }
}
