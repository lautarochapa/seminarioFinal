<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
set_exception_handler(function (\Throwable $exception) {
    fwrite(STDERR, 'FAIL: '.$exception->getMessage()."\n");
    exit(1);
});

use App\Mail\Transport\ResendTransport;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

function checkResend($condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function resendMessage()
{
    return (new Swift_Message('Prueba'))
        ->setFrom(['onboarding@resend.dev' => 'CocinaComidaControl'])
        ->setTo('cuenta@example.com')
        ->setBody('Prueba de correo', 'text/plain');
}

function expectResendFailure(callable $send, string $expected): void
{
    try {
        $send();
        throw new RuntimeException('Se esperaba que el envio fuera rechazado.');
    } catch (Swift_TransportException $exception) {
        checkResend(strpos($exception->getMessage(), $expected) !== false, 'Error inesperado: '.$exception->getMessage());
        checkResend($exception->getPrevious() === null, 'No incluir requests con claves en la cadena de errores.');
        checkResend(strpos($exception->getMessage(), 're_test_secret') === false, 'No exponer la clave.');
    }
}

$id = '49a3999c-0ce1-4ea6-ab68-afcd6dc2e794';
$history = [];
$mock = new MockHandler([
    new Response(200, [], json_encode(['id' => $id])),
    new Response(200, [], json_encode(['id' => $id])),
    new Response(403, [], '{"message":"re_test_secret: datos privados"}'),
    new Response(429, [], '{}'),
    new Response(200, [], '{}'),
    new Response(200, [], 'no-json'),
    new \GuzzleHttp\Exception\ConnectException('re_test_secret', new \GuzzleHttp\Psr7\Request('POST', 'https://api.resend.com/emails')),
]);
$stack = HandlerStack::create($mock);
$stack->push(Middleware::history($history));
$client = new Client(['handler' => $stack]);
$transport = new ResendTransport($client, 're_test_secret', 'CUENTA@example.com');
$message = resendMessage()->addPart('<p>Prueba de correo</p>', 'text/html')->setReplyTo('respuesta@example.com');
checkResend($transport->send($message) === 1, 'No se conto el destinatario.');
$payload = json_decode((string) $history[0]['request']->getBody(), true);
checkResend($payload['text'] === 'Prueba de correo' && $payload['html'] === '<p>Prueba de correo</p>', 'No se preservo texto y HTML.');
checkResend($payload['to'] === ['cuenta@example.com'] && $payload['reply_to'] === ['respuesta@example.com'], 'Destinatario o reply_to incorrecto.');
checkResend($history[0]['request']->getHeaderLine('Authorization') === 'Bearer re_test_secret', 'Falta autenticacion.');
checkResend($history[0]['request']->getUri()->__toString() === 'https://api.resend.com/emails', 'Destino de API incorrecto.');
checkResend($history[0]['options']['allow_redirects'] === false, 'No seguir redirecciones con claves.');
checkResend($history[0]['options']['timeout'] === 8, 'Falta limite de espera inferior al del cliente mobile.');
checkResend($message->getHeaders()->get('X-Resend-Id')->getFieldBody() === $id, 'Falta identificador de seguimiento.');
$transport->send($message);
checkResend($history[0]['request']->getHeaderLine('Idempotency-Key') === $history[1]['request']->getHeaderLine('Idempotency-Key'), 'Un reintento del mismo mensaje cambia la idempotencia.');

foreach (['to', 'cc', 'bcc'] as $field) {
    $method = 'set'.ucfirst($field);
    $blocked = resendMessage()->$method('otro@example.com');
    expectResendFailure(function () use ($transport, $blocked) { $transport->send($blocked); }, 'destinatario no esta permitido');
}
expectResendFailure(function () use ($client) { (new ResendTransport($client, ''))->send(resendMessage()); }, 'RESEND_API_KEY');
expectResendFailure(function () use ($client) { (new ResendTransport($client, 're_test_secret'))->send(resendMessage()); }, 'RESEND_TEST_RECIPIENT');
expectResendFailure(function () use ($transport) { $transport->send(resendMessage()->setTo([])); }, 'destinatario');
expectResendFailure(function () use ($transport) { $transport->send(resendMessage()->attach(new Swift_Attachment('privado', 'archivo.txt'))); }, 'adjuntos');
checkResend(count($history) === 2, 'Los rechazos locales no deben llamar a Resend.');
foreach (['HTTP 403', 'HTTP 429', 'HTTP 200', 'HTTP 200', 'No se pudo contactar'] as $error) {
    expectResendFailure(function () use ($transport) { $transport->send(resendMessage()); }, $error);
}
checkResend(count($history) === 7, 'No reintentar automaticamente envios inciertos.');

config([
    'mail.mailers.resend.key' => 're_test_secret',
    'mail.mailers.resend.test_recipient' => 'cuenta@example.com',
    'mail.from.address' => 'onboarding@resend.dev',
]);
checkResend(app('mail.manager')->mailer('resend')->getSwiftMailer()->getTransport() instanceof ResendTransport, 'El mailer no esta registrado en Laravel.');
checkResend($kernel->call('mail:test-resend') === 0, 'Falla la validacion sin envio.');
config(['mail.mailers.resend.key' => null]);
checkResend($kernel->call('mail:test-resend') === 1, 'No debe aceptar una clave ausente.');
echo "OK: Resend HTTPS, texto/HTML, sandbox To/Cc/Bcc, credenciales privadas, errores, idempotencia y comando sin envio.\n";
