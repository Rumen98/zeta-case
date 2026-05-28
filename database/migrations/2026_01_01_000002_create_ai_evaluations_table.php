<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_email_id')->constrained()->cascadeOnDelete();

            $table->string('provider');
            $table->string('model')->nullable();
            $table->string('status'); // success | failed

            $table->json('output')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();

            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->index(['incoming_email_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_evaluations');
    }
};
