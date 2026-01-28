<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Install BlaBla - Setup Wizard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <h1 class="text-4xl font-bold text-gray-900 mb-2">BlaBla Installation</h1>
                <p class="text-gray-600">Welcome! Let's get your carpool application set up.</p>
            </div>

            <!-- Pre-install Checks -->
            <div id="preChecks" class="bg-white shadow rounded-lg p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">System Requirements</h2>
                <div id="checksList" class="space-y-2">
                    <div class="flex items-center gap-2">
                        <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                        <span>Checking system requirements...</span>
                    </div>
                </div>
            </div>

            <!-- Installation Form -->
            <div id="installForm" class="bg-white shadow rounded-lg p-6" style="display: none;">
                <h2 class="text-xl font-semibold mb-6">Installation Details</h2>
                
                <form id="installFormElement" class="space-y-6">
                    <!-- Database Section -->
                    <div class="border-b pb-6">
                        <h3 class="text-lg font-medium mb-4">Database Configuration</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Database Host</label>
                                <input type="text" name="db_host" value="127.0.0.1" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Database Port</label>
                                <input type="number" name="db_port" value="3306" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Database Name</label>
                                <input type="text" name="db_database" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Database Username</label>
                                <input type="text" name="db_username" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Database Password</label>
                                <input type="password" name="db_password"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Application Section -->
                    <div class="border-b pb-6">
                        <h3 class="text-lg font-medium mb-4">Application Settings</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Application Name</label>
                                <input type="text" name="app_name" value="BlaBla" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Application URL</label>
                                <input type="url" name="app_url" value="{{ url('/') }}" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Admin User Section -->
                    <div>
                        <h3 class="text-lg font-medium mb-4">Admin Account</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Admin Name</label>
                                <input type="text" name="admin_name" value="Admin" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Admin Email</label>
                                <input type="email" name="admin_email" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Admin Password</label>
                                <input type="password" name="admin_password" required minlength="8"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <button type="submit" id="installButton"
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span id="installButtonText">Install Application</span>
                            <span id="installButtonSpinner" class="hidden">Installing...</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Success Message -->
            <div id="successMessage" class="bg-green-50 border border-green-200 rounded-lg p-6" style="display: none;">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h3 class="text-lg font-semibold text-green-900">Installation Complete!</h3>
                        <p class="text-green-700 mt-1" id="successDetails"></p>
                        <a href="/admin" class="mt-4 inline-block px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            Go to Admin Panel
                        </a>
                    </div>
                </div>
            </div>

            <!-- Error Message -->
            <div id="errorMessage" class="bg-red-50 border border-red-200 rounded-lg p-6" style="display: none;">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <div>
                        <h3 class="text-lg font-semibold text-red-900">Installation Failed</h3>
                        <p class="text-red-700 mt-1" id="errorDetails"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Check installation status and pre-requirements
        async function checkInstallation() {
            try {
                const response = await fetch('/install/check');
                const data = await response.json();

                if (data.installed) {
                    // Already installed, redirect to admin
                    window.location.href = '/admin';
                    return;
                }

                // Show pre-install checks
                displayPreChecks(data.pre_checks);

                // Show install form if all checks pass
                const allPass = Object.values(data.pre_checks).every(check => check.status === 'pass');
                if (allPass) {
                    document.getElementById('installForm').style.display = 'block';
                } else {
                    document.getElementById('preChecks').classList.add('border-red-300', 'bg-red-50');
                }
            } catch (error) {
                console.error('Error checking installation:', error);
                document.getElementById('checksList').innerHTML = 
                    '<div class="text-red-600">Error checking system requirements. Please refresh the page.</div>';
            }
        }

        function displayPreChecks(checks) {
            const checksList = document.getElementById('checksList');
            checksList.innerHTML = '';

            for (const [key, check] of Object.entries(checks)) {
                const statusIcon = check.status === 'pass' 
                    ? '<svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>'
                    : '<svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
                
                const statusText = check.status === 'pass' 
                    ? '<span class="text-green-600 font-medium">Pass</span>'
                    : '<span class="text-red-600 font-medium">Fail</span>';

                let details = '';
                if (key === 'php_version') {
                    details = `Current: ${check.current}, Required: ${check.required}`;
                } else if (key === 'extensions') {
                    details = check.missing.length > 0 
                        ? `Missing: ${check.missing.join(', ')}`
                        : 'All required extensions installed';
                } else if (key === 'permissions') {
                    details = `Storage: ${check.storage_writable ? 'Writable' : 'Not Writable'}, Bootstrap Cache: ${check.bootstrap_cache_writable ? 'Writable' : 'Not Writable'}`;
                } else if (key === 'env') {
                    details = check.writable ? 'Writable' : 'Not Writable';
                }

                checksList.innerHTML += `
                    <div class="flex items-center justify-between p-3 rounded ${check.status === 'pass' ? 'bg-green-50' : 'bg-red-50'}">
                        <div class="flex items-center gap-3">
                            ${statusIcon}
                            <div>
                                <div class="font-medium">${check.name}</div>
                                <div class="text-sm text-gray-600">${details}</div>
                            </div>
                        </div>
                        ${statusText}
                    </div>
                `;
            }
        }

        // Handle form submission
        document.getElementById('installFormElement').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            
            const installButton = document.getElementById('installButton');
            const installButtonText = document.getElementById('installButtonText');
            const installButtonSpinner = document.getElementById('installButtonSpinner');
            
            installButton.disabled = true;
            installButtonText.classList.add('hidden');
            installButtonSpinner.classList.remove('hidden');
            
            document.getElementById('errorMessage').style.display = 'none';
            document.getElementById('successMessage').style.display = 'none';

            try {
                const response = await fetch('/install/run', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    document.getElementById('successMessage').style.display = 'block';
                    const checklist = result.checklist;
                    let details = 'Installation completed successfully. ';
                    if (checklist.cron_reminder) {
                        details += '<br><strong>Important:</strong> Add this cron job to your server:<br><code class="bg-gray-100 px-2 py-1 rounded text-sm">' + checklist.cron_reminder + '</code>';
                    }
                    document.getElementById('successDetails').innerHTML = details;
                    document.getElementById('installForm').style.display = 'none';
                } else {
                    document.getElementById('errorMessage').style.display = 'block';
                    document.getElementById('errorDetails').textContent = result.message || 'Installation failed. Please check the errors above.';
                }
            } catch (error) {
                document.getElementById('errorMessage').style.display = 'block';
                document.getElementById('errorDetails').textContent = 'Network error: ' + error.message;
            } finally {
                installButton.disabled = false;
                installButtonText.classList.remove('hidden');
                installButtonSpinner.classList.add('hidden');
            }
        });

        // Initialize
        checkInstallation();
    </script>
</body>
</html>

