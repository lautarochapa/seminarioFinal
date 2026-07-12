import React from 'react';
import { FlatList, StyleSheet, View } from 'react-native';
import { useRouter } from 'expo-router';
import { AppHeader } from '@/components/AppHeader';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { FamilyGroupSelector } from '@/components/FamilyGroupSelector';
import { PurchaseSummaryCard } from '@/components/PurchaseSummaryCard';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { usePurchases } from '@/hooks/usePurchases';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, SPACING } from '@/utils/theme';
import type { Purchase } from '@/types/purchase';

export function PurchasesScreen() {
  const router = useRouter();
  const { selectedGroup } = useFamilyGroupContext();
  const groupId = selectedGroup?.id ?? null;
  const { data, loading, loadingMore, error, refresh, loadMore } = usePurchases(groupId);

  const handlePress = (p: Purchase) => {
    router.push({ pathname: '/(app)/purchases/[id]' as never, params: { id: String(p.id) } });
  };

  if (!selectedGroup) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Historial de compras" showBack onBack={goBackOrHome} />
        <View style={styles.centered}>
          <FamilyGroupSelector />
          <EmptyState icon="account-group-outline" message="Seleccioná un grupo familiar para ver el historial." />
        </View>
      </View>
    );
  }

  if (loading && data.length === 0) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Historial de compras" subtitle={selectedGroup.name} showBack onBack={goBackOrHome} />
        <LoadingScreen message="Cargando compras..." />
      </View>
    );
  }

  return (
    <View style={styles.fill}>
      <AppHeader title="Historial de compras" subtitle={selectedGroup.name} showBack onBack={goBackOrHome} />
      {error && data.length === 0 ? (
        <ErrorState
          message={friendlyMessage(error)}
          traceId={error.traceId ?? ''}
          onRetry={refresh}
          type={error.isNetworkError ? 'network' : 'server'}
        />
      ) : (
        <FlatList
          data={data}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => <PurchaseSummaryCard purchase={item} onPress={() => handlePress(item)} />}
          contentContainerStyle={styles.list}
          onRefresh={refresh}
          refreshing={loading && data.length > 0}
          onEndReached={loadMore}
          onEndReachedThreshold={0.3}
          ListEmptyComponent={
            <EmptyState icon="clipboard-list-outline" message="No hay compras registradas." />
          }
          ListFooterComponent={loadingMore ? <LoadingScreen message="Cargando más..." /> : null}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  centered: { flex: 1, justifyContent: 'center' },
  list: { padding: SPACING.md, gap: SPACING.sm, paddingBottom: SPACING.xxl },
});
