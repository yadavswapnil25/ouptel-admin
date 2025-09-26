<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\OfferCategory;
use App\Models\OfferApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OffersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $type = $request->query('type', 'all');
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $query = Offer::query();

        if ($type === 'my_offers') {
            // Note: user_id column might not exist in Wo_Offers table
            // Return empty results for now
            $query->where('id', 0); // This will return no results
        } elseif ($type === 'applied_offers') {
            // Note: Wo_Offer_Apply table might not exist
            // Return empty results for now
            $query->where('id', 0); // This will return no results
        } elseif ($type === 'saved_offers') {
            // Note: Wo_Offer_Apply table might not exist
            // Return empty results for now
            $query->where('id', 0); // This will return no results
        } else {
            // Return all offers
        }

        // Note: category column might not exist in Wo_Offers table
        // if ($request->filled('category')) {
        //     $query->where('category_id', $request->query('category'));
        // }

        // Note: location column doesn't exist in Wo_Offers table
        // if ($request->filled('location')) {
        //     $query->where('location', 'like', '%' . $request->query('location') . '%');
        // }

        // Note: price column doesn't exist in Wo_Offers table
        // if ($request->filled('price_min')) {
        //     $query->where('price', '>=', $request->query('price_min'));
        // }

        // if ($request->filled('price_max')) {
        //     $query->where('price', '<=', $request->query('price_max'));
        // }

        if ($request->filled('term')) {
            $like = '%' . str_replace('%', '\\%', $request->query('term')) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('description', 'like', $like);
                // Note: title and company columns don't exist in Wo_Offers table
            });
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Offer $offer) use ($tokenUserId) {
            return [
                'id' => $offer->id,
                'title' => 'Offer Title', // Default value since column doesn't exist
                'description' => $offer->description,
                'price' => 0, // Default value since column doesn't exist
                'currency' => $offer->currency ?? 'USD',
                'location' => 'Unknown', // Default value since column doesn't exist
                'status' => 'active', // Default value since column doesn't exist
                'expire_date' => $offer->expire_date,
                'expire_time' => $offer->expire_time_as_timestamp,
                'applications_count' => $offer->applications_count,
                'is_applied' => $offer->is_applied,
                'is_owner' => false, // Simplified since user_id doesn't exist
                'owner' => [
                    'user_id' => null,
                    'username' => 'Unknown',
                    'avatar_url' => null,
                ],
                'created_at' => $offer->time ? date('c', $offer->time_as_timestamp) : null,
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
            'description' => ['required', 'string', 'max:2000'],
            'currency' => ['nullable', 'string', 'max:3'],
            // Note: title, price, location, and category columns don't exist in Wo_Offers table
        ]);

        // Note: title column doesn't exist in Wo_Offers table
        // Skip title uniqueness check

        $offer = new Offer();
        // Note: title column doesn't exist in Wo_Offers table
        $offer->description = $validated['description'];
        // Note: price column doesn't exist in Wo_Offers table
        $offer->currency = $validated['currency'] ?? 'USD';
        // Note: location column doesn't exist in Wo_Offers table
        // Note: user_id column might not exist in Wo_Offers table
        // Note: status column doesn't exist in Wo_Offers table
        $offer->expire_date = date('Y-m-d H:i:s', strtotime('+30 days')); // Set expire date to 30 days from now
        $offer->expire_time = date('Y-m-d H:i:s', strtotime('+30 days')); // Set expire time to 30 days from now (datetime format)
        $offer->time = (string) time();
        $offer->save();

        return response()->json([
            'ok' => true,
            'message' => 'Offer created successfully',
            'data' => [
                'id' => $offer->id,
                'title' => 'Offer Title', // Default value since column doesn't exist
                'description' => $offer->description,
                'price' => 0, // Default value since column doesn't exist
                'currency' => $offer->currency,
                'location' => 'Unknown', // Default value since column doesn't exist
                'status' => 'active', // Default value since column doesn't exist
                'applications_count' => 0,
                'is_applied' => false,
                'is_owner' => false, // Simplified since user_id doesn't exist
                'owner' => [
                    'user_id' => null,
                    'username' => 'Unknown',
                    'avatar_url' => null,
                ],
                'created_at' => $offer->time ? date('c', $offer->time_as_timestamp) : null,
            ],
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $offer = Offer::where('id', $id)->first();
        if (!$offer) {
            return response()->json(['ok' => false, 'message' => 'Offer not found'], 404);
        }

        // Note: Wo_Offer_Apply table doesn't exist, so return empty applications
        $applicationsData = [];
        $applications = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([]), // Empty collection
            0, // Total count
            12, // Per page
            1, // Current page
            ['path' => $request->url()]
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'offer' => [
                    'id' => $offer->id,
                    'title' => 'Offer Title', // Default value since column doesn't exist
                    'description' => $offer->description,
                    'price' => 0, // Default value since column doesn't exist
                    'currency' => $offer->currency ?? 'USD',
                    'location' => 'Unknown', // Default value since column doesn't exist
                    'status' => 'active', // Default value since column doesn't exist
                    'applications_count' => $offer->applications_count,
                    'is_applied' => $offer->is_applied,
                    'is_owner' => false, // Simplified since user_id doesn't exist
                    'owner' => [
                        'user_id' => null,
                        'username' => 'Unknown',
                        'avatar_url' => null,
                    ],
                    'created_at' => $offer->time ? date('c', $offer->time_as_timestamp) : null,
                ],
                'applications' => $applicationsData,
                'meta' => [
                    'current_page' => $applications->currentPage(),
                    'per_page' => $applications->perPage(),
                    'total' => $applications->total(),
                    'last_page' => $applications->lastPage(),
                ],
            ],
        ]);
    }

    public function applications(Request $request, $id): JsonResponse
    {
        $offer = Offer::where('id', $id)->first();
        if (!$offer) {
            return response()->json(['ok' => false, 'message' => 'Offer not found'], 404);
        }

        // Note: Wo_Offer_Apply table doesn't exist, so return empty applications
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([]), // Empty collection
            0, // Total count
            $perPage, // Per page
            1, // Current page
            ['path' => $request->url()]
        );

        $data = [];

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

    public function apply(Request $request, $id): JsonResponse
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

        $offer = Offer::where('id', $id)->first();
        if (!$offer) {
            return response()->json(['ok' => false, 'message' => 'Offer not found'], 404);
        }

        // Note: Wo_Offer_Apply table doesn't exist, so return error
        return response()->json([
            'ok' => false,
            'message' => 'Application feature is currently unavailable. Please try again later.'
        ], 503);
    }

    public function search(Request $request): JsonResponse
    {
        $term = $request->query('term', '');
        if (empty($term)) {
            return response()->json([
                'data' => [
                    'offers' => [],
                    'applications' => [],
                ],
            ]);
        }

        $like = '%' . str_replace('%', '\\%', $term) . '%';

        // Search offers
        $offers = Offer::where(function ($q) use ($like) {
                $q->where('description', 'like', $like);
                // Note: title and company columns don't exist in Wo_Offers table
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (Offer $offer) {
                return [
                    'id' => $offer->id,
                    'title' => 'Offer Title', // Default value since column doesn't exist
                    'price' => 0, // Default value since column doesn't exist
                    'currency' => $offer->currency ?? 'USD',
                    'location' => 'Unknown', // Default value since column doesn't exist
                    'type' => 'offer',
                    'created_at' => $offer->time ? date('c', $offer->time_as_timestamp) : null,
                ];
            });

        // Note: Wo_Offer_Apply table might not exist
        // Return empty applications data
        $applications = collect([]);

        $allResults = collect()
            ->merge($offers)
            ->merge($applications);

        return response()->json([
            'data' => [
                'offers' => $offers,
                'applications' => $applications,
                'total' => $allResults->count(),
            ],
        ]);
    }

    public function myApplications(Request $request): JsonResponse
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

        // Note: Wo_Offer_Apply table doesn't exist, so return empty applications
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([]), // Empty collection
            0, // Total count
            $perPage, // Per page
            1, // Current page
            ['path' => $request->url()]
        );

        $data = [];

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
        // Note: Wo_OfferCategories table might not exist
        // Return hardcoded categories
        $categories = [
            [
                'id' => 1,
                'name' => 'Services',
                'description' => 'Professional services and consulting',
            ],
            [
                'id' => 2,
                'name' => 'Products',
                'description' => 'Physical and digital products',
            ],
            [
                'id' => 3,
                'name' => 'Digital',
                'description' => 'Digital products and services',
            ],
            [
                'id' => 4,
                'name' => 'Creative',
                'description' => 'Creative services and artwork',
            ],
            [
                'id' => 5,
                'name' => 'Technical',
                'description' => 'Technical services and solutions',
            ],
        ];

        $currencies = [
            ['value' => 'USD', 'label' => 'US Dollar'],
            ['value' => 'EUR', 'label' => 'Euro'],
            ['value' => 'GBP', 'label' => 'British Pound'],
            ['value' => 'CAD', 'label' => 'Canadian Dollar'],
            ['value' => 'AUD', 'label' => 'Australian Dollar'],
        ];

        return response()->json([
            'data' => [
                'categories' => $categories,
                'currencies' => $currencies,
            ],
        ]);
    }
}
