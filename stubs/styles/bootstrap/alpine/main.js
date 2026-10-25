import Alpine from 'alpinejs'
import { backendStatus } from './backend.js'
import './style.css'
document.getElementById('app').innerHTML = `<main x-data="welcome"><small class="fw-bold text-success">NOVAPHP + ALPINE</small><h1 class="display-4 fw-bold my-4">Your next idea starts here.</h1><p class="lead text-secondary mb-4">A clean Alpine frontend with a NovaPHP backend. Edit main.js to begin.</p><button class="btn btn-success btn-lg" @click="count++">Count: <span x-text="count"></span></button><footer class="mt-4 text-secondary small"><span x-text="status"></span> · <a class="link-success" href="/docs">Documentation</a></footer></main>`
Alpine.data('welcome', () => ({ count: 0, status: 'Checking backend…', async init() { this.status = await backendStatus() } }))
window.Alpine = Alpine
Alpine.start()
