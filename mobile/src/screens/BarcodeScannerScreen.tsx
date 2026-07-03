import React, { useState } from 'react';
import { ActivityIndicator, Modal, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { useRouter } from 'expo-router';
import { AppHeader } from '@/components/AppHeader';
import { AppButton } from '@/components/AppButton';
import { BarcodeScanner } from '@/components/BarcodeScanner';
import { BarcodeResult } from '@/components/BarcodeResult';
import { productsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { setPendingScanResult } from '@/utils/barcodeScanResult';
import { goBackOrHome } from '@/utils/navigation';
import { COLORS, FONT, RADIUS, SPACING, TOUCH_TARGET } from '@/utils/theme';
import type { ProductDetail } from '@/types/product';

export function BarcodeScannerScreen() {
  const router = useRouter();
  const [lastCode, setLastCode] = useState<string | null>(null);
  const [product, setProduct] = useState<ProductDetail | null>(null);
  const [notFound, setNotFound] = useState(false);
  const [loading, setLoading] = useState(false);
  const [manualVisible, setManualVisible] = useState(false);
  const [manualCode, setManualCode] = useState('');

  async function lookup(code: string) {
    setLoading(true);
    setProduct(null);
    setNotFound(false);
    setLastCode(code);
    try {
      const res = await productsApi.findByBarcode(code);
      setProduct(res.data);
    } catch (err) {
      if (err instanceof ApiError && err.normalized.status === 404) {
        setNotFound(true);
      } else {
        setNotFound(true);
      }
    } finally {
      setLoading(false);
    }
  }

  function handleUseProduct() {
    if (!product || !lastCode) return;
    setPendingScanResult({ productId: product.id, productName: product.name, barcode: lastCode });
    router.back();
  }

  function handleScanAgain() {
    setProduct(null);
    setNotFound(false);
    setLastCode(null);
  }

  function handleManualSubmit() {
    const code = manualCode.trim();
    if (!code) return;
    setManualVisible(false);
    setManualCode('');
    lookup(code);
  }

  const showingResult = Boolean(lastCode) && (product !== null || notFound) && !loading;

  return (
    <View style={styles.fill}>
      <AppHeader title="Escanear código" showBack onBack={goBackOrHome} />
      {showingResult ? (
        <View style={styles.resultWrap}>
          <BarcodeResult
            barcode={lastCode ?? ''}
            product={product}
            notFound={notFound}
            onUseProduct={product ? handleUseProduct : undefined}
            onScanAgain={handleScanAgain}
          />
        </View>
      ) : (
        <BarcodeScanner
          active={!loading}
          onScanned={lookup}
          onManualEntry={() => setManualVisible(true)}
        />
      )}
      {loading ? (
        <View style={styles.loadingOverlay}>
          <ActivityIndicator size="large" color={COLORS.primary} />
        </View>
      ) : null}

      <Modal visible={manualVisible} transparent animationType="fade" onRequestClose={() => setManualVisible(false)}>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Ingresar código manualmente</Text>
            <TextInput
              style={styles.modalInput}
              value={manualCode}
              onChangeText={setManualCode}
              placeholder="Código de barras"
              placeholderTextColor={COLORS.textHint}
              keyboardType="numeric"
              autoFocus
              accessibilityLabel="Código de barras"
            />
            <View style={styles.modalActions}>
              <Pressable
                onPress={() => { setManualVisible(false); setManualCode(''); }}
                accessibilityRole="button"
                style={styles.modalCancelBtn}
              >
                <Text style={styles.modalCancelText}>Cancelar</Text>
              </Pressable>
              <AppButton title="Buscar" onPress={handleManualSubmit} disabled={!manualCode.trim()} />
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: COLORS.background },
  resultWrap: { flex: 1, padding: SPACING.md, justifyContent: 'center' },
  loadingOverlay: {
    ...StyleSheet.absoluteFill,
    backgroundColor: 'rgba(0,0,0,0.3)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', alignItems: 'center', justifyContent: 'center', padding: SPACING.lg },
  modalCard: { width: '100%', backgroundColor: COLORS.surface, borderRadius: RADIUS.md, padding: SPACING.lg, gap: SPACING.md },
  modalTitle: { fontSize: FONT.subtitleSize, fontWeight: '700', color: COLORS.textPrimary },
  modalInput: {
    minHeight: TOUCH_TARGET,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.sm,
    paddingHorizontal: SPACING.md,
    fontSize: FONT.bodySize,
    color: COLORS.textPrimary,
  },
  modalActions: { flexDirection: 'row', justifyContent: 'flex-end', alignItems: 'center', gap: SPACING.md },
  modalCancelBtn: { paddingHorizontal: SPACING.md, paddingVertical: SPACING.sm, minHeight: TOUCH_TARGET, justifyContent: 'center' },
  modalCancelText: { color: COLORS.textSecondary, fontWeight: '700', fontSize: FONT.bodySize },
});
