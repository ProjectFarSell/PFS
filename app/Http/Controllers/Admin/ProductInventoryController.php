<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;

class ProductInventoryController extends Controller
{
    public function index(): View
    {
        return view('admin.products.index', [
            'products' => Product::query()->where('is_active', true)->withAvailableStock()
                ->with(['shop:id,name', 'category:id,name', 'variants' => fn ($query) => $query->where('is_active', true)->orderBy('id')])
                ->orderBy('name')->paginate(20),
        ]);
    }
}
