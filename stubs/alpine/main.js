import Alpine from 'alpinejs'
import { backendStatus } from './backend.js'
import './style.css'
document.getElementById('app').innerHTML = `<main x-data="welcome"><small>NOVAPHP + ALPINE</small><h1>Your next idea starts here.</h1><p>A clean Alpine frontend with a NovaPHP backend. Edit main.js to begin.</p><button @click="count++">Count: <span x-text="count"></span></button><footer><span x-text="status"></span> · <a href="/docs">Documentation</a></footer></main>`
Alpine.data('welcome', () => ({ count: 0, status: 'Checking backend…', async init() { this.status = await backendStatus() } }))
window.Alpine = Alpine
Alpine.start()
