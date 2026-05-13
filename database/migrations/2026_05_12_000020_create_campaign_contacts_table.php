<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 30);
            $table->string('status')->default('pending');
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'campaign_id', 'phone']);
            $table->index(['company_id', 'campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_contacts');
    }
};
