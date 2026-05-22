<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE activities MODIFY COLUMN type ENUM('call','sms','email','note','meeting','voicemail','direct_mail','stage_change','whatsapp') DEFAULT 'note'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE activities MODIFY COLUMN type ENUM('call','sms','email','note','meeting','voicemail','direct_mail','stage_change') DEFAULT 'note'");
        }
    }
};
