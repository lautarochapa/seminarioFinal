import { useState } from 'react';
import { Image, StyleSheet, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import type { ProductImage } from '@/types/product';
import { productImageUrl } from '@/utils/productImages';
import { COLORS } from '@/utils/theme';

interface ProductPhotoProps {
  images?: ProductImage[];
  name: string;
  size?: number;
}

export function ProductPhoto({ images, name, size = 52 }: ProductPhotoProps) {
  const uri = productImageUrl(images);
  // A different product/URL can load even if the previous photo failed.
  const [failedUri, setFailedUri] = useState<string | null>(null);
  const showImage = !!uri && failedUri !== uri;
  return (
    <View style={[styles.frame, { width: size, height: size }, showImage && styles.photoFrame]}>
      {showImage ? (
        <Image
          key={uri}
          source={{ uri }}
          style={styles.photo}
          resizeMode="contain"
          accessibilityLabel={`Foto de ${name}`}
          onError={() => setFailedUri(uri)}
        />
      ) : (
        <MaterialCommunityIcons name="package-variant-closed" size={Math.min(48, size / 2)} color={COLORS.primary} />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  frame: { flexShrink: 0, borderRadius: 8, backgroundColor: COLORS.primarySurface, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  photoFrame: { backgroundColor: COLORS.surface },
  photo: { width: '100%', height: '100%' },
});
