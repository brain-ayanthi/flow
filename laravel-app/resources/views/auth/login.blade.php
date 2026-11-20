<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · PressFlow</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f8f6;color:#1d2c29;font-family:system-ui,-apple-system,"Segoe UI",Arial,sans-serif}.box{width:min(420px,calc(100% - 28px));background:#fff;border:1px solid #e7eeea;border-radius:18px;padding:28px;box-shadow:0 15px 38px rgba(20,55,45,.07)}.brand{font-size:24px;font-weight:850;letter-spacing:-.6px}.brand span{color:#117765}.hint{font-size:14.4px;color:#788680;margin:7px 0 23px}.field{display:grid;gap:6px;margin:14px 0}.field label{font-size:12px;font-weight:750}.field input{height:41px;border:1px solid #e0e9e3;border-radius:9px;padding:0 11px;font:inherit;font-size:14.4px}.remember{font-size:12px;color:#68756e;display:flex;gap:7px;align-items:center;margin:12px 0}.btn{border:0;border-radius:9px;width:100%;height:42px;background:#117765;color:#fff;font-weight:800;cursor:pointer}.error{font-size:12px;color:#a33d37;margin-top:4px}.notice{color:#849088;font-size:10.8px;line-height:1.5;margin-top:16px}
    </style>
</head>
<body>
    <main class="box">
        <div class="brand">press<span>flow</span></div>
        <p class="hint">Sign in to your print shop workspace.</p>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" autofocus>@error('email')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password">@error('password')<div class="error">{{ $message }}</div>@enderror</div>
            <label class="remember"><input type="checkbox" name="remember"> Keep me signed in</label>
            <button class="btn" type="submit">Sign in</button>
        </form>
        <div class="notice">New workspace registration and account recovery are separate onboarding tasks. Use the API registration endpoint for the current backend pilot.</div>
    </main>
</body>
</html>
