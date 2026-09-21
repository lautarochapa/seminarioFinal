<?php

namespace App\Notifications;

use App\FamilyGroupInvitation;
use App\Services\TransactionalMailService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FamilyInvitationMail extends Notification
{
    private $invitation;

    public function __construct(FamilyGroupInvitation $invitation) { $this->invitation = $invitation; }
    public function via($notifiable) { return ['mail']; }

    public function toMail($notifiable)
    {
        return (new MailMessage)->subject("Invitaci\u{00f3}n a un grupo familiar - CocinaComidaControl")
            ->view(['mail.transactional', 'mail.transactional-text'], [
                'title' => 'Te invitaron a un grupo familiar',
                'paragraphs' => [
                    'El grupo '.$this->invitation->familyGroup->name.' te invita a compartir su cocina.',
                    "Ingres\u{00e1} o cre\u{00e1} una cuenta con el correo que recibi\u{00f3} esta invitaci\u{00f3}n. Luego confirm\u{00e1} que quer\u{00e9}s unirte.",
                    "La invitaci\u{00f3}n vence el ".$this->invitation->expires_at->format('d/m/Y').'.',
                    "N\u{00fa}mero de invitaci\u{00f3}n: ".$this->invitation->id.". Si no la esperabas, pod\u{00e9}s ignorarla.",
                ],
                'actionLabel' => "Ver invitaci\u{00f3}n",
                'actionUrl' => TransactionalMailService::url('/web/family-group', ['invitation' => $this->invitation->id]),
            ]);
    }
}
