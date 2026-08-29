<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Models\User; use Illuminate\Http\RedirectResponse; use Illuminate\Http\Request; use Illuminate\Validation\Rule; use Illuminate\View\View;
class UserController extends Controller {
    public function index(): View { return view('admin.users.index',['users'=>User::orderBy('name')->paginate(30)]); }
    public function store(Request $request): RedirectResponse { $data=$request->validate(['name'=>['required','string','max:150'],'username'=>['required','string','max:100','alpha_dash',Rule::unique('users','username')],'password'=>['required','string','min:6'],'role'=>['required',Rule::in([User::ROLE_ADMIN,User::ROLE_CHECKER])]]); $data['is_active']=true; User::create($data); return back()->with('success','User berhasil dibuat.'); }
    public function update(Request $request, User $user): RedirectResponse { $data=$request->validate(['name'=>['required','string','max:150'],'username'=>['required','string','max:100','alpha_dash',Rule::unique('users','username')->ignore($user->id)],'role'=>['required',Rule::in([User::ROLE_ADMIN,User::ROLE_CHECKER])],'is_active'=>['nullable','boolean'],'password'=>['nullable','string','min:6']]); $data['is_active']=$request->boolean('is_active'); if(empty($data['password'])) unset($data['password']); $user->update($data); return back()->with('success','User berhasil diperbarui.'); }
}
