<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller; use App\Models\User; use Illuminate\Http\RedirectResponse; use Illuminate\Http\Request; use Illuminate\Support\Facades\Auth; use Illuminate\View\View;
class LoginController extends Controller {
    public function create(): View { return view('auth.login'); }
    public function store(Request $request): RedirectResponse { $credentials=$request->validate(['username'=>['required','string'],'password'=>['required','string']]); if(!Auth::attempt(['username'=>$credentials['username'],'password'=>$credentials['password'],'is_active'=>true],true)){ return back()->withErrors(['username'=>'Username atau password tidak sesuai.'])->onlyInput('username'); } $request->session()->regenerate(); return $request->user()->role===User::ROLE_ADMIN ? redirect()->route('admin.dashboard') : redirect()->route('checker.home'); }
    public function destroy(Request $request): RedirectResponse { Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login'); }
}
