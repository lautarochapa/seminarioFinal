<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestResendMail extends Command
{
    protected $signature = 'mail:test-resend {--send : Confirmar el envio de un unico correo de prueba}';

    protected $description = 'Comprueba la configuracion de Resend sin revelar la clave; envia solo con --send.';

    public function handle()
    {
        $recipient = config('mail.mailers.resend.test_recipient');
        if (! config('mail.mailers.resend.key') || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('Configura RESEND_API_KEY y RESEND_TEST_RECIPIENT en el entorno privado del servidor.');
            return 1;
        }
        if (! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) {
            $this->error('Configura un MAIL_FROM_ADDRESS valido.');
            return 1;
        }
        if (! $this->option('send')) {
            $this->info('Configuracion presente. No se envio ningun correo. Usa --send para confirmar la prueba.');
            return 0;
        }

        try {
            Mail::mailer('resend')->raw(
                "Hola. Este es un correo de prueba de CocinaComidaControl enviado mediante Resend.\n\nNo modifica tu cuenta ni requiere contrasenas o codigos.\n\nEquipo de CocinaComidaControl",
                function ($message) use ($recipient) {
                    $message->to($recipient)->subject('CocinaComidaControl - prueba de correo');
                }
            );
        } catch (\Swift_TransportException $exception) {
            $this->error($exception->getMessage());
            return 1;
        }

        $this->info('Resend acepto un correo de prueba. Comprueba su entrega en el panel y en la casilla destinataria.');
        return 0;
    }
}
