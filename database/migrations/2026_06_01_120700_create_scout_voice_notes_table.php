<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scout_voice_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scout_session_id')->nullable()->constrained('scout_sessions')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->string('audio_path');
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('transcribed_at')->nullable();
            $table->longText('transcript')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'captured_at']);
            $table->index(['scout_session_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_voice_notes');
    }
};
