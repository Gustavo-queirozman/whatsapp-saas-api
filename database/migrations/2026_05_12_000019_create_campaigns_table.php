<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_instance_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('message');
            $table->unsignedInteger('send_limit_per_minute')->default(20);
            $table->string('status')->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'scheduled_at']);
            $table->index(['company_id', 'whatsapp_instance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
