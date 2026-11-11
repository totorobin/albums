<template>
  <div class="albums-grid">
    <router-link
      v-for="album in albums"
      :key="album.id"
      :to="{ name: 'album', params: { id: album.id } }"
      class="album-card"
    >
      <div class="album-thumb">
        <img v-if="album.thumbnail" :src="album.thumbnailThumb ?? album.thumbnail" :alt="album.title" />
        <div v-else class="no-thumb">📁</div>
      </div>
      <div class="album-card-info">
        <component :is="titleTag">{{ album.title }}</component>
        <div class="album-meta">
          <span v-if="album.date" class="album-meta-item">📅 {{ album.date }}</span>
          <span v-if="album.author" class="album-meta-item">👤 {{ album.author }}</span>
          <span v-if="album.lieu" class="album-meta-item">📍 {{ album.lieu }}</span>
        </div>
        <span class="album-count">{{ album.images.length }} photo(s)</span>
      </div>
    </router-link>
  </div>
</template>

<script setup lang="ts">
import type { Album } from "@/types";

withDefaults(defineProps<{
  albums: Album[];
  titleTag?: string;
}>(), {
  titleTag: "h3",
});
</script>

<style scoped>
.albums-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 1.2rem;
}

.album-card {
  background: #fff;
  border: 1px solid #e8e0d0;
  border-radius: 8px;
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
  cursor: pointer;
  text-decoration: none;
}

.album-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(24, 58, 99, 0.12);
}

.album-thumb {
  width: 100%;
  height: 160px;
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

.album-card-info {
  padding: 0.8rem;
}

.album-card-info h2,
.album-card-info h3 {
  font-size: 1rem;
  font-weight: 500;
  color: #183a63;
  margin-bottom: 0.2rem;
}

.album-meta {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  margin-bottom: 0.3rem;
}

.album-meta-item {
  font-size: 0.75rem;
  color: #666;
}

.album-count {
  font-size: 0.8rem;
  color: #888;
}

@media (max-width: 480px) {
  .albums-grid {
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 0.6rem;
  }

  .album-thumb {
    height: 100px;
  }

  .album-card-info {
    padding: 0.5rem;
  }

  .album-card-info h2,
  .album-card-info h3 {
    font-size: 0.85rem;
  }
}
</style>
