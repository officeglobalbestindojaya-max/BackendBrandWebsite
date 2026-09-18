<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of products
     */
    public function index(Request $request)
    {
        $query = Product::with([
            'category',
            'materials',
            'images',
            'sizeImage'
        ]);

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->get();
        if ($products->isEmpty()) {
            $response['message'] = 'Products not found';
            $response['success'] = false;
            return response()->json($response, Response::HTTP_NOT_FOUND);
        }

        $response['success'] = true;
        $response['message'] = 'Products found';
        $response['data'] = $products;
        return response()->json($response, Response::HTTP_OK);
    }

    /**
     * Store a newly created product
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_name' => 'required|string|max:255',
            'product_code' => 'required|string|max:255|unique:products,product_code',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:255',
            'finishing' => 'nullable|string|max:255',
            'shopee_url' => 'nullable|string|max:255',
            'tokopedia_url' => 'nullable|string|max:255',
            'materials' => 'nullable|array',
            'materials.*' => 'exists:materials,id',
        ]);

        // Create product
        $product = Product::create([
            'product_name' => $validated['product_name'],
            'product_code' => $validated['product_code'],
            'category_id' => $validated['category_id'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? null,
            'finishing' => $validated['finishing'] ?? null,
            'shopee_url' => $validated['shopee_url'] ?? null,
            'tokopedia_url' => $validated['tokopedia_url'] ?? null,
        ]);

        // Attach materials if provided
        if (isset($validated['materials'])) {
            $product->materials()->attach($validated['materials']);
        }

        return response()->json($product->load(['category', 'materials']), 201);
    }

    /**
     * Display the specified product
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'product_name' => 'sometimes|required|string|max:255',
            'product_code' => 'sometimes|required|string|max:255|unique:products,product_code,' . $id,
            'category_id' => 'sometimes|required|exists:categories,id',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:255',
            'finishing' => 'nullable|string|max:255',
            'shopee_url' => 'nullable|string|max:255',
            'tokopedia_url' => 'nullable|string|max:255',
            'materials' => 'nullable|array',
            'materials.*' => 'exists:materials,id',
        ]);

        // Update basic fields
        $updateData = [];
        foreach (['product_name', 'product_code', 'category_id', 'description', 'color', 'finishing', 'shopee_url', 'tokopedia_url'] as $field) {
            if (isset($validated[$field])) {
                $updateData[$field] = $validated[$field];
            }
        }

        $product->update($updateData);

        // Sync materials if provided
        if (isset($validated['materials'])) {
            $product->materials()->sync($validated['materials']);
        }

        return response()->json($product->load(['category', 'materials']));
    }

    /**
     * Remove the specified product
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        // Delete all product images from storage
        foreach ($product->images as $image) {
            if ($image->image_url && Storage::disk('public')->exists($image->image_url)) {
                Storage::disk('public')->delete($image->image_url);
            }
        }

        // Delete size image from storage
        if ($product->sizeImage && $product->sizeImage->image_url) {
            if (Storage::disk('public')->exists($product->sizeImage->image_url)) {
                Storage::disk('public')->delete($product->sizeImage->image_url);
            }
        }

        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function filter(Request $request)
    {
        $query = Product::with(['category', 'materials', 'images', 'sizeImage']);

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by material
        if ($request->filled('material_id')) {
            $query->whereHas('materials', function ($q) use ($request) {
                $q->where('materials.id', $request->material_id);
            });
        }

        // Search by product name or product code
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('product_name', 'LIKE', '%' . $request->search . '%')
                    ->orWhere('product_code', 'LIKE', '%' . $request->search . '%');
            });
        }

        $products = $query->get();
        if ($products->isEmpty()) {
            $response['message'] = 'Products not found';
            $response['success'] = false;
            return response()->json($response, Response::HTTP_NOT_FOUND);
        }

        $response['success'] = true;
        $response['message'] = 'Products found';
        $response['total'] = $products->count();
        $response['data'] = $products;
        return response()->json($response, Response::HTTP_OK);
    }

    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1|max:255',
        ]);

        $products = Product::with(['category', 'images'])
            ->where('product_name', 'LIKE', '%' . $request->q . '%')
            ->get();

        if ($products->isEmpty()) {
            $response['message'] = 'Products not found';
            $response['success'] = false;
            return response()->json($response, Response::HTTP_NOT_FOUND);
        }

        $response['success'] = true;
        $response['message'] = 'Products found';
        $response['total'] = $products->count();
        $response['data'] = $products;
        return response()->json($response, Response::HTTP_OK);
    }

    public function recommendedProducts($currentProductId)
    {
        $recommendedProducts = Product::where('id', '!=', $currentProductId)
            ->with(['category', 'images', 'materials'])
            ->has('images') // pastikan punya gambar
            ->inRandomOrder()
            ->limit(4)
            ->get();

        $response['success'] = true;
        $response['total'] = $recommendedProducts->count();
        $response['data'] = $recommendedProducts;
        return response()->json($response, Response::HTTP_OK);
    }
}
