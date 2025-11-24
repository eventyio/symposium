<div>
    <div class="flex space-x-10">
        <x-input.text
            name="search"
            placeholder="Search conferences"
            wire:model.live="search"
            class="flex-1"
        />
        <x-input.select
            :inline="true"
            name="filter"
            label="Filter by"
            option-text="label"
            option-value="value"
            :options="$this->filter_options"
            wire:model.live="filter"
            class="w-1/4"
            label-class="font-semibold text-gray-500"
            input-class="font-semibold text-indigo-600"
        />
        <x-input.select
            :inline="true"
            name="sort"
            label="Sort by"
            option-text="label"
            option-value="value"
            :options="$this->sort_options"
            wire:model.live="sort"
            class="w-1/4"
            label-class="font-semibold text-gray-500"
            input-class="font-semibold text-indigo-600"
        />
    </div>
    @forelse ($conferences as $month => $conferences)
        <div class="flex justify-between mt-8">
            <h2 class="text-2xl leading-8 font-semibold text-indigo-600">
                {{ Carbon\Carbon::make($month)?->format('F Y') ?? 'Date Not Set' }}
            </h2>
            @if ($this->filter === 'all')
                <div class="flex">
                    <x-button.secondary
                        icon="chevron-left"
                        aria-label="Previous"
                        class="rounded-r-none"
                        wire:click.prevent="previous"
                        :href="route('conferences.index', [
                            'year' => $date->subMonth()->year,
                            'month' => $date->subMonth()->month,
                        ])"
                    />
                    <x-button.secondary
                        icon="chevron-right"
                        aria-label="Next"
                        class="rounded-l-none border-l-0"
                        wire:click.prevent="next"
                        :href="route('conferences.index', [
                            'year' => $date->addMonth()->year,
                            'month' => $date->addMonth()->month,
                        ])"
                    />
                </div>
            @endif
        </div>
        <x-panel size="xl" :padding="false" class="mt-5">
            @each('conferences.listing', $conferences, 'conference', 'conferences.listing-empty')
        </x-panel>
    @empty
        <x-panel size="xl" :padding="false" class="mt-5">
            @include('conferences.listing-empty')
        </x-panel>
    @endforelse

    <div class="mt-4">{{ $conferenceQuery->links() }}</div>
</div>
