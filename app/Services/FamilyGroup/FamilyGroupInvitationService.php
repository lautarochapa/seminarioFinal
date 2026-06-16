<?php

namespace App\Services\FamilyGroup;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\FamilyGroupInvitation;
use App\Repositories\FamilyGroup\FamilyGroupInvitationRepository;
use App\Repositories\FamilyGroup\FamilyGroupMemberRepository;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FamilyGroupInvitationService
{
    private $groupRepo;
    private $memberRepo;
    private $invitationRepo;

    public function __construct(
        FamilyGroupRepository $groupRepo,
        FamilyGroupMemberRepository $memberRepo,
        FamilyGroupInvitationRepository $invitationRepo
    ) {
        $this->groupRepo      = $groupRepo;
        $this->memberRepo     = $memberRepo;
        $this->invitationRepo = $invitationRepo;
    }

    public function create(int $groupId, int $actorId, array $data, string $ip, string $ua): FamilyGroupInvitation
    {
        $this->groupRepo->findOrFailForUser($groupId, $actorId);
        $this->requireAdminOrOwner($groupId, $actorId);

        $email = strtolower(trim($data['email']));
        $role  = $data['role'] ?? 'member';

        // Check if user with this email is already a member
        $existingUser = User::where('email', $email)->first();
        if ($existingUser && $this->memberRepo->findMembership($groupId, $existingUser->id)) {
            throw new FamilyGroupException('USER_ALREADY_FAMILY_MEMBER', 'El usuario ya es miembro de este grupo.', 409);
        }

        // Check for duplicate pending invitation
        if ($this->invitationRepo->findPendingForGroup($groupId, $email)) {
            throw new FamilyGroupException('FAMILY_INVITATION_ALREADY_EXISTS', 'Ya existe una invitación pendiente para ese email.', 409);
        }

        return DB::transaction(function () use ($groupId, $actorId, $email, $role, $existingUser, $ip, $ua) {
            $invitation = $this->invitationRepo->create([
                'family_group_id' => $groupId,
                'invited_email'   => $email,
                'invited_user_id' => $existingUser ? $existingUser->id : null,
                'invited_by'      => $actorId,
                'token'           => Str::random(64),
                'status'          => 'pending',
                'expires_at'      => now()->addDays(7),
            ]);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'family_group.invitation.create',
                'entity_name' => 'family_group_invitations',
                'entity_id'   => (string) $invitation->id,
                'old_values'  => null,
                'new_values'  => ['invited_email' => $email, 'role' => $role],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $invitation;
        });
    }

    public function accept(int $invitationId, int $userId, string $ip, string $ua): FamilyGroupInvitation
    {
        return DB::transaction(function () use ($invitationId, $userId, $ip, $ua) {
            $invitation = $this->invitationRepo->findByIdForUpdate($invitationId);

            if (!$invitation) {
                throw new FamilyGroupException('FAMILY_INVITATION_NOT_FOUND', 'Invitación no encontrada.', 404);
            }

            if ($invitation->status === 'accepted') {
                throw new FamilyGroupException('FAMILY_INVITATION_ALREADY_ACCEPTED', 'La invitación ya fue aceptada.', 409);
            }

            if (in_array($invitation->status, ['cancelled', 'rejected'])) {
                throw new FamilyGroupException('FAMILY_INVITATION_CANCELLED', 'La invitación fue cancelada.', 409);
            }

            if ($invitation->status !== 'pending') {
                throw new FamilyGroupException('FAMILY_INVITATION_CANCELLED', 'La invitación no está disponible.', 409);
            }

            if ($invitation->expires_at && $invitation->expires_at->isPast()) {
                throw new FamilyGroupException('FAMILY_INVITATION_EXPIRED', 'La invitación ha vencido.', 409);
            }

            $user = User::find($userId);
            $emailMatch = strtolower($invitation->invited_email) === strtolower($user->email);
            $idMatch    = $invitation->invited_user_id !== null && $invitation->invited_user_id === $userId;

            if (!$emailMatch && !$idMatch) {
                throw new FamilyGroupException('FAMILY_INVITATION_RECIPIENT_MISMATCH', 'No sos el destinatario de esta invitación.', 403);
            }

            if ($this->groupRepo->userHasActiveGroup($userId)) {
                throw new FamilyGroupException('USER_BELONGS_TO_ANOTHER_FAMILY_GROUP', 'Ya pertenecés a otro grupo familiar activo.', 409);
            }

            $accepted = $this->invitationRepo->accept($invitation);

            $this->memberRepo->create([
                'family_group_id' => $invitation->family_group_id,
                'user_id'         => $userId,
                'role_in_group'   => 'member',
                'status'          => 'active',
                'joined_at'       => now(),
            ]);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'family_group.invitation.accept',
                'entity_name' => 'family_group_invitations',
                'entity_id'   => (string) $invitation->id,
                'old_values'  => ['status' => 'pending'],
                'new_values'  => ['status' => 'accepted'],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $accepted;
        });
    }

    private function requireAdminOrOwner(int $groupId, int $userId): void
    {
        $membership = $this->memberRepo->findMembership($groupId, $userId);
        if (!$membership || !in_array($membership->role_in_group, ['owner', 'admin'])) {
            throw new FamilyGroupException('FAMILY_GROUP_ACCESS_DENIED', 'Se requiere rol de propietario o administrador.', 403);
        }
    }
}
