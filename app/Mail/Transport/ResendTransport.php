<?php

namespace App\Mail\Transport;

use GuzzleHttp\ClientInterface;
use Illuminate\Mail\Transport\Transport;
use Swift_Mime_SimpleMessage;
use Swift_TransportException;

class ResendTransport extends Transport
{
    private $client;
    private $key;
    private $testRecipient;

    public function __construct(ClientInterface $client, string $key, ?string $testRecipient = null)
    {
        $this->client = $client;
        $this->key = trim($key);
        $this->testRecipient = strtolower(trim((string) $testRecipient));
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        if ($this->key === '') {
            throw new Swift_TransportException('Falta configurar RESEND_API_KEY en el servidor.');
        }

        $this->beforeSendPerformed($message);
        $recipients = array_merge((array) $message->getTo(), (array) $message->getCc(), (array) $message->getBcc());
        if (! $recipients || ! $message->getFrom()) {
            throw new Swift_TransportException('El correo necesita remitente y destinatario.');
        }

        // Reject other recipients; never redirect password links to a test mailbox.
        if ($this->testRecipient !== '') {
            foreach (array_keys($recipients) as $email) {
                if (strtolower($email) !== $this->testRecipient) {
                    throw new Swift_TransportException('Resend esta en modo de prueba: el destinatario no esta permitido.');
                }
            }
        }

        $from = $message->getFrom();
        $fromAddress = key($from);
        if (substr(strtolower($fromAddress), -11) === '@resend.dev' && $this->testRecipient === '') {
            throw new Swift_TransportException('Configura RESEND_TEST_RECIPIENT para usar el remitente de prueba.');
        }

        $payload = [
            'from' => $message->getHeaders()->get('From')->getFieldBody(),
            'subject' => $message->getSubject(),
        ];
        foreach (['to' => $message->getTo(), 'cc' => $message->getCc(), 'bcc' => $message->getBcc(), 'reply_to' => $message->getReplyTo()] as $field => $addresses) {
            if ($addresses) {
                $payload[$field] = array_keys($addresses);
            }
        }
        $this->addBody($message, $payload);
        if (! isset($payload['text']) && ! isset($payload['html'])) {
            throw new Swift_TransportException('El correo necesita contenido de texto o HTML.');
        }

        try {
            $response = $this->client->request('POST', 'https://api.resend.com/emails', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->key,
                    'Accept' => 'application/json',
                    'Idempotency-Key' => 'ccc-'.hash('sha256', $message->getId()),
                ],
                'json' => $payload,
                'connect_timeout' => 5,
                'timeout' => 8,
                'http_errors' => false,
                'allow_redirects' => false,
            ]);
        } catch (\Throwable $exception) {
            // Do not expose provider requests containing API keys or reset tokens.
            throw new Swift_TransportException('No se pudo contactar al servicio de correo.');
        }

        $data = json_decode((string) $response->getBody(), true);
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300 || ! is_array($data)
            || ! isset($data['id']) || ! is_string($data['id'])
            || ! preg_match('/^[a-f0-9-]{36}$/i', $data['id'])) {
            throw new Swift_TransportException('El servicio de correo no confirmo el envio (HTTP '.$status.').');
        }

        $message->getHeaders()->addTextHeader('X-Resend-Id', $data['id']);
        $this->sendPerformed($message);

        return count($recipients);
    }

    private function addBody($part, array &$payload): void
    {
        if ($part instanceof \Swift_Attachment) {
            throw new Swift_TransportException('Los adjuntos no estan habilitados en este transporte de correo.');
        }
        $type = $part->getBodyContentType();
        if (in_array($type, ['text/plain', 'text/html'], true) && $part->getBody() !== null) {
            $payload[$type === 'text/html' ? 'html' : 'text'] = (string) $part->getBody();
        }
        foreach ($part->getChildren() as $child) {
            $this->addBody($child, $payload);
        }
    }
}
