<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('event'); // see App\Enums\MessageEvent
            $table->string('channel'); // whatsapp, sms, email
            $table->string('subject')->nullable();
            $table->text('body');
            // Approved WhatsApp template name / MSG91 flow id. Required by both providers
            // for business-initiated messages; placeholders are sent as variables in order.
            $table->string('provider_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['gym_id', 'event', 'channel']);
        });

        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->json('channels');
            $table->string('audience')->default('active'); // all, active, expired
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('broadcast_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->string('channel');
            $table->string('recipient');
            $table->text('body');
            $table->string('status'); // sent, failed, skipped
            $table->text('error')->nullable();
            // Used to avoid sending the same reminder twice for the same date.
            $table->string('dedupe_key')->nullable();
            $table->timestamps();
            $table->index(['gym_id', 'dedupe_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
        Schema::dropIfExists('broadcasts');
        Schema::dropIfExists('message_templates');
    }
};
