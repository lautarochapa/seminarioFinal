import React, { useMemo, useState } from 'react';
import { FlatList, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { router } from 'expo-router';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { AppButton } from '@/components/AppButton';
import { AppHeader } from '@/components/AppHeader';
import { BranchCard } from '@/components/BranchCard';
import { BranchMap } from '@/components/BranchMap';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { useBranches } from '@/hooks/useBranches';
import { useNearbyBranches } from '@/hooks/useNearbyBranches';
import { friendlyMessage } from '@/utils/errorParser';
import { goBackOrHome } from '@/utils/navigation';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';
import type { BranchFilters, SupermarketBranch } from '@/types/retail';

export function BranchesScreen() {
  const [search, setSearch] = useState('');
  const [service, setService] = useState<'delivery' | 'pickup' | undefined>();
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const filters = useMemo<BranchFilters>(() => ({ search: search || undefined, service }), [search, service]);
  const { data, loading, error, refresh } = useBranches(filters);
  const nearby = useNearbyBranches();
  const source = nearby.data.length > 0 ? nearby.data : data;
  const visible = useMemo(() => source.filter((branch) => {
    if (service === 'delivery' && !branch.delivery_available) return false;
    if (service === 'pickup' && !branch.pickup_available) return false;
    return true;
  }), [source, service]);

  const openBranch = (branch: SupermarketBranch) => {
    router.push({ pathname: '/(app)/branches/[id]' as never, params: { id: String(branch.id) } });
  };

  return (
    <View style={styles.fill}>
      <AppHeader title="Sucursales" showBack onBack={goBackOrHome} />
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.filters}>
        <View style={styles.search}>
          <MaterialCommunityIcons name="magnify" size={18} color={COLORS.textHint} />
          <TextInput style={styles.input} value={search} onChangeText={setSearch} placeholder="Buscar sucursal" placeholderTextColor={COLORS.textHint} />
        </View>
        <Pressable style={[styles.chip, service === undefined && styles.selected]} onPress={() => setService(undefined)}><Text style={styles.chipText}>Todas</Text></Pressable>
        <Pressable style={[styles.chip, service === 'delivery' && styles.selected]} onPress={() => setService('delivery')}><Text style={styles.chipText}>Delivery</Text></Pressable>
        <Pressable style={[styles.chip, service === 'pickup' && styles.selected]} onPress={() => setService('pickup')}><Text style={styles.chipText}>Pickup</Text></Pressable>
      </ScrollView>
      <View style={styles.locationBox}>
        <Text style={styles.locationText}>La ubicacion se solicita solo al tocar el boton.</Text>
        <AppButton title="Usar mi ubicacion" onPress={() => nearby.requestNearby(5)} loading={nearby.loading} />
      </View>
      {nearby.error ? <ErrorState message={nearby.error.message} traceId={nearby.error.traceId} onRetry={() => nearby.requestNearby(5)} type="network" /> : null}
      {visible.length > 0 ? (
        <View style={styles.mapWrap}>
          <BranchMap branches={visible} selectedId={selectedId} onSelect={(branch) => setSelectedId(branch.id)} />
        </View>
      ) : null}
      {loading && data.length === 0 ? <LoadingScreen message="Cargando sucursales..." /> : null}
      {error && data.length === 0 ? <ErrorState message={friendlyMessage(error)} traceId={error.traceId} onRetry={refresh} type="server" /> : null}
      {!loading && !error ? (
        <FlatList
          data={visible}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => <BranchCard item={item} onPress={() => openBranch(item)} />}
          ListEmptyComponent={<EmptyState icon="map-marker-off-outline" message="No hay sucursales para los filtros elegidos." />}
          contentContainerStyle={styles.list}
        />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  filters: { padding: SPACING.md, gap: SPACING.sm, alignItems: 'center' },
  search: { flexDirection: 'row', alignItems: 'center', gap: SPACING.xs, minWidth: 190, backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border, borderRadius: RADIUS.md, paddingHorizontal: SPACING.sm },
  input: { flex: 1, minHeight: 42, color: COLORS.textPrimary },
  chip: { backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border, borderRadius: RADIUS.full, paddingHorizontal: SPACING.md, paddingVertical: SPACING.sm },
  selected: { backgroundColor: COLORS.primarySurface, borderColor: COLORS.primary },
  chipText: { color: COLORS.textPrimary, fontSize: FONT.captionSize, fontWeight: '700' },
  locationBox: { paddingHorizontal: SPACING.md, paddingBottom: SPACING.md, gap: SPACING.sm },
  locationText: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
  mapWrap: { paddingHorizontal: SPACING.md, paddingBottom: SPACING.md },
  list: { padding: SPACING.md, gap: SPACING.md },
});
