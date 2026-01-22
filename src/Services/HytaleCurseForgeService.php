<?php

namespace OneOfOne\HytaleCurseForge\Services;

use App\Models\Server;
use Exception;
use Illuminate\Support\Facades\Http;

class HytaleCurseForgeService
{
    private const BASE_URL = 'https://api.curseforge.com';

    /**
     * CurseForge requires a real User-Agent header.
     */
    private const USER_AGENT = '1of1Servers-Pelican-HytaleCF/1.0 (+https://1of1servers.com)';

    public function getHytaleGameId(): int
    {
        return (int) config('services.curseforge.hytale_game_id', 70216);
    }

    public function getModsPath(): string
    {
        // In Pelican Files, "mods" means /files/mods
        return (string) config('services.hytale.mods_path', 'mods');
    }

    /**
     * Fetch category options from CurseForge for Hytale.
     *
     * @return array<int, string>
     */
    public function getCategories(?string $browseBy = null): array
    {
        $apiKey = (string) config('services.curseforge.key', '');

        if ($apiKey === '') {
            return [];
        }

        $query = [
            'gameId' => $this->getHytaleGameId(),
        ];

        if ($browseBy && $browseBy !== 'all') {
            $classId = $this->getClassIdForBrowseByOrNull($browseBy);
            if ($classId !== null) {
                $query['classId'] = $classId;
            }
        }

        try {
            $response = Http::asJson()
                ->timeout(15)
                ->connectTimeout(10)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'x-api-key' => $apiKey,
                    'User-Agent' => self::USER_AGENT,
                ])
                ->get(self::BASE_URL . '/v1/categories', $query);

            if (! $response->successful()) {
                return [];
            }

            $json = $response->json();

            $data = $json['data'] ?? [];
            $options = [];

            if (is_array($data)) {
                foreach ($data as $category) {
                    if (! is_array($category)) {
                        continue;
                    }

                    $id = $category['id'] ?? null;
                    $name = $category['name'] ?? null;

                    if (is_numeric($id) && is_string($name) && $name !== '') {
                        $options[(int) $id] = $name;
                    }
                }
            }

            asort($options);

            return $options;
        } catch (Exception $e) {
            report($e);
            return [];
        }
    }

    public function isHytaleServer(Server $server): bool
    {
        $server->loadMissing('egg');

        $features = $server->egg->features ?? [];
        $tags = $server->egg->tags ?? [];

        // Accept either marker (you said you're using curseforge_mods now)
        $markers = ['curseforge_mods', 'curse_forge_mod_plugin'];

        foreach ($markers as $marker) {
            if (in_array($marker, $features, true) || in_array($marker, $tags, true)) {
                return true;
            }
        }

        // Secondary: basic hytale tag
        if (in_array('hytale', $features, true) || in_array('hytale', $tags, true)) {
            return true;
        }

        // Last fallback: egg name contains "hytale"
        $eggName = (string) ($server->egg->name ?? '');
        return str($eggName)->lower()->contains('hytale');
    }

    /**
     * Map browse option to CurseForge class ID.
     */
    private function getClassIdForBrowseBy(string $browseBy): int
    {
        $override = (array) config('services.curseforge.hytale_class_ids', []);
        if (isset($override[$browseBy]) && is_numeric($override[$browseBy])) {
            return (int) $override[$browseBy];
        }

        $mapping = [
            'mods'         => 6,      // Mods
            'prefabs'      => 17,     // Prefabs
            'worlds'       => 18,     // Worlds
            'bootstrap'    => 19,     // Bootstrap
            'translations' => 20,     // Translations
        ];

        return $mapping[$browseBy] ?? 6;
    }

    private function getClassIdForBrowseByOrNull(string $browseBy): ?int
    {
        $override = (array) config('services.curseforge.hytale_class_ids', []);
        if (isset($override[$browseBy]) && is_numeric($override[$browseBy])) {
            return (int) $override[$browseBy];
        }

        // Default mappings may not apply to Hytale; return null unless overridden.
        return null;
    }

    /**
     * Map sort option to CurseForge numeric sort field.
     */
    private function getSortByValue(string $sortBy): ?int
    {
        $override = (array) config('services.curseforge.hytale_sort_by', []);
        if (isset($override[$sortBy]) && is_numeric($override[$sortBy])) {
            return (int) $override[$sortBy];
        }

        $mapping = [
            'downloads' => 6,
            'updated'   => 3,
            'newest'    => 4,
        ];

        return $mapping[$sortBy] ?? null;
    }

    /**
     * Normalize category values (UI keys or numeric IDs) into numeric IDs.
     * Supports optional mapping via config('services.curseforge.hytale_category_ids').
     *
     * @return array<int, int>
     */
    private function normalizeCategoryIds(array $categories): array
    {
        $mapped = (array) config('services.curseforge.hytale_category_ids', []);
        $ids = [];

        foreach ($categories as $category) {
            if (is_numeric($category)) {
                $ids[] = (int) $category;
                continue;
            }

            if (is_string($category) && isset($mapped[$category]) && is_numeric($mapped[$category])) {
                $ids[] = (int) $mapped[$category];
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * CurseForge uses "index" + "pageSize" (not "page").
     * We convert page -> index internally.
     *
     * @return array{data: array<int, array<string, mixed>>, total: int, pageSize: int, index: int}
     */
    public function searchMods(int $page = 1, string $search = '', array $filters = []): array
    {
        $apiKey = (string) config('services.curseforge.key', '');

        $pageSize = 10;
        $page = max(1, $page);
        $index = ($page - 1) * $pageSize;

        if ($apiKey === '') {
            return ['data' => [], 'total' => 0, 'pageSize' => $pageSize, 'index' => $index];
        }

        $query = [
            'gameId' => $this->getHytaleGameId(),
            'pageSize' => $pageSize,
            'index' => $index,
        ];

        if (trim($search) !== '') {
            $query['searchFilter'] = $search;
        }

        // Apply filters from the UI
        $browseBy = $filters['browseBy'] ?? 'all';
        if ($browseBy !== 'all' && $browseBy !== 'mods' && $browseBy !== '') {
            $classId = $this->getClassIdForBrowseByOrNull($browseBy);
            if ($classId !== null) {
                $query['classId'] = $classId;
            }
        }

        $sortBy = $filters['sortBy'] ?? 'relevance';
        $sortByValue = $sortBy !== 'relevance' && $sortBy !== ''
            ? $this->getSortByValue($sortBy)
            : null;

        if ($sortByValue !== null) {
            $query['sortBy'] = $sortByValue;
            $query['sortOrder'] = 'desc';
        }

        $categories = $filters['categories'] ?? [];
        if (is_array($categories) && ! empty($categories)) {
            $categoryIds = $this->normalizeCategoryIds($categories);

            if (! empty($categoryIds)) {
                if (count($categoryIds) === 1) {
                    $query['categoryId'] = (string) $categoryIds[0];
                    return $this->performSearch($query, $pageSize, $index);
                }

                // CurseForge rejects comma-separated categoryId lists; merge results instead.
                $merged = [];
                $total = 0;

                foreach ($categoryIds as $categoryId) {
                    $result = $this->performSearch(array_merge($query, [
                        'categoryId' => (string) $categoryId,
                    ]), $pageSize, $index);

                    $total += (int) ($result['total'] ?? 0);

                    foreach ($result['data'] ?? [] as $record) {
                        if (is_array($record) && isset($record['id'])) {
                            $merged[(string) $record['id']] = $record;
                        }
                    }
                }

                return [
                    'data' => array_values($merged),
                    'total' => $total,
                    'pageSize' => $pageSize,
                    'index' => $index,
                ];
            }
        }

        return $this->performSearch($query, $pageSize, $index);
    }

    /**
     * Perform a CurseForge search request.
     *
     * @return array{data: array<int, array<string, mixed>>, total: int, pageSize: int, index: int}
     */
    private function performSearch(array $query, int $pageSize, int $index): array
    {
        $apiKey = (string) config('services.curseforge.key', '');

        if ($apiKey === '') {
            return ['data' => [], 'total' => 0, 'pageSize' => $pageSize, 'index' => $index];
        }

        try {
            $response = Http::asJson()
                ->timeout(15)
                ->connectTimeout(10)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'x-api-key' => $apiKey,
                    'User-Agent' => self::USER_AGENT,
                ])
                ->get(self::BASE_URL . '/v1/mods/search', $query);

            if (! $response->successful()) {
                return ['data' => [], 'total' => 0, 'pageSize' => $pageSize, 'index' => $index];
            }

            $json = $response->json();

            $data = $json['data'] ?? [];
            $pagination = $json['pagination'] ?? [];

            return [
                'data' => is_array($data) ? $data : [],
                'total' => (int) ($pagination['totalCount'] ?? 0),
                'pageSize' => $pageSize,
                'index' => $index,
            ];
        } catch (Exception $e) {
            report($e);

            return ['data' => [], 'total' => 0, 'pageSize' => $pageSize, 'index' => $index];
        }
    }

    /**
     * Pick a reasonable default file to download from a mod search record.
     * - Prefer mainFileId if present
     * - Else use the first entry in latestFiles (if present)
     */
    public function getBestFileId(array $modRecord): ?int
    {
        if (isset($modRecord['mainFileId']) && is_numeric($modRecord['mainFileId'])) {
            return (int) $modRecord['mainFileId'];
        }

        $latestFiles = $modRecord['latestFiles'] ?? null;
        if (is_array($latestFiles) && isset($latestFiles[0]['id']) && is_numeric($latestFiles[0]['id'])) {
            return (int) $latestFiles[0]['id'];
        }

        return null;
    }

    /**
     * Gets a downloadable URL for a specific mod file.
     * Some mod search results already include latestFiles[].downloadUrl,
     * but this is here as a fallback.
     */
    public function getDownloadUrl(int $modId, int $fileId): ?string
    {
        $apiKey = (string) config('services.curseforge.key', '');

        if ($apiKey === '') {
            return null;
        }

        try {
            $json = Http::asJson()
                ->timeout(20)
                ->connectTimeout(10)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'x-api-key' => $apiKey,
                    'User-Agent' => self::USER_AGENT,
                ])
                ->get(self::BASE_URL . "/v1/mods/$modId/files/$fileId/download-url")
                ->throw()
                ->json();

            $url = $json['data'] ?? null;

            return is_string($url) && str($url)->startsWith('http') ? $url : null;
        } catch (Exception $e) {
            report($e);
            return null;
        }
    }
}