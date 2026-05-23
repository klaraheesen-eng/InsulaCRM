<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'started_at']);
        });

        Schema::create('scout_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scout_session_id')->nullable()->constrained('scout_sessions')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('previous_point_id')->nullable()->constrained('scout_points')->nullOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'captured_at']);
            $table->index(['scout_session_id', 'captured_at']);
        });

        Schema::create('scout_lead_captures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scout_point_id')->nullable()->constrained('scout_points')->nullOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('address')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['lead_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_lead_captures');
        Schema::dropIfExists('scout_points');
        Schema::dropIfExists('scout_sessions');
    }
};
