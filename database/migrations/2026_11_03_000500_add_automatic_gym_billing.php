<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->string('discount_type'); // percent, fixed
            $table->decimal('value', 10, 2);
            $table->char('currency', 3)->nullable(); // for fixed discounts
            $table->json('plans')->nullable(); // plan slugs; null = all plans
            $table->boolean('first_payment_only')->default(false);
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions')->default(0);
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Gap-free invoice numbers per series (e.g. GF-IN-2026).
        Schema::create('billing_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('series')->unique();
            $table->unsignedInteger('next')->default(1);
            $table->timestamps();
        });

        Schema::table('saas_payments', function (Blueprint $table) {
            $table->char('currency', 3)->default('INR')->after('amount');
            $table->string('gateway')->default('manual')->after('kind'); // manual, razorpay, stripe
            $table->string('period')->nullable()->after('months'); // monthly, yearly
            $table->foreignId('saas_coupon_id')->nullable()->after('saas_plan_id')->constrained()->nullOnDelete();
            $table->string('invoice_number')->nullable()->unique()->after('reference');
            $table->decimal('subtotal', 10, 2)->default(0)->after('amount'); // before tax
            $table->decimal('tax_amount', 10, 2)->default(0)->after('subtotal');
            $table->json('invoice')->nullable()->after('invoice_number'); // seller, buyer and line snapshot
            $table->date('period_start')->nullable()->after('period');
            $table->date('period_end')->nullable()->after('period_start');
        });

        // Gateway plan ids for auto-renew (Razorpay needs a plan per price).
        Schema::table('saas_plans', function (Blueprint $table) {
            $table->json('gateway_ids')->nullable();
        });

        Schema::table('gyms', function (Blueprint $table) {
            $table->boolean('auto_renew')->default(false)->after('subscription_ends_at');
            $table->string('billing_gateway')->nullable()->after('auto_renew'); // razorpay, stripe
            $table->string('billing_subscription_id')->nullable()->index()->after('billing_gateway');
            $table->string('billing_period')->nullable()->after('billing_subscription_id'); // monthly, yearly
            $table->string('suspension_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropIndex(['billing_subscription_id']);
            $table->dropColumn(['auto_renew', 'billing_gateway', 'billing_subscription_id', 'billing_period', 'suspension_reason']);
        });

        Schema::table('saas_plans', function (Blueprint $table) {
            $table->dropColumn('gateway_ids');
        });

        Schema::table('saas_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('saas_coupon_id');
            $table->dropUnique(['invoice_number']);
            $table->dropColumn(['currency', 'gateway', 'period', 'invoice_number', 'subtotal', 'tax_amount', 'invoice', 'period_start', 'period_end']);
        });

        Schema::dropIfExists('billing_sequences');
        Schema::dropIfExists('saas_coupons');
    }
};
