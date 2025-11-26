<?php

namespace Equidna\BirdFlock\Models;

use Illuminate\Database\Eloquent\Model;

class DeadLetterEntry extends Model
{
    protected $table = 'bird_flock_dead_letters';

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

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
