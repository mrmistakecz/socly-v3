<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class RegisterController extends Controller
{
    public function show()
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request)
    {
        // Normalize email and username to lowercase to prevent duplicates
        $request->merge([
            'email' => strtolower(trim($request->email)),
            'username' => strtolower(trim($request->username)),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username', 'regex:/^[a-z0-9_]+$/', 'min:3'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:' . now()->subYears(18)->format('Y-m-d')],
            'terms' => ['required', 'accepted'],
        ], [
            'name.required' => 'Jméno je povinné.',
            'email.required' => 'Email je povinný.',
            'email.email' => 'Zadejte platný email.',
            'email.unique' => 'Tento email je již registrován.',
            'password.required' => 'Heslo je povinné.',
            'password.min' => 'Heslo musí mít alespoň 8 znaků.',
            'password.confirmed' => 'Hesla se neshodují.',
            'username.required' => 'Uživatelské jméno je povinné.',
            'username.unique' => 'Toto uživatelské jméno je již obsazeno.',
            'username.regex' => 'Uživatelské jméno může obsahovat pouze malá písmena, čísla a podtržítka.',
            'username.min' => 'Uživatelské jméno musí mít alespoň 3 znaky.',
            'date_of_birth.required' => 'Datum narození je povinné.',
            'date_of_birth.before_or_equal' => 'Musíte být starší 18 let.',
            'terms.required' => 'Musíte souhlasit s podmínkami.',
            'terms.accepted' => 'Musíte souhlasit s podmínkami.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'username' => $validated['username'],
            'date_of_birth' => $validated['date_of_birth'],
            'terms_accepted_at' => now(),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect('/email/verify');
    }
}
