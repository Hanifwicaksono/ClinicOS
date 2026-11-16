<?php

use App\Services\Realtime\AuthorizationContext;
use Illuminate\Contracts\Console\Kernel;

require getenv('AUDIT_ROOT').'/application.php';
$app->make(Kernel::class)->bootstrap();
$out = [];
foreach ([13, 15] as $id) {
    try {
        $context = app(AuthorizationContext::class)->read($id);
        $out[$id] = ['version' => $context['version'], 'active' => $context['active'], 'verified' => $context['verified'],
            'roles' => $context['roles'], 'queue_permitted' => app(AuthorizationContext::class)->permits('private-clinic.6.queues', $context),
            'notification_permitted' => app(AuthorizationContext::class)->permits('private-users.'.$id, $context)];
    } catch (Throwable $error) {
        $out[$id] = ['error' => $error::class, 'code' => $error->getCode()];
    }
}
echo json_encode($out);
