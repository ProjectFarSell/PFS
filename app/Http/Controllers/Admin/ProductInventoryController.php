<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductInventoryController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['visibility' => ['nullable', 'in:active,hidden,inactive']]);
        $visibility = $data['visibility'] ?? 'active';

        return view('admin.products.index', [
            'visibility' => $visibility,
            'products' => Product::query()->when($visibility === 'hidden', fn ($query) => $query->whereNotNull('admin_hidden_at'))
                ->when($visibility === 'inactive', fn ($query) => $query->where('is_active', false)->whereNull('admin_hidden_at'))
                ->when($visibility === 'active', fn ($query) => $query->where('is_active', true))->withAvailableStock()
                ->with(['shop:id,name', 'category:id,name', 'variants' => fn ($query) => $query->where('is_active', true)->orderBy('id')])
                ->orderBy('name')->paginate(20),
        ]);
    }
}
