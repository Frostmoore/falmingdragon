<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Security;

use App\Enums\RiskCategory;
use App\Models\AllowedCommand;
use App\Services\Command\ParsedCommand;
use App\Services\Security\ConfirmationGate;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConfirmationGateTest extends TestCase
{
    private ConfirmationGate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gate = new ConfirmationGate();
    }

    // -------------------------------------------------------------------------
    // requiresGate()
    // -------------------------------------------------------------------------

    public function test_requiresGate_returns_true_for_dangerous_command(): void
    {
        $cmd = $this->makeCommand(is_dangerous: true, skip_confirmation: false);
        $this->assertTrue($this->gate->requiresGate($cmd));
    }

    public function test_requiresGate_returns_false_for_safe_command(): void
    {
        $cmd = $this->makeCommand(is_dangerous: false, skip_confirmation: false);
        $this->assertFalse($this->gate->requiresGate($cmd));
    }

    public function test_requiresGate_returns_false_when_skip_confirmation_is_true(): void
    {
        $cmd = $this->makeCommand(is_dangerous: true, skip_confirmation: true);
        $this->assertFalse($this->gate->requiresGate($cmd));
    }

    // -------------------------------------------------------------------------
    // store() + retrieve()
    // -------------------------------------------------------------------------

    public function test_store_and_retrieve_returns_stored_command(): void
    {
        Cache::flush();

        $cmd = $this->makeParsedCommand('bash', ['cmd' => 'ls -la']);
        $this->gate->store(12345, $cmd);

        $result = $this->gate->retrieve(12345);
        $this->assertNotNull($result);
        $this->assertSame('bash', $result['command']);
        $this->assertSame(['cmd' => 'ls -la'], $result['args']);
    }

    public function test_retrieve_returns_null_for_unknown_chat(): void
    {
        Cache::flush();
        $this->assertNull($this->gate->retrieve(99999));
    }

    // -------------------------------------------------------------------------
    // clear()
    // -------------------------------------------------------------------------

    public function test_clear_removes_pending_command(): void
    {
        Cache::flush();

        $cmd = $this->makeParsedCommand('file_delete', ['path' => '/tmp/x']);
        $this->gate->store(12345, $cmd);

        $this->assertNotNull($this->gate->retrieve(12345));

        $this->gate->clear(12345);

        $this->assertNull($this->gate->retrieve(12345));
    }

    public function test_clear_on_nonexistent_chat_does_not_throw(): void
    {
        Cache::flush();
        $this->gate->clear(99999); // must not throw
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // buildPrompt()
    // -------------------------------------------------------------------------

    public function test_buildPrompt_contains_command_name(): void
    {
        $cmd    = $this->makeCommand(name: 'bash', is_dangerous: true);
        $prompt = $this->gate->buildPrompt($cmd);
        $this->assertStringContainsString('bash', $prompt);
    }

    public function test_buildPrompt_contains_confirm_deny_instructions(): void
    {
        $cmd    = $this->makeCommand(name: 'bash', is_dangerous: true);
        $prompt = $this->gate->buildPrompt($cmd);
        $this->assertStringContainsString('/confirm', $prompt);
        $this->assertStringContainsString('/deny', $prompt);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCommand(
        string $name             = 'test_cmd',
        bool   $is_dangerous     = false,
        bool   $skip_confirmation = false,
        array  $tools_allowed    = [],
    ): AllowedCommand {
        $cmd                   = new AllowedCommand();
        $cmd->name             = $name;
        $cmd->is_dangerous     = $is_dangerous;
        $cmd->skip_confirmation = $skip_confirmation;
        $cmd->tools_allowed    = $tools_allowed;
        $cmd->description      = 'test command';
        return $cmd;
    }

    private function makeParsedCommand(string $name, array $args = []): ParsedCommand
    {
        $def              = new AllowedCommand();
        $def->name        = $name;
        $def->description = 'test';

        return new ParsedCommand(
            commandName:          $name,
            arguments:            $args,
            definition:           $def,
            requiresConfirmation: false,
            executionMode:        \App\Enums\ExecutionMode::Sync,
            naturalLanguageInput: null,
        );
    }
}
