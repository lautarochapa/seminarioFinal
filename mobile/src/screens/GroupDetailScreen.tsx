import React, { useState } from 'react';
import {
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useFamilyGroupDetail } from '@/hooks/useFamilyGroupDetail';
import { useAuth } from '@/auth/AuthContext';
import { familyGroupsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { AppHeader } from '@/components/AppHeader';
import { LoadingScreen } from '@/components/LoadingScreen';
import { ErrorState } from '@/components/ErrorState';
import { SectionHeader } from '@/components/SectionHeader';
import { AppButton } from '@/components/AppButton';
import { AppInput } from '@/components/AppInput';
import { FormError } from '@/components/FormError';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, FONT_SIZE, RADIUS, SHADOW, SPACING } from '@/utils/theme';

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

const ROLE_COLORS: Record<string, string> = {
  owner: COLORS.primary,
  admin: COLORS.info,
  member: COLORS.textSecondary,
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
        <AppHeader title="Detalle del grupo" showBack onBack={goBackOrHome} />
        <ErrorState
          message={friendlyMessage(error)}
          traceId={error.traceId}
          onRetry={refresh}
          type="server"
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
    <View style={styles.fill}>
      <AppHeader title={group.name} subtitle="Detalle del grupo" showBack onBack={goBackOrHome} />
    <ScrollView
      style={styles.scroll}
      contentContainerStyle={styles.content}
      refreshControl={
        <RefreshControl refreshing={loading} onRefresh={refresh} tintColor={COLORS.primary} />
      }
      showsVerticalScrollIndicator={false}
    >
      {/* Group header */}
      <View style={styles.groupHeader}>
        <View style={styles.groupIconWrap}>
          <MaterialCommunityIcons name="account-group" size={32} color={COLORS.primary} />
        </View>
        <Text style={styles.groupName}>{group.name}</Text>
        <View style={styles.statusBadge}>
          <Text style={styles.statusText}>{STATUS_LABELS[group.status] ?? group.status}</Text>
        </View>
      </View>

      {group.default_address ? (
        <View style={styles.card}>
          <Text style={styles.cardLabel}>Dirección</Text>
          <Text style={styles.cardValue}>{group.default_address}</Text>
        </View>
      ) : null}

      <SectionHeader title={`Miembros (${members.length})`} />
      <View style={styles.membersCard}>
        {members.map((m, index) => (
          <View
            key={m.id}
            style={[
              styles.memberRow,
              index < members.length - 1 && styles.memberRowBorder,
            ]}
          >
            <View style={styles.memberAvatar}>
              <Text style={styles.memberInitial}>{m.name.charAt(0).toUpperCase()}</Text>
            </View>
            <View style={styles.memberInfo}>
              <Text style={styles.memberName}>{m.name}</Text>
              <Text style={styles.memberEmail} numberOfLines={1}>{m.email}</Text>
            </View>
            <View style={[styles.roleBadge, { borderColor: ROLE_COLORS[m.role] ?? COLORS.border }]}>
              <Text style={[styles.roleText, { color: ROLE_COLORS[m.role] ?? COLORS.textSecondary }]}>
                {ROLE_LABELS[m.role] ?? m.role}
              </Text>
            </View>
          </View>
        ))}
      </View>

      {isOwner ? (
        <>
          <SectionHeader title="Invitar miembro" />
          <View style={styles.inviteCard}>
            {inviteSuccess ? (
              <View style={styles.successRow}>
                <MaterialCommunityIcons name="check-circle-outline" size={18} color={COLORS.success} />
                <Text style={styles.successText}>Invitación enviada.</Text>
              </View>
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
              fullWidth
            />
            <Text style={styles.inviteNote}>
              En desarrollo el email puede estar en el log del servidor.
            </Text>
          </View>
        </>
      ) : null}
    </ScrollView>
    </View>
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
  groupHeader: {
    alignItems: 'center',
    paddingVertical: SPACING.lg,
    gap: SPACING.sm,
  },
  groupIconWrap: {
    width: 72,
    height: 72,
    borderRadius: RADIUS.xl,
    backgroundColor: COLORS.primarySurface,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: SPACING.xs,
  },
  groupName: {
    fontSize: FONT.titleSize,
    fontWeight: FONT.titleWeight,
    color: COLORS.textPrimary,
    textAlign: 'center',
  },
  statusBadge: {
    backgroundColor: COLORS.primarySurface,
    borderRadius: RADIUS.full,
    paddingHorizontal: SPACING.sm,
    paddingVertical: 3,
    borderWidth: 1,
    borderColor: COLORS.primaryLight,
  },
  statusText: {
    fontSize: FONT.captionSize,
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
    ...SHADOW.sm,
  },
  cardLabel: {
    fontSize: FONT_SIZE.xs,
    color: COLORS.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    fontWeight: '600',
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
    ...SHADOW.sm,
  },
  memberRow: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: SPACING.md,
    gap: SPACING.sm,
  },
  memberRowBorder: {
    borderBottomWidth: 1,
    borderBottomColor: COLORS.borderLight,
  },
  memberAvatar: {
    width: 40,
    height: 40,
    borderRadius: RADIUS.full,
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
    gap: 2,
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
  roleBadge: {
    borderWidth: 1,
    borderRadius: RADIUS.full,
    paddingHorizontal: SPACING.sm,
    paddingVertical: 2,
  },
  roleText: {
    fontSize: 11,
    fontWeight: '600',
  },
  inviteCard: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    gap: SPACING.sm,
    ...SHADOW.sm,
  },
  successRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: SPACING.xs,
    paddingVertical: SPACING.xs,
  },
  successText: {
    fontSize: FONT_SIZE.sm,
    color: COLORS.success,
    fontWeight: '500',
  },
  inviteNote: {
    fontSize: FONT_SIZE.xs,
    color: COLORS.textHint,
    textAlign: 'center',
  },
});
