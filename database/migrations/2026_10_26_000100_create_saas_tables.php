<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plans that gym owners buy from the SaaS (Basic / Pro / Enterprise).
        Schema::create('saas_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('yearly_price', 10, 2)->default(0);
            // null = unlimited
            $table->unsignedInteger('max_members')->nullable();
            $table->unsignedInteger('max_branches')->nullable();
            $table->unsignedInteger('max_staff')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // The tenant. Every gym-owned row carries gym_id.
        Schema::create('gyms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saas_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 7)->default('#f59e0b');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('state_code', 2)->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->string('timezone')->default('Asia/Kolkata');
            $table->json('opening_hours')->nullable();
            $table->json('settings')->nullable();
            // Encrypted gym-own credentials (Razorpay, WhatsApp, MSG91); empty values fall back to platform config.
            $table->text('integrations')->nullable();
            $table->string('invoice_prefix', 10)->default('INV');
            $table->unsignedInteger('next_invoice_number')->default(1);
            $table->unsignedInteger('next_receipt_number')->default(1);
            $table->string('member_code_prefix', 10)->default('M');
            $table->unsignedInteger('next_member_number')->default(1);
            $table->string('status')->default('trial'); // trial, active, suspended, cancelled
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->boolean('onboarding_completed')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->json('opening_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Staff membership of a gym: one row per (gym, user) with the role in that gym.
        Schema::create('gym_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role'); // owner, manager, trainer, receptionist
            $table->string('designation')->nullable();
            $table->time('shift_start')->nullable();
            $table->time('shift_end')->nullable();
            $table->date('joined_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['gym_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_user');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('gyms');
        Schema::dropIfExists('saas_plans');
    }
};
