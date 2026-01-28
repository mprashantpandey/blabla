<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronRun extends Model
{
    protected $fillable = [
        'command',
        'last_ran_at',
        'status',
        'message',
    ];

    protected $casts = [
        'last_ran_at' => 'datetime',
    ];

    /**
     * Update or create a cron run record.
     */
    public static function record(string $command, string $status, ?string $message = null): void
    {
        static::updateOrCreate(
            ['command' => $command],
            [
                'last_ran_at' => now(),
                'status' => $status,
                'message' => $message,
            ]
        );
    }
}
