@csrf

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Full name</label>
        <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}"
               class="form-control @error('name') is-invalid @enderror" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">Email address</label>
        <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}"
               class="form-control @error('email') is-invalid @enderror" required>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="role" class="form-label">Role</label>
        <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $user->role?->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="college_program" class="form-label">College or program <span class="text-body-secondary">(optional)</span></label>
        <input id="college_program" type="text" name="college_program" list="college-list"
               value="{{ old('college_program', $user->college_program) }}"
               class="form-control @error('college_program') is-invalid @enderror">
        <datalist id="college-list">
            @foreach ($colleges as $college)
                <option value="{{ $college }}">
            @endforeach
        </datalist>
        @error('college_program')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="contact_number" class="form-label">Contact number <span class="text-body-secondary">(optional)</span></label>
        <input id="contact_number" type="text" name="contact_number" value="{{ old('contact_number', $user->contact_number) }}"
               class="form-control @error('contact_number') is-invalid @enderror">
        @error('contact_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
