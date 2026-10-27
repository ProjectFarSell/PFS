<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class BuyerController extends Controller
{
    public function index(): View
    {
        return view('admin.buyers.index', [
            'buyers' => User::query()->where('role', UserRole::Buyer)
                ->withCount('orders')->latest('id')->paginate(20),
        ]);
    }
}
