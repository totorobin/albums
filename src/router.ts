import { createRouter, createWebHashHistory } from "vue-router";
import HomeView from "@/views/HomeView.vue";
import AlbumView from "@/views/AlbumView.vue";

const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: "/", name: "home", component: HomeView },
    { path: "/album/:id(.*)", name: "album", component: AlbumView, props: true },
  ],
});

export default router;
