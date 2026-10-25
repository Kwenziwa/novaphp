import { createSignal, onMount } from 'solid-js'
import { backendStatus } from './backend.js'
export default function App() {
  const [count, setCount] = createSignal(0)
  const [status, setStatus] = createSignal('Checking backend…')
  onMount(async () => setStatus(await backendStatus()))
  return <main class="mx-auto my-16 max-w-3xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm"><small class="font-semibold tracking-widest text-emerald-700">NOVAPHP + SOLID</small><h1 class="my-6 text-5xl font-bold tracking-tight text-slate-900">Your next idea starts here.</h1><p class="mb-8 text-lg text-slate-600">A clean Solid frontend with a NovaPHP backend. Edit App.jsx to begin.</p><button class="rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white hover:bg-emerald-800" onClick={() => setCount(count() + 1)}>Count: {count()}</button><footer class="mt-8 text-sm text-slate-500">{status()} · <a class="underline text-emerald-700" href="/docs">Documentation</a></footer></main>
}
