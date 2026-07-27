<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Tests\Fixtures;

use Carbon\CarbonImmutable;
use HoceineEl\UsageBilling\Contracts\MeteredModule;
use HoceineEl\UsageBilling\Data\UsageBucket;
use HoceineEl\UsageBilling\Enums\ModuleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A metered module backed by a real table, so tests exercise the same
 * query-derived path production uses rather than a stub.
 */
class DocumentsModule implements MeteredModule
{
    public static function key(): string
    {
        return 'documents';
    }

    public function label(): string
    {
        return 'Documents';
    }

    public function unitLabel(): string
    {
        return 'documents';
    }

    public function type(): ModuleType
    {
        return ModuleType::Metered;
    }

    public function usage(Model $subscriber, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return DB::table('documents')
            ->selectRaw('customer_id, count(*) as aggregate')
            ->where('cabinet_id', $subscriber->getKey())
            ->whereBetween('created_at', [$from, $to])
            ->where('failed', false)
            ->groupBy('customer_id')
            ->get()
            ->map(function (object $row): UsageBucket {
                $customer = $row->customer_id === null
                    ? null
                    : Customer::find($row->customer_id);

                return $customer === null
                    ? UsageBucket::unattributed((int) $row->aggregate)
                    : UsageBucket::for($customer, (int) $row->aggregate);
            });
    }
}
