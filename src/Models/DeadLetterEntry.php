<?php

/**
 * Eloquent model for dead-letter queue entries.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Models
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Dead-letter queue entry model.
 *
 * @method static \Illuminate\Database\Eloquent\Builder where(mixed ...$args)
 * @method static \Illuminate\Database\Eloquent\Builder select(mixed ...$columns)
 * @method static \Illuminate\Database\Eloquent\Builder orderByDesc(string $column)
 * @method static \Illuminate\Database\Eloquent\Builder whereKey(mixed $id)
 * @method static static|null find(string $id)
 * @method static static create(array $attributes)
 */
class DeadLetterEntry extends Model
{
    protected $table = 'bird_flock_dead_letters';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'message_id',
        'channel',
        'payload',
        'attempts',
        'error_code',
        'error_message',
        'last_exception',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function getTable()
    {
        return config('bird-flock.dead_letter.table', parent::getTable());
    }
}
