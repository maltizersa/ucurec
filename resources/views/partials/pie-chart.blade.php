{{--
    One pie chart card. Expects $chart = {title, subtitle, total, slices: [{label, value, percent, color}]}.
    The list beside the pie repeats every value, so the chart never relies on color alone.
--}}
<div class="card border-0 shadow-sm h-100 chart-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-1">
            <h3 class="h6 fw-bold mb-0">{{ $chart['title'] }}</h3>
            <span class="badge text-bg-light border">Total {{ $chart['total'] }}</span>
        </div>
        <p class="small text-body-secondary mb-3">{{ $chart['subtitle'] }}</p>

        @if ($chart['total'] === 0)
            <div class="text-center text-body-secondary small py-5">
                <i class="bi bi-pie-chart fs-2 d-block mb-1"></i>
                No data yet
            </div>
        @else
            <div class="pie-wrap mx-auto mb-3">
                <canvas data-pie-chart="{{ json_encode($chart['slices']) }}" role="img"
                        aria-label="{{ $chart['title'] }}: {{ collect($chart['slices'])->map(fn ($s) => $s['label'].' '.$s['value'])->implode(', ') }}"></canvas>
            </div>

            <ul class="list-unstyled small mb-0 pie-legend">
                @foreach (collect($chart['slices'])->sortByDesc('value') as $slice)
                    <li class="d-flex align-items-center gap-2 py-1">
                        <span class="pie-swatch flex-shrink-0" style="background-color: {{ $slice['color'] }}"></span>
                        <span class="flex-grow-1 text-truncate" title="{{ $slice['label'] }}">{{ $slice['label'] }}</span>
                        <span class="fw-semibold text-nowrap">{{ $slice['value'] }}</span>
                        <span class="text-body-secondary text-nowrap pie-percent">{{ rtrim(rtrim(number_format($slice['percent'], 1), '0'), '.') }}%</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
