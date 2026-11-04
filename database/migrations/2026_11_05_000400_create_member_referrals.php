<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('referral_code', 20)->nullable()->after('member_code');
            $table->foreignId('referred_by_member_id')->nullable()->after('trainer_id')->constrained('members')->nullOnDelete();
            $table->unsignedInteger('bonus_days')->default(0)->after('marketing_opt_out'); // free days waiting for the next membership
            $table->unique(['gym_id', 'referral_code']);
        });

        // "Bring a friend": both get free days once the friend makes their first payment.
        Schema::create('member_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referrer_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('referred_id')->constrained('members')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, rewarded
            $table->unsignedInteger('referrer_days')->default(0);
            $table->unsignedInteger('friend_days')->default(0);
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();
            $table->unique('referred_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_referrals');
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['gym_id', 'referral_code']);
            $table->dropConstrainedForeignId('referred_by_member_id');
            $table->dropColumn(['referral_code', 'bonus_days']);
        });
    }
};
