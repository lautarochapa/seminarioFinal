import { useEffect, useState } from 'react';
import { getNetworkState, subscribeNetworkState, type NetworkState } from '@/utils/networkStatus';

export function useNetworkStatus(): NetworkState {
  const [state, setState] = useState<NetworkState>(getNetworkState());

  useEffect(() => subscribeNetworkState(setState), []);

  return state;
}
