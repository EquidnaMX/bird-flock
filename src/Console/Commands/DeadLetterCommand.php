<?php

namespace Equidna\BirdFlock\Console\Commands;

use Equidna\BirdFlock\Models\DeadLetterEntry;
use Equidna\BirdFlock\Support\DeadLetterService;
use Illuminate\Console\Command;

class DeadLetterCommand extends Command
{
    protected $signature = 'bird-flock:dead-letter
        {action : list|replay|purge}
        {message_id? : Required for replay/purge of a single entry}
        {--limit=50 : Number of entries to show when listing}';

    protected $description = 'Inspect and manage Bird Flock dead-letter messages.';
    /**
     * Detailed help shown by `php artisan help bird-flock:dead-letter`.
     *
     * Examples:
     *  php artisan bird-flock:dead-letter list --limit=50
     *  php artisan bird-flock:dead-letter replay --id=ulid_0000 --force
     *  php artisan bird-flock:dead-letter purge --before="2025-01-01"
     */
    protected $help = "Manage message dead-letter queue.\n\n" .
        "Actions: list, replay, purge.\n" .
        "Examples: see README or docs/CLI.md for expanded usage and safe replay guidance.";

    public function __construct(
        private readonly DeadLetterService $service
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('bird-flock.dead_letter.enabled', true)) {
            $this->warn('Dead-letter queue is disabled in configuration.');

            return self::SUCCESS;
        }

        return match ($this->argument('action')) {
            'list' => $this->listEntries(),
            'replay' => $this->replayEntry(),
            'purge' => $this->purgeEntries(),
            default => $this->error('Unsupported action.') ?? self::INVALID,
        };
    }

    private function listEntries(): int
    {
        $limit = (int) $this->option('limit');
        $entries = DeadLetterEntry::orderByDesc('created_at')->limit($limit)->get();

        if ($entries->isEmpty()) {
            $this->info('No dead-letter entries found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Entry ID', 'Message ID', 'Channel', 'Attempts', 'Error Code', 'Created'],
            $entries->map(fn($entry) => [
                $entry->id,
                $entry->message_id,
                $entry->channel,
                $entry->attempts,
                $entry->error_code,
                optional($entry->created_at)->toDateTimeString(),
            ])
        );

        return self::SUCCESS;
    }

    private function replayEntry(): int
    {
        $entryId = $this->argument('message_id');

        if (!$entryId) {
            $this->error('message_id argument is required for replay.');

            return self::INVALID;
        }

        $entry = DeadLetterEntry::find($entryId);

        if (!$entry) {
            $this->error('Dead-letter entry not found.');

            return self::FAILURE;
        }

        $this->service->replay($entry);
        $this->info("Message {$entry->message_id} dispatched back to queue.");

        return self::SUCCESS;
    }

    private function purgeEntries(): int
    {
        $entryId = $this->argument('message_id');

        if ($entryId) {
            $deleted = DeadLetterEntry::whereKey($entryId)->delete();
            $deleted
                ? $this->info("Entry {$entryId} removed.")
                : $this->warn('Entry not found.');

            return self::SUCCESS;
        }

        if (!$this->confirm('Purge all dead-letter entries?', false)) {
            $this->line('Cancelled.');

            return self::SUCCESS;
        }

        DeadLetterEntry::query()->delete();
        $this->info('All dead-letter entries removed.');

        return self::SUCCESS;
    }
}
