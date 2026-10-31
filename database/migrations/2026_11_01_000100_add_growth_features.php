<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Resellers / affiliates who refer gyms to the platform.
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('code')->unique();
            $table->decimal('commission_rate', 5, 2)->default(20); // percent of each software payment
            $table->text('payout_details')->nullable(); // encrypted: bank / UPI for payouts
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('gyms', function (Blueprint $table) {
            $table->foreignId('partner_id')->nullable()->after('saas_plan_id')->constrained()->nullOnDelete();
            // Public website content: tagline, about, cover, gallery, social links, SEO text.
            $table->json('website')->nullable()->after('settings');
            $table->string('custom_domain')->nullable()->unique()->after('slug');
            $table->string('custom_domain_status')->nullable()->after('custom_domain'); // pending, active, failed
            $table->string('cloudflare_hostname_id')->nullable()->after('custom_domain_status');
            $table->timestamp('custom_domain_verified_at')->nullable()->after('cloudflare_hostname_id');
            // Plain (not encrypted) so incoming Meta lead webhooks can find the gym.
            $table->string('facebook_page_id')->nullable()->index()->after('custom_domain_verified_at');
            $table->string('lead_webhook_token', 64)->nullable()->unique()->after('facebook_page_id');
            $table->integer('message_credits')->default(0)->after('next_member_number');
            $table->timestamp('last_activity_at')->nullable()->after('subscription_ends_at');
        });

        // Trainer profile shown on the gym's website.
        Schema::table('gym_user', function (Blueprint $table) {
            $table->text('bio')->nullable()->after('designation');
            $table->string('photo_path')->nullable()->after('bio');
            $table->string('specialities')->nullable()->after('photo_path');
            $table->boolean('show_on_website')->default(false)->after('specialities');
        });

        // Enquiries: walk-ins, calls, website trial bookings, Facebook/Instagram lead ads.
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('source')->default('walk_in'); // see App\Enums\LeadSource
            $table->string('status')->default('new'); // see App\Enums\LeadStatus
            $table->string('interest')->nullable();
            $table->date('trial_date')->nullable();
            $table->string('trial_time')->nullable();
            $table->dateTime('follow_up_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('external_id')->nullable(); // e.g. Meta leadgen id, for de-duplication
            $table->json('raw')->nullable();
            $table->timestamps();
            $table->index(['gym_id', 'status']);
            $table->unique(['gym_id', 'external_id']);
        });

        // What gyms pay the platform, and partner commissions on it.
        Schema::create('saas_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saas_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind')->default('subscription'); // subscription, credits
            $table->decimal('amount', 10, 2);
            $table->unsignedInteger('months')->nullable();
            $table->string('reference')->nullable();
            $table->string('gateway_payment_id')->nullable()->unique();
            $table->dateTime('paid_at');
            $table->timestamps();
        });

        Schema::create('partner_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saas_payment_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate', 5, 2);
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending'); // pending, paid, cancelled
            $table->timestamp('paid_at')->nullable();
            $table->string('payout_reference')->nullable();
            $table->timestamps();
            $table->unique('saas_payment_id');
        });

        // Prepaid SMS / WhatsApp credits ledger.
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_log_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('saas_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // purchase, usage, adjustment
            $table->integer('credits'); // positive adds, negative uses
            $table->integer('balance_after');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->index(['gym_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('partner_commissions');
        Schema::dropIfExists('saas_payments');
        Schema::dropIfExists('leads');

        Schema::table('gym_user', function (Blueprint $table) {
            $table->dropColumn(['bio', 'photo_path', 'specialities', 'show_on_website']);
        });

        Schema::table('gyms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_id');
            $table->dropUnique(['custom_domain']);
            $table->dropUnique(['lead_webhook_token']);
            $table->dropIndex(['facebook_page_id']);
            $table->dropColumn([
                'website', 'custom_domain', 'custom_domain_status', 'cloudflare_hostname_id', 'custom_domain_verified_at',
                'facebook_page_id', 'lead_webhook_token', 'message_credits', 'last_activity_at',
            ]);
        });

        Schema::dropIfExists('partners');
    }
};
