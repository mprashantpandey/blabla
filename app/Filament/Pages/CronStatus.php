<?php

namespace App\Filament\Pages;

use App\Models\CronRun;
use Filament\Pages\Page;

class CronStatus extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Cron Status';
    protected static ?string $title = 'Cron Jobs Status';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.cron-status';

    public function getCronJobs(): array
    {
        $commands = [
            'bookings:expire-holds' => 'Expire booking holds that have passed their expiration time',
        ];

        $jobs = [];
        foreach ($commands as $command => $description) {
            $cronRun = CronRun::where('command', $command)->first();
            
            $jobs[] = [
                'command' => $command,
                'description' => $description,
                'last_ran_at' => $cronRun?->last_ran_at,
                'status' => $cronRun?->status,
                'message' => $cronRun?->message,
                'never_run' => $cronRun === null,
            ];
        }

        return $jobs;
    }
}
