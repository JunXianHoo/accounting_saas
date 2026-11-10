<?php

namespace App\Models;

use App\Support\DashboardCache;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['amount', 'paid_at', 'reference', 'submission_id'])]
#[Hidden(['submission_id'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope('require_tenant', function (Builder $builder): void {
            if (! tenancy()->initialized) {
                throw new LogicException('Payment queries require an active tenant.');
            }
        });

        static::creating(function (Payment $payment): void {
            if (! tenancy()->initialized) {
                throw new LogicException('Creating a payment requires an active tenant.');
            }

            if ((string) $payment->tenant_id !== (string) tenant()->getTenantKey()) {
                throw new LogicException('The payment must belong to the active tenant.');
            }
        });

        static::updated(function (Payment $payment): void {
            $tenantId = (string) $payment->tenant_id;

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
