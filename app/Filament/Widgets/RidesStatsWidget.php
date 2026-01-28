<?php

namespace App\Filament\Widgets;

use App\Models\Ride;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class RidesStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $query = Ride::query();

        // City Admin scope
        if (auth()->user()->hasRole('City Admin') && !auth()->user()->hasRole('Super Admin')) {
            $assignedCityIds = auth()->user()->assignedCities()->pluck('cities.id');
            $query->whereIn('city_id', $assignedCityIds);
        }

        $totalRides = (clone $query)->count();
        $publishedRides = (clone $query)->where('status', 'published')->count();
        $cancelledRides = (clone $query)->where('status', 'cancelled')->count();
        $upcomingRides = (clone $query)->published()->upcoming()->count();

        return [
            Stat::make('Total Rides', $totalRides)
                ->description('All rides')
                ->icon('heroicon-o-map'),
            Stat::make('Published Rides', $publishedRides)
                ->description('Currently published')
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Upcoming Rides', $upcomingRides)
                ->description('Scheduled rides')
                ->icon('heroicon-o-clock')
                ->color('info'),
            Stat::make('Cancelled Rides', $cancelledRides)
                ->description('Cancelled rides')
                ->icon('heroicon-o-x-circle')
                ->color('danger'),
        ];
    }
}

