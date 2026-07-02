import React, { useCallback } from 'react';
import { FlatList, Pressable, StyleSheet, View } from 'react-native';
import { useRouter } from 'expo-router';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { AppHeader } from '@/components/AppHeader';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { FamilyGroupSelector } from '@/components/FamilyGroupSelector';
import { ShoppingListCard } from '@/components/ShoppingListCard';
import { useFamilyGroupContext } from '@/auth/FamilyGroupContext';
import { useShoppingLists } from '@/hooks/useShoppingLists';
import { goBackOrHome } from '@/utils/navigation';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, SPACING, TOUCH_TARGET } from '@/utils/theme';
import type { ShoppingList } from '@/types/shopping';

export function ShoppingListsScreen() {
  const router = useRouter();
  const { selectedGroup } = useFamilyGroupContext();
  const groupId = selectedGroup?.id ?? null;
  const { data, loading, loadingMore, error, refresh, loadMore } = useShoppingLists(groupId);

  const handlePress = useCallback((list: ShoppingList) => {
    router.push({ pathname: '/(app)/shopping-lists/[id]' as never, params: { id: String(list.id) } });
  }, [router]);

  if (!selectedGroup) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Listas de compras" showBack onBack={goBackOrHome} />
        <View style={styles.centered}>
          <FamilyGroupSelector />
          <EmptyState icon="account-group-outline" message="Seleccioná un grupo familiar para ver las listas." />
        </View>
      </View>
    );
  }

  if (loading && data.length === 0) {
    return (
      <View style={styles.fill}>
        <AppHeader title="Listas de compras" subtitle={selectedGroup.name} showBack onBack={goBackOrHome} />
        <LoadingScreen message="Cargando listas..." />
      </View>
    );
  }

  return (
    <View style={styles.fill}>
      <AppHeader
        title="Listas de compras"
        subtitle={selectedGroup.name}
        showBack
        onBack={goBackOrHome}
        rightAction={
          <Pressable
            onPress={() => router.push('/(app)/shopping-lists/create' as never)}
            accessibilityLabel="Nueva lista"
            accessibilityRole="button"
            style={styles.addBtn}
          >
            <MaterialCommunityIcons name="plus" size={26} color={COLORS.textInverse} />
          </Pressable>
        }
      />
      {error && data.length === 0 ? (
        <ErrorState
          message={friendlyMessage(error)}
          traceId={error.traceId}
          onRetry={refresh}
          type={error.isNetworkError ? 'network' : 'server'}
        />
      ) : (
        <FlatList
          data={data}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => <ShoppingListCard list={item} onPress={() => handlePress(item)} />}
          contentContainerStyle={styles.list}
          onRefresh={refresh}
          refreshing={loading && data.length > 0}
          onEndReached={loadMore}
          onEndReachedThreshold={0.3}
          ListEmptyComponent={
            <EmptyState icon="clipboard-list-outline" message="No hay listas. Creá la primera." />
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
  addBtn: { width: TOUCH_TARGET, height: TOUCH_TARGET, alignItems: 'center', justifyContent: 'center' },
});
