<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One reviewer assigned to review one exact document set (version).
        Schema::create('review_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_set_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->index();
            $table->date('due_at');

            $table->foreignId('proposed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->string('authorization_note', 500)->nullable();

            // Reviewer response and conflict-of-interest declaration.
            $table->timestamp('responded_at')->nullable();
            $table->boolean('has_conflict')->nullable();
            $table->text('conflict_details')->nullable();
            $table->string('decline_reason', 500)->nullable();

            // The review itself.
            $table->string('recommendation', 40)->nullable();
            $table->text('review_comments')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();

            $table->timestamps();

            $table->index(['reviewer_id', 'status']);
        });

        // Completed UCUREC Forms 4A / 4B (Word or PDF) uploaded by the reviewer.
        Schema::create('review_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_assignment_id')->constrained()->cascadeOnDelete();
            $table->string('form_type', 30);
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_files');
        Schema::dropIfExists('review_assignments');
    }
};
