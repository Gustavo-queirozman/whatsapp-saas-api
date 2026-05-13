<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->text('welcome_message')->nullable();
            $table->text('menu_message')->nullable();
            $table->text('invalid_option_message')->nullable();
            $table->text('out_of_hours_message')->nullable();
            $table->boolean('office_hours_enabled')->default(false);
            $table->string('office_hours_timezone')->nullable();
            $table->json('office_hours')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'sector_id']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_flows');
    }
};
