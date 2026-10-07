import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './app/App.vue';
import router from './router/index.js';
import { useAuthStore } from './stores/auth.js';

const pinia = createPinia();

window.addEventListener('auth:expired', () => {
    useAuthStore(pinia).clearSession();
    router.replace({ name: 'login' });
});

createApp(App).use(pinia).use(router).mount('#app');
