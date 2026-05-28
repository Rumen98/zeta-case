<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('incoming_emails', function (Blueprint $table) {
            $table->id();
            $table->string('message_hash', 64)->unique(); // sha256(from|subject|body) — dedup key
            $table->string('from_address');
            $table->string('subject', 998);
            $table->longText('body');
            $table->json('raw_payload')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index('from_address');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_emails');
    }
};
