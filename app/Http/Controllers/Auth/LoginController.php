<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Services\MenuPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
class LoginController extends Controller
{
    public function create(): View { return view('auth.login'); }
    public function store(Request $request, MenuPermissionService $permissions): RedirectResponse
    {
        $credentials = $request->validate(['username'=>['required','string'], 'password'=>['required','string']]);
        if (!Auth::attempt(['username'=>$credentials['username'], 'password'=>$credentials['password'], 'is_active'=>true], true)) {
            return back()->withErrors(['username'=>'Username atau password tidak sesuai.'])->onlyInput('username');
        }
        $request->session()->regenerate();
        $user = $request->user();
        $route = $permissions->firstAllowedRoute((string)$user->role);
        return $route ? redirect()->route($route) : redirect()->route('password.edit');
    }
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
