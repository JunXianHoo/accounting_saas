<?php

namespace App\Models;

use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['description', 'quantity', 'unit_price'])]
class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory, BelongsToTenant;

    protected static function booted(): void
    {
        static::addGlobalScope('require_tenant', function (Builder $builder): void {
            if (! tenancy()->initialized) {
                throw new LogicException(
                    'Invoice item queries require an active tenant.'
                );
            }
        });

        static::creating(function (InvoiceItem $item): void {
            if (! tenancy()->initialized) {
                throw new LogicException(
                    'Creating an invoice item requires an active tenant.'
                );
            }

            if ((string) $item->tenant_id !== (string) tenant()->getTenantKey()) {
                throw new LogicException(
                    'The invoice item must belong to the active tenant.'
                );
            }
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
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }
}