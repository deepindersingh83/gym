<?php

use App\Support\Modules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saas_plans', function (Blueprint $table) {
            // Feature modules the plan includes; null means every module.
            $table->json('modules')->nullable()->after('max_staff');
            $table->decimal('monthly_price_aud', 10, 2)->default(0)->after('yearly_price');
            $table->decimal('yearly_price_aud', 10, 2)->default(0)->after('monthly_price_aud');
        });

        $aud = ['basic' => [29, 290], 'pro' => [59, 590], 'enterprise' => [149, 1490]];

        foreach (Modules::DEFAULTS as $slug => $modules) {
            DB::table('saas_plans')->where('slug', $slug)->update([
                'modules' => $modules === null ? null : json_encode($modules),
                'monthly_price_aud' => $aud[$slug][0],
                'yearly_price_aud' => $aud[$slug][1],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('saas_plans', function (Blueprint $table) {
            $table->dropColumn(['modules', 'monthly_price_aud', 'yearly_price_aud']);
        });
    }
};
