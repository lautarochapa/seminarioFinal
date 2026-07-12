import React from 'react';
import { Alert, Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import type { AuthUser } from '@/types/auth';
import type { FamilyGroup } from '@/types/familyGroup';
import { COLORS, FONT_SIZE, RADIUS, SHADOW, SPACING } from '@/utils/theme';

interface Props {
  visible: boolean;
  user: AuthUser | null;
  group: FamilyGroup | null;
  onClose: () => void;
  onNavigate: (route: string) => void;
  onLogout: () => void;
}

const ITEMS = [
  ['account-outline', 'Mi perfil', '/(app)/profile'],
  ['target', 'Mis objetivos', '/(app)/goals'],
  ['account-group-outline', 'Grupo familiar', '/(app)/groups'],
  ['food-apple-outline', 'Preferencias alimentarias', '/(app)/dietary-preferences'],
  ['allergy', 'Restricciones y alergias', '/(app)/restrictions'],
  ['calendar-month-outline', 'Mis planes', '/(app)/meal-plans'],
  ['clipboard-list-outline', 'Mis listas', '/(app)/shopping-lists'],
  ['cog-outline', 'Configuración', '/(app)/settings'],
  ['help-circle-outline', 'Ayuda', 'soon:help'],
] as const;

export function UserAccountMenu({ visible, user, group, onClose, onNavigate, onLogout }: Props) {
  const insets = useSafeAreaInsets();
  const name = user ? `${user.name} ${user.lastname}`.trim() || user.email : '';
  const initials = user ? `${user.name?.[0] ?? '?'}${user.lastname?.[0] ?? ''}`.toUpperCase() : '?';
  const role = group && user ? (group.owner_user_id === user.id ? 'Responsable del grupo' : 'Integrante') : null;

  function activate(target: string) {
    if (target.startsWith('soon:')) {
      Alert.alert('Próximamente', target === 'soon:help'
        ? 'La ayuda guiada estará disponible en una próxima versión.'
        : 'El backend actual todavía no ofrece gestión de restricciones y alergias.');
      return;
    }
    onClose();
    onNavigate(target);
  }

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.overlay}>
        <Pressable style={StyleSheet.absoluteFill} onPress={onClose} accessibilityLabel="Cerrar menú de usuario" />
        <View style={[styles.sheet, { paddingBottom: Math.max(insets.bottom, SPACING.md) }]} accessibilityViewIsModal>
          <View style={styles.handle} />
          <View style={styles.identity}>
            <View style={styles.avatar}><Text style={styles.avatarText}>{initials}</Text></View>
            <View style={styles.identityText}>
              <Text style={styles.name}>{name}</Text>
              <Text style={styles.email}>{user?.email}</Text>
              <Text style={styles.group}>{group?.name ?? 'Sin grupo familiar activo'}{role ? ` · ${role}` : ''}</Text>
            </View>
          </View>
          <ScrollView showsVerticalScrollIndicator={false}>
            {ITEMS.map(([icon, label, target]) => (
              <Pressable key={label} style={({ pressed }) => [styles.item, pressed && styles.pressed]} onPress={() => activate(target)} accessibilityRole="button" accessibilityLabel={label}>
                <MaterialCommunityIcons name={icon} size={23} color={COLORS.textSecondary} />
                <Text style={styles.itemText}>{label}</Text>
                {target.startsWith('soon:') ? <Text style={styles.soon}>Próximamente</Text> : <MaterialCommunityIcons name="chevron-right" size={22} color={COLORS.textHint} />}
              </Pressable>
            ))}
            <Pressable style={({ pressed }) => [styles.item, styles.logout, pressed && styles.pressed]} onPress={() => { onClose(); onLogout(); }} accessibilityRole="button" accessibilityLabel="Cerrar sesión">
              <MaterialCommunityIcons name="logout" size={23} color={COLORS.error} />
              <Text style={[styles.itemText, styles.logoutText]}>Cerrar sesión</Text>
            </Pressable>
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.48)', justifyContent: 'flex-end' },
  sheet: { maxHeight: '88%', backgroundColor: COLORS.surface, borderTopLeftRadius: RADIUS.xl, borderTopRightRadius: RADIUS.xl, paddingHorizontal: SPACING.md, ...SHADOW.lg },
  handle: { width: 42, height: 4, borderRadius: RADIUS.full, backgroundColor: COLORS.border, alignSelf: 'center', marginVertical: SPACING.sm },
  identity: { flexDirection: 'row', alignItems: 'center', gap: SPACING.md, paddingVertical: SPACING.sm, borderBottomWidth: 1, borderBottomColor: COLORS.border },
  avatar: { width: 56, height: 56, borderRadius: RADIUS.full, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  avatarText: { color: '#fff', fontSize: FONT_SIZE.lg, fontWeight: '800' },
  identityText: { flex: 1 }, name: { color: COLORS.textPrimary, fontSize: FONT_SIZE.lg, fontWeight: '800' },
  email: { color: COLORS.textSecondary, fontSize: FONT_SIZE.sm }, group: { color: COLORS.primary, fontSize: FONT_SIZE.xs, marginTop: 2 },
  item: { minHeight: 52, flexDirection: 'row', alignItems: 'center', gap: SPACING.md, paddingHorizontal: SPACING.sm, borderBottomWidth: 1, borderBottomColor: COLORS.border },
  pressed: { backgroundColor: COLORS.surfaceElevated }, itemText: { flex: 1, color: COLORS.textPrimary, fontSize: FONT_SIZE.sm, fontWeight: '600' },
  soon: { color: COLORS.textHint, fontSize: FONT_SIZE.xs }, logout: { marginTop: SPACING.sm, borderBottomWidth: 0 }, logoutText: { color: COLORS.error },
});
