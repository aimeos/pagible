<x-pulse full-width cols="12">
    @foreach (\Aimeos\Cms\Pulse\CmsMetricCard::available() as $metric)
        <livewire:cms-metric-card :metric="$metric" cols="4" />
    @endforeach
</x-pulse>
