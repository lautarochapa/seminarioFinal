import React, { useMemo, useState } from 'react';
import { FlatList, RefreshControl, StyleSheet, TextInput, View } from 'react-native';
import { useRouter } from 'expo-router';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { AppHeader } from '@/components/AppHeader';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { LoadingScreen } from '@/components/LoadingScreen';
import { SupermarketCard } from '@/components/SupermarketCard';
import { useSupermarkets } from '@/hooks/useSupermarkets';
import { friendlyMessage } from '@/utils/errorParser';
import { goBackOrHome } from '@/utils/navigation';
import { COLORS, SPACING } from '@/utils/theme';
import type { SupermarketChain } from '@/types/retail';

export function SupermarketsScreen() {
  const router = useRouter();
  const [search, setSearch] = useState('');
  const { data, loading, error, refresh } = useSupermarkets();
  const filtered = useMemo(() => data.filter((item) => item.name.toLowerCase().includes(search.toLowerCase())), [data, search]);

  return (
    <View style={styles.fill}>
      <AppHeader title="Supermercados" showBack onBack={goBackOrHome} />
      <View style={styles.search}>
        <MaterialCommunityIcons name="magnify" size={20} color={COLORS.textHint} />
        <TextInput
          style={styles.input}
          placeholder="Buscar cadena"
          placeholderTextColor={COLORS.textHint}
          value={search}
          onChangeText={setSearch}
        />
      </View>
      {loading && data.length === 0 ? <LoadingScreen message="Cargando supermercados..." /> : null}
      {error && data.length === 0 ? <ErrorState message={friendlyMessage(error)} traceId={error.traceId} onRetry={refresh} type="server" /> : null}
      {!loading && !error ? (
        <FlatList
          data={filtered}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }: { item: SupermarketChain }) => (
            <SupermarketCard item={item} onPress={() => router.push({ pathname: '/(app)/supermarkets/[id]' as never, params: { id: String(item.id) } })} />
          )}
          ListEmptyComponent={<EmptyState icon="store-search-outline" message="No hay supermercados para mostrar." />}
          refreshControl={<RefreshControl refreshing={loading} onRefresh={refresh} tintColor={COLORS.primary} />}
          contentContainerStyle={styles.list}
        />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  search: { flexDirection: 'row', alignItems: 'center', gap: SPACING.sm, padding: SPACING.md, backgroundColor: COLORS.surface, borderBottomWidth: 1, borderBottomColor: COLORS.border },
  input: { flex: 1, color: COLORS.textPrimary, paddingVertical: SPACING.sm },
  list: { padding: SPACING.md, gap: SPACING.md },
});
