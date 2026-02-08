<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class AlbumController extends BaseController
{
    public function store(Request $request): JsonResponse
    {
        // Authenticate via bearer token (Wo_AppsSessions)
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = \Illuminate\Support\Facades\DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $validated = $request->validate([
            'album_name' => ['required', 'string', 'max:255'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'], // 2MB max per image
        ]);

        $post = new Post();
        $post->user_id = (int) $userId;
        $post->album_name = $validated['album_name'];
        $post->multi_image_post = !empty($validated['images']) ? 1 : 0;
        // Do not store concatenated images in Wo_Posts.multi_image; persist in Wo_Albums_Media
        // Some legacy schemas expect a string '0'/'1' here, not numeric
        $post->multi_image = $post->multi_image_post ? '1' : '0';
        
        // Handle file uploads and store paths
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $imageFile) {
                $imageName = 'album_' . time() . '_' . uniqid() . '_' . $index . '.' . $imageFile->getClientOriginalExtension();
                $imagePath = $imageFile->storeAs('albums', $imageName, 'public');
                $imagePaths[] = $imagePath;
            }
        }
        
        if (!empty($imagePaths)) {
            $post->postPhoto = $imagePaths[0]; // First image as main photo
        }
        $post->postType = 'album';
        $post->active = 1;
        // Legacy schema stores time as a Unix timestamp integer
        $post->time = now()->timestamp;
        $post->save();

        // Ensure legacy non-zero post_id is set
        if (empty($post->post_id) || $post->post_id === 0) {
            $post->post_id = $post->id; // fallback to PK to match legacy expectations
            $post->save();
        }

        // Insert album media records
        if (!empty($imagePaths)) {
            foreach ($imagePaths as $path) {
                \App\Models\AlbumMedia::create([
                    'post_id' => $post->id,
                    'image' => $path,
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $post->id,
                'post_id' => $post->post_id,
                'album_name' => $post->album_name,
                'multi_image_post' => (bool) $post->multi_image_post,
                'images_count' => count($imagePaths),
                'image_urls' => array_map(function($path) {
                    return asset('storage/' . $path);
                }, $imagePaths),
                'created_at' => optional($post->time)->toIso8601String(),
            ],
        ], 201);
    }
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $query = Post::query()
            ->whereNotNull('album_name')
            ->where('album_name', '!=', '')
            ->where('multi_image_post', 1)
            ->where('active', 1)
            ->orderByDesc('id');
        // Authenticate via bearer token stored in Wo_Sessions
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
            if ($userId) {
                $query->where('user_id', $userId);
            }
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Post $post) {
            // Get album images from AlbumMedia table
            $albumImages = \App\Models\AlbumMedia::where('post_id', $post->id)->get();
            $imageUrls = $albumImages->map(function($albumImage) {
                return asset('storage/' . $albumImage->image);
            })->toArray();

            return [
                'id' => $post->id,
                'post_id' => $post->post_id,
                'album_name' => $post->album_name,
                'cover_image' => $post->post_image_url,
                'images_count' => $albumImages->count(),
                'image_urls' => $imageUrls,
                'created_at' => optional($post->time)->toIso8601String(),
                'user' => [
                    'user_id' => optional($post->user)->user_id,
                    'username' => optional($post->user)->username,
                    'avatar_url' => optional($post->user)->avatar_url,
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

    public function show(Request $request, $id): JsonResponse
    {
        // Find the album (stored as Post with album_name)
        $album = Post::where('id', $id)
            ->whereNotNull('album_name')
            ->where('album_name', '!=', '')
            ->where('multi_image_post', 1)
            ->where('active', 1)
            ->first();

        if (!$album) {
            return response()->json([
                'ok' => false,
                'message' => 'Album not found',
            ], 404);
        }

        // Get album images from AlbumMedia table
        $albumImages = \App\Models\AlbumMedia::where('post_id', $album->id)
            ->orderBy('id')
            ->get();

        $imageUrls = $albumImages->map(function($albumImage) {
            return [
                'id' => $albumImage->id,
                'image_url' => asset('storage/' . $albumImage->image),
            ];
        })->toArray();

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $album->id,
                'post_id' => $album->post_id,
                'album_name' => $album->album_name,
                'description' => $album->postText ?? '',
                'cover_image' => $album->post_image_url,
                'images_count' => $albumImages->count(),
                'images' => $imageUrls,
                'created_at' => $album->time ? \Carbon\Carbon::createFromTimestamp($album->time)->toIso8601String() : null,
                'user' => [
                    'user_id' => optional($album->user)->user_id,
                    'username' => optional($album->user)->username ?? 'Unknown',
                    'name' => optional($album->user)->name ?? optional($album->user)->username ?? 'Unknown',
                    'avatar_url' => optional($album->user)->avatar_url,
                ],
            ],
        ]);
    }
}


