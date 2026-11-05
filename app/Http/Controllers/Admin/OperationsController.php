<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Operations\OperationalStatus;
use App\Services\Operations\ReleaseReadiness;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function __invoke(OperationalStatus $status, ReleaseReadiness $release): View
    {
        return view('admin.operations', ['status' => $status->inspect(), 'checks' => $release->checks(), 'revision' => config('release.version')]);
    }
}
