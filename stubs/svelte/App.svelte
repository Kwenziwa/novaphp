<script>
import { onMount } from 'svelte'
import { backendStatus } from './backend.js'
let count = $state(0)
let status = $state('Checking backend…')
onMount(() => { backendStatus().then(value => status = value) })
</script>
<main><small>NOVAPHP + SVELTE</small><h1>Your next idea starts here.</h1><p>A clean Svelte frontend with a NovaPHP backend. Edit App.svelte to begin.</p><button onclick={() => count++}>Count: {count}</button><footer>{status} · <a href="/docs">Documentation</a></footer></main>
