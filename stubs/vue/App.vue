<script setup>
import { ref, onMounted } from 'vue'
import { backendStatus } from './backend.js'
const count = ref(0)
const status = ref('Checking backend…')
onMounted(async () => { status.value = await backendStatus() })
</script>
<template><main><small>NOVAPHP + VUE</small><h1>Your next idea starts here.</h1><p>A clean Vue frontend with a NovaPHP backend. Edit App.vue to begin.</p><button @click="count++">Count: {{ count }}</button><footer>{{ status }} · <a href="/docs">Documentation</a></footer></main></template>
