<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connect {{ $client->name }} — ChinaInNigeria</title>
    @vite(['resources/js/app.jsx'])
</head>
<body class="min-h-screen bg-tb-page font-sans antialiased flex items-center justify-center px-4">
    <div class="w-full max-w-md bg-white rounded-xl shadow-card border border-tb-border p-8">
        <div class="flex items-center gap-3 mb-6">
            <img src="/favicon.svg" alt="" class="w-8 h-8">
            <span class="text-sm text-tb-muted">+</span>
            <div class="w-8 h-8 rounded-full bg-tb-soft flex items-center justify-center text-tb font-semibold">
                {{ strtoupper(substr($client->name, 0, 1)) }}
            </div>
        </div>

        <h1 class="text-lg font-semibold text-tb-text mb-1">
            {{ $client->name }} wants to access your ChinaInNigeria account
        </h1>
        <p class="text-sm text-tb-muted mb-6">
            Signed in as <span class="font-medium text-tb-text">{{ $user->email }}</span>
        </p>

        <ul class="space-y-2 mb-8">
            @foreach ($scopes as $scope)
                <li class="flex items-start gap-2 text-sm text-tb-text">
                    <span class="text-tb-green mt-0.5">&#10003;</span>
                    <span>{{ $scope->description }}</span>
                </li>
            @endforeach
        </ul>

        <p class="text-xs text-tb-muted mb-6">
            This does not give {{ $client->name }} your password, and it can never charge you
            directly — placing an order always ends with a payment link you have to open and pay
            yourself. You can revoke this access at any time from your account settings.
        </p>

        <div class="flex gap-3">
            <form method="post" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="w-full bg-tb hover:bg-tb-hover text-white font-medium rounded-lg py-2.5 transition-colors">
                    Allow
                </button>
            </form>
            <form method="post" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="w-full bg-white border border-tb-border hover:bg-tb-card text-tb-text font-medium rounded-lg py-2.5 transition-colors">
                    Deny
                </button>
            </form>
        </div>
    </div>
</body>
</html>
