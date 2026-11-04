<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Member asked not to get offers (broadcasts, win-back). Service reminders still go out.
            $table->boolean('marketing_opt_out')->default(false)->after('consent_source');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('marketing_opt_out');
        });
    }
};
