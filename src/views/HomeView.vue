<template>
  <div class="home">
    <h1 class="page-title">Albums</h1>
    <p v-if="loading" class="empty">Chargement des albums…</p>
    <p v-else-if="rootAlbums.length === 0" class="empty">
      Aucun album trouvé. Ajoutez des dossiers dans le répertoire <code>photos/</code>.
    </p>
    <div v-else class="albums-grid">
      <router-link
        v-for="album in rootAlbums"
        :key="album.id"
        :to="{ name: 'album', params: { id: album.id } }"
        class="album-card"
      >
        <div class="album-thumb">
          <img
            v-if="album.thumbnail"
            :src="album.thumbnail"
            :alt="album.title"
          />
          <div v-else class="no-thumb">📁</div>
        </div>
        <div class="album-info">
          <h2>{{ album.title }}</h2>
          <div class="album-meta">
            <span v-if="album.date" class="album-meta-item">📅 {{ album.date }}</span>
            <span v-if="album.author" class="album-meta-item">👤 {{ album.author }}</span>
            <span v-if="album.lieu" class="album-meta-item">📍 {{ album.lieu }}</span>
          </div>
          <span class="album-count">{{ album.images.length }} photo(s)</span>
        </div>
      </router-link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { albums, loading } from "@/albums";

const rootAlbums = computed(() =>
  albums.value.filter((a) => {
    if (a.id.includes("/")) return false;
    // Masquer les albums vides (sans photos et sans sous-albums)
    const hasSubAlbums = albums.value.some((s) => s.id.startsWith(a.id + "/") && !s.id.substring(a.id.length + 1).includes("/"));
    return a.images.length > 0 || hasSubAlbums;
  }),
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

.albums-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1.5rem;
}

.album-card {
  background: #fff;
  border: 1px solid #e8e0d0;
  border-radius: 8px;
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
  cursor: pointer;
}

.album-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(24, 58, 99, 0.12);
}

.album-thumb {
  width: 100%;
  height: 200px;
  overflow: hidden;
  background: #f0f4f9;
  display: flex;
  align-items: center;
  justify-content: center;
}

.album-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.no-thumb {
  font-size: 3rem;
  color: #b89a5a;
}

.album-info {
  padding: 1rem;
}

.album-info h2 {
  font-size: 1.05rem;
  font-weight: 500;
  margin-bottom: 0.3rem;
  color: #183a63;
}

.album-meta {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  margin-bottom: 0.3rem;
}

.album-meta-item {
  font-size: 0.78rem;
  color: #666;
}

.album-count {
  font-size: 0.85rem;
  color: #888;
}

@media (max-width: 480px) {
  .page-title {
    font-size: 1.3rem;
    margin-bottom: 1rem;
  }

  .albums-grid {
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 0.8rem;
  }

  .album-thumb {
    height: 120px;
  }

  .album-info {
    padding: 0.6rem;
  }

  .album-info h2 {
    font-size: 0.9rem;
  }
}
</style>
