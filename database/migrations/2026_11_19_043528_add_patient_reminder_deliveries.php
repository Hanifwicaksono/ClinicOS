<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('reminder_preferences')->nullable();
        });
        Schema::table('queues', function (Blueprint $table): void {
            $table->unsignedInteger('near_turn_generation')->default(1);
            $table->boolean('near_turn_return_pending')->default(false);
        });
        Schema::table('follow_ups', function (Blueprint $table): void {
            $table->unsignedInteger('reminder_generation')->default(1);
        });
        Schema::create('patient_reminder_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 30);
            $table->unsignedInteger('generation');
            $table->uuid('notification_id')->unique();
            $table->string('state', 20)->default('PENDING');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('broadcast_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['target_type', 'target_id', 'recipient_user_id', 'category', 'generation'], 'patient_reminder_event_unique');
            $table->index(['state', 'queued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_reminder_deliveries');
        Schema::table('follow_ups', fn (Blueprint $t) => $t->dropColumn('reminder_generation'));
        Schema::table('queues', fn (Blueprint $t) => $t->dropColumn(['near_turn_generation', 'near_turn_return_pending']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('reminder_preferences'));
    }
};
