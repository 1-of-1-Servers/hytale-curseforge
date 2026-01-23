<?php

namespace OneOfOne\HytaleCurseForge\Filament\Server\Pages;

use App\Filament\Server\Resources\Files\Pages\ListFiles;
use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Traits\Filament\BlockAccessInConflict;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use OneOfOne\HytaleCurseForge\Facades\HytaleCurseForge;

class HytaleCurseForgeModsPage extends Page implements HasTable
{
    use BlockAccessInConflict;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-packages';
    protected static ?string $slug = 'hytale-mods';
    protected static ?int $navigationSort = 30;

    // Plugin view namespace (registered in HytaleCurseForgeServiceProvider)
    protected string $view = 'hytale-curseforge::filament.server.pages.hytale-curse-forge-mods-page';

    /**
     * Sidebar filters (Livewire state)
     */
    public string $browseBy = 'all';      // all | mods | prefabs | worlds | bootstrap | translations
    public string $sortBy = 'relevance';  // relevance | downloads | updated | newest
    public string $gameVersion = 'all';   // all | early_access (etc)
    public array $categories = [];        // array of category ids/keys

    public static function canAccess(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return parent::canAccess() && HytaleCurseForge::isHytaleServer($server);
    }

    public static function getNavigationLabel(): string
    {
        return 'CurseForge Mods';
    }

    public function getTitle(): string
    {
        return 'CurseForge Mods';
    }

    protected ?array $installedEntries = null;

    /**
     * Options used by Blade sidebar
     */
    public function getBrowseByOptions(): array
    {
        return [
            'all'          => 'All',
            'mods'         => 'Mods',
            'prefabs'      => 'Prefabs',
            'worlds'       => 'Worlds',
            'bootstrap'    => 'Bootstrap',
            'translations' => 'Translations',
        ];
    }

    public function getSortOptions(): array
    {
        return [
            'relevance' => 'Relevance',
            'downloads' => 'Downloads',
            'updated'   => 'Recently updated',
            'newest'    => 'Newest',
        ];
    }

    public function getGameVersionOptions(): array
    {
        // Keep it simple until the API supports more
        return [
            'all'          => 'All',
            'early_access' => 'Early Access',
        ];
    }

    public function getCategoryOptions(): array
    {
        $labels = [
            'blocks'        => 'Blocks',
            'food_farming'  => 'Food\\Farming',
            'furniture'     => 'Furniture',
            'gameplay'      => 'Gameplay',
            'library'       => 'Library',
            'misc'          => 'Miscellaneous',
            'mobs'          => 'Mobs\\Characters',
            'prefab'        => 'Prefab',
            'qol'           => 'Quality of Life',
            'utility'       => 'Utility',
            'world_gen'     => 'World Gen',
        ];

        try {
            $mapped = (array) config('services.curseforge.hytale_category_ids', []);

            if (! empty($mapped)) {
                return collect($labels)
                    ->mapWithKeys(fn (string $label, string $key) => [
                        (is_numeric($mapped[$key] ?? null) ? (int) $mapped[$key] : $key) => $label,
                    ])
                    ->all();
            }

            $apiCategories = HytaleCurseForge::getCategories($this->browseBy);

            return ! empty($apiCategories) ? $apiCategories : $labels;
        } catch (\Throwable $e) {
            report($e);
            return $labels;
        }
    }

    public function updatedBrowseBy(): void
    {
        $this->categories = [];
        $this->resetTablePage();
    }

    public function updatedCategories(): void
    {
        $this->resetTablePage();
    }

    protected function resetTablePage(): void
    {
        if (method_exists($this, 'setPage')) {
            $this->setPage(1, 'tablePage');
            return;
        }

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->browseBy = 'all';
        $this->sortBy = 'relevance';
        $this->gameVersion = 'all';
        $this->categories = [];

        // Reset table to page 1
        $this->resetTablePage();
    }

    /**
     * Filament requires this to be public.
     */
    public function getTableRecords(): LengthAwarePaginator
    {
        $page = (int) ($this->getTablePage() ?: 1);
        $search = (string) ($this->getTableSearch() ?? '');
        $perPage = (int) ($this->getTableRecordsPerPage() ?: 10);

        $filters = [
            'browseBy'    => $this->browseBy,
            'sortBy'      => $this->sortBy,
            'gameVersion' => $this->gameVersion,
            'categories'  => array_values(array_filter($this->categories)),
        ];

        $response = HytaleCurseForge::searchMods($page, $search, $filters);

        $data = $response['data'] ?? [];
        $total = (int) ($response['total'] ?? (is_array($data) ? count($data) : 0));

        // Filament needs a unique __key for array records
        if (is_array($data)) {
            $new = [];

            foreach ($data as $i => $record) {
                $record = is_array($record) ? $record : [];
                $id = $record['id'] ?? null;
                $record['__key'] = $id ? (string) $id : ('row_' . $page . '_' . $i);
                $new[] = $record;
            }

            $data = $new;
        }

        return new LengthAwarePaginator(
            $data,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    public function getTableRecordKey($record): string
    {
        if (is_array($record) && ! empty($record['__key'])) {
            return (string) $record['__key'];
        }

        if (is_array($record) && ! empty($record['id'])) {
            return (string) $record['id'];
        }

        if (is_object($record) && method_exists($record, 'getKey')) {
            return (string) $record->getKey();
        }

        return sha1(json_encode($record));
    }

    protected function resolveTableRecord(string $key): ?array
    {
        $records = $this->getTableRecords();

        $items = method_exists($records, 'getCollection')
            ? $records->getCollection()
            : collect($records);

        return $items->first(function ($record) use ($key) {
            if (! is_array($record)) return false;

            $rk = (string) ($record['__key'] ?? $record['id'] ?? '');
            return $rk === (string) $key;
        });
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated([10])
            ->columns([
                ImageColumn::make('logo.thumbnailUrl')
                    ->label('')
                    ->circular(),

                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (array $record) =>
                        Str::limit((string) ($record['summary'] ?? ''), 120, '…')
                    ),

                TextColumn::make('installed')
                    ->label('Status')
                    ->badge()
                    ->color('success')
                    ->state(fn (array $record) => $this->isModInstalled($record) ? 'Installed' : null),

                TextColumn::make('authors.0.name')
                    ->label('Author'),

                TextColumn::make('downloadCount')
                    ->label('Downloads')
                    ->icon('tabler-download'),
            ])
            ->recordUrl(
                fn (array $record) => (string) ($record['links']['websiteUrl'] ?? ''),
                true
            )
            ->recordActions([
                Action::make('download')
                    ->label(fn (array $record) => $this->isModInstalled($record) ? 'Installed' : 'Download')
                    ->disabled(fn (array $record) => $this->isModInstalled($record))
                    ->color(fn (array $record) => $this->isModInstalled($record) ? 'success' : 'primary')
                    ->action(function (array $record, DaemonFileRepository $fileRepository) {
                        try {
                            /** @var Server $server */
                            $server = Filament::getTenant();

                            $modId = (int) ($record['id'] ?? 0);
                            if ($modId <= 0) {
                                throw new Exception('Invalid mod id.');
                            }

                            $fileId = HytaleCurseForge::getBestFileId($record);
                            if (! $fileId) {
                                throw new Exception('No downloadable file found.');
                            }

                            $downloadUrl = HytaleCurseForge::getDownloadUrl($modId, $fileId);
                            if (! $downloadUrl) {
                                throw new Exception('Could not retrieve download URL.');
                            }

                            $destRoot = trim(HytaleCurseForge::getModsPath(), '/');

                            // Pull the file into /mods
                            $fileRepository
                                ->setServer($server)
                                ->pull($downloadUrl, $destRoot);

                            // Guess filename from record data (preferred) or URL (fallback)
                            $filename =
                                $record['latestFilesIndexes'][0]['filename']
                                    ?? $record['latestFiles'][0]['fileName']
                                    ?? basename(parse_url($downloadUrl, PHP_URL_PATH) ?: '');

                            if (! $filename) {
                                Notification::make()
                                    ->title('Download started')
                                    ->body("Downloaded to /{$destRoot} (could not detect filename for auto-extract).")
                                    ->success()
                                    ->send();
                                return;
                            }

                            // Auto-extract ZIPs (Pelican daemon handles /files/decompress)
                            if (Str::endsWith(Str::lower($filename), ['.zip', '.tar', '.tar.gz', '.tgz', '.rar', '.7z'])) {
                                try {
                                    $fileRepository
                                        ->setServer($server)
                                        ->decompressFile($destRoot, $filename);

                                    Notification::make()
                                        ->title('Downloaded & extracted')
                                        ->body("Extracted {$filename} into /{$destRoot}.")
                                        ->success()
                                        ->send();
                                } catch (\Throwable $e) {
                                    report($e);

                                    Notification::make()
                                        ->title('Downloaded, but extract failed')
                                        ->body("File saved as {$filename} in /{$destRoot}. Extract error: {$e->getMessage()}")
                                        ->warning()
                                        ->send();
                                }
                            } else {
                                Notification::make()
                                    ->title('Download complete')
                                    ->body("Saved {$filename} into /{$destRoot}.")
                                    ->success()
                                    ->send();
                            }
                        } catch (Exception $e) {
                            report($e);

                            Notification::make()
                                ->title('Download failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_mods_folder')
                ->label('Open Mods Folder')
                ->url(
                    fn () => ListFiles::getUrl([
                        'path' => trim(HytaleCurseForge::getModsPath(), '/'),
                    ]),
                    true
                ),
        ];
    }

    protected function isModInstalled(array $record): bool
    {
        $entries = $this->getInstalledEntries();
        if (empty($entries)) {
            return false;
        }

        $candidates = $this->getCandidateNames($record);
        if (empty($candidates)) {
            return false;
        }

        $lookup = array_flip(array_map('strtolower', $entries));
        foreach ($candidates as $candidate) {
            $candidate = strtolower($candidate);
            if (isset($lookup[$candidate])) {
                return true;
            }
        }

        return false;
    }

    protected function getCandidateNames(array $record): array
    {
        $candidates = [];

        $filename =
            $record['latestFilesIndexes'][0]['filename']
                ?? $record['latestFiles'][0]['fileName']
                ?? null;

        if (! $filename) {
            $downloadUrl = $record['latestFiles'][0]['downloadUrl'] ?? null;
            if (is_string($downloadUrl) && $downloadUrl !== '') {
                $filename = basename(parse_url($downloadUrl, PHP_URL_PATH) ?: '');
            }
        }

        if (is_string($filename) && $filename !== '') {
            $candidates[] = $filename;
            $base = pathinfo($filename, PATHINFO_FILENAME);
            if ($base !== '') {
                $candidates[] = $base;
            }
        }

        if (! empty($record['slug'])) {
            $candidates[] = (string) $record['slug'];
        }

        if (! empty($record['name'])) {
            $candidates[] = (string) $record['name'];
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    protected function getInstalledEntries(): array
    {
        if ($this->installedEntries !== null) {
            return $this->installedEntries;
        }

        try {
            /** @var Server $server */
            $server = Filament::getTenant();
            $destRoot = trim(HytaleCurseForge::getModsPath(), '/');

            /** @var DaemonFileRepository $repo */
            $repo = app(DaemonFileRepository::class);
            $repo->setServer($server);

            if (method_exists($repo, 'getDirectory')) {
                $entries = $repo->getDirectory($destRoot);
            } elseif (method_exists($repo, 'listDirectory')) {
                $entries = $repo->listDirectory($destRoot);
            } else {
                return $this->installedEntries = [];
            }

            return $this->installedEntries = $this->extractEntryNames($entries);
        } catch (\Throwable $e) {
            report($e);
            return $this->installedEntries = [];
        }
    }

    protected function extractEntryNames($entries): array
    {
        $names = [];

        foreach (collect($entries) as $entry) {
            $name = null;

            if (is_array($entry)) {
                $name = $entry['name'] ?? $entry['file'] ?? $entry['path'] ?? null;
            } elseif (is_object($entry)) {
                if (isset($entry->name)) {
                    $name = $entry->name;
                } elseif (method_exists($entry, 'getName')) {
                    $name = $entry->getName();
                }
            }

            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }
}