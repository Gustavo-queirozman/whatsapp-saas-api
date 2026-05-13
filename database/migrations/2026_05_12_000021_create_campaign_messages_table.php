<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_instance_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->string('phone', 30);
            $table->string('external_id')->nullable();
            $table->text('body');
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'campaign_id', 'status']);
            $table->index(['company_id', 'campaign_contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_messages');
    }
};
