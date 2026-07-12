import { ENV } from '@/config/env';

export type NetworkState = 'online' | 'offline' | 'reconnecting';

type Listener = (state: NetworkState) => void;

let state: NetworkState = 'online';
const listeners = new Set<Listener>();
let heartbeatTimer: ReturnType<typeof setInterval> | null = null;
let heartbeatInFlight = false;

const HEARTBEAT_INTERVAL_MS = 10_000;
const HEARTBEAT_TIMEOUT_MS = 5_000;

function setState(next: NetworkState): void {
  if (next === state) return;
  state = next;
  listeners.forEach((listener) => listener(state));
  if (state === 'online') {
    stopHeartbeat();
  } else {
    startHeartbeat();
  }
}

function startHeartbeat(): void {
  if (heartbeatTimer) return;
  heartbeatTimer = setInterval(() => {
    void runHeartbeat();
  }, HEARTBEAT_INTERVAL_MS);
}

function stopHeartbeat(): void {
  if (!heartbeatTimer) return;
  clearInterval(heartbeatTimer);
  heartbeatTimer = null;
}

async function runHeartbeat(): Promise<void> {
  if (heartbeatInFlight) return;
  heartbeatInFlight = true;
  setState('reconnecting');
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), HEARTBEAT_TIMEOUT_MS);
  try {
    // Any HTTP response (even 401/404) proves the server is reachable.
    await fetch(ENV.API_URL, { method: 'GET', signal: controller.signal });
    setState('online');
  } catch {
    setState('offline');
  } finally {
    clearTimeout(timeoutId);
    heartbeatInFlight = false;
  }
}

export function getNetworkState(): NetworkState {
  return state;
}

export function subscribeNetworkState(listener: Listener): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

export function reportRequestSuccess(): void {
  setState('online');
}

export function reportRequestFailure(): void {
  if (state === 'online') {
    setState('reconnecting');
    void runHeartbeat();
  } else if (state !== 'offline' && !heartbeatInFlight) {
    setState('offline');
  }
}

/** Test-only: resets the module singleton state between test cases. */
export function __resetNetworkStateForTests(): void {
  stopHeartbeat();
  heartbeatInFlight = false;
  state = 'online';
  listeners.clear();
}
