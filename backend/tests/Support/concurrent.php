<?php

use App\Http\Controllers\TwoFactorSessionController;
use App\Models\QsoMatch;
use App\Models\User;
use App\Services\QslService;
use App\Services\QsoMatchingService;
use App\Services\QsoService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$action = $argv[1];
$data = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
try {
    if ($action === 'qso') {
        $result = app(QsoService::class)->create(User::findOrFail($data['user']), $data['payload'])->id;
    } elseif ($action === 'confirm') {
        $result = app(QsoMatchingService::class)->decide(User::findOrFail($data['user']), QsoMatch::findOrFail($data['match']), 'confirm')->status;
    } elseif ($action === 'issue') {
        $result = app(QslService::class)->issue(User::findOrFail($data['user']), QsoMatch::findOrFail($data['match']))->public_id;
    } elseif ($action === 'recover') {
        $request = TwoFactorLoginRequest::create('/api/v1/auth/two-factor-challenge', 'POST', ['recovery_code' => $data['code']], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $request->setContainer($app);
        $request->setRedirector(app('redirect'));
        $session = app('session')->driver();
        $session->start();
        $session->put('login.id', $data['user']);
        $request->setLaravelSession($session);
        $app->instance('request', $request);
        app(TwoFactorSessionController::class)->store($request);
        $result = true;
    }
    echo json_encode(['result' => $result], JSON_THROW_ON_ERROR);
} catch (ValidationException $exception) {
    echo json_encode(['result' => false]);
}
