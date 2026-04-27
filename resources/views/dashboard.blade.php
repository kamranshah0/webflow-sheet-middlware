<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Dashboard - Webflow Sync</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .glass-panel {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .glass-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .progress-bar-transition {
            transition: width 0.5s ease-in-out;
        }
        /* Custom scrollbar for chunks table */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05); 
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2); 
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3); 
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 min-h-screen text-white antialiased font-sans flex flex-col">

    <!-- Navbar -->
    <nav class="glass-panel sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-3">
                    <i data-lucide="activity" class="text-blue-400"></i>
                    <span class="font-bold text-xl tracking-tight">Sync Admin</span>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2 text-sm text-blue-200">
                        <div class="h-2 w-2 bg-green-500 rounded-full animate-pulse"></div>
                        <span id="last-updated">Live</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-sm bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-lg border border-white/10 transition-colors flex items-center space-x-2">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        
        <!-- Top Widgets -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="glass-card rounded-2xl p-6 shadow-lg relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-blue-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 group-hover:opacity-40 transition-opacity"></div>
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-medium text-blue-200 mb-1">Total Syncs</p>
                        <h3 class="text-3xl font-bold" id="widget-total">-</h3>
                    </div>
                    <div class="p-3 bg-blue-500/20 rounded-xl">
                        <i data-lucide="layers" class="w-6 h-6 text-blue-400"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-2xl p-6 shadow-lg relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-emerald-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 group-hover:opacity-40 transition-opacity"></div>
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-medium text-emerald-200 mb-1">Currently Running</p>
                        <h3 class="text-3xl font-bold" id="widget-running">-</h3>
                    </div>
                    <div class="p-3 bg-emerald-500/20 rounded-xl">
                        <i data-lucide="loader" class="w-6 h-6 text-emerald-400 animate-spin-slow"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-2xl p-6 shadow-lg relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-rose-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 group-hover:opacity-40 transition-opacity"></div>
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-medium text-rose-200 mb-1">Failed Jobs</p>
                        <h3 class="text-3xl font-bold" id="widget-failed">-</h3>
                    </div>
                    <div class="p-3 bg-rose-500/20 rounded-xl">
                        <i data-lucide="alert-triangle" class="w-6 h-6 text-rose-400"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Jobs Table -->
        <div class="glass-card rounded-2xl shadow-xl overflow-hidden">
            <div class="px-6 py-5 border-b border-white/10 flex justify-between items-center bg-white/5">
                <h2 class="text-lg font-semibold flex items-center space-x-2">
                    <i data-lucide="list" class="w-5 h-5 text-blue-400"></i>
                    <span>Sync History</span>
                </h2>
                <div class="flex items-center space-x-4">
                    <button onclick="resetCooldownsJS()" class="text-xs bg-slate-700/50 hover:bg-slate-700 text-slate-300 px-3 py-1.5 rounded-lg border border-slate-600 transition-colors flex items-center space-x-1">
                        <i data-lucide="timer-reset" class="w-4 h-4"></i>
                        <span>Reset Cooldowns</span>
                    </button>
                    <button onclick="fetchJobs()" class="text-blue-300 hover:text-white transition-colors" title="Refresh">
                        <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/10">
                    <thead class="bg-black/20">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-blue-200 uppercase tracking-wider">Job Details</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-blue-200 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-blue-200 uppercase tracking-wider">Progress</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-blue-200 uppercase tracking-wider">Metrics</th>
                            <th scope="col" class="px-6 py-4 text-right text-xs font-semibold text-blue-200 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="jobs-table-body" class="divide-y divide-white/5">
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-blue-200/50">
                                <i data-lucide="loader" class="w-8 h-8 animate-spin mx-auto mb-2"></i>
                                Loading sync data...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Chunk Details Modal -->
    <div id="chunk-modal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="glass-card rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-4xl w-full border border-white/20">
                <div class="px-6 py-4 border-b border-white/10 flex justify-between items-center bg-white/5">
                    <h3 class="text-lg font-semibold text-white flex items-center space-x-2">
                        <i data-lucide="layers" class="w-5 h-5 text-blue-400"></i>
                        <span id="modal-title">Job Chunks Breakdown</span>
                    </h3>
                    <button onclick="closeModal()" class="text-slate-400 hover:text-white transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <div class="p-6">
                    <div class="max-h-[60vh] overflow-y-auto pr-2" id="modal-content">
                        <!-- Chunks injected here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Icons
        lucide.createIcons();

        let globalJobs = [];

        function getStatusBadge(status) {
            const styles = {
                'queued': 'bg-slate-500/20 text-slate-300 border-slate-500/30',
                'processing': 'bg-blue-500/20 text-blue-300 border-blue-500/30 animate-pulse',
                'completed': 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                'completed_cleared': 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                'failed': 'bg-rose-500/20 text-rose-300 border-rose-500/30',
                'cancelled': 'bg-rose-500/20 text-rose-300 border-rose-500/30'
            };
            const style = styles[status] || styles['queued'];
            const displayStatus = status === 'completed_cleared' ? 'COMPLETED' : status.toUpperCase();
            return `<span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border ${style}">${displayStatus}</span>`;
        }

        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleString();
        }

        function openModal(jobId) {
            const job = globalJobs.find(j => j.id === jobId);
            if (!job) return;

            document.getElementById('modal-title').innerText = `Job #${job.id} - ${job.category} (${job.type})`;
            
            let contentHtml = '';
            if (!job.chunks || job.chunks.length === 0) {
                // If no chunks, check if the job itself has an error log (e.g. for summary jobs)
                let jobLogsHtml = '';
                if (job.error_log) {
                    try {
                        const logs = JSON.parse(job.error_log);
                        if (logs && logs.length > 0) {
                            jobLogsHtml = `<div class="space-y-3 mt-4 text-sm bg-black/20 p-4 rounded-xl border border-white/5">`;
                            logs.forEach(log => {
                                const icon = log.type === 'error' ? '<i data-lucide="alert-circle" class="w-4 h-4 text-rose-400 inline"></i>' : '<i data-lucide="skip-forward" class="w-4 h-4 text-slate-400 inline"></i>';
                                const color = log.type === 'error' ? 'text-rose-200' : 'text-slate-300';
                                jobLogsHtml += `<div class="flex items-start gap-2 ${color}">
                                    <div class="mt-0.5">${icon}</div>
                                    <div>
                                        <span class="font-medium">${log.identifier}:</span> ${log.reason}
                                    </div>
                                </div>`;
                            });
                            jobLogsHtml += `</div>`;
                        }
                    } catch(e) {
                        jobLogsHtml = `<div class="mt-4 p-4 bg-rose-500/10 border border-rose-500/20 text-rose-200 rounded-xl text-sm">${job.error_log}</div>`;
                    }
                }
                
                contentHtml = `<div class="text-center py-8 text-slate-400">
                    <p>No chunk data available (Single record sync).</p>
                    ${jobLogsHtml}
                </div>`;
            } else {
                contentHtml += `
                    <table class="min-w-full divide-y divide-white/10 text-sm">
                        <thead class="text-blue-200">
                            <tr>
                                <th class="py-2 text-left font-medium">Chunk</th>
                                <th class="py-2 text-left font-medium">Status</th>
                                <th class="py-2 text-center font-medium">Rows</th>
                                <th class="py-2 text-center font-medium">Created</th>
                                <th class="py-2 text-center font-medium">Updated</th>
                                <th class="py-2 text-center font-medium">Skipped</th>
                                <th class="py-2 text-center font-medium">Errors</th>
                                <th class="py-2 text-right font-medium">Started</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                `;

                job.chunks.forEach(chunk => {
                    let logsHtml = '';
                    if (chunk.error_log) {
                        try {
                            const logs = JSON.parse(chunk.error_log);
                            if (logs && logs.length > 0) {
                                logsHtml = `<tr class="bg-black/20 text-xs hidden" id="logs-chunk-${chunk.id}">
                                    <td colspan="8" class="p-4">
                                        <div class="space-y-2 max-h-40 overflow-y-auto pr-2">`;
                                logs.forEach(log => {
                                    const icon = log.type === 'error' ? '<i data-lucide="alert-circle" class="w-4 h-4 text-rose-400 inline"></i>' : '<i data-lucide="skip-forward" class="w-4 h-4 text-slate-400 inline"></i>';
                                    const color = log.type === 'error' ? 'text-rose-200' : 'text-slate-300';
                                    logsHtml += `<div class="flex items-start gap-2 ${color}">
                                        <div class="mt-0.5">${icon}</div>
                                        <div>
                                            <span class="font-medium">${log.identifier}:</span> ${log.reason}
                                        </div>
                                    </div>`;
                                });
                                logsHtml += `</div></td></tr>`;
                            }
                        } catch(e) {}
                    }

                    const hasLogs = logsHtml !== '';

                    contentHtml += `
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="py-3 text-white font-medium">
                                #${chunk.chunk_index}
                                ${hasLogs ? `<button onclick="toggleLogs(${chunk.id})" class="ml-2 text-[10px] bg-slate-700/50 hover:bg-slate-700 px-2 py-0.5 rounded border border-slate-600">View Logs</button>` : ''}
                            </td>
                            <td class="py-3">${getStatusBadge(chunk.status)}</td>
                            <td class="py-3 text-center text-slate-300">${chunk.processed_rows} / ${chunk.total_rows}</td>
                            <td class="py-3 text-center text-emerald-400">${chunk.created_count}</td>
                            <td class="py-3 text-center text-blue-400">${chunk.updated_count}</td>
                            <td class="py-3 text-center text-slate-400">${chunk.skipped_count}</td>
                            <td class="py-3 text-center text-rose-400">${chunk.error_count}</td>
                            <td class="py-3 text-right text-slate-400 text-xs">${formatDate(chunk.started_at)}</td>
                        </tr>
                        ${logsHtml}
                    `;
                });

                contentHtml += `</tbody></table>`;
            }

            document.getElementById('modal-content').innerHTML = contentHtml;
            document.getElementById('chunk-modal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('chunk-modal').classList.add('hidden');
        }

        function toggleLogs(chunkId) {
            const el = document.getElementById('logs-chunk-' + chunkId);
            if (el) {
                el.classList.toggle('hidden');
            }
        }

        async function fetchJobs() {
            try {
                const response = await fetch('/api/dashboard/data');
                const jobs = await response.json();
                globalJobs = jobs;
                
                const tbody = document.getElementById('jobs-table-body');
                tbody.innerHTML = '';

                // Update Widgets
                document.getElementById('widget-total').innerText = jobs.length;
                document.getElementById('widget-running').innerText = jobs.filter(j => j.status === 'processing').length;
                document.getElementById('widget-failed').innerText = jobs.filter(j => j.status === 'failed').length;

                if (jobs.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">No sync jobs found.</td></tr>';
                    return;
                }

                jobs.forEach(job => {
                    let progressPct = job.total_rows > 0 ? Math.round((job.processed_rows / job.total_rows) * 100) : 0;
                    if (progressPct > 100) progressPct = 100;
                    if (job.status === 'completed') progressPct = 100;

                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-white/5 transition-colors group';
                    tr.innerHTML = `
                        <td class="px-6 py-5 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 bg-blue-500/20 rounded-lg flex items-center justify-center border border-blue-500/30">
                                    <i data-lucide="file-spreadsheet" class="w-5 h-5 text-blue-400"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-bold text-white capitalize flex items-center gap-2">
                                        ${job.category.replace('_', ' ')}
                                        <span class="px-2 py-0.5 rounded text-[10px] bg-white/10 text-slate-300 border border-white/10">${job.type}</span>
                                    </div>
                                    <div class="text-xs text-slate-400 mt-1 flex items-center gap-2">
                                        <span>#${job.id}</span>
                                        <span>•</span>
                                        <span>${formatDate(job.created_at)}</span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-5 whitespace-nowrap">
                            ${getStatusBadge(job.status)}
                        </td>
                        <td class="px-6 py-5 whitespace-nowrap">
                            <div class="w-full max-w-[180px]">
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-blue-200">${progressPct}%</span>
                                    <span class="text-slate-400">${job.processed_chunks} / ${job.total_chunks} chunks</span>
                                </div>
                                <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden border border-white/5">
                                    <div class="bg-gradient-to-r from-blue-500 to-indigo-400 h-2 rounded-full progress-bar-transition" style="width: ${progressPct}%"></div>
                                </div>
                                <div class="text-[10px] text-slate-500 mt-1.5 text-right">${job.processed_rows} / ${job.total_rows} rows</div>
                            </div>
                        </td>
                        <td class="px-6 py-5 whitespace-nowrap">
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-slate-400">New:</span>
                                    <span class="font-medium text-emerald-400">${job.created_count}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-slate-400">Skip:</span>
                                    <span class="font-medium text-slate-300">${job.skipped_count}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-slate-400">Upd:</span>
                                    <span class="font-medium text-blue-400">${job.updated_count}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-slate-400">Err:</span>
                                    <span class="font-medium text-rose-400">${job.error_count}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-5 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            ${(job.status === 'queued' || job.status === 'processing') ? 
                            `<button onclick="cancelJobJS(${job.id})" class="text-rose-400 hover:text-rose-300 bg-rose-500/10 hover:bg-rose-500/20 px-3 py-1.5 rounded-lg border border-rose-500/20 transition-colors">
                                Cancel
                            </button>` : ''}
                            <button onclick="openModal(${job.id})" class="text-blue-400 hover:text-blue-300 bg-blue-500/10 hover:bg-blue-500/20 px-3 py-1.5 rounded-lg border border-blue-500/20 transition-colors">
                                Details
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });

                lucide.createIcons();
                document.getElementById('last-updated').innerText = new Date().toLocaleTimeString();
            } catch (error) {
                console.error("Error fetching dashboard data:", error);
            }
        }

        async function cancelJobJS(jobId) {
            if (!confirm('Are you sure you want to cancel this job? This will stop background processing.')) {
                return;
            }

            try {
                const response = await fetch(`/api/dashboard/jobs/${jobId}/cancel`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                if (response.ok) {
                    fetchJobs(); // Refresh immediately
                } else {
                    alert('Failed to cancel job.');
                }
            } catch (error) {
                console.error("Error cancelling job:", error);
                alert('Error cancelling job.');
            }
        }

        async function resetCooldownsJS() {
            if (!confirm('Are you sure you want to reset all active cooldowns? This will allow new jobs to start immediately.')) {
                return;
            }

            try {
                const response = await fetch('/api/dashboard/reset-cooldowns', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                if (response.ok) {
                    alert('Cooldowns reset successfully. You can now send new hits.');
                    fetchJobs(); // Refresh immediately
                } else {
                    alert('Failed to reset cooldowns.');
                }
            } catch (error) {
                console.error("Error resetting cooldowns:", error);
                alert('Error resetting cooldowns.');
            }
        }

        // Fetch immediately, then every 3 seconds
        fetchJobs();
        setInterval(fetchJobs, 3000);
    </script>
</body>
</html>
