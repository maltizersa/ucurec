{{-- Plain-text body: not HTML, so values are printed unescaped. --}}
UCUREC Ethics Review
Urdaneta City University Research Ethics Committee

Hello {!! $user->name !!},

@if ($isReset)
Your password for the UCUREC Ethics Review system was reset by the IT administrator.
@else
An account was created for you in the UCUREC Ethics Review system as {!! $user->role->label() !!}.
@endif

Email: {!! $user->email !!}
Temporary password: {!! $password !!}

Sign in: {!! $signInUrl !!}

You will be asked to choose a new password the first time you sign in.
Do not share this password with anyone, and delete this email after you have changed it.
If you did not expect this email, contact the REC Secretariat or the IT administrator.
