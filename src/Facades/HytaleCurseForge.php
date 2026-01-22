<?php

namespace OneOfOne\HytaleCurseForge\Facades;

use App\Models\Server;
use Illuminate\Support\Facades\Facade;
use OneOfOne\HytaleCurseForge\Services\HytaleCurseForgeService;

/**
 * @method static bool isHytaleServer(Server $server)
 * @method static int getHytaleGameId()
 * @method static string getModsPath()
 * @method static array<int, string> getCategories(?string $browseBy = null)
 * @method static array{data: array<int, array<string, mixed>>, total: int, pageSize: int, index: int} searchMods(int $page = 1, string $search = '', array $filters = [])
 * @method static ?int getBestFileId(array $modRecord)
 * @method static ?string getDownloadUrl(int $modId, int $fileId)
 *
 * @see HytaleCurseForgeService
 */
class HytaleCurseForge extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HytaleCurseForgeService::class;
    }
}
