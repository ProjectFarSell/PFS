<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::query()
            ->with(['shop', 'category'])
            ->visible();

        if ($search = $request->string('q')->toString()) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if ($category = $request->integer('category')) {
            $query->where('category_id', $category);
        }

        return view('catalog.index', [
            'products' => $query->latest()->paginate(16)->withQueryString(),
            'categories' => Category::query()->orderBy('sort_order')->get(),
            'q' => $search ?? '',
            'activeCategory' => $request->integer('category') ?: null,
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active && $product->shop?->is_active, 404);

        $product->load(['shop', 'category']);

        $related = Product::query()
            ->with('shop')
            ->visible()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->take(8)
            ->get();

        return view('catalog.show', compact('product', 'related'));
    }
}
