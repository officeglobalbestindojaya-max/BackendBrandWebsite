<?php

namespace App\Http\Controllers;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class ProductImageController extends Controller
{
    /**
     * Display a listing of product images
     */
    public function index(Request $request)
    {
        $query = ProductImage::with('product');

        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $images = $query->orderBy('sort_order')->get();
        
        if ($images->isEmpty()) {
            $response['message'] = 'Images not found';
            $response['success'] = false;
            return response()->json($response, Response::HTTP_NOT_FOUND);
        }

        $response['success'] = true;
        $response['message'] = 'Images found';
        $response['data'] = $images;
        return response()->json($response, Response::HTTP_OK);
    }

    /**
     * Store a newly created product image
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'image_url' => 'required|file|mimes:jpeg,png,jpg,webp,gif',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Handle image upload
        if($request->hasFile('image_url')) {
            $uploadedFile = Cloudinary::upload($request->file('image_url')->getRealPath(), [
                'folder' => 'uploads/product-image',
            ]);

            // Ambil URL aman dan public_id untuk file yang diunggah
            $validated['image_url'] = $uploadedFile->getSecurePath(); // URL aman
        }

        // If sort_order is not provided, set it to the next available number
        if (!isset($validated['sort_order'])) {
            $maxOrder = ProductImage::where('product_id', $validated['product_id'])
                ->max('sort_order');
            $validated['sort_order'] = $maxOrder !== null ? $maxOrder + 1 : 0;
        }

        $image = ProductImage::create($validated);
        
        return response()->json($image, 201);
    }

    /**
     * Display the specified product image
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified product image
     */
    public function update(Request $request, $id)
    {
        $image = ProductImage::findOrFail($id);

        $validated = $request->validate([
            'product_id' => 'sometimes|required|exists:products,id',
            'image_url' => 'nullable|file|mimes:jpeg,png,jpg,webp,gif',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Handle image upload
        if ($request->hasFile('image_url')) {
            if ($image->image_url) {
                // Ekstrak public_id dari URL
                $fileUrl = $image->image_url;
                $publicId = substr(
                    $fileUrl,
                    strpos($fileUrl, 'uploads/product-image/'),
                    strrpos($fileUrl, '.') - strpos($fileUrl, 'uploads/product-image/')
                );

                // return($publicId);

                // Hapus file lama di Cloudinary
                Cloudinary::destroy($publicId);
            }

            // Upload gambar baru ke Cloudinary
            $uploadedFile = Cloudinary::upload($request->file('image_url')->getRealPath(), [
                'folder' => 'uploads/product-image',
            ]);
            $validated['image_url'] = $uploadedFile->getSecurePath(); // URL file baru
        } else {
            // Jika tidak ada file baru, tetap gunakan image_url lama
            $validated['image_url'] = $image->image_url;
        }

        $image->update($validated);
        
        return response()->json($image);
    }

    /**
     * Remove the specified product image
     */
    public function destroy($id)
    {
        $image = ProductImage::findOrFail($id);
        
        $fileUrl = $image->image_url;

        if($image){
            // Hapus gambar lama
            $publicId = substr($fileUrl, strpos($fileUrl, 'uploads/product-image/'), strrpos($fileUrl, '.') - strpos($fileUrl, 'uploads/product-image/'));

            // Hapus file lama di Cloudinary
            Cloudinary::destroy($publicId);

            $image->delete();
            $data["success"] = true;
            $data["message"] = "Product Image deleted successfully";
            return response()->json($data, Response::HTTP_OK);
        }else {
            $data["success"] = false;
            $data["message"] = "Product Image not found";
            return response()->json($data, Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Reorder product images
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'images' => 'required|array',
            'images.*.id' => 'required|exists:product_images,id',
            'images.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['images'] as $imageData) {
            ProductImage::where('id', $imageData['id'])
                ->update(['sort_order' => $imageData['sort_order']]);
        }

        return response()->json(['message' => 'Images reordered successfully']);
    }
}