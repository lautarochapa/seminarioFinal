import { Redirect } from 'expo-router';

// Keep old links navigable after retiring this module, without loading its API.
export default function RetiredPaymentMethodsRoute() {
  return <Redirect href="/(app)/profile" />;
}
