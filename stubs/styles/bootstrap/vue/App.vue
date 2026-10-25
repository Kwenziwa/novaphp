<script setup>
import { ref, onMounted } from 'vue'
import { backendStatus } from './backend.js'
const count = ref(0)
const status = ref('Checking backend…')
onMounted(async () => { status.value = await backendStatus() })
</script>
<template><main class="container my-5 p-5 bg-white border rounded-4 shadow-sm"><small class="fw-bold text-success">NOVAPHP + VUE</small><h1 class="display-4 fw-bold my-4">Your next idea starts here.</h1><p class="lead text-secondary mb-4">A clean Vue frontend with a NovaPHP backend. Edit App.vue to begin.</p><button class="btn btn-success btn-lg" @click="count++">Count: {{ count }}</button><footer class="mt-4 text-secondary small">{{ status }} · <a class="link-success" href="/docs">Documentation</a></footer></main></template>
