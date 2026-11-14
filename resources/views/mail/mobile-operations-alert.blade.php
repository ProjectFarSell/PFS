FarSell mobile delivery needs attention.

Pending events: {{ $health['pending'] }}
Failed events: {{ $health['failed'] }}
Oldest pending age (seconds): {{ $health['oldest_pending_age_seconds'] ?? 'none' }}
Mobile worker healthy: {{ $health['worker_healthy'] ? 'yes' : 'no' }}

Review the protected admin notification monitor and the mobile queue worker.
{{ route('admin.notifications.index') }}

This alert contains operational counts only, no account credentials or delivery details.
