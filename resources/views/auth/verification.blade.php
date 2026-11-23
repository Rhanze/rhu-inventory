<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Sign-in | PHO Supply Portal</title>
    <style>
        body { margin: 0; padding: 1rem; min-height: calc(100dvh - 2rem); display: grid; place-items: center; background: #e8f5ef; color: #0f172a; font: 1rem/1.5 Arial, sans-serif; }
        main { width: min(100%, 27rem); box-sizing: border-box; padding: clamp(1rem, 5vw, 2rem); background: #fff; border: 1px solid #c9ded5; border-radius: 1rem; }
        h1 { font-size: 1.5rem; margin: 0; }
        p { margin: .6rem 0 1rem; }
        label { display: block; font-weight: 700; }
        input { display: block; width: 100%; box-sizing: border-box; min-height: 2.75rem; padding: .5rem .75rem; margin: .35rem 0 0; border: 1px solid #64748b; border-radius: .5rem; font: inherit; }
        button { min-height: 2.75rem; margin-top: 1rem; border: 0; border-radius: .5rem; padding: .5rem 1.25rem; background: #0f5c4b; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        a { color: #0f5c4b; }
        :focus-visible { outline: 2px solid #0f5c4b; outline-offset: 2px; }
        [role=alert] { color: #991b1b; }
    </style>
</head>
<body>
    <main>
        <h1>Verify your sign-in</h1>
        <p>Open your authenticator app and enter its current six-digit code.</p>
        <form method="post" action="{{ route('login.verify') }}">
            @csrf
            <label for="mfa-code">Authenticator code</label>
            <input id="mfa-code" name="mfa_code" type="text" inputmode="numeric" autocomplete="one-time-code"
                   minlength="6" maxlength="6" pattern="[0-9]{6}" required autofocus
                   @error('mfa_code') aria-invalid="true" aria-describedby="mfa-error" @enderror>
            @error('mfa_code')<p id="mfa-error" role="alert">{{ $message }}</p>@enderror
            <button type="submit">Verify and sign in</button>
        </form>
        <p><a href="{{ route('login') }}">Return to sign-in</a></p>
    </main>
</body>
</html>
