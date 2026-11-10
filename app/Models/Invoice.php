<?php

namespace App\Models;

use App\Support\InvoiceNumber;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * @property numeric-string|null $total_paid
 * @property numeric-string $outstanding_balance
 */
#[Fillable(['customer_id', 'issue_date', 'due_date'])]
#[Hidden(['email_payload', 'email_first_attempt_at'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope('require_tenant', function (Builder $builder): void {
            if (! tenancy()->initialized) {
                throw new LogicException(
                    'Invoice queries require an active tenant.'
                );
            }
        });

        static::creating(function (Invoice $invoice): void {
            if (! tenancy()->initialized) {
                throw new LogicException(
                    'Creating an invoice requires an active tenant.'
                );
            }

            if ((string) $invoice->tenant_id !== (string) tenant()->getTenantKey()) {
                throw new LogicException(
                    'The invoice must belong to the active tenant.'
                );
            }

            $invoice->invoice_number = InvoiceNumber::next(
                (string) $invoice->tenant_id,
                $invoice->issue_date->toDateString(),
            );
        });

        static::updating(function (Invoice $invoice): void {
            if ($invoice->isDirty('invoice_number')) {
                throw new LogicException('An assigned invoice number cannot be changed.');
            }
        });
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid'
            && $this->due_date->lt(today());
    }

    /** @param Builder<Invoice> $query
     * @return Builder<Invoice>
     */
    public function scopeWithPaymentTotals(Builder $query): Builder
    {
        return $query->withSum('payments as total_paid', 'amount')
            ->withCasts(['total_paid' => 'decimal:2']);
    }

    public function appendBalance(): self
    {
        $this->total_paid ??= '0.00';
        $this->outstanding_balance = bcsub($this->total, $this->total_paid, 2);

        return $this->append('overdue');
    }

    /** @return Attribute<bool, never> */
    protected function overdue(): Attribute
    {
        return Attribute::get(fn (): bool => $this->isOverdue());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'emailed_at' => 'datetime',
            'email_payload' => 'array',
            'email_first_attempt_at' => 'immutable_datetime',
        ];
    }
}
