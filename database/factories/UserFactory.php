<?php
namespace Database\Factories;
use App\Models\User; use Illuminate\Database\Eloquent\Factories\Factory; use Illuminate\Support\Facades\Hash;
class UserFactory extends Factory { protected $model=User::class; public function definition(): array { return ['name'=>fake()->name(),'username'=>fake()->unique()->userName(),'password'=>Hash::make('password'),'role'=>User::ROLE_CHECKER,'is_active'=>true]; } }
