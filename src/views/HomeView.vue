<template>
  <div class="home">
    <h1 class="page-title">Albums</h1>
    <p v-if="loading" class="empty">Chargement des albums…</p>
    <p v-else-if="rootAlbums.length === 0" class="empty">
      Aucun album trouvé. Ajoutez des dossiers dans le répertoire <code>photos/</code>.
    </p>
    <AlbumGrid v-else :albums="rootAlbums" title-tag="h2" />
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { albums, loading } from "@/albums";
import AlbumGrid from "@/components/AlbumGrid.vue";
import { parseDateScore } from "@/utils";

const rootAlbums = computed(() =>
  albums.value
    .filter((a) => {
      if (a.id.includes("/")) return false;
      const hasSubAlbums = albums.value.some((s) => s.id.startsWith(a.id + "/") && !s.id.substring(a.id.length + 1).includes("/"));
      return a.images.length > 0 || hasSubAlbums;
    })
    .sort((a, b) => parseDateScore(b.date) - parseDateScore(a.date)),
);
</script>

<style scoped>
.page-title {
  font-size: 1.8rem;
  font-weight: 300;
  margin-bottom: 1.5rem;
  color: #183a63;
  letter-spacing: 0.02em;
}

.empty {
  font-size: 1rem;
  color: #888;
}

.empty code {
  background: #f0ebe0;
  padding: 0.2em 0.5em;
  border-radius: 4px;
  color: #183a63;
}

@media (max-width: 480px) {
  .page-title {
    font-size: 1.3rem;
    margin-bottom: 1rem;
  }
}
</style>
