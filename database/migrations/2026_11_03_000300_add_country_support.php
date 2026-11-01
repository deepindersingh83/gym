<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->char('country', 2)->default('IN')->after('pincode'); // IN, AU
            $table->string('abn', 14)->nullable()->after('gstin'); // Australian Business Number
            $table->boolean('gst_registered')->default(false)->after('abn'); // Australia: registered for GST
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // Total tax on the invoice (India: CGST + SGST; Australia: GST included in the price).
            $table->decimal('tax_amount', 10, 2)->default(0)->after('sgst_amount');
            $table->boolean('tax_inclusive')->default(false)->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['tax_amount', 'tax_inclusive']);
        });

        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn(['country', 'abn', 'gst_registered']);
        });
    }
};
