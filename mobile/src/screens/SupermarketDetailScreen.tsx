import React from 'react';
import { FlatList, StyleSheet, Text, View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { AppHeader } from '@/components/AppHeader';
import { BranchCard } from '@/components/BranchCard';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { useBranches } from '@/hooks/useBranches';
import { useSupermarkets } from '@/hooks/useSupermarkets';
import { friendlyMessage } from '@/utils/errorParser';
import { goBackOrHome } from '@/utils/navigation';
import { COLORS, FONT, SPACING } from '@/utils/theme';

export function SupermarketDetailScreen() {
  const params = useLocalSearchParams<{ id?: string }>();
  const chainId = Number(params.id);
  const { data: chains } = useSupermarkets();
  const chain = chains.find((item) => item.id === chainId);
  const { data, loading, error, refresh } = useBranches({ chain_id: chainId });

  return (
    <View style={styles.fill}>
      <AppHeader title={chain?.name || 'Supermercado'} subtitle="Sucursales" showBack onBack={goBackOrHome} />
      <View style={styles.summary}>
        <Text style={styles.title}>{chain?.name || 'Cadena'}</Text>
        <Text style={styles.meta}>Estado: {chain?.status || 'Sin informar'}</Text>
        <Text style={styles.meta}>Logo y cantidad total de sucursales no estan expuestos por la API publica.</Text>
      </View>
      {loading ? <LoadingScreen message="Cargando sucursales..." /> : null}
      {error ? <ErrorState message={friendlyMessage(error)} traceId={error.traceId} onRetry={refresh} type="server" /> : null}
      {!loading && !error ? (
        <FlatList
          data={data}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => <BranchCard item={item} onPress={() => router.push({ pathname: '/(app)/branches/[id]' as never, params: { id: String(item.id) } })} />}
          ListEmptyComponent={<EmptyState icon="map-marker-off-outline" message="No hay sucursales para esta cadena." />}
          contentContainerStyle={styles.list}
        />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  summary: { padding: SPACING.md, backgroundColor: COLORS.surface, borderBottomWidth: 1, borderBottomColor: COLORS.border, gap: SPACING.xs },
  title: { fontSize: FONT.titleSize, fontWeight: FONT.titleWeight, color: COLORS.textPrimary },
  meta: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
  list: { padding: SPACING.md, gap: SPACING.md },
});
