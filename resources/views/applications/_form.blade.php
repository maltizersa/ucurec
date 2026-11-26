@csrf

<p class="text-body-secondary small">
    Keep this short. Detailed research and ethics information belongs in the official UCUREC application form you will upload next.
</p>

<div class="row g-3">
    <div class="col-12">
        <label for="title" class="form-label">Research title</label>
        <textarea id="title" name="title" rows="2" required
                  class="form-control @error('title') is-invalid @enderror">{{ old('title', $application->title) }}</textarea>
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="principal_investigator" class="form-label">Principal investigator</label>
        <input id="principal_investigator" type="text" name="principal_investigator" required
               value="{{ old('principal_investigator', $application->principal_investigator) }}"
               class="form-control @error('principal_investigator') is-invalid @enderror">
        @error('principal_investigator')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="pi_contact" class="form-label">PI contact (email / phone)</label>
        <input id="pi_contact" type="text" name="pi_contact" required
               value="{{ old('pi_contact', $application->pi_contact) }}"
               class="form-control @error('pi_contact') is-invalid @enderror">
        @error('pi_contact')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="adviser" class="form-label">Adviser <span class="text-body-secondary">(if applicable)</span></label>
        <input id="adviser" type="text" name="adviser"
               value="{{ old('adviser', $application->adviser) }}"
               class="form-control @error('adviser') is-invalid @enderror">
        @error('adviser')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="college_program" class="form-label">College or program</label>
        <select id="college_program" name="college_program" required
                class="form-select @error('college_program') is-invalid @enderror">
            <option value="">Select…</option>
            @foreach ($colleges as $college)
                <option value="{{ $college }}" @selected(old('college_program', $application->college_program) === $college)>{{ $college }}</option>
            @endforeach
        </select>
        @error('college_program')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="research_type" class="form-label">Research type</label>
        <select id="research_type" name="research_type" required
                class="form-select @error('research_type') is-invalid @enderror">
            <option value="">Select…</option>
            @foreach ($researchTypes as $type)
                <option value="{{ $type }}" @selected(old('research_type', $application->research_type) === $type)>{{ $type }}</option>
            @endforeach
        </select>
        @error('research_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
