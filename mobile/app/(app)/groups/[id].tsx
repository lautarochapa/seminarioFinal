import { useLocalSearchParams } from 'expo-router';
import { GroupDetailScreen } from '@/screens/GroupDetailScreen';

export default function GroupDetailRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <GroupDetailScreen groupId={parseInt(id, 10)} />;
}
