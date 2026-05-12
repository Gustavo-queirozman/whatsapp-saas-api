<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->timestamp('assigned_at')->nullable()->after('status');
            $table->timestamp('closed_at')->nullable()->after('assigned_at');

            $table->index(['company_id', 'assigned_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex('conversations_company_id_assigned_user_id_index');
            $table->dropColumn(['assigned_at', 'closed_at']);
        });
    }
};
