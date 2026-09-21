<?php

// Disposable PostgreSQL database and fake notifications: never sends real mail.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\TransactionalMailService;
use App\Services\Auth\AuthRedirect;
use App\Services\Auth\AuthService;
use App\Services\FamilyGroup\FamilyGroupInvitationService;
use App\Notifications\WelcomeMail;
use App\Notifications\FamilyInvitationMail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

function checkMail($condition, string $message): void
{
    if (! $condition) { throw new RuntimeException($message); }
}

$db = config('database.connections.pgsql');
checkMail(in_array($db['host'], ['localhost', '127.0.0.1'], true) && empty($db['url']), 'Solo PostgreSQL local sin DATABASE_URL.');
$admin = new PDO('pgsql:host='.$db['host'].';port='.$db['port'].';dbname=postgres', $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'cccontrol_mail_smoke_'.bin2hex(random_bytes(6));
$admin->exec('CREATE DATABASE "'.$database.'"');
$exitCode = 0;
try {
    config([
        'database.default' => 'pgsql', 'database.connections.pgsql.database' => $database,
        'database.connections.pgsql.url' => null, 'session.driver' => 'array', 'cache.default' => 'array',
        'mail.default' => 'array', 'mail.transactional_enabled' => true,
        'mail.from.address' => 'no-reply@example.com', 'app.url' => 'https://cocina.example.com',
    ]);
    DB::purge('pgsql');
    $app['env'] = 'production';
    checkMail($kernel->call('app:initialize-database', ['--force' => true]) === 0, 'No se pudo inicializar la base aislada.');
    $app['env'] = 'testing';
    $notificationManager = Notification::getFacadeRoot();
    $fake = Notification::fake();
    $service = app(TransactionalMailService::class);
    $request = Illuminate\Http\Request::create('https://untrusted.example/api/v1/auth/register', 'POST');
    $request->setLaravelSession(app('session.store'));
    $owner = app(AuthService::class)->register([
        'name' => 'Prueba correo', 'email' => 'owner@example.com', 'password' => 'test-password-123',
    ], $request);
    checkMail($fake->sent($owner, WelcomeMail::class)->count() === 1, 'El registro debe enviar una sola bienvenida.');
    app(AuthService::class)->login(['email' => $owner->email, 'password' => 'test-password-123'], $request);
    checkMail($fake->sent($owner, WelcomeMail::class)->count() === 1, 'El login no debe reenviar la bienvenida.');
    $welcome = $fake->sent($owner, WelcomeMail::class)->first()->toMail($owner);
    checkMail($welcome->viewData['actionUrl'] === 'https://cocina.example.com/login', 'No confiar en Host para enlaces.');

    config(['mail.transactional_enabled' => false]);
    checkMail($service->welcome($owner) === 'disabled', 'Los envios deben poder desactivarse.');
    checkMail($fake->sent($owner, WelcomeMail::class)->count() === 1, 'Desactivado no envia.');
    config(['mail.transactional_enabled' => true, 'mail.default' => 'resend', 'mail.mailers.resend.test_recipient' => 'solo@example.com']);
    checkMail($service->welcome($owner) === 'restricted', 'El modo de prueba debe bloquear otros destinatarios.');
    config(['mail.default' => 'array']);

    Password::sendResetLink(['email' => $owner->email]);
    $reset = $fake->sent($owner, ResetPassword::class)->first();
    checkMail($reset !== null, 'Falta correo de recuperacion.');
    $resetMail = $reset->toMail($owner);
    checkMail($resetMail->viewData['actionUrl'] === 'https://cocina.example.com/password/reset/'.$reset->token.'?email=owner%40example.com', 'Reset debe abrir en navegador de PC o celular.');
    $controller = app(App\Http\Controllers\Api\V1\Auth\PasswordController::class);
    $forgot = function ($email) use ($controller) {
        $req = App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest::create('/api/v1/auth/forgot-password', 'POST', ['email' => $email]);
        $req->attributes->set('trace_id', 'mail-test');
        return $controller->forgotPassword($req)->getContent();
    };
    checkMail($forgot($owner->email) === $forgot('missing@example.com'), 'No revelar si el email existe.');
    $payload = ['email' => $owner->email, 'token' => $reset->token, 'password' => 'changed-password-123', 'password_confirmation' => 'changed-password-123'];
    $req = App\Http\Requests\Api\V1\Auth\ResetPasswordRequest::create('/api/v1/auth/reset-password', 'POST', $payload);
    $req->attributes->set('trace_id', 'mail-test');
    checkMail($controller->resetPassword($req)->getStatusCode() === 200, 'No se pudo restablecer la contrasena.');
    checkMail(Hash::check('changed-password-123', $owner->fresh()->password), 'No se guardo la nueva contrasena.');
    checkMail(! Password::tokenExists($owner, $reset->token), 'El token no debe poder reutilizarse.');

    $group = factory(App\FamilyGroup::class)->create(['owner_user_id' => $owner->id, 'name' => '<script>privado</script>']);
    factory(App\FamilyGroupMember::class)->create(['family_group_id' => $group->id, 'user_id' => $owner->id, 'role_in_group' => 'owner', 'status' => 'active']);
    $invites = app(FamilyGroupInvitationService::class);
    $invitation = $invites->create($group->id, $owner->id, ['email' => 'guest@example.com'], '127.0.0.1', 'smoke');
    checkMail($invitation->emailDeliveryStatus === 'accepted', 'La invitacion debe enviarse despues de guardarse.');
    $anonymous = new Illuminate\Notifications\AnonymousNotifiable;
    $inviteMail = $fake->sent($anonymous, FamilyInvitationMail::class)->first()->toMail($anonymous);
    checkMail(strpos($inviteMail->viewData['actionUrl'], '/web/family-group?invitation='.$invitation->id) !== false, 'Falta enlace de invitacion.');
    foreach ([$welcome, $resetMail, $inviteMail] as $mail) {
        $html = view('mail.transactional', $mail->viewData)->render();
        $text = view('mail.transactional-text', $mail->viewData)->render();
        checkMail(strpos($html, 'CocinaComidaControl') !== false && strpos($text, 'CocinaComidaControl') !== false, 'Falta contenido HTML/texto.');
        checkMail(strpos($html, '<script>privado') === false, 'Escapar datos del grupo en correos.');
    }
    checkMail($invites->resend($group->id, $owner->id, $invitation->id)->emailDeliveryStatus === 'throttled', 'No reenviar antes de un minuto.');
    Cache::forget('family-invitation-mail:'.$invitation->id);
    checkMail($invites->resend($group->id, $owner->id, $invitation->id)->emailDeliveryStatus === 'accepted', 'Reenvio autorizado.');
    checkMail(App\FamilyGroupInvitation::count() === 1, 'Reenviar no debe duplicar invitaciones.');
    $guest = factory(App\User::class)->create(['email' => 'guest@example.com']);
    $stranger = factory(App\User::class)->create(['email' => 'stranger@example.com']);
    foreach (['resend', 'accept'] as $action) {
        try {
            $action === 'resend' ? $invites->resend($group->id, $stranger->id, $invitation->id)
                : $invites->accept($invitation->id, $stranger->id, '127.0.0.1', 'smoke');
            throw new RuntimeException('Un tercero no debe '.$action.' la invitacion.');
        } catch (App\Exceptions\FamilyGroup\FamilyGroupException $expected) {}
    }
    checkMail($invites->accept($invitation->id, $guest->id, '127.0.0.1', 'smoke')->status === 'accepted', 'El destinatario debe poder aceptar.');
    checkMail(App\FamilyGroupMember::where('user_id', $guest->id)->count() === 1, 'Falta membresia del invitado.');
    try {
        $invites->resend($group->id, $owner->id, $invitation->id);
        throw new RuntimeException('No reenviar invitacion aceptada.');
    } catch (App\Exceptions\FamilyGroup\FamilyGroupException $expected) {}

    session(['url.intended' => 'https://evil.example/web/family-group?invitation=123']);
    checkMail(parse_url(AuthRedirect::afterLogin(), PHP_URL_HOST) !== 'evil.example', 'No redirigir a sitios externos.');
    checkMail(strpos(AuthRedirect::afterLogin(), '/web/family-group?invitation=123') !== false, 'Conservar invitacion despues del login.');
    session(['url.intended' => 'https://evil.example/otro?invitation=123']);
    checkMail(strpos(AuthRedirect::afterLogin(), 'invitation') === false, 'Solo admitir el destino esperado.');
    $rules = (new App\Http\Requests\Api\V1\FamilyGroup\CreateInvitationRequest)->rules();
    checkMail(! validator(['email' => 'guest@example.com'], $rules)->fails(), 'Compatibilidad con APK que no envia role.');
    checkMail(validator(['email' => 'guest@example.com', 'role' => 'owner'], $rules)->fails(), 'No permitir escalamiento de rol.');

    Notification::shouldReceive('send')->andThrow(new RuntimeException('secret-provider-token'));
    Illuminate\Support\Facades\Log::spy();
    $survivor = app(AuthService::class)->register(['name' => 'Correo caido', 'email' => 'failure@example.com', 'password' => 'test-password-123'], $request);
    checkMail($survivor->exists, 'La falla del proveedor no debe impedir registrar la cuenta.');
    $failedInvite = $invites->create($group->id, $owner->id, ['email' => 'failure-invite@example.com'], '127.0.0.1', 'smoke');
    checkMail($failedInvite->exists && $failedInvite->emailDeliveryStatus === 'failed', 'Preservar invitacion ante falla y no afirmar envio.');
    $resource = (new App\Http\Resources\Api\V1\FamilyGroup\FamilyGroupInvitationResource($failedInvite))->resolve();
    checkMail($resource['email_delivery']['status'] === 'failed' && ! isset($resource['token']), 'La respuesta no debe exponer secretos.');
    $survivor->sendPasswordResetNotification('private-reset-token');
    Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->with('transactional_mail_failed', ['kind' => 'password_reset'])->once();

    Notification::swap($notificationManager);
    checkMail($service->welcome($owner) === 'accepted', 'No se pudo renderizar el correo real en memoria.');
    $owner->sendPasswordResetNotification('render-only-token');
    checkMail($service->invitation($failedInvite) === 'accepted', 'No se pudo renderizar la invitacion en memoria.');
    $messages = app('mail.manager')->mailer('array')->getSwiftMailer()->getTransport()->messages();
    checkMail($messages->count() === 3, 'Faltan correos en el transporte de memoria.');
    foreach ($messages as $message) {
        checkMail(strpos($message->getBody(), 'CocinaComidaControl') !== false, 'El correo renderizado esta vacio.');
        checkMail(count($message->getChildren()) > 0, 'Falta version alternativa de texto.');
    }
    echo "OK: registro/login, fallas de correo, sandbox, reset de un uso, enlaces web, plantillas, invitaciones, reenvio, permisos y compatibilidad APK.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage()."\n");
    $exitCode = 1;
} finally {
    DB::purge('pgsql');
    $admin->exec('DROP DATABASE "'.$database.'"');
}
exit($exitCode);
