<?php

namespace OneOfOne\HytaleCurseForge;

use Filament\Contracts\Plugin;
use Filament\Panel;

class HytaleCurseForgePlugin implements Plugin
{
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
}