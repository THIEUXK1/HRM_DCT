import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import '../../css/app.css';

const app = createApp(App);

app.config.errorHandler = (err, _instance, info) => {
    console.error('[HCM]', info, err);
};

app.use(createPinia());
app.use(router);
app.mount('#app');
