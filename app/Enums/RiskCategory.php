<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Risk categories for tools that require confirmation_gate.
 * Used alongside the boolean `requires_confirmation` flag on Tool:
 *   - requires_confirmation: gate is active (boolean, operational)
 *   - risk_category: WHY the gate is active (enum, informational / UX)
 *
 * The category is surfaced to the user in the Telegram confirmation prompt.
 */
enum RiskCategory: string
{
    /** Shell execution, artisan, composer, npm — arbitrary code can run */
    case Bash = 'bash';

    /** File deletion, soft-delete of email/calendar entries */
    case FileDelete = 'file_delete';

    /** DDL/DML statements that destroy or alter data (DROP, TRUNCATE, DELETE without WHERE) */
    case DatabaseDestructive = 'db_destructive';

    /** git push, force-push, rebase on published branches */
    case GitPush = 'git_push';

    /** Sending messages to third parties: email, WhatsApp, social, Telegram */
    case MessageThirdParty = 'message_third_party';

    /** Firewall or network access-control changes (no tool currently — reserved) */
    case FirewallChange = 'firewall';

    /** Modification of system .md files (FLAMINGDRAGON.md, TOOLS.md, etc.) */
    case SystemFileMd = 'system_md';

    public function label(): string
    {
        return match($this) {
            self::Bash               => 'Esecuzione shell',
            self::FileDelete         => 'Eliminazione file',
            self::DatabaseDestructive => 'Operazione DB distruttiva',
            self::GitPush            => 'Push git',
            self::MessageThirdParty  => 'Messaggio a terzi',
            self::FirewallChange     => 'Modifica firewall',
            self::SystemFileMd       => 'Modifica file di sistema',
        };
    }
}
