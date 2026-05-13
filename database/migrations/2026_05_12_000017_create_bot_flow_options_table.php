<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_flow_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bot_flow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->string('label');
            $table->string('number', 20)->nullable();
            $table->json('keywords')->nullable();
            $table->string('action')->default('reply');
            $table->text('response_message')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'bot_flow_id']);
            $table->index(['company_id', 'target_sector_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_flow_options');
    }
};
