<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            // Last automatic payment that failed; cleared when a payment goes through.
            $table->timestamp('payment_failed_at')->nullable()->after('billing_period');
            $table->string('payment_failure_reason')->nullable()->after('payment_failed_at');
            $table->timestamp('payment_retry_at')->nullable()->after('payment_failure_reason');
            $table->string('billing_customer_id')->nullable()->after('billing_subscription_id'); // Stripe customer, for "update card"
        });

        // Refunds of platform payments, each with its own GST credit note.
        Schema::create('saas_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saas_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gym_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('currency', 3);
            $table->decimal('amount', 10, 2); // incl. GST
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_amount', 10, 2);
            $table->string('reason');
            $table->string('gateway')->default('manual'); // manual, razorpay, stripe
            $table->string('gateway_refund_id')->nullable()->unique();
            $table->string('status')->default('processed'); // processed, pending, failed
            $table->boolean('shortened_plan')->default(false);
            $table->string('credit_note_number')->unique();
            $table->json('credit_note')->nullable(); // seller, buyer, tax split snapshot
            $table->timestamp('refunded_at');
            $table->timestamps();
        });

        Schema::table('saas_payments', function (Blueprint $table) {
            $table->decimal('refunded_amount', 10, 2)->default(0)->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('saas_payments', function (Blueprint $table) {
            $table->dropColumn('refunded_amount');
        });
        Schema::dropIfExists('saas_refunds');
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn(['payment_failed_at', 'payment_failure_reason', 'payment_retry_at', 'billing_customer_id']);
        });
    }
};
