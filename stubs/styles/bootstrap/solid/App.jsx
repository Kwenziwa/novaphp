import { createSignal, onMount } from 'solid-js'
import { backendStatus } from './backend.js'
export default function App() {
  const [count, setCount] = createSignal(0)
  const [status, setStatus] = createSignal('Checking backend…')
  onMount(async () => setStatus(await backendStatus()))
  return <main class="container my-5 p-5 bg-white border rounded-4 shadow-sm"><small class="fw-bold text-success">NOVAPHP + SOLID</small><h1 class="display-4 fw-bold my-4">Your next idea starts here.</h1><p class="lead text-secondary mb-4">A clean Solid frontend with a NovaPHP backend. Edit App.jsx to begin.</p><button class="btn btn-success btn-lg" onClick={() => setCount(count() + 1)}>Count: {count()}</button><footer class="mt-4 text-secondary small">{status()} · <a class="link-success" href="/docs">Documentation</a></footer></main>
}
