<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->string('status', 30)->default('recording');
            $table->string('consent_status', 20)->default('granted');
            $table->string('consent_version', 100);
            $table->timestamp('consented_at');
            $table->timestamp('consent_withdrawn_at')->nullable();
            $table->unsignedInteger('expected_segments')->nullable();
            $table->unsignedInteger('total_seconds')->default(0);
            $table->unsignedInteger('processing_attempts')->default(0);
            $table->string('provider', 30);
            $table->string('transcription_model', 100);
            $table->string('extraction_model', 100);
            $table->string('failure_code', 50)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['clinic_id', 'idempotency_key']);
            $table->index(['clinic_id', 'created_at']);
            $table->index(['visit_id', 'status']);
        });
        Schema::create('voice_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('session_id')->constrained('voice_sessions')->restrictOnDelete();
            $table->unsignedInteger('segment_no');
            $table->string('audio_path')->nullable();
            $table->string('audio_format', 10);
            $table->string('checksum', 64);
            $table->unsignedInteger('duration_seconds');
            $table->string('processing_status', 20)->default('pending');
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'segment_no']);
        });
        Schema::create('voice_transcripts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('session_id')->constrained('voice_sessions')->restrictOnDelete();
            $table->foreignId('segment_id')->unique()->constrained('voice_segments')->restrictOnDelete();
            $table->longText('transcript_text')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('ai_clinical_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('session_id')->unique()->constrained('voice_sessions')->restrictOnDelete();
            $table->longText('payload')->nullable();
            $table->longText('edited_soap')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('review_status', 20)->default('review_required');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('applied_record_version')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
        Schema::create('ai_review_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('draft_id')->constrained('ai_clinical_drafts')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 30);
            $table->longText('changes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_review_logs');
        Schema::dropIfExists('ai_clinical_drafts');
        Schema::dropIfExists('voice_transcripts');
        Schema::dropIfExists('voice_segments');
        Schema::dropIfExists('voice_sessions');
    }
};
