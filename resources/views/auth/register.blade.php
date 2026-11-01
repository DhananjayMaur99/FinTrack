<x-guest-layout title="Create account">
    <h1 class="text-lg font-semibold">Start your ledger</h1>
    <p class="mt-1 text-sm text-ink-soft">Takes under a minute. You'll get a starter set of reasons.</p>

    <form method="POST" action="{{ route('register.store') }}" class="mt-6">
        @csrf
        <input type="hidden" name="timezone" id="timezone" />

        <x-ui.field label="Name" name="name" required autofocus />
        <x-ui.field label="Email" name="email" type="email" required autocomplete="username" />
        <x-ui.field label="Password" name="password" type="password" required autocomplete="new-password" hint="At least 8 characters." />
        <x-ui.field label="Confirm password" name="password_confirmation" type="password" required autocomplete="new-password" />

        <button type="submit" class="btn btn-primary w-full">Create account</button>
    </form>

    <p class="mt-5 text-center text-sm text-ink-soft">
        Already have one?
        <a href="{{ route('login') }}" class="font-medium text-accent underline underline-offset-2">Log in</a>
    </p>

    <script>
        document.getElementById('timezone').value = Intl.DateTimeFormat().resolvedOptions().timeZone;
    </script>
</x-guest-layout>
