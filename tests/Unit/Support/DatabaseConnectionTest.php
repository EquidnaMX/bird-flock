<?php

namespace Equidna\BirdFlock\Tests\Unit\Support;

use Equidna\BirdFlock\Support\DatabaseConnection;
use Equidna\BirdFlock\Tests\TestCase;

final class DatabaseConnectionTest extends TestCase
{
    public function testNameReturnsNullWhenConnectionIsNotConfigured(): void
    {
        $this->assertNull(DatabaseConnection::name());
    }

    public function testNameReturnsConfiguredConnection(): void
    {
        config(['bird-flock.database.connection' => 'bird_flock']);

        $this->assertSame('bird_flock', DatabaseConnection::name());
    }

    public function testConnectionSchemaTableTransactionAndRawUseConfiguredConnection(): void
    {
        config(['bird-flock.database.connection' => 'bird_flock']);

        $connection = new class {
            public array $calls = [];

            public function getSchemaBuilder(): object
            {
                $this->calls[] = 'schema';

                return (object) ['name' => 'schema'];
            }

            public function table(string $table): object
            {
                $this->calls[] = "table:{$table}";

                return (object) ['table' => $table];
            }

            public function transaction(callable $callback): mixed
            {
                $this->calls[] = 'transaction';

                return $callback();
            }

            public function raw(string $value): object
            {
                $this->calls[] = "raw:{$value}";

                return (object) ['raw' => $value];
            }
        };

        $db = new class ($connection) {
            public array $names = [];

            public function __construct(private readonly object $connection)
            {
            }

            public function connection(?string $name = null): object
            {
                $this->names[] = $name;

                return $this->connection;
            }
        };

        app()->instance('db', $db);

        $this->assertSame($connection, DatabaseConnection::connection());
        $this->assertEquals((object) ['name' => 'schema'], DatabaseConnection::schema());
        $this->assertEquals((object) ['table' => 'bird_flock_outbound_messages'], DatabaseConnection::table('bird_flock_outbound_messages'));
        $this->assertSame('done', DatabaseConnection::transaction(static fn () => 'done'));
        $this->assertEquals((object) ['raw' => 'count(*) as count'], DatabaseConnection::raw('count(*) as count'));
        $this->assertSame(['bird_flock', 'bird_flock', 'bird_flock', 'bird_flock', 'bird_flock'], $db->names);
    }
}
