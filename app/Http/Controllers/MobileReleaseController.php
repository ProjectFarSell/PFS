<?php

namespace App\Http\Controllers;

class MobileReleaseController extends Controller
{
    public function android()
    {
        $apps = [];
        foreach (['', 'rider_'] as $prefix) {
            $package = (string) config('mobile.'.$prefix.'android_package');
            $fingerprints = config('mobile.'.$prefix.'android_fingerprints');
            if ($package === '' && ! $fingerprints) {
                continue;
            }
            abort_unless(preg_match('/^[a-zA-Z][a-zA-Z0-9_]*(\.[a-zA-Z][a-zA-Z0-9_]*)+$/', $package) && $fingerprints && collect($fingerprints)->every(fn ($value) => preg_match('/^([A-F0-9]{2}:){31}[A-F0-9]{2}$/', $value)), 503, 'App links are not configured yet.');
            $apps[] = ['relation' => ['delegate_permission/common.handle_all_urls'], 'target' => ['namespace' => 'android_app', 'package_name' => $package, 'sha256_cert_fingerprints' => $fingerprints]];
        }
        abort_unless($apps, 503, 'App links are not configured yet.');

        return response()->json($apps);
    }

    public function ios()
    {
        $ids = config('mobile.ios_app_ids');
        abort_unless($ids && collect($ids)->every(fn ($value) => preg_match('/^[A-Z0-9]{10}\.[a-zA-Z0-9_.-]+$/', $value)), 503, 'App links are not configured yet.');

        return response()->json(['applinks' => ['apps' => [], 'details' => array_map(fn ($id) => ['appID' => $id, 'paths' => ['/p/*', '/orders/*']], $ids)]]);
    }
}
