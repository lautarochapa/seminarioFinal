import React, { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { healthPreferencesApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { AppHeader } from '@/components/AppHeader';
import { ErrorState } from '@/components/ErrorState';
import { goBackOrHome } from '@/utils/navigation';
import { offlineCache } from '@/storage/offlineCache';
import { friendlyMessage } from '@/utils/errorParser';
import type { HealthPreferenceCatalogItem, HealthPreferenceType, UserHealthPreference } from '@/types/profile';
import { COLORS, FONT_SIZE, RADIUS, SPACING } from '@/utils/theme';

export function HealthPreferencesScreen({ title, sections }: { title: string; sections: { type: HealthPreferenceType; label: string; help: string }[] }) {
  const [catalogs, setCatalogs] = useState<Record<string, HealthPreferenceCatalogItem[]>>({});
  const [selected, setSelected] = useState<Record<string, UserHealthPreference[]>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState<string | null>(null);
  const [error, setError] = useState<ApiError | null>(null);

  const load = useCallback(async () => {
    setLoading(true); setError(null);
    try {
      const pairs = await Promise.all(sections.map(async ({ type }) => ({ type, catalog: (await healthPreferencesApi.catalog(type)).data, selected: (await healthPreferencesApi.list(type)).data })));
      setCatalogs(Object.fromEntries(pairs.map((p) => [p.type, p.catalog])));
      setSelected(Object.fromEntries(pairs.map((p) => [p.type, p.selected])));
    } catch (err) { if (err instanceof ApiError) setError(err); }
    finally { setLoading(false); }
  }, [sections]);

  useEffect(() => { const timer = setTimeout(() => { void load(); }, 0); return () => clearTimeout(timer); }, [load]);

  async function toggle(type: HealthPreferenceType, item: HealthPreferenceCatalogItem) {
    const current = selected[type] ?? [];
    const relation = current.find((value) => value.item.id === item.id);
    const key = `${type}:${item.id}`; setSaving(key);
    try {
      if (relation) await healthPreferencesApi.remove(type, relation.id);
      else await healthPreferencesApi.add(type, item.id);
      await offlineCache.clearAll();
      await load();
    } catch (err) { if (err instanceof ApiError) setError(err); }
    finally { setSaving(null); }
  }

  return <View style={styles.fill}>
    <AppHeader title={title} showBack onBack={goBackOrHome} />
    {loading && Object.keys(catalogs).length === 0 ? <ActivityIndicator style={styles.loader} color={COLORS.primary} /> : null}
    {error && Object.keys(catalogs).length === 0 ? <ErrorState message={friendlyMessage(error.normalized)} traceId={error.normalized.traceId} onRetry={load} type="server" /> : null}
    <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      {sections.map((section) => <View key={section.type} style={styles.section}>
        <Text style={styles.title}>{section.label}</Text><Text style={styles.help}>{section.help}</Text>
        <View style={styles.chips}>{(catalogs[section.type] ?? []).map((item) => {
          const active = (selected[section.type] ?? []).some((value) => value.item.id === item.id);
          return <Pressable key={item.id} onPress={() => void toggle(section.type, item)} disabled={saving !== null} accessibilityRole="checkbox" accessibilityState={{ checked: active, disabled: saving !== null }} style={({ pressed }) => [styles.chip, active && styles.chipActive, pressed && styles.pressed]}>
            {saving === `${section.type}:${item.id}` ? <ActivityIndicator size="small" color={COLORS.primary} /> : <MaterialCommunityIcons name={active ? 'check-circle' : 'plus-circle-outline'} size={18} color={active ? COLORS.primary : COLORS.textSecondary} />}
            <Text style={[styles.chipText, active && styles.chipTextActive]}>{item.name}</Text>
          </Pressable>;
        })}</View>
      </View>)}
      <Text style={styles.note}>Los cambios se guardan al seleccionar. Se limpia la caché de recomendaciones para la próxima consulta.</Text>
    </ScrollView>
  </View>;
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background }, loader: { margin: SPACING.xl }, content: { padding: SPACING.md, paddingBottom: SPACING.xxl, gap: SPACING.md },
  section: { backgroundColor: COLORS.surface, borderRadius: RADIUS.md, borderWidth: 1, borderColor: COLORS.border, padding: SPACING.md }, title: { color: COLORS.textPrimary, fontSize: FONT_SIZE.lg, fontWeight: '800' },
  help: { color: COLORS.textSecondary, fontSize: FONT_SIZE.sm, marginVertical: SPACING.sm }, chips: { flexDirection: 'row', flexWrap: 'wrap', gap: SPACING.sm },
  chip: { minHeight: 44, flexDirection: 'row', alignItems: 'center', gap: SPACING.xs, borderRadius: RADIUS.full, borderWidth: 1, borderColor: COLORS.border, paddingHorizontal: SPACING.md }, chipActive: { borderColor: COLORS.primary, backgroundColor: COLORS.primarySurface },
  chipText: { color: COLORS.textPrimary, fontSize: FONT_SIZE.sm }, chipTextActive: { color: COLORS.primary, fontWeight: '700' }, pressed: { opacity: 0.7 }, note: { color: COLORS.textSecondary, fontSize: FONT_SIZE.xs },
});
