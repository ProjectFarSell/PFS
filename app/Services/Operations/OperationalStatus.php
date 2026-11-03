<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OperationalStatus
{
    public function inspect(): array
    {
        $result = ['database' => false, 'scheduler' => false, 'notification_worker' => false, 'failed_jobs' => null, 'failed_notifications' => null, 'stale_notifications' => null];
        try {
            DB::select('SELECT 1');
            $result['database'] = Schema::hasTable('orders') && Schema::hasTable('notification_deliveries');
            foreach (['scheduler', 'worker'] as $kind) {
                $seen = Cache::get('ops.'.$kind.'.last_seen');
                $result[$kind === 'worker' ? 'notification_worker' : 'scheduler'] = is_numeric($seen) && (int) $seen <= now()->timestamp && (int) $seen >= now()->timestamp - config('release.heartbeat_max_age');
            }
            $result['failed_jobs'] = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            $result['failed_notifications'] = DB::table('notification_deliveries')->where('status', 'failed')->count();
            $result['stale_notifications'] = DB::table('notification_deliveries')->whereIn('status', ['pending', 'queued', 'sending', 'retrying'])->where('updated_at', '<', now()->subSeconds(config('release.queue_max_age')))->count();
        } catch (Throwable) {
            $result['database'] = false;
        }
        $result['healthy'] = $result['database'] && (! $result['failed_jobs']) && (! $result['failed_notifications']) && (! $result['stale_notifications'])
            && (! config('operations.require_heartbeats') || ($result['scheduler'] && $result['notification_worker']));

        return $result;
    }
}
