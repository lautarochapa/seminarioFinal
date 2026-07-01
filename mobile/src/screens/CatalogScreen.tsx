import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useProducts } from '@/hooks/useProducts';
import { EmptyState } from '@/components/EmptyState';
import { ErrorState } from '@/components/ErrorState';
import { friendlyMessage } from '@/utils/errorParser';
import { COLORS, FONT, RADIUS, SHADOW, SPACING, TOUCH_TARGET } from '@/utils/theme';
import type { ProductSummary } from '@/types/product';

const DEBOUNCE_MS = 400;

function ProductCard({ item, onPress }: { item: ProductSummary; onPress: () => void }) {
  return (
    <Pressable
      style={({ pressed }) => [styles.card, pressed && styles.cardPressed]}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`Ver detalle de ${item.name}`}
    >
      <View style={styles.cardIcon}>
        <MaterialCommunityIcons name="package-variant-closed" size={24} color={COLORS.primary} />
      </View>
      <View style={styles.cardBody}>
        <Text style={styles.cardName} numberOfLines={2}>{item.name}</Text>
        {item.brand?.name ? (
          <Text style={styles.cardMeta} numberOfLines={1}>{item.brand.name}</Text>
        ) : null}
        {item.category?.name ? (
          <Text style={styles.cardCategory} numberOfLines={1}>{item.category.name}</Text>
        ) : null}
        {item.net_quantity != null && item.unit ? (
          <Text style={styles.cardPresentation} numberOfLines={1}>
            {item.net_quantity} {item.unit.symbol}
          </Text>
        ) : null}
      </View>
      <MaterialCommunityIcons name="chevron-right" size={20} color={COLORS.textHint} />
    </Pressable>
  );
}

export function CatalogScreen() {
  const router = useRouter();
  const [searchText, setSearchText] = useState('');
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const { data, meta, loading, loadingMore, error, refresh, loadMore, setFilters } = useProducts();

  const handleSearchChange = useCallback((text: string) => {
    setSearchText(text);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      setFilters({ search: text || undefined });
    }, DEBOUNCE_MS);
  }, [setFilters]);

  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, []);

  const handleEndReached = useCallback(() => {
    loadMore();
  }, [loadMore]);

  const renderItem = useCallback(({ item }: { item: ProductSummary }) => (
    <ProductCard
      item={item}
      onPress={() => router.push({ pathname: '/(app)/products/[id]', params: { id: String(item.id) } })}
    />
  ), [router]);

  const renderFooter = useCallback(() => {
    if (!loadingMore) return null;
    return (
      <View style={styles.footer}>
        <ActivityIndicator size="small" color={COLORS.primary} />
      </View>
    );
  }, [loadingMore]);

  const renderEmpty = useCallback(() => {
    if (loading) return null;
    if (error) {
      return (
        <ErrorState
          message={friendlyMessage(error)}
          traceId={error.traceId}
          onRetry={refresh}
          type="server"
        />
      );
    }
    return (
      <EmptyState
        icon="clipboard-list-outline"
        message={searchText ? `Sin resultados para "${searchText}".` : 'No hay productos disponibles.'}
      />
    );
  }, [loading, error, searchText, refresh]);

  return (
    <View style={styles.container}>
      {/* Search bar */}
      <View style={styles.searchWrap}>
        <MaterialCommunityIcons name="magnify" size={20} color={COLORS.textHint} style={styles.searchIcon} />
        <TextInput
          style={styles.searchInput}
          placeholder="Buscar producto..."
          placeholderTextColor={COLORS.textHint}
          value={searchText}
          onChangeText={handleSearchChange}
          returnKeyType="search"
          clearButtonMode="while-editing"
          autoCapitalize="none"
          autoCorrect={false}
        />
        {searchText.length > 0 ? (
          <Pressable
            onPress={() => handleSearchChange('')}
            hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
            accessibilityLabel="Limpiar búsqueda"
          >
            <MaterialCommunityIcons name="close-circle" size={18} color={COLORS.textHint} />
          </Pressable>
        ) : null}
      </View>

      {/* Count */}
      {meta && !loading ? (
        <Text style={styles.countText}>
          {meta.total} {meta.total === 1 ? 'producto' : 'productos'}
        </Text>
      ) : null}

      <FlatList
        data={data}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderItem}
        ListEmptyComponent={renderEmpty}
        ListFooterComponent={renderFooter}
        onEndReached={handleEndReached}
        onEndReachedThreshold={0.3}
        refreshControl={
          <RefreshControl
            refreshing={loading && data.length > 0}
            onRefresh={refresh}
            tintColor={COLORS.primary}
          />
        }
        contentContainerStyle={[styles.list, data.length === 0 && styles.listEmpty]}
        showsVerticalScrollIndicator={false}
        initialNumToRender={15}
      />

      {loading && data.length === 0 ? (
        <View style={styles.loadingOverlay}>
          <ActivityIndicator size="large" color={COLORS.primary} />
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  searchWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    gap: SPACING.xs,
  },
  searchIcon: {
    marginRight: SPACING.xs,
  },
  searchInput: {
    flex: 1,
    fontSize: FONT.bodySize,
    color: COLORS.textPrimary,
    paddingVertical: SPACING.xs,
    minHeight: TOUCH_TARGET - 8,
  },
  countText: {
    fontSize: FONT.captionSize,
    color: COLORS.textSecondary,
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.xs,
  },
  list: {
    padding: SPACING.md,
    gap: SPACING.sm,
  },
  listEmpty: {
    flexGrow: 1,
    justifyContent: 'center',
  },
  card: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    flexDirection: 'row',
    alignItems: 'center',
    gap: SPACING.md,
    marginBottom: SPACING.sm,
    ...SHADOW.sm,
  },
  cardPressed: {
    opacity: 0.85,
  },
  cardIcon: {
    width: 44,
    height: 44,
    borderRadius: RADIUS.md,
    backgroundColor: COLORS.primarySurface,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cardBody: {
    flex: 1,
    gap: 2,
  },
  cardName: {
    fontSize: FONT.bodySize,
    fontWeight: '600',
    color: COLORS.textPrimary,
    lineHeight: 20,
  },
  cardMeta: {
    fontSize: FONT.captionSize + 1,
    color: COLORS.textSecondary,
  },
  cardCategory: {
    fontSize: FONT.captionSize,
    color: COLORS.textHint,
  },
  cardPresentation: {
    fontSize: FONT.captionSize,
    color: COLORS.primary,
    fontWeight: '500',
  },
  footer: {
    padding: SPACING.md,
    alignItems: 'center',
  },
  loadingOverlay: {
    position: 'absolute' as const,
    top: 0, bottom: 0, left: 0, right: 0,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: 'rgba(244,246,245,0.85)',
  },
});
