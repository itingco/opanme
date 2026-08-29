<?php
namespace Database\Factories;
use App\Models\StockOpnameCycle; use App\Models\User; use Illuminate\Database\Eloquent\Factories\Factory;
class StockOpnameCycleFactory extends Factory { protected $model=StockOpnameCycle::class; public function definition(): array { return ['cycle_no'=>'SO-'.now()->format('Ymd').'-'.str_pad((string) fake()->unique()->numberBetween(1,999),3,'0',STR_PAD_LEFT),'source_database'=>StockOpnameCycle::DB_INGCO,'cutoff_date'=>today(),'status'=>StockOpnameCycle::STATUS_DRAFT,'created_by'=>User::factory()]; } }
