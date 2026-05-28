<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('approval_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_draft_id')->constrained()->cascadeOnDelete();

            // String for now — no auth in this case study. In prod this is users.id.
            $table->string('operator_identifier');

            $table->string('decision'); // approved | rejected | overridden
            $table->text('note')->nullable();
            $table->json('overridden_fields')->nullable();

            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index('task_draft_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_decisions');
    }
};
