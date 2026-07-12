import React, { useCallback, useRef, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { CameraView, useCameraPermissions, type BarcodeScanningResult } from 'expo-camera';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { AppButton } from '@/components/AppButton';
import { EmptyState } from '@/components/EmptyState';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

interface Props {
  onScanned: (code: string) => void;
  onManualEntry?: () => void;
  active?: boolean;
}

const SCAN_LOCK_MS = 1500;

export function BarcodeScanner({ onScanned, onManualEntry, active = true }: Props) {
  const [permission, requestPermission] = useCameraPermissions();
  const [torch, setTorch] = useState(false);
  const lockedRef = useRef(false);

  const handleScan = useCallback((result: BarcodeScanningResult) => {
    if (lockedRef.current || !active) return;
    lockedRef.current = true;
    onScanned(result.data);
    setTimeout(() => { lockedRef.current = false; }, SCAN_LOCK_MS);
  }, [onScanned, active]);

  if (!permission) {
    return <View style={styles.center} />;
  }

  if (!permission.granted) {
    const blocked = !permission.canAskAgain;
    return (
      <View style={styles.center}>
        <EmptyState
          icon="package-variant-closed"
          message={blocked
            ? 'El permiso de cámara está bloqueado. Habilitalo desde los ajustes del sistema para escanear códigos.'
            : 'CocinaComidaControl necesita acceso a la cámara para escanear códigos de barras.'}
        />
        {!blocked ? (
          <AppButton title="Permitir cámara" onPress={requestPermission} style={styles.permissionBtn} />
        ) : null}
        {onManualEntry ? (
          <Pressable onPress={onManualEntry} accessibilityRole="button" style={styles.manualLink}>
            <Text style={styles.manualLinkText}>Ingresar código manualmente</Text>
          </Pressable>
        ) : null}
      </View>
    );
  }

  return (
    <View style={styles.fill}>
      <CameraView
        style={styles.camera}
        facing="back"
        enableTorch={torch}
        barcodeScannerSettings={{ barcodeTypes: ['ean13', 'ean8', 'upc_a', 'upc_e', 'code128', 'code39', 'qr'] }}
        onBarcodeScanned={handleScan}
      >
        <View style={styles.overlay}>
          <Text style={styles.hint}>Apuntá la cámara al código de barras</Text>
          <View style={styles.frame} />
          <View style={styles.controls}>
            <Pressable
              onPress={() => setTorch((v) => !v)}
              accessibilityRole="button"
              accessibilityLabel={torch ? 'Apagar linterna' : 'Encender linterna'}
              style={styles.controlBtn}
            >
              <MaterialCommunityIcons name={torch ? 'flashlight-off' : 'flashlight'} size={24} color="#fff" />
            </Pressable>
            {onManualEntry ? (
              <Pressable onPress={onManualEntry} accessibilityRole="button" style={styles.manualBtn}>
                <Text style={styles.manualBtnText}>Ingresar manualmente</Text>
              </Pressable>
            ) : null}
          </View>
        </View>
      </CameraView>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: '#000' },
  camera: { flex: 1 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: SPACING.lg, gap: SPACING.md },
  permissionBtn: { marginTop: SPACING.sm },
  manualLink: { marginTop: SPACING.sm, padding: SPACING.sm },
  manualLinkText: { color: COLORS.primary, fontWeight: '700', fontSize: FONT.bodySize },
  overlay: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: SPACING.lg },
  hint: { color: '#fff', fontSize: FONT.bodySize, fontWeight: '700', marginBottom: SPACING.lg, textAlign: 'center' },
  frame: { width: '80%', aspectRatio: 1.8, borderWidth: 2, borderColor: '#fff', borderRadius: RADIUS.md },
  controls: { position: 'absolute', bottom: SPACING.xl, flexDirection: 'row', alignItems: 'center', gap: SPACING.lg },
  controlBtn: { width: 48, height: 48, borderRadius: 24, backgroundColor: 'rgba(0,0,0,0.5)', alignItems: 'center', justifyContent: 'center' },
  manualBtn: { paddingHorizontal: SPACING.md, paddingVertical: SPACING.sm, backgroundColor: 'rgba(0,0,0,0.5)', borderRadius: RADIUS.full },
  manualBtnText: { color: '#fff', fontWeight: '700', fontSize: FONT.captionSize },
});
