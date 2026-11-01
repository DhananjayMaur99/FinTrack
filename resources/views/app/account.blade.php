<x-app-layout title="Account">
    <h1 class="mb-5 text-xl font-semibold tracking-tight">Account</h1>

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-out/30 bg-out-dim px-4 py-3 text-sm text-out">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="sheet mb-4">
        <div class="border-b border-line px-5 py-3.5">
            <h2 class="text-sm font-semibold">Your details</h2>
        </div>
        <form method="POST" action="{{ route('account.update') }}" class="px-5 py-4">
            @csrf
            @method('PUT')
            <x-ui.field label="Name" name="name" :value="$user->name" required />
            <x-ui.field label="Email" name="email" type="email" :value="$user->email" required />
            <x-ui.field
                label="Time zone"
                name="timezone"
                :value="$user->timezone"
                placeholder="Asia/Kolkata"
                hint="Used to work out which month an entry belongs to."
            />
            <button type="submit" class="btn btn-primary">Save details</button>
        </form>
    </section>

    <section class="sheet mb-4">
        <div class="border-b border-line px-5 py-3.5">
            <h2 class="text-sm font-semibold">Password</h2>
        </div>
        <form method="POST" action="{{ route('account.update') }}" class="px-5 py-4">
            @csrf
            @method('PUT')
            {{-- Carried along so the shared update endpoint doesn't clear them. --}}
            <input type="hidden" name="name" value="{{ $user->name }}">
            <input type="hidden" name="email" value="{{ $user->email }}">
            <input type="hidden" name="timezone" value="{{ $user->timezone }}">

            <x-ui.field label="New password" name="password" type="password" autocomplete="new-password" hint="At least 8 characters." required />
            <x-ui.field label="Confirm new password" name="password_confirmation" type="password" autocomplete="new-password" required />
            <button type="submit" class="btn btn-primary">Change password</button>
        </form>
    </section>

    <section class="sheet mb-4 md:hidden">
        <form method="POST" action="{{ route('logout') }}" class="px-5 py-4">
            @csrf
            <button type="submit" class="btn btn-ghost w-full">Sign out</button>
        </form>
    </section>

    <section class="sheet border-out/30">
        <div class="border-b border-line px-5 py-3.5">
            <h2 class="text-sm font-semibold text-out">Delete account</h2>
        </div>
        <div class="px-5 py-4">
            <p class="mb-4 text-sm text-ink-soft">
                This signs you out and closes your account immediately. Your entries are kept for historical integrity, not shown to anyone.
            </p>
            <div x-data="{ confirming: false }">
                <button type="button" x-show="!confirming" x-on:click="confirming = true" class="btn btn-danger">Delete account</button>
                <form method="POST" action="{{ route('account.destroy') }}" x-show="confirming" x-cloak class="flex items-center gap-3">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Yes, delete it</button>
                    <button type="button" x-on:click="confirming = false" class="btn btn-ghost">Keep my account</button>
                </form>
            </div>
        </div>
    </section>
</x-app-layout>
