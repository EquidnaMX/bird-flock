<?php

/**
 * Command to display DLQ analytics and statistics.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Console\Commands
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Console\Commands;

use Equidna\BirdFlock\Models\DeadLetterEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Provides statistical analysis of dead-letter queue entries.
 */
final class DeadLetterStatsCommand extends Command
{
    protected $signature = 'bird-flock:dead-letter:stats
        {--days=7 : Number of days to analyze}
        {--top=10 : Number of top errors to display}';

    protected $description = 'Display DLQ analytics including error distribution and channel breakdown';

    /**
     * Execute the console command.
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        if (! config('bird-flock.dead_letter.enabled', true)) {
            $this->warn('Dead-letter queue is disabled in configuration.');

            return self::SUCCESS;
        }

        $days = (int) $this->option('days');
        $topN = (int) $this->option('top');

        $tableName = config('bird-flock.dead_letter.table', 'bird_flock_dead_letters');

        if (! DB::getSchemaBuilder()->hasTable($tableName)) {
            $this->error("Dead-letter table '{$tableName}' does not exist. Run migrations.");

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($days);

        $this->info("Dead Letter Queue Analytics (Last {$days} days)");
        $this->newLine();

        // Total count
        $total = DeadLetterEntry::where('created_at', '>=', $cutoff)->count();
        $this->line("📊 <options=bold>Total Entries:</> {$total}");
        $this->newLine();

        if ($total === 0) {
            $this->info('No dead-letter entries found in the specified period.');

            return self::SUCCESS;
        }

        // Channel breakdown
        $byChannel = DeadLetterEntry::select('channel', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $cutoff)
            ->groupBy('channel')
            ->orderByDesc('count')
            ->get();

        $this->line('📱 <options=bold>Breakdown by Channel:</>');
        $this->table(
            ['Channel', 'Count', 'Percentage'],
            $byChannel->map(fn($row) => [
                $row->channel,
                $row->count,
                number_format(($row->count / $total) * 100, 2) . '%',
            ])
        );
        $this->newLine();

        // Top error codes
        $topErrors = DeadLetterEntry::select('error_code', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $cutoff)
            ->whereNotNull('error_code')
            ->groupBy('error_code')
            ->orderByDesc('count')
            ->limit($topN)
            ->get();

        $this->line("🔴 <options=bold>Top {$topN} Error Codes:</>");
        $this->table(
            ['Error Code', 'Count', 'Percentage'],
            $topErrors->map(fn($row) => [
                $row->error_code,
                $row->count,
                number_format(($row->count / $total) * 100, 2) . '%',
            ])
        );
        $this->newLine();

        // Attempts distribution
        $attemptStats = DeadLetterEntry::select('attempts', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $cutoff)
            ->groupBy('attempts')
            ->orderBy('attempts')
            ->get();

        $this->line('🔄 <options=bold>Attempts Distribution:</>');
        $this->table(
            ['Attempts', 'Count'],
            $attemptStats->map(fn($row) => [
                $row->attempts,
                $row->count,
            ])
        );
        $this->newLine();

        // Time-series (daily histogram)
        $dailyStats = DeadLetterEntry::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('count(*) as count')
        )
            ->where('created_at', '>=', $cutoff)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $this->line('📈 <options=bold>Daily Histogram:</>');
        $maxCount = $dailyStats->max('count') ?: 1;
        $barWidth = 50;

        foreach ($dailyStats as $stat) {
            $barLength = (int) (($stat->count / $maxCount) * $barWidth);
            $bar = str_repeat('█', $barLength);
            $this->line(sprintf(
                '%s │ %s %d',
                $stat->date,
                $bar,
                $stat->count
            ));
        }
        $this->newLine();

        // Recent entries
        $this->line('🕒 <options=bold>Most Recent Entries (Last 5):</>');
        $recent = DeadLetterEntry::where('created_at', '>=', $cutoff)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $this->table(
            ['Entry ID', 'Channel', 'Error Code', 'Attempts', 'Created'],
            $recent->map(fn($entry) => [
                substr($entry->id, 0, 16) . '...',
                $entry->channel,
                $entry->error_code,
                $entry->attempts,
                $entry->created_at->format('Y-m-d H:i:s'),
            ])
        );

        return self::SUCCESS;
    }
}
