<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\VoiceSessionRequest;
use App\Models\Visit;
use App\Models\VoiceSession;
use App\Services\VoiceScribeService;
use Illuminate\Http\JsonResponse;

class VoiceSessionController extends Controller
{
    public function index(VoiceSessionRequest $request, Visit $visit, VoiceScribeService $scribe): JsonResponse
    {
        $session = VoiceSession::query()->where('clinic_id', $visit->clinic_id)->where('visit_id', $visit->id)
            ->where('created_by_user_id', $request->user()->id)->latest()->first();

        return response()->json(['session' => $session === null ? null : $scribe->present($session)])->header('Cache-Control', 'private, no-store');
    }

    public function store(VoiceSessionRequest $request, Visit $visit, VoiceScribeService $scribe): JsonResponse
    {
        return response()->json($scribe->present($scribe->start($visit, $request->user(), $request->validated())))->header('Cache-Control', 'private, no-store');
    }

    public function show(VoiceSessionRequest $request, Visit $visit, VoiceSession $voiceSession, VoiceScribeService $scribe): JsonResponse
    {
        return response()->json($scribe->present($voiceSession))->header('Cache-Control', 'private, no-store');
    }

    public function segment(VoiceSessionRequest $request, Visit $visit, VoiceSession $voiceSession, VoiceScribeService $scribe): JsonResponse
    {
        $scribe->upload($visit, $voiceSession, $request->user(), $request->file('audio'), (int) $request->validated('segment_no'));

        return response()->json(['message' => 'Segmen tersimpan.'])->header('Cache-Control', 'private, no-store');
    }

    public function change(VoiceSessionRequest $request, Visit $visit, VoiceSession $voiceSession, string $action, VoiceScribeService $scribe): JsonResponse
    {
        $session = in_array($action, ['review', 'approve'], true)
            ? $scribe->review($visit, $voiceSession, $request->user(), $request->validated(), $action === 'approve')
            : $scribe->change($visit, $voiceSession, $request->user(), $action, $request->validated());

        return response()->json($scribe->present($session))->header('Cache-Control', 'private, no-store');
    }
}
