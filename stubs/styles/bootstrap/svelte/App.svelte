<script>
import { onMount } from 'svelte'
import { backendStatus } from './backend.js'
let count = $state(0)
let status = $state('Checking backend…')
onMount(() => { backendStatus().then(value => status = value) })
</script>
<main class="container my-5 p-5 bg-white border rounded-4 shadow-sm"><small class="fw-bold text-success">NOVAPHP + SVELTE</small><h1 class="display-4 fw-bold my-4">Your next idea starts here.</h1><p class="lead text-secondary mb-4">A clean Svelte frontend with a NovaPHP backend. Edit App.svelte to begin.</p><button class="btn btn-success btn-lg" onclick={() => count++}>Count: {count}</button><footer class="mt-4 text-secondary small">{status} · <a class="link-success" href="/docs">Documentation</a></footer></main>
