import React, { useState } from 'react';
import { FlatList, Pressable, StyleSheet, Switch, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { AppButton } from '@/components/AppButton';
import { AppHeader } from '@/components/AppHeader';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { NotificationCard } from '@/components/NotificationCard';
import { UnreadBadge } from '@/components/UnreadBadge';
import { useNotificationPreferences } from '@/hooks/useNotificationPreferences';
import { useNotifications } from '@/hooks/useNotifications';
import { friendlyMessage } from '@/utils/errorParser';
import { goBackOrHome } from '@/utils/navigation';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

export function NotificationsScreen() {
  const [showPrefs, setShowPrefs] = useState(false);
  const notifications = useNotifications();
  const prefs = useNotificationPreferences();

  return (
    <View style={styles.fill}>
      <AppHeader
        title="Notificaciones"
        showBack
        onBack={goBackOrHome}
        rightAction={<UnreadBadge count={notifications.unreadCount} />}
      />
      <View style={styles.actions}>
        <AppButton title="Marcar todas" onPress={notifications.markAllAsRead} disabled={notifications.unreadCount === 0} />
        <Pressable style={styles.prefBtn} onPress={() => setShowPrefs((value) => !value)}>
          <MaterialCommunityIcons name="cog-outline" size={18} color={COLORS.primary} />
          <Text style={styles.prefText}>Preferencias</Text>
        </Pressable>
      </View>
      {notifications.actionError ? <Text style={styles.error}>{notifications.actionError}</Text> : null}
      {showPrefs ? (
        <View style={styles.prefs}>
          {prefs.data.map((item) => (
            <View key={item.notification_type} style={styles.prefRow}>
              <Text style={styles.prefName}>{item.notification_type}</Text>
              <Switch
                value={item.app_enabled}
                onValueChange={(value) => {
                  const next = prefs.data.map((pref) => pref.notification_type === item.notification_type ? { ...pref, app_enabled: value } : pref);
                  prefs.save(next);
                }}
              />
            </View>
          ))}
          {prefs.saveError ? <Text style={styles.error}>{prefs.saveError}</Text> : null}
        </View>
      ) : null}
      {notifications.loading && notifications.data.length === 0 ? <LoadingScreen message="Cargando notificaciones..." /> : null}
      {notifications.error && notifications.data.length === 0 ? <ErrorState message={friendlyMessage(notifications.error)} traceId={notifications.error.traceId} onRetry={notifications.refresh} type="server" /> : null}
      {!notifications.loading && !notifications.error ? (
        <FlatList
          data={notifications.data}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => <NotificationCard item={item} onRead={() => !item.read_at && notifications.markAsRead(item.id)} />}
          ListEmptyComponent={<EmptyState icon="bell-off-outline" message="No hay notificaciones." />}
          contentContainerStyle={styles.list}
        />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  actions: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', padding: SPACING.md, gap: SPACING.md },
  prefBtn: { flexDirection: 'row', alignItems: 'center', gap: SPACING.xs, padding: SPACING.sm },
  prefText: { color: COLORS.primary, fontWeight: '800', fontSize: FONT.captionSize },
  prefs: { marginHorizontal: SPACING.md, padding: SPACING.md, backgroundColor: COLORS.surface, borderRadius: RADIUS.md, gap: SPACING.sm },
  prefRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  prefName: { color: COLORS.textPrimary, fontWeight: '700' },
  list: { padding: SPACING.md, gap: SPACING.md },
  error: { marginHorizontal: SPACING.md, color: COLORS.error, fontSize: FONT.captionSize, fontWeight: '700' },
});
