<?php

namespace OneOfOne\HytaleCurseForge;

use Illuminate\Support\ServiceProvider;

class HytaleCurseForgeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Points to: plugins/hytale-curseforge/resources/views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'hytale-curseforge');
    }
}