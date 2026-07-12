import React from 'react';
import { Image, Pressable, StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import type { SupermarketChain } from '@/types/retail';

export function SupermarketCard({ item, onPress }: { item: SupermarketChain; onPress: () => void }) {
  return (
    <Pressable
      style={({ pressed }) => [styles.card, pressed && styles.pressed]}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={item.name}
    >
      <View style={styles.icon}>
        {item.logo_url ? (
          <Image source={{ uri: item.logo_url }} style={styles.logo} accessibilityIgnoresInvertColors />
        ) : (
          <MaterialCommunityIcons name="storefront-outline" size={24} color={COLORS.primary} />
        )}
      </View>
      <View style={styles.body}>
        <Text style={styles.title} numberOfLines={1}>{item.name}</Text>
        <Text style={styles.meta}>{item.status}</Text>
        {typeof item.branches_count === 'number' ? (
          <Text style={styles.hint}>{item.branches_count} sucursal{item.branches_count === 1 ? '' : 'es'}</Text>
        ) : null}
      </View>
      <MaterialCommunityIcons name="chevron-right" size={20} color={COLORS.textHint} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: { flexDirection: 'row', alignItems: 'center', gap: SPACING.md, backgroundColor: COLORS.surface, borderRadius: RADIUS.md, padding: SPACING.md, ...SHADOW.sm },
  pressed: { opacity: 0.85 },
  icon: { width: 44, height: 44, borderRadius: RADIUS.md, backgroundColor: COLORS.primarySurface, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  logo: { width: 44, height: 44 },
  body: { flex: 1, gap: 2 },
  title: { fontSize: FONT.subtitleSize, fontWeight: '700', color: COLORS.textPrimary },
  meta: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
  hint: { fontSize: FONT.captionSize, color: COLORS.textHint },
});
