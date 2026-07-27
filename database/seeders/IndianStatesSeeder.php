<?php

namespace Database\Seeders;

use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IndianStatesSeeder extends Seeder
{
    private const INDIA_COUNTRY_ID = 99;

    public function run(): void
    {
        if (!Schema::hasTable('Wo_States')) {
            return;
        }

        foreach ($this->getIndianStates() as $stateName => $pageTitle) {
            $existing = State::query()
                ->where('country_id', self::INDIA_COUNTRY_ID)
                ->where('name', $stateName)
                ->first();

            $photoPath = $this->resolvePhotoPath($stateName, $pageTitle, $existing?->photo);

            State::query()->updateOrCreate(
                [
                    'country_id' => self::INDIA_COUNTRY_ID,
                    'name' => $stateName,
                ],
                [
                    'photo' => $photoPath,
                ],
            );
        }
    }

    private function resolvePhotoPath(string $stateName, string $pageTitle, ?string $existingPhoto): ?string
    {
        $photoPath = $existingPhoto;

        if ($existingPhoto && Storage::disk('public')->exists($existingPhoto)) {
            return $photoPath;
        }

        $imageUrl = $this->resolveWikipediaImageUrl($pageTitle);
        if ($imageUrl) {
            try {
                $response = Http::timeout(30)
                    ->retry(2, 500)
                    ->get($imageUrl);
            } catch (\Throwable) {
                $response = null;
            }

            if ($response && $response->successful() && $response->body() !== '') {
                $extension = $this->detectExtension($response->header('Content-Type'), $imageUrl);
                $photoPath = 'upload/states/' . Str::slug($stateName) . '.' . $extension;

                Storage::disk('public')->put($photoPath, $response->body());
            }
        }

        return $photoPath;
    }

    private function resolveWikipediaImageUrl(string $pageTitle): ?string
    {
        try {
            $response = Http::acceptJson()
                ->timeout(20)
                ->retry(2, 500)
                ->get('https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode($pageTitle));
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        return $data['originalimage']['source']
            ?? $data['thumbnail']['source']
            ?? null;
    }

    private function detectExtension(?string $contentType, string $url): string
    {
        $normalizedType = strtolower(trim((string) $contentType));

        return match (true) {
            str_contains($normalizedType, 'image/png') => 'png',
            str_contains($normalizedType, 'image/webp') => 'webp',
            str_contains($normalizedType, 'image/svg+xml') => 'svg',
            str_contains($normalizedType, 'image/gif') => 'gif',
            str_contains($normalizedType, 'image/jpeg'),
            str_contains($normalizedType, 'image/jpg') => 'jpg',
            default => $this->detectExtensionFromUrl($url),
        };
    }

    private function detectExtensionFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'], true)) {
            return 'jpg';
        }

        if ($extension === 'jpeg') {
            return 'jpg';
        }

        return $extension;
    }

    private function getIndianStates(): array
    {
        return [
            'Andhra Pradesh' => 'Andhra Pradesh',
            'Arunachal Pradesh' => 'Arunachal Pradesh',
            'Assam' => 'Assam',
            'Bihar' => 'Bihar',
            'Chhattisgarh' => 'Chhattisgarh',
            'Goa' => 'Goa',
            'Gujarat' => 'Gujarat',
            'Haryana' => 'Haryana',
            'Himachal Pradesh' => 'Himachal Pradesh',
            'Jharkhand' => 'Jharkhand',
            'Karnataka' => 'Karnataka',
            'Kerala' => 'Kerala',
            'Madhya Pradesh' => 'Madhya Pradesh',
            'Maharashtra' => 'Maharashtra',
            'Manipur' => 'Manipur',
            'Meghalaya' => 'Meghalaya',
            'Mizoram' => 'Mizoram',
            'Nagaland' => 'Nagaland',
            'Odisha' => 'Odisha',
            'Punjab' => 'Punjab, India',
            'Rajasthan' => 'Rajasthan',
            'Sikkim' => 'Sikkim',
            'Tamil Nadu' => 'Tamil Nadu',
            'Telangana' => 'Telangana',
            'Tripura' => 'Tripura',
            'Uttar Pradesh' => 'Uttar Pradesh',
            'Uttarakhand' => 'Uttarakhand',
            'West Bengal' => 'West Bengal',
            'Andaman and Nicobar Islands' => 'Andaman and Nicobar Islands',
            'Chandigarh' => 'Chandigarh',
            'Dadra and Nagar Haveli and Daman and Diu' => 'Dadra and Nagar Haveli and Daman and Diu',
            'Delhi' => 'Delhi',
            'Jammu and Kashmir' => 'Jammu and Kashmir',
            'Ladakh' => 'Ladakh',
            'Lakshadweep' => 'Lakshadweep',
            'Puducherry' => 'Puducherry',
        ];
    }
}
