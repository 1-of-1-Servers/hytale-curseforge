<?php

namespace OneOfOne\HytaleCurseForge;

use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Forms\Components\TextInput;
/**
 * Fixed by Eranio
 */
class HytaleCurseForgePlugin implements HasPluginSettings, Plugin
{
    use EnvironmentWriterTrait;

    public function getId(): string
    {
        return 'hytale-curseforge';
    }

    public function register(Panel $panel): void
    {
        // "Server" panel in Pelican has id "server" which becomes "Server" here.
        $id = str($panel->getId())->title();

        $panel->discoverPages(
            plugin_path($this->getId(), "src/Filament/$id/Pages"),
            "OneOfOne\\HytaleCurseForge\\Filament\\$id\\Pages"
        );
    }

    public function boot(Panel $panel): void
    {
        // Register Laravel service provider so view namespace "hytale-curseforge::" exists.
        app()->register(HytaleCurseForgeServiceProvider::class);
    }

    public function getSettingsForm(): array
    {
        return [
            TextInput::make('curseforge_api_key')
                ->label('Curseforge API Key')
                ->password()
                ->revealable()
                ->helperText('Curseforge API Key get on')
                ->default(fn () => config('hytale-curseforge.curseforge_api_key', '')),
        ];
    }

    public function saveSettings(array $data): void
    {
        $envData = [];
        if (isset($data['curseforge_api_key'])) {
            $envData['CURSEFORGE_API_KEY'] = $data['curseforge_api_key'];
        }

        $this->writeToEnvironment($envData);
    }

}