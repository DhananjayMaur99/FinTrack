<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Users\Models\User;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Classic session-based login/register for the Blade web app — distinct from
 * AuthController, which issues Sanctum bearer tokens for API/external clients.
 */
class SessionController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Those details don\'t match our records.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', 'min:8'],
            'timezone' => ['nullable', 'timezone:all'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'timezone' => $data['timezone'] ?? null,
        ]);

        $this->seedStarterReasons($user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    /**
     * A new account with no reasons can't log anything, so give people a
     * sensible starting set they can rename or delete.
     */
    private function seedStarterReasons(User $user): void
    {
        $starters = [
            ['name' => 'Groceries', 'icon' => '🛒'],
            ['name' => 'Eating out', 'icon' => '🍜'],
            ['name' => 'Transport', 'icon' => '🚌'],
            ['name' => 'Rent', 'icon' => '🏠'],
            ['name' => 'Utilities', 'icon' => '💡'],
            ['name' => 'Shopping', 'icon' => '🛍️'],
            ['name' => 'Salary', 'icon' => '💰'],
        ];

        foreach ($starters as $starter) {
            $user->categories()->create($starter);
        }
    }
}
