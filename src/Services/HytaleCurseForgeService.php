<?php

namespace OneOfOne\HytaleCurseForge\Services;

use App\Models\Server;
use Exception;
use Illuminate\Support\Facades\Http;
/**
 * Fixed by Eranio
 */
class HytaleCurseForgeService
{
    private const BASE_URL = 'https://api.curseforge.com';

    /**
     * CurseForge requires a real User-Agent header.
     */
    private const USER_AGENT = '1of1Servers-Pelican-HytaleCF/1.0 (+https://1of1servers.com)';

    /**
     * Get API Key from config or fallback to .env
     */
    private function getApiKey(): string
    {
        return (string) config('minecraft-modpacks.curseforge_api_key', env('CURSEFORGE_API_KEY', ''));
    }

    public function getHytaleGameId(): int
    {
        return 70216;
    }

    public function getModsPath(): string
    {
        return 'mods';
    }

    /**
     * Fetch categories from CurseForge.
     */
    public function getCategories(?string $browseBy = null): array
    {
        $apiKey = $this->getApiKey();
        if ($apiKey === '') return [];

        $query = ['gameId' => $this->getHytaleGameId()];

        if ($browseBy && $browseBy !== 'all') {
            $classId = $this->getClassIdForBrowseByOrNull($browseBy);
            if ($classId !== null) $query['classId'] = $classId;
        }

        try {
            $response = Http::asJson()
                ->timeout(15)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'x-api-key' => $apiKey,
                    'User-Agent' => self::USER_AGENT,
                ])
                ->get(self::BASE_URL . '/v1/categories', $query);

            if (!$response->successful()) return [];

            $options = [];
            foreach ($response->json()['data'] ?? [] as $category) {
                if (isset($category['id'], $category['name'])) {
                    $options[(int) $category['id']] = $category['name'];
                }
            }

            asort($options);
            return $options;
        } catch (Exception $e) {
            report($e);
            return [];
        }
    }

    /**
     * Check if the server is allowed to use this plugin.
     */
    public function isHytaleServer(Server $server): bool
    {
        $server->loadMissing('egg');
        $features = $server->egg->features ?? [];
        $tags = $server->egg->tags ?? [];

        $markers = ['curseforge_mods', 'curse_forge_mod_plugin', 'hytale'];

        foreach ($markers as $marker) {
            if (in_array($marker, $features, true) || in_array($marker, $tags, true)) {
                return true;
            }
        }

        return str($server->egg->name ?? '')->lower()->contains('hytale');
    }

    /**
     * Search for mods with full filter support.
     */
    public function searchMods(int $page = 1, string $search = '', array $filters = []): array
    {
        $apiKey = $this->getApiKey();
        $pageSize = 10;
        $index = ($page - 1) * $pageSize;

        if ($apiKey === '') return ['data' => [], 'total' => 0];

        $query = [
            'gameId' => $this->getHytaleGameId(),
            'pageSize' => $pageSize,
            'index' => $index,
        ];

        if (!empty($search)) $query['searchFilter'] = $search;

        // Class ID mapping
        $browseBy = $filters['browseBy'] ?? 'all';
        if ($browseBy !== 'all') {
            $classId = $this->getClassIdForBrowseByOrNull($browseBy);
            if ($classId) $query['classId'] = $classId;
        }

        // Sorting mapping
        $sortByValue = $this->getSortByValue($filters['sortBy'] ?? 'relevance');
        if ($sortByValue) {
            $query['sortBy'] = $sortByValue;
            $query['sortOrder'] = 'desc';
        }

        // Categories (CurseForge API only allows one categoryId at a time in search)
        $categories = (array) ($filters['categories'] ?? []);
        if (!empty($categories)) {
            // We use the first selected category for the API request
            $query['categoryId'] = (int) $categories[0];
        }

        return $this->performSearch($query, $pageSize, $index);
    }

    private function performSearch(array $query, int $pageSize, int $index): array
    {
        $apiKey = $this->getApiKey();

        try {
            $response = Http::asJson()
                ->timeout(15)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'x-api-key' => $apiKey,
                    'User-Agent' => self::USER_AGENT,
                ])
                ->get(self::BASE_URL . '/v1/mods/search', $query);

            if (!$response->successful()) return ['data' => [], 'total' => 0];

            $json = $response->json();
            return [
                'data' => $json['data'] ?? [],
                'total' => (int) ($json['pagination']['totalCount'] ?? 0),
            ];
        } catch (Exception $e) {
            report($e);
            return ['data' => [], 'total' => 0];
        }
    }

    public function getBestFileId(array $modRecord): ?int
    {
        return $modRecord['mainFileId'] ?? $modRecord['latestFiles'][0]['id'] ?? null;
    }

    public function getDownloadUrl(int $modId, int $fileId): ?string
    {
        $apiKey = $this->getApiKey();
        try {
            $response = Http::asJson()
                ->timeout(20)
                ->withHeaders(['x-api-key' => $apiKey, 'User-Agent' => self::USER_AGENT])
                ->get(self::BASE_URL . "/v1/mods/$modId/files/$fileId/download-url");

            return $response->json()['data'] ?? null;
        } catch (Exception $e) {
            report($e);
            return null;
        }
    }

    private function getClassIdForBrowseByOrNull(string $browseBy): ?int {
        $map = ['mods' => 6, 'prefabs' => 17, 'worlds' => 18, 'bootstrap' => 19, 'translations' => 20];
        return $map[$browseBy] ?? null;
    }

    private function getSortByValue(string $sortBy): ?int {
        $map = ['downloads' => 6, 'updated' => 3, 'newest' => 4];
        return $map[$sortBy] ?? null;
    }
}