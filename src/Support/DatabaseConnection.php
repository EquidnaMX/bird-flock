<?php

/**
 * Database connection helper for package-owned tables.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Support;

use Closure;
use Illuminate\Support\Facades\DB;

final class DatabaseConnection
{
    public static function name(): ?string
    {
        $connection = config('bird-flock.database.connection');

        return is_string($connection) && trim($connection) !== ''
            ? $connection
            : null;
    }

    public static function connection(): mixed
    {
        return DB::connection(self::name());
    }

    public static function schema(): mixed
    {
        return self::connection()->getSchemaBuilder();
    }

    public static function table(string $tableName): mixed
    {
        return self::connection()->table($tableName);
    }

    /**
     * @template TReturn
     *
     * @param Closure(): TReturn $callback
     * @return TReturn
     */
    public static function transaction(Closure $callback): mixed
    {
        return self::connection()->transaction($callback);
    }

    public static function raw(string $value): mixed
    {
        return self::connection()->raw($value);
    }
}
