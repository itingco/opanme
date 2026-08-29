<?php
namespace Database\Seeders;
use App\Models\User; use Illuminate\Database\Seeder; use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder { public function run(): void { User::updateOrCreate(['username'=>'admin'],['name'=>'Administrator','password'=>Hash::make('admin123'),'role'=>User::ROLE_ADMIN,'is_active'=>true]); User::updateOrCreate(['username'=>'checker1'],['name'=>'Checker Demo','password'=>Hash::make('checker123'),'role'=>User::ROLE_CHECKER,'is_active'=>true]); } }
