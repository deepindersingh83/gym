<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // When and how the member agreed to the gym storing their details.
            $table->timestamp('consented_at')->nullable()->after('source');
            $table->string('consent_source')->nullable()->after('consented_at'); // online_signup, staff, member_app
            $table->timestamp('erased_at')->nullable()->after('consent_source');
        });

        Schema::table('gyms', function (Blueprint $table) {
            // Closed accounts are kept 30 days (can be restored), then deleted for good.
            $table->timestamp('deletion_scheduled_at')->nullable()->after('suspension_reason');
            $table->string('closure_reason')->nullable()->after('deletion_scheduled_at');
        });

        // The platform must keep its tax invoices after a gym is deleted.
        foreach (['saas_payments', 'partner_commissions'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['gym_id']);
            });
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('gym_id')->nullable()->change();
                $table->foreign('gym_id')->references('id')->on('gyms')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['saas_payments', 'partner_commissions'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['gym_id']);
            });
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('gym_id')->nullable(false)->change();
                $table->foreign('gym_id')->references('id')->on('gyms')->cascadeOnDelete();
            });
        }

        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn(['deletion_scheduled_at', 'closure_reason']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['consented_at', 'consent_source', 'erased_at']);
        });
    }
};
