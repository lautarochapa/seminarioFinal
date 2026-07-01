import React, { useState } from 'react';
import {
  Alert,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useFamilyGroupDetail } from '@/hooks/useFamilyGroupDetail';
import { useAuth } from '@/auth/AuthContext';
import { familyGroupsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { LoadingScreen } from '@/components/LoadingScreen';
import { ErrorState } from '@/components/ErrorState';
import { SectionHeader } from '@/components/SectionHeader';
import { AppButton } from '@/components/AppButton';
import { AppInput } from '@/components/AppInput';
import { FormError } from '@/components/FormError';
import { ListItem } from '@/components/ListItem';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT_SIZE, RADIUS, SPACING } from '@/utils/theme';

interface GroupDetailScreenProps {
  groupId: number;
}

const STATUS_LABELS: Record<string, string> = {
  active: 'Activo',
  inactive: 'Inactivo',
};

const ROLE_LABELS: Record<string, string> = {
  owner: 'Propietario',
  admin: 'Administrador',
  member: 'Miembro',
};

export function GroupDetailScreen({ groupId }: GroupDetailScreenProps) {
  const { user } = useAuth();
  const { group, members, loading, error, refresh } = useFamilyGroupDetail(groupId);

  const [inviteEmail, setInviteEmail] = useState('');
  const [inviting, setInviting] = useState(false);
  const [inviteError, setInviteError] = useState<string | null>(null);
  const [inviteSuccess, setInviteSuccess] = useState(false);

  const isOwner = user && group && group.owner_user_id === user.id;

  if (loading && !group) return <LoadingScreen message="Cargando grupo..." />;
  if (error && !group) {
    return (
      <View style={styles.fill}>
        <ErrorState
          message={friendlyMessage(error)}
          traceId={error.traceId}
          onRetry={refresh}
        />
      </View>
    );
  }
  if (!group) return null;

  async function handleInvite() {
    if (!inviteEmail.trim()) {
      setInviteError('Ingresá un email.');
      return;
    }
    setInviting(true);
    setInviteError(null);
    setInviteSuccess(false);
    try {
      await familyGroupsApi.invite(groupId, { email: inviteEmail.trim() });
      setInviteEmail('');
      setInviteSuccess(true);
      setTimeout(() => setInviteSuccess(false), 4000);
    } catch (err) {
      if (err instanceof ApiError) {
        switch (err.normalized.status) {
          case 409: setInviteError('El usuario ya es miembro o tiene una invitación pendiente.'); break;
          case 422: setInviteError(err.normalized.fieldErrors?.email?.[0] ?? err.normalized.message); break;
          case 404: setInviteError('No se encontró un usuario con ese email.'); break;
          default: setInviteError(friendlyMessage(err.normalized));
        }
      } else {
        setInviteError('Error al enviar la invitación.');
      }
    }
    setInviting(false);
  }

  return (
    <ScrollView
      style={styles.scroll}
      contentContainerStyle={styles.content}
      refreshControl={
        <RefreshControl refreshing={loading} onRefresh={refresh} tintColor={COLORS.primary} />
      }
      showsVerticalScrollIndicator={false}
    >
      <View style={styles.header}>
        <Text style={styles.groupName}>{group.name}</Text>
        <Text style={styles.status}>{STATUS_LABELS[group.status] ?? group.status}</Text>
      </View>

      {group.default_address ? (
        <View style={styles.card}>
          <Text style={styles.cardLabel}>Dirección</Text>
          <Text style={styles.cardValue}>{group.default_address}</Text>
        </View>
      ) : null}

      <SectionHeader title={`Miembros (${members.length})`} />
      <View style={styles.membersCard}>
        {members.map((m) => (
          <View key={m.id} style={styles.memberRow}>
            <View style={styles.memberAvatar}>
              <Text style={styles.memberInitial}>{m.name.charAt(0).toUpperCase()}</Text>
            </View>
            <View style={styles.memberInfo}>
              <Text style={styles.memberName}>{m.name}</Text>
              <Text style={styles.memberEmail}>{m.email}</Text>
            </View>
            <Text style={styles.memberRole}>{ROLE_LABELS[m.role] ?? m.role}</Text>
          </View>
        ))}
      </View>

      {isOwner ? (
        <>
          <SectionHeader title="Invitar miembro" />
          <View style={styles.inviteCard}>
            {inviteSuccess ? (
              <Text style={styles.successText}>✓ Invitación enviada.</Text>
            ) : null}
            <FormError message={inviteError} />
            <AppInput
              label="Email del invitado"
              value={inviteEmail}
              onChangeText={(t) => { setInviteEmail(t); setInviteError(null); }}
              keyboardType="email-address"
              autoCapitalize="none"
              autoCorrect={false}
              placeholder="usuario@ejemplo.com"
              editable={!inviting}
              returnKeyType="send"
              onSubmitEditing={handleInvite}
            />
            <AppButton
              title="Enviar invitación"
              onPress={handleInvite}
              loading={inviting}
            />
            <Text style={styles.inviteNote}>
              En desarrollo el email puede estar en el log del servidor.
            </Text>
          </View>
        </>
      ) : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1 },
  scroll: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  content: {
    padding: SPACING.md,
    paddingBottom: SPACING.xxl,
  },
  header: {
    alignItems: 'center',
    paddingVertical: SPACING.lg,
    gap: SPACING.xs,
  },
  groupName: {
    fontSize: FONT_SIZE.xl,
    fontWeight: '700',
    color: COLORS.textPrimary,
    textAlign: 'center',
  },
  status: {
    fontSize: FONT_SIZE.sm,
    color: COLORS.primary,
    fontWeight: '600',
  },
  card: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    marginBottom: SPACING.md,
    gap: SPACING.xs,
  },
  cardLabel: {
    fontSize: FONT_SIZE.xs,
    color: COLORS.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 0.6,
  },
  cardValue: {
    fontSize: FONT_SIZE.md,
    color: COLORS.textPrimary,
  },
  membersCard: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    marginBottom: SPACING.md,
    overflow: 'hidden',
  },
  memberRow: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: SPACING.md,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
    gap: SPACING.sm,
  },
  memberAvatar: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: COLORS.primaryLight,
    alignItems: 'center',
    justifyContent: 'center',
  },
  memberInitial: {
    fontSize: FONT_SIZE.md,
    fontWeight: '700',
    color: '#fff',
  },
  memberInfo: {
    flex: 1,
  },
  memberName: {
    fontSize: FONT_SIZE.sm,
    fontWeight: '600',
    color: COLORS.textPrimary,
  },
  memberEmail: {
    fontSize: FONT_SIZE.xs,
    color: COLORS.textSecondary,
  },
  memberRole: {
    fontSize: FONT_SIZE.xs,
    color: COLORS.primary,
    fontWeight: '600',
  },
  inviteCard: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    gap: SPACING.sm,
  },
  inviteNote: {
    fontSize: FONT_SIZE.xs,
    color: COLORS.textHint,
    textAlign: 'center',
  },
  successText: {
    fontSize: FONT_SIZE.sm,
    color: COLORS.success,
    fontWeight: '500',
    textAlign: 'center',
    paddingVertical: SPACING.sm,
  },
});
