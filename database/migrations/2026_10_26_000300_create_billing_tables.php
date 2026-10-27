<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_number')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('method'); // see App\Enums\PaymentMethod
            $table->string('reference')->nullable();
            $table->string('gateway_payment_id')->nullable()->unique();
            $table->dateTime('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['gym_id', 'paid_at']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category'); // rent, salary, equipment, electricity, maintenance, marketing, other
            $table->string('description')->nullable();
            $table->decimal('amount', 10, 2);
            $table->date('spent_on');
            $table->string('method')->default('cash');
            $table->string('reference')->nullable();
            $table->timestamps();
            $table->index(['gym_id', 'spent_on']);
        });

        // Day-end closing: compare the cash the system expects with the cash counted at the desk.
        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('closing_date');
            $table->decimal('opening_balance', 10, 2)->default(0);
            $table->decimal('cash_collected', 10, 2)->default(0);
            $table->decimal('cash_expenses', 10, 2)->default(0);
            $table->decimal('expected_cash', 10, 2)->default(0);
            $table->decimal('counted_cash', 10, 2)->default(0);
            $table->decimal('difference', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'closing_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closings');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('payments');
    }
};
