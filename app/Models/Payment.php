<?php

namespace App\Models;

use App\Support\DashboardCache;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

#[Fillable(['amount', 'paid_at', 'reference', 'submission_id'])]
#[Hidden(['submission_id'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, TenantConnection;

    protected static function booted(): void
    {
        static::updated(function (): void {
            $tenantId = (string) tenant()->getTenantKey();

            DB::afterCommit(
                fn () => DashboardCache::invalidate($tenantId)
            );
        });
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }
}
