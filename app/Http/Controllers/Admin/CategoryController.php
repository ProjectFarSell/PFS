<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()->withCount('products')->orderBy('sort_order')->orderBy('name')->paginate(20);
        $applicationCategoryIds = SellerApplication::query()->whereNotNull('category_ids')->get(['category_ids'])
            ->flatMap(fn (SellerApplication $application) => array_map('intval', $application->category_ids ?? []))
            ->countBy();
        $categories->getCollection()->each(fn (Category $category) => $category->setAttribute(
            'applications_count',
            $applicationCategoryIds->get($category->id, 0)
        ));

        return view('admin.categories.index', [
            'categories' => $categories,
            'replacementCategories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedCategory($request);

        $slug = Str::slug($data['name']);
        if ($slug === '' || Category::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'A category with that name or URL slug already exists.']);
        }

        Category::query()->create([
            'name' => trim($data['name']),
            'slug' => $slug,
            'icon' => filled($data['icon'] ?? null) ? trim($data['icon']) : null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return to_route('admin.categories.index')->with('status', 'Category created. It is now available in product listings and seller applications.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validatedCategory($request);
        $slug = Str::slug($data['name']);
        if ($slug === '' || Category::query()->where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
            throw ValidationException::withMessages(['name' => 'A category with that name or URL slug already exists.']);
        }

        $category->update([
            'name' => trim($data['name']),
            'slug' => $slug,
            'icon' => filled($data['icon'] ?? null) ? trim($data['icon']) : null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return to_route('admin.categories.index')->with('status', 'Category updated.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'replacement_category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        DB::transaction(function () use ($category, $data) {
            $category = Category::query()->lockForUpdate()->findOrFail($category->id);
            $applications = SellerApplication::query()
                ->whereJsonContains('category_ids', $category->id)
                ->lockForUpdate()->get();
            $hasProducts = $category->products()->exists();
            $isUsedByApplications = $applications->isNotEmpty();
            $replacement = null;

            if ($hasProducts || $isUsedByApplications) {
                $replacementId = (int) ($data['replacement_category_id'] ?? 0);
                if (! $replacementId || $replacementId === $category->id) {
                    throw ValidationException::withMessages([
                        'replacement_category_id' => 'Choose another category to receive this category’s products and seller-application selections.',
                    ]);
                }
                $replacement = Category::query()->lockForUpdate()->findOrFail($replacementId);
            }

            if ($hasProducts) {
                Product::query()->where('category_id', $category->id)->update(['category_id' => $replacement->id]);
            }

            foreach ($applications as $application) {
                $categoryIds = array_map('intval', $application->category_ids ?? []);
                $categoryIds = array_values(array_unique(array_map(
                    fn (int $id) => $id === $category->id ? $replacement->id : $id,
                    $categoryIds
                )));
                $application->update(['category_ids' => $categoryIds]);
            }

            $category->delete();
        });

        return to_route('admin.categories.index')->with('status', 'Category deleted. Any linked listings and applications were moved to the replacement category.');
    }

    private function validatedCategory(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'icon' => ['nullable', 'string', 'max:8'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }
}
