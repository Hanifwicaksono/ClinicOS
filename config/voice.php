<?php

return [
    'enabled' => (bool) env('VOICE_ENABLED', false),
    'clinic_ids' => array_filter(explode(',', (string) env('VOICE_CLINIC_IDS', ''))),
    'privacy_approved' => (bool) env('VOICE_PRIVACY_APPROVED', false),
    'consent_version' => env('VOICE_CONSENT_VERSION', 'voice-v1'),
    'provider' => env('VOICE_PROVIDER', 'openai'),
    'api_key' => env('VOICE_OPENAI_API_KEY', ''),
    'transcription_model' => env('VOICE_TRANSCRIPTION_MODEL', ''),
    'extraction_model' => env('VOICE_EXTRACTION_MODEL', ''),
    'ffprobe' => env('VOICE_FFPROBE_BINARY', 'ffprobe'),
    'max_segment_kb' => 10240,
    'max_segment_seconds' => 65,
    'max_session_seconds' => (int) env('VOICE_MAX_SESSION_SECONDS', 1800),
    'monthly_seconds' => (int) env('VOICE_MONTHLY_SECONDS', 18000),
    'audio_retention_hours' => (int) env('VOICE_AUDIO_RETENTION_HOURS', 24),
    'transcript_retention_days' => (int) env('VOICE_TRANSCRIPT_RETENTION_DAYS', 7),
    'draft_retention_days' => (int) env('VOICE_DRAFT_RETENTION_DAYS', 30),
];
