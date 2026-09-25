<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View { return view('auth.login'); }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['username'=>['required','string'], 'password'=>['required','string']]);
        if (! Auth::attempt(['username'=>$credentials['username'], 'password'=>$credentials['password'], 'is_active'=>true], true)) {
            return back()->withErrors(['username'=>'Username atau password tidak sesuai.'])->onlyInput('username');
        }
        $request->session()->regenerate();
        $user = $request->user();

        return match ($user->role) {
            User::ROLE_ADMIN => redirect()->route('admin.dashboard'),
            User::ROLE_ADMIN_GERAI => redirect()->route('gerai.admin.home'),
            User::ROLE_CHECKER_GERAI, User::ROLE_GERAI => redirect()->route('gerai.checker.home'),
            User::ROLE_ADMIN_GUDANG => redirect()->route('warehouse.admin.index'),
            User::ROLE_CHECKER_GUDANG => redirect()->route('warehouse.checker.index'),
            default => redirect()->route('checker.home'),
        };
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
