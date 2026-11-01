<?php

use App\Services\Messaging\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Members may have only a mobile number.
            $table->string('email')->nullable()->change();
            // Phone in international digits (919876543210) so any way of typing it matches.
            $table->string('phone_e164', 20)->nullable()->index()->after('phone');
        });

        DB::table('users')->whereNotNull('phone')->orderBy('id')->each(function ($user) {
            $normalized = preg_replace('/\D/', '', (string) $user->phone) === '' ? null : PhoneNumber::international($user->phone);
            DB::table('users')->where('id', $user->id)->update(['phone_e164' => $normalized]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone_e164']);
            $table->dropColumn('phone_e164');
        });
    }
};
