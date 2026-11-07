<?php

use App\Support\InvoiceNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_number_sequences', function (Blueprint $table) {
            $table->string('tenant_id');
            $table->date('issue_date');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->primary(['tenant_id', 'issue_date']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_number')->nullable();
            $table->unique(['tenant_id', 'invoice_number']);
        });

        // Assign existing invoices in ID order within each company and issue date.
        DB::table('invoices')->orderBy('id')->chunkById(100, function ($invoices) {
            foreach ($invoices as $invoice) {
                $number = InvoiceNumber::next(
                    (string) $invoice->tenant_id,
                    substr($invoice->issue_date, 0, 10),
                );

                DB::table('invoices')->where('id', $invoice->id)
                    ->update(['invoice_number' => $number]);
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_number')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'invoice_number']);
            $table->dropColumn('invoice_number');
        });

        Schema::dropIfExists('invoice_number_sequences');
    }
};
