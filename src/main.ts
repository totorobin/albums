import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import { loadAlbums } from "./albums";
import "@brand-css";

loadAlbums();

const app = createApp(App);
app.use(router);
app.mount("#app");
