<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\RiderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\SellerApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate(['status' => ['nullable', Rule::enum(OrderStatus::class)]]);
        $selectedStatus = $validated['status'] ?? null;

        $stats = $this->stats();

        $orders = Order::query()->with('buyer:id,name')
            ->when($selectedStatus, fn ($query) => $query->where('status', $selectedStatus))
            ->latest()->orderByDesc('id')->paginate(10)->withQueryString();

        $lowStockQuery = Product::query()->lowStock();
        $lowStockCount = (clone $lowStockQuery)->count();
        $lowStockProducts = $lowStockQuery->with('shop:id,name')->orderBy('available_stock')->orderBy('products.id')->limit(5)->get();

        return view('admin.dashboard', [
            'pendingSellers' => SellerApplication::where('status', 'pending')->count(),
            'stats' => $stats,
            'orders' => $orders,
            'statuses' => OrderStatus::cases(),
            'selectedStatus' => $selectedStatus,
            'lowStockCount' => $lowStockCount,
            'lowStockProducts' => $lowStockProducts,
        ]);
    }

    public function summary(): JsonResponse
    {
        $lowStockProducts = Product::query()->lowStock()
            ->with('shop:id,name')->orderBy('available_stock')->orderBy('products.id')->limit(5)->get();

        return response()->json([
            'stats' => $this->stats(),
            'lowStockCount' => Product::query()->lowStock()->count(),
            'lowStockProducts' => $lowStockProducts->map(fn (Product $product) => [
                'name' => $product->name,
                'shop' => $product->shop?->name ?? 'Unavailable shop',
                'stock' => $product->availableStock(),
                'url' => route('admin.products.index'),
            ])->values(),
        ])->header('Cache-Control', 'no-store, private');
    }

    private function stats(): array
    {
        return [
            'buyers' => User::query()->where('role', UserRole::Buyer)->count(),
            'products' => Product::query()->where('is_active', true)->count(),
            'orders' => Order::query()->count(),
            'pendingRiders' => RiderProfile::query()->where('status', RiderStatus::Pending)->count(),
        ];
    }
}
