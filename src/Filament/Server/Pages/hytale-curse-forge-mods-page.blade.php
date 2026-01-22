<x-filament-panels::page class="!max-w-none !mx-0 !w-full">
    <style>
        .hcf-select {
            background-color: #111827 !important;
            color: #f9fafb !important;
            color-scheme: dark !important;
        }
        .hcf-select option {
            background-color: #111827 !important;
            color: #f9fafb !important;
        }
    </style>
    <div class="flex gap-6 w-full justify-start">
        {{-- Sidebar --}}
        <div class="w-72 shrink-0">
            <div class="rounded-xl border border-gray-800 bg-gray-900/40 p-4 space-y-6">
                <div>
                    <div class="text-sm font-semibold text-gray-200 mb-3">Browse by</div>
                    <div class="space-y-2">
                        @foreach ($this->getBrowseByOptions() as $value => $label)
                            <button
                                type="button"
                                wire:click="$set('browseBy', '{{ $value }}')"
                                class="w-full text-left text-sm px-3 py-2 rounded-lg
                                    {{ $this->browseBy === $value ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800/60' }}"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <div class="text-sm font-semibold text-gray-200 mb-2">Sort by</div>
                    <select wire:model.live="sortBy" class="hcf-select w-full rounded-lg border-gray-700 text-sm">
                        @foreach ($this->getSortOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <div class="text-sm font-semibold text-gray-200 mb-2">Game Version</div>
                    <select wire:model.live="gameVersion" class="hcf-select w-full rounded-lg border-gray-700 text-sm">
                        @foreach ($this->getGameVersionOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="text-xs text-gray-400 leading-relaxed">
                    Note: Not all mods install cleanly in this category. Please verify the correct mod type before
                    downloading; some mods require manual installation.
                </div>

                <div>
                    <div class="text-sm font-semibold text-gray-200 mb-3">Categories</div>
                    <div class="space-y-2 max-h-[340px] overflow-auto pr-1">
                        @foreach ($this->getCategoryOptions() as $value => $label)
                            <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                                <input type="checkbox" value="{{ $value }}" wire:model.live="categories" class="rounded border-gray-700 bg-gray-950" />
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-3 flex gap-2">
                        <button type="button" wire:click="clearFilters"
                            class="text-xs px-3 py-2 rounded-lg bg-gray-800 text-gray-200 hover:bg-gray-700">
                            Clear
                        </button>
                    </div>
                </div>

            </div>
        </div>

        {{-- Table --}}
        <div class="min-w-0 flex-1">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>