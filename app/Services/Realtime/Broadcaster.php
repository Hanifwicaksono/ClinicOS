<?php

namespace App\Services\Realtime;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;

class Broadcaster extends PusherBroadcaster
{
    public function validAuthenticationResponse($request, $result)
    {
        $channel = $request->channel_name;
        $authority = app(AuthorizationContext::class);
        $context = $authority->read($request->user()->id);
        abort_unless(! config('reverb.servers.reverb.scaling.enabled') && $authority->permits($channel, $context), 403);
        $identity = ['user_id' => $context['user_id'], 'version' => $context['version'], 'clinic_id' => $context['clinic_id'],
            'app_id' => (string) config('broadcasting.connections.reverb.app_id'), 'channel' => $channel,
            'session_scope' => app(SessionGrants::class)->issue($request->session()->getId(), $request->user()->id)];

        return json_decode($this->pusher->authorizeChannel($channel, $request->socket_id,
            json_encode($identity, JSON_THROW_ON_ERROR)), true, flags: JSON_THROW_ON_ERROR);
    }
}
