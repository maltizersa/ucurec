<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The REC decision for one reviewed version, recorded by the
        // Secretariat and confirmed for release by the Chairperson.
        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_set_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->index();

            $table->string('category', 50);
            $table->date('decision_date');
            $table->date('approval_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('basis')->nullable();                  // internal
            $table->text('consolidated_comments')->nullable();  // released to the researcher

            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('sent_for_confirmation_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('chair_note', 1000)->nullable();     // when returned to the Secretariat
            $table->timestamp('released_at')->nullable();

            $table->timestamps();
        });

        Schema::create('decision_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('decision_id')->constrained()->cascadeOnDelete();
            // letter = signed official letter or clearance; comments = consolidated
            // comments document; minutes = decision record / minutes extract (internal)
            $table->string('kind', 30);
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
        Schema::dropIfExists('decision_files');
        Schema::dropIfExists('decisions');
    }
};
