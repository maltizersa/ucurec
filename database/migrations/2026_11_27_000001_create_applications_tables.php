<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Yearly counter for reference numbers (UCUREC-2026-0001).
        Schema::create('reference_counters', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->nullable()->unique();
            $table->foreignId('researcher_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 500);
            $table->string('principal_investigator');
            $table->string('pi_contact');
            $table->string('adviser')->nullable();
            $table->string('college_program');
            $table->string('research_type', 100);
            $table->string('status', 40)->index();
            $table->string('review_route', 40)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('last_submitted_at')->nullable();
            $table->timestamps();
        });

        // Every submission or revision is a numbered, dated set of documents.
        // A set is editable only while submitted_at is null (a draft).
        Schema::create('document_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('kind', 30);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'version']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_set_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50);
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            // Set when the row was carried forward from an earlier set (same stored file).
            $table->foreignId('carried_from_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->index('stored_path');
        });

        Schema::create('screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_set_id')->constrained()->restrictOnDelete();
            $table->foreignId('screened_by')->constrained('users')->restrictOnDelete();
            $table->string('result', 20);
            // Snapshot of the checklist: [{key, label, state, note}]
            $table->json('checklist');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('application_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_status_histories');
        Schema::dropIfExists('screenings');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_sets');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('reference_counters');
    }
};
