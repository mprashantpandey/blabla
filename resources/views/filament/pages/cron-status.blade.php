<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Scheduled Commands
            </x-slot>
            <x-slot name="description">
                Status of all scheduled cron jobs. Ensure your server cron is configured correctly.
            </x-slot>
            
            <div class="space-y-4">
                <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Add this to your server crontab:
                    </p>
                    <code class="block bg-white dark:bg-gray-900 p-3 rounded border text-sm">
                        * * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1
                    </code>
                </div>

                <div class="space-y-3">
                    @foreach($this->getCronJobs() as $job)
                        <div class="border rounded-lg p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ $job['command'] }}
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        {{ $job['description'] }}
                                    </p>
                                    
                                    <div class="mt-3 space-y-1">
                                        @if($job['never_run'])
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                    Never Run
                                                </span>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $job['status'] === 'success' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300' }}">
                                                    {{ ucfirst($job['status']) }}
                                                </span>
                                                @if($job['last_ran_at'])
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                                        Last ran: {{ $job['last_ran_at']->diffForHumans() }}
                                                    </span>
                                                @endif
                                            </div>
                                            @if($job['message'])
                                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                                    {{ $job['message'] }}
                                                </p>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
