<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_email_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_evaluation_id')->nullable()->constrained()->nullOnDelete();

            // AI-suggested fields. All nullable — the parser may fail or only
            // return part of the answer; the human fills the gaps.
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->string('priority')->nullable();
            $table->string('suggested_project')->nullable();
            $table->string('suggested_team')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->json('missing_information')->nullable();
            $table->text('suggested_next_action')->nullable();

            // pending | approved | rejected | overridden | needs_manual_review
            $table->string('status')->default('pending');

            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_drafts');
    }
};
