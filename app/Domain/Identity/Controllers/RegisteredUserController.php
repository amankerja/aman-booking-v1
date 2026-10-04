<?php

namespace App\Domain\Identity\Controllers;

use App\Domain\Identity\Actions\RegisterOwnerAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request, RegisterOwnerAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'business_name' => ['required', 'string', 'max:255'],
        ]);

        /** @var array{name: string, email: string, password: string, business_name: string} $data */
        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'business_name' => $validated['business_name'],
        ];

        $result = $action->execute($data);

        Auth::login($result['user']);

        $request->session()->regenerate();
        $request->session()->put('active_tenant_id', $result['tenant']->id);

        return redirect()->route('owner.dashboard')->with('success', 'Selamat datang di AMAN BOOKING! Akun usaha Anda berhasil dibuat.');
    }
}
