import { ref } from "vue";
import type { Album } from "@/types";

export const albums = ref<Album[]>([]);
export const loading = ref(true);

export async function loadAlbums(): Promise<void> {
  try {
    const response = await fetch("/api.php");
    albums.value = await response.json();
  } catch (e) {
    console.error("Erreur lors du chargement des albums:", e);
    albums.value = [];
  } finally {
    loading.value = false;
  }
}
