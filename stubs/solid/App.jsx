import { createSignal, onMount } from 'solid-js'
import { backendStatus } from './backend.js'
export default function App() {
  const [count, setCount] = createSignal(0)
  const [status, setStatus] = createSignal('Checking backend…')
  onMount(async () => setStatus(await backendStatus()))
  return <main><small>NOVAPHP + SOLID</small><h1>Your next idea starts here.</h1><p>A clean Solid frontend with a NovaPHP backend. Edit App.jsx to begin.</p><button onClick={() => setCount(count() + 1)}>Count: {count()}</button><footer>{status()} · <a href="/docs">Documentation</a></footer></main>
}
