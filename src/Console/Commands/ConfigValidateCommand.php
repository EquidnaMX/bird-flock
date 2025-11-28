<?php

/**
 * Configuration validation CLI command.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Console\Commands
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Console\Commands;

use Illuminate\Console\Command;
use Equidna\BirdFlock\Support\ConfigValidator;
use Throwable;

/**
 * Validates Bird Flock configuration.
 *
 * Runs boot-time validators and reports fatal errors or warnings.
 */
final class ConfigValidateCommand extends Command
{
    protected $signature = 'bird-flock:config:validate';

    protected $description = 'Validate Bird Flock configuration and surface warnings or fatal errors.';

    /**
     * Execute the console command.
     *
     * @return int Exit code (0 on success, non-zero on failure)
     */
    public function handle(): int
    {
        $validator = new ConfigValidator();

        try {
            $validator->validateAll();
            $this->info('Bird Flock configuration looks valid (warnings may have been emitted).');
            return 0;
        } catch (Throwable $e) {
            $this->error('Configuration validation failed: ' . $e->getMessage());
            return 2;
        }
    }
}
