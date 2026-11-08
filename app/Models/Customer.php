<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['name', 'email', 'phone', 'address'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope('require_tenant', function (Builder $builder): void {
            if (! tenancy()->initialized) {
                throw new LogicException(
                    'Customer queries require an active tenant.'
                );
            }
        });

        static::creating(function (Customer $customer): void {
            if (! tenancy()->initialized) {
                throw new LogicException(
                    'Creating a customer requires an active tenant.'
                );
            }

            if ((string) $customer->tenant_id !== (string) tenant()->getTenantKey()) {
                throw new LogicException(
                    'The customer must belong to the active tenant.'
                );
            }
        });
    }
}
