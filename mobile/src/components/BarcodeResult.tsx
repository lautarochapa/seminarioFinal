import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { AppButton } from '@/components/AppButton';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import type { ProductDetail } from '@/types/product';

interface Props {
  barcode: string;
  product: ProductDetail | null;
  notFound: boolean;
  stockStatus?: string | null;
  onUseProduct?: () => void;
  onAddToStock?: () => void;
  onRequestProduct?: () => void;
  onScanAgain: () => void;
}

export function BarcodeResult({ barcode, product, notFound, stockStatus, onUseProduct, onAddToStock, onRequestProduct, onScanAgain }: Props) {
  if (product) {
    return (
      <View style={styles.card}>
        <View style={styles.row}>
          <MaterialCommunityIcons name="check-circle" size={22} color={COLORS.success} />
          <Text style={styles.title}>{product.name}</Text>
        </View>
        {product.brand?.name ? <Text style={styles.meta}>{product.brand.name}</Text> : null}
        <Text style={styles.code}>Código: {barcode}</Text>
        {stockStatus ? <Text style={styles.meta}>{stockStatus}</Text> : null}
        <View style={styles.actions}>
          {onAddToStock ? <AppButton title="Agregar al stock" onPress={onAddToStock} fullWidth /> : null}
          {onUseProduct ? <AppButton title="Usar este producto" onPress={onUseProduct} fullWidth /> : null}
          {onRequestProduct ? <AppButton title="Cargar manualmente" onPress={onRequestProduct} fullWidth /> : null}
          <AppButton title="Escanear nuevamente" variant="outline" onPress={onScanAgain} fullWidth />
        </View>
      </View>
    );
  }

  if (notFound) {
    return (
      <View style={styles.card}>
        <View style={styles.row}>
          <MaterialCommunityIcons name="alert-circle-outline" size={22} color={COLORS.warning} />
          <Text style={styles.title}>No encontramos un producto con este código.</Text>
        </View>
        <Text style={styles.code}>Código: {barcode}</Text>
        <Text style={styles.meta}>Podés cargarlo ahora y quedará pendiente de revisión.</Text>
        <View style={styles.actions}>
          {onRequestProduct ? <AppButton title="Cargar manualmente" onPress={onRequestProduct} fullWidth /> : null}
          <AppButton title="Escanear nuevamente" variant="outline" onPress={onScanAgain} fullWidth />
        </View>
      </View>
    );
  }

  return null;
}

const styles = StyleSheet.create({
  card: { backgroundColor: COLORS.surface, borderRadius: RADIUS.md, padding: SPACING.md, gap: SPACING.sm, ...SHADOW.sm },
  row: { flexDirection: 'row', alignItems: 'center', gap: SPACING.xs },
  title: { flex: 1, fontSize: FONT.bodySize, fontWeight: '700', color: COLORS.textPrimary },
  meta: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
  code: { fontSize: FONT.captionSize, color: COLORS.textHint, fontFamily: 'monospace' },
  actions: { gap: SPACING.sm, marginTop: SPACING.xs },
});
