<?php
namespace Database\Factories;
use App\Models\CycleWarehouse; use App\Models\StockOpnameCycle; use Illuminate\Database\Eloquent\Factories\Factory;
class CycleWarehouseFactory extends Factory { protected $model=CycleWarehouse::class; public function definition(): array { $id=fake()->unique()->numberBetween(1,99999); return ['cycle_id'=>StockOpnameCycle::factory(),'erp_warehouse_id'=>$id,'warehouse_code'=>'WH-'.$id,'warehouse_name'=>'Warehouse '.$id,'created_at'=>now()]; } }
