import Alpine from 'alpinejs'
import { backendStatus } from './backend.js'
import './style.css'
document.getElementById('app').innerHTML = `<main x-data="welcome"><small class="font-semibold tracking-widest text-emerald-700">NOVAPHP + ALPINE</small><h1 class="my-6 text-5xl font-bold tracking-tight text-slate-900">Your next idea starts here.</h1><p class="mb-8 text-lg text-slate-600">A clean Alpine frontend with a NovaPHP backend. Edit main.js to begin.</p><button class="rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white hover:bg-emerald-800" @click="count++">Count: <span x-text="count"></span></button><footer class="mt-8 text-sm text-slate-500"><span x-text="status"></span> · <a class="underline text-emerald-700" href="/docs">Documentation</a></footer></main>`
Alpine.data('welcome', () => ({ count: 0, status: 'Checking backend…', async init() { this.status = await backendStatus() } }))
window.Alpine = Alpine
Alpine.start()
