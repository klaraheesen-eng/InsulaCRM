<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('office_address_lookup_phone')->nullable()->after('measurement_system');
        });

        Schema::create('office_lookup_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 96)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->json('completed_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_lookup_requests');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('office_address_lookup_phone');
        });
    }
};
