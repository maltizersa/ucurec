{{-- Reviewer recommendations for the version being decided (internal, advice only). --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h3 class="h6 fw-bold"><i class="bi bi-people"></i> Reviewer recommendations <span class="badge text-bg-light border fw-normal">internal</span></h3>
        @forelse ($recommendations as $review)
            <div class="border-bottom pb-2 mb-2 small">
                <div class="d-flex justify-content-between">
                    <span class="fw-semibold">{{ $review->reviewer->name }}</span>
                    <span>{{ $review->recommendationLabel() }}</span>
                </div>
                @if ($review->review_comments)
                    <div class="text-body-secondary">{{ $review->review_comments }}</div>
                @endif
                @foreach ($review->files as $file)
                    <div><a href="{{ route('review-files.download', $file) }}"><i class="bi bi-file-earmark-arrow-down"></i> {{ $file->formLabel() }}</a></div>
                @endforeach
            </div>
        @empty
            <p class="small text-body-secondary mb-0">No submitted reviews for this version.</p>
        @endforelse
        <p class="small text-body-secondary mb-0">Recommendations never become the REC decision automatically.</p>
    </div>
</div>
