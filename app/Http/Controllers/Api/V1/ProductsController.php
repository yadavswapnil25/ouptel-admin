<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class ProductsController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $query = Product::query()
            ->where('active', '1')
            ->orderByDesc('id');

        if ($request->filled('category')) {
            $query->where('category', (int) $request->query('category'));
        }

        if ($request->filled('sub_category')) {
            $query->where('sub_category', (int) $request->query('sub_category'));
        }

        if ($request->filled('type')) { // 0=physical,1=digital
            $query->where('type', (string) $request->query('type'));
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->query('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->query('max_price'));
        }

        $term = $request->query('term', $request->query('q'));
        if (!empty($term)) {
            $like = '%' . str_replace('%', '\\%', $term) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('description', 'like', $like)
                  ->orWhere('location', 'like', $like);
            });
        }

        if ($request->boolean('only_my', false)) {
            $authHeader = $request->header('Authorization');
            if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
                return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
            }
            $token = substr($authHeader, 7);
            $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
            if (!$userId) {
                return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
            }
            $query->where('user_id', (string) $userId);
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Product $product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => (float) $product->price,
                'price_formatted' => $product->price_formatted,
                'currency' => $product->currency,
                'location' => $product->location,
                'category' => $product->category,
                'sub_category' => $product->sub_category,
                'status' => $product->status_text,
                'type' => $product->type_text,
                'posted_at' => $product->posted_date,
                'main_image' => $product->main_image,
                'rating' => $product->average_rating,
                'reviews' => $product->reviews_count,
                'orders' => $product->orders_count,
                'total_sales' => (float) $product->total_sales,
                'user' => [
                    'user_id' => optional($product->user)->user_id,
                    'username' => optional($product->user)->username,
                    'avatar_url' => optional($product->user)->avatar_url,
                ],
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function meta(): JsonResponse
    {
        $categories = ProductCategory::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]);

        return response()->json([
            'ok' => true,
            'data' => [
                'types' => [0 => 'Physical', 1 => 'Digital'],
                'categories' => $categories,
            ],
        ]);
    }

    public function my(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $request->merge(['only_my' => true]);
        return $this->index($request);
    }

    public function purchased(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $orders = DB::table('Wo_UserOrders')
            ->join('Wo_Products', 'Wo_UserOrders.product_id', '=', 'Wo_Products.id')
            ->where('Wo_UserOrders.user_id', $userId)
            ->orderByDesc('Wo_UserOrders.id')
            ->select(
                'Wo_UserOrders.*',
                'Wo_Products.name as product_name',
                'Wo_Products.description as product_description',
                'Wo_Products.currency as product_currency',
                'Wo_Products.user_id as owner_id',
                'Wo_Products.id as product_pk'
            )
            ->paginate($perPage);

        $data = $orders->getCollection()->map(function ($row) {
            return [
                'order_id' => $row->id,
                'hash_id' => $row->hash_id,
                'status' => $row->status,
                'status_text' => $row->status, // raw text; could map
                'units' => (int) $row->units,
                'price' => (float) $row->price,
                'final_price' => (float) $row->final_price,
                'order_date' => date('c', (int) $row->time),
                'product' => [
                    'id' => (int) $row->product_pk,
                    'name' => $row->product_name,
                    'description' => $row->product_description,
                    'currency' => $row->product_currency,
                ],
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'integer'],
            'sub_category' => ['nullable', 'integer'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'type' => ['required', 'integer', 'in:0,1'],
            'units' => ['nullable', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['string'],
        ]);

        $product = new Product();
        $product->user_id = (string) $userId;
        $product->name = $validated['name'];
        $product->description = $validated['description'] ?? '';
        $product->category = $validated['category'];
        $product->sub_category = $validated['sub_category'] ?? 0;
        $product->price = (float) $validated['price'];
        $product->currency = $validated['currency'];
        $product->type = (string) $validated['type'];
        $product->units = isset($validated['units']) ? (string) $validated['units'] : '0';
        $product->location = $validated['location'] ?? '';
        $product->status = '1'; // Active
        $product->active = '1';
        $product->time = (string) time();
        $product->save();

        if (!empty($validated['images'])) {
            foreach ($validated['images'] as $img) {
                ProductMedia::create([
                    'product_id' => $product->id,
                    'image' => $img,
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'message' => 'Product created successfully',
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'price_formatted' => $product->price_formatted,
                'currency' => $product->currency,
                'type' => $product->type_text,
                'posted_at' => $product->posted_date,
                'main_image' => $product->main_image,
            ],
        ], 201);
    }
}


