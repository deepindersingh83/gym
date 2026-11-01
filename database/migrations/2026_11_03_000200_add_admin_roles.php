<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Platform staff: owner, support, sales, finance. Null for everyone else.
            $table->string('admin_role', 20)->nullable()->after('is_super_admin');
        });

        // Existing super admins become platform owners.
        DB::table('users')->where('is_super_admin', true)->update(['admin_role' => 'owner']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('admin_role');
        });
    }
};
