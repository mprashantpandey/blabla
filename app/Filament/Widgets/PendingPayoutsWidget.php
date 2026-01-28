<?php

namespace App\Filament\Widgets;

use App\Models\PayoutRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PendingPayoutsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $user = auth()->user();
        
        $query = PayoutRequest::whereIn('status', ['requested', 'approved', 'processing']);

        // Apply city scoping for city admins
        if ($user && $user->hasRole('city_admin') && !$user->hasRole('super_admin')) {
            $assignedCityIds = \App\Models\CityAdminAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('city_id');
            $query->whereHas('driverProfile', function ($q) use ($assignedCityIds) {
                $q->whereIn('city_id', $assignedCityIds);
            });
        }

        $pendingCount = $query->count();
        $pendingAmount = $query->sum('amount');

        return [
            Stat::make('Pending Payout Requests', $pendingCount)
                ->description('Awaiting approval or processing')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            Stat::make('Pending Amount', number_format($pendingAmount, 2))
                ->description('Total amount pending')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),
        ];
    }
}

