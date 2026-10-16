<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;

class WarehouseSamplingHistoryEnhancedService extends WarehouseSamplingHistoryService
{
    public function query(User $user, array $filters): Builder
    {
        return parent::query($user, $filters)
            ->addSelect('item.admin_override_qty as admin_override_qty');
    }
}
