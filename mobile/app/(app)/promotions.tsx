import { Redirect } from 'expo-router';

// Keep old links navigable after retiring this module, without loading its API.
export default function RetiredPromotionsRoute() {
  return <Redirect href="/(app)/branches" />;
}
