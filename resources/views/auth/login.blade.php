<x-guest-layout title="Log in">
    <h1 class="text-lg font-semibold">Welcome back</h1>
    <p class="mt-1 text-sm text-ink-soft">Log in to pick up where your ledger left off.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6">
        @csrf

        <x-ui.field label="Email" name="email" type="email" required autofocus autocomplete="username" />
        <x-ui.field label="Password" name="password" type="password" required autocomplete="current-password" />

        <label class="mb-5 flex items-center gap-2 text-sm text-ink-soft">
            <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-line text-accent">
            Keep me signed in
        </label>

        <button type="submit" class="btn btn-primary w-full">Log in</button>
    </form>

    <p class="mt-5 text-center text-sm text-ink-soft">
        New here?
        <a href="{{ route('register') }}" class="font-medium text-accent underline underline-offset-2">Create an account</a>
    </p>
</x-guest-layout>
