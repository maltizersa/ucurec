{{-- Expects: $documents, $application, $removable (bool) --}}
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Category</th>
                <th>File</th>
                <th>Size</th>
                <th>Uploaded</th>
                @if ($removable)<th></th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse ($documents as $document)
                <tr>
                    <td class="small">{{ $document->categoryLabel() }}</td>
                    <td class="small">
                        <a href="{{ route('documents.download', $document) }}"><i class="bi bi-file-earmark-arrow-down"></i> {{ $document->original_name }}</a>
                        @if ($document->carried_from_id)
                            <span class="badge text-bg-light border ms-1" title="Same file as the previous version">from previous version</span>
                        @endif
                    </td>
                    <td class="small text-nowrap">{{ $document->humanSize() }}</td>
                    <td class="small text-nowrap text-body-secondary">{{ $document->created_at->format('M j, Y g:i A') }}</td>
                    @if ($removable)
                        <td class="text-end">
                            <form method="POST" action="{{ route('applications.documents.destroy', [$application, $document]) }}"
                                  onsubmit="return confirm(@js('Remove '.$document->original_name.' from this draft?'))">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Remove" aria-label="Remove"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $removable ? 5 : 4 }}" class="text-center text-body-secondary py-3">No files yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
