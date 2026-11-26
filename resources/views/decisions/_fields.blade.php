@csrf

<div class="row g-3">
    <div class="col-md-7">
        <label for="category" class="form-label">REC decision</label>
        <select id="category" name="category" class="form-select @error('category') is-invalid @enderror" required>
            <option value="">Select the decision the Committee made…</option>
            @foreach ($categories as $value => $category)
                <option value="{{ $value }}" @selected(old('category', $decision->category) === $value)>{{ $category['label'] }}</option>
            @endforeach
        </select>
        <div class="form-text">Record the Committee's decision. Reviewer recommendations are advice only.</div>
        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-5">
        <label for="decision_date" class="form-label">Decision date</label>
        <input id="decision_date" type="date" name="decision_date" max="{{ today()->toDateString() }}"
               value="{{ old('decision_date', $decision->decision_date?->toDateString()) }}"
               class="form-control @error('decision_date') is-invalid @enderror" required>
        @error('decision_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="approval_date" class="form-label">Approval date <span class="text-body-secondary">(for approvals)</span></label>
        <input id="approval_date" type="date" name="approval_date"
               value="{{ old('approval_date', $decision->approval_date?->toDateString()) }}"
               class="form-control @error('approval_date') is-invalid @enderror">
        @error('approval_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="expiry_date" class="form-label">Approval expiry <span class="text-body-secondary">(when applicable)</span></label>
        <input id="expiry_date" type="date" name="expiry_date"
               value="{{ old('expiry_date', $decision->expiry_date?->toDateString()) }}"
               class="form-control @error('expiry_date') is-invalid @enderror">
        @error('expiry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="basis" class="form-label">Brief decision basis <span class="badge text-bg-light border fw-normal">internal</span></label>
        <textarea id="basis" name="basis" rows="2" class="form-control @error('basis') is-invalid @enderror"
                  placeholder="e.g. Approved at the full committee meeting of …">{{ old('basis', $decision->basis) }}</textarea>
        @error('basis')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="consolidated_comments" class="form-label">
            Consolidated comments for the researcher
            <span class="badge text-bg-warning fw-normal">released to the researcher</span>
        </label>
        <textarea id="consolidated_comments" name="consolidated_comments" rows="5"
                  class="form-control @error('consolidated_comments') is-invalid @enderror"
                  placeholder="Only comments authorized for release. Do not include reviewer names.">{{ old('consolidated_comments', $decision->consolidated_comments) }}</textarea>
        <div class="form-text">Required for decisions that ask for revisions (or upload a consolidated comments document).</div>
        @error('consolidated_comments')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
