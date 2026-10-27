<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // see App\Enums\PlanType
            $table->unsignedInteger('duration_value');
            $table->string('duration_unit')->default('month'); // day, month
            $table->decimal('price', 10, 2);
            $table->decimal('admission_fee', 10, 2)->default(0);
            $table->unsignedTinyInteger('max_persons')->default(1); // 2 = couple, 4 = family
            $table->unsignedInteger('pt_sessions')->nullable();
            $table->decimal('gst_rate', 5, 2)->default(18);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('razorpay_plan_id')->nullable();
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('description')->nullable();
            $table->string('category')->default('offer'); // offer, festival, referral, corporate
            $table->string('discount_type'); // percent, fixed
            $table->decimal('value', 10, 2);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['gym_id', 'code']);
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('member_code');
            $table->string('name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('anniversary_date')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('occupation')->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->json('health_questionnaire')->nullable(); // PAR-Q answers
            $table->text('medical_conditions')->nullable();
            $table->string('goal')->nullable();
            $table->decimal('target_weight_kg', 5, 1)->nullable();
            $table->date('joined_on')->nullable();
            $table->string('source')->default('walk_in'); // walk_in, online, referral
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['gym_id', 'member_code']);
            $table->index(['gym_id', 'phone']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('renewed_from_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invoice_number')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('plan_price', 10, 2);
            $table->decimal('admission_fee', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('taxable_amount', 10, 2);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('cgst_amount', 10, 2)->default(0);
            $table->decimal('sgst_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->unsignedInteger('pt_sessions_total')->nullable();
            $table->unsignedInteger('pt_sessions_used')->default(0);
            $table->string('status')->default('active'); // active, expired, cancelled
            $table->boolean('auto_renew')->default(false);
            $table->string('gateway_subscription_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['gym_id', 'invoice_number']);
            $table->index(['gym_id', 'end_date']);
        });

        // Additional people covered by a couple / family subscription.
        Schema::create('member_subscription', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unique(['subscription_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_subscription');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('members');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('membership_plans');
    }
};
