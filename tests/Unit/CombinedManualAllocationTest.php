<?php

namespace Tests\Unit;

use App\Services\CombinedManualAllocationService as Allocation;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CombinedManualAllocationTest extends TestCase
{
    public function test_manual_allocation_preserves_total(): void
    {
        $this->assertSame([1=>'40.0000',2=>'35.0000',3=>'15.0000'], Allocation::validateQuantities([1=>'40',2=>'35',3=>'15'],[1,2,3],'90.0000'));
        $this->assertSame([1=>'0.3333',2=>'0.6668'],Allocation::validateQuantities([1=>'0.3333',2=>'0.6668'],[1,2],'1.0001'));
    }
    public function test_mismatching_total_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        Allocation::validateQuantities([1=>'40',2=>'40'],[1,2],'90');
    }
    public function test_unselected_source_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        Allocation::validateQuantities([1=>'40',9=>'50'],[1,2],'90');
    }
    public function test_fractional_distribution_of_whole_total_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        Allocation::validateQuantities([1=>'40.5',2=>'49.5'],[1,2],'90');
    }
}
