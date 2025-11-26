<?php

/**
 * Eloquent model for outbound messages.
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
 * Represents an outbound message in the messaging system.
 */
class OutboundMessage extends Model
{
    protected $primaryKey = 'id_outboundMessage';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = [];

    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    /**
     * Get the casts array.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'queuedAt' => 'datetime',
            'sentAt' => 'datetime',
            'deliveredAt' => 'datetime',
            'failedAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('bird-flock.tables.outbound_messages', parent::getTable());
    }
}
