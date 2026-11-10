<template>
  <div class="album-view">
    <router-link :to="parentRoute" class="back-link">← {{ parentLabel }}</router-link>

    <p v-if="!album" class="empty">Album introuvable.</p>

    <template v-else>
      <h1 class="album-title">{{ album.title }}</h1>

      <!-- Progression génération miniatures -->
      <div v-if="thumbGenerating" class="thumb-progress">
        <span class="thumb-spinner"></span>
        <span>Création de miniatures manquantes… {{ thumbGenerated }} / {{ thumbTotal }}</span>
      </div>


      <!-- Sous-albums -->
      <div v-if="subAlbums.length > 0" class="sub-albums">
        <h2 class="sub-albums-title">Sous-albums</h2>
        <AlbumGrid :albums="subAlbums" />
      </div>

      <div class="photos-grid">
        <div
          v-for="(img, index) in album.images"
          :key="img"
          class="photo-card"
          @click="openLightbox(index)"
        >
          <LazyPictureRenderer :src="thumbSrc(img, index)" :alt="album.imageNames[index]" :fallback-src="img" />
        </div>
      </div>

    </template>

    <!-- Lightbox -->
    <LightboxViewer
      v-if="album"
      v-model="lightboxOpen"
      :images="album.images"
      :image-names="album.imageNames"
      :start-index="currentIndex"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, watch, nextTick } from "vue";
import { useRoute, onBeforeRouteUpdate } from "vue-router";
import type { Album } from "@/types";
import { albums } from "@/albums";
import AlbumGrid from "@/components/AlbumGrid.vue";
import LazyPictureRenderer from "@/components/LazyPictureRenderer.vue";
import LightboxViewer from "@/components/LightboxViewer.vue";
import { parseDateScore } from "@/utils";

const route = useRoute();
const albumId = computed(() => route.params.id as string);

const album = computed<Album | null>(() =>
  albums.value.find((a: Album) => a.id === albumId.value) || null,
);

const subAlbums = computed(() =>
  albums.value
    .filter((a) => {
      if (!a.id.includes("/")) return false;
      const parentId = a.id.substring(0, a.id.lastIndexOf("/"));
      if (parentId !== albumId.value) return false;
      const hasSubAlbums = albums.value.some((s) => s.id.startsWith(a.id + "/") && !s.id.substring(a.id.length + 1).includes("/"));
      return a.images.length > 0 || hasSubAlbums;
    })
    .sort((a, b) => parseDateScore(b.date) - parseDateScore(a.date)),
);

const parentRoute = computed(() => {
  if (albumId.value.includes("/")) {
    const parentId = albumId.value.substring(0, albumId.value.lastIndexOf("/"));
    return { name: "album", params: { id: parentId } };
  }
  return "/";
});

const parentLabel = computed(() => {
  if (albumId.value.includes("/")) {
    const parentId = albumId.value.substring(0, albumId.value.lastIndexOf("/"));
    const parent = albums.value.find((a) => a.id === parentId);
    return `Retour à ${parent ? parent.title : "l'album parent"}`;
  }
  return "Retour aux albums";
});
const lightboxOpen = ref(false);
const currentIndex = ref(0);

function openLightbox(index: number) {
  currentIndex.value = index;
  lightboxOpen.value = true;
}

function thumbSrc(img: string, index: number): string {
  if (!album.value) return img;
  const thumb = album.value.thumbs?.[index];
  return thumb || img;
}

const thumbGenerating = ref(false);
const thumbGenerated = ref(0);
const thumbTotal = ref(0);

async function triggerThumbGeneration() {
  if (!album.value) return;
  // Trouver les images sans miniature
  const missing: { index: number; name: string }[] = [];
  album.value.thumbs.forEach((t, i) => {
    if (!t) missing.push({ index: i, name: album.value!.imageNames[i] });
  });
  if (missing.length === 0) return;

  thumbGenerating.value = true;
  thumbGenerated.value = 0;
  thumbTotal.value = missing.length;

  const albumId = album.value.id;
  for (const item of missing) {
    try {
      const resp = await fetch(
        `/generate_thumbs.php?album=${encodeURIComponent(albumId)}&file=${encodeURIComponent(item.name)}`
      );
      const data = await resp.json();
      if (data.status === 'generated' || data.status === 'skipped') {
        // Mettre à jour le thumb dans l'album pour affichage immédiat
        if (album.value && album.value.id === albumId) {
          const albumUrl = 'photos/' + albumId.split('/').map(encodeURIComponent).join('/');
          album.value.thumbs[item.index] = albumUrl + '/thumbs/' + encodeURIComponent(item.name);
        }
      }
    } catch { /* ignore */ }
    thumbGenerated.value++;
  }

  if (album.value && album.value.id === albumId) {
    album.value.hasThumbs = true;
  }
  thumbGenerating.value = false;
}

function onAlbumChange() {
  thumbGenerating.value = false;
  thumbGenerated.value = 0;
  thumbTotal.value = 0;
  if (album.value && album.value.images.length > 0 && album.value.thumbs.some((t: string | null) => !t)) {
    triggerThumbGeneration();
  }
}

onBeforeRouteUpdate(() => {
  nextTick(() => onAlbumChange());
});

watch(album, (val, oldVal) => {
  if (val && !oldVal) {
    onAlbumChange();
  }
});

onMounted(() => {
  onAlbumChange();
});
</script>

<style scoped>
.back-link {
  display: inline-block;
  margin-bottom: 1rem;
  color: #183a63;
  font-size: 0.95rem;
}

.back-link:hover {
  text-decoration: underline;
  color: #b89a5a;
}

.album-title {
  font-size: 1.8rem;
  font-weight: 300;
  margin-bottom: 1rem;
  color: #183a63;
  letter-spacing: 0.02em;
}

@media (max-width: 480px) {
  .album-title {
    font-size: 1.3rem;
    margin-bottom: 0.5rem;
  }
}


.photos-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 2px;
  padding: 0.5rem 0;
  margin-left: calc(-20px + 4px);
  margin-right: calc(-20px + 4px);
}

.photo-card {
  background: none;
  border: none;
  border-radius: 0;
  overflow: hidden;
  cursor: pointer;
  flex-grow: 1;
  height: 150px;
  min-width: 100px;
  max-width: 300px;
  transition: transform 0.25s, box-shadow 0.25s;
  transform: rotate(var(--rotate, 0deg));
  box-shadow: 2px 3px 12px rgba(24, 58, 99, 0.07);
  contain: layout style paint;
}

.photo-card:nth-child(3n+1) { --rotate: -1deg; }
.photo-card:nth-child(3n+2) { --rotate: 0.8deg; }
.photo-card:nth-child(3n)   { --rotate: -0.5deg; }
.photo-card:nth-child(5n+1) { --rotate: 1.2deg; }
.photo-card:nth-child(7n)   { --rotate: -1.2deg; }

.photo-card:hover {
  transform: rotate(0deg) scale(1.06);
  box-shadow: 0 6px 24px rgba(24, 58, 99, 0.15);
  z-index: 2;
  position: relative;
}

.photo-card img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
}

@media (max-width: 768px) {
  .photos-grid {
    margin-left: calc(-10px + 4px);
    margin-right: calc(-10px + 4px);
  }

  .photo-card {
    height: 110px;
    min-width: 80px;
    max-width: 200px;
  }
}

@media (max-width: 480px) {
  .photo-card {
    height: 120px;
    min-width: calc(33.333% - 2px);
    max-width: calc(50% - 2px);
    flex-grow: 0;
  }
}

.thumb-progress {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  justify-content: center;
  padding: 1rem;
  color: #888;
  font-size: 0.85rem;
}

.thumb-spinner {
  width: 18px;
  height: 18px;
  border: 2px solid #ddd;
  border-top-color: #183a63;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}


/* Sous-albums */
.sub-albums {
  margin-bottom: 2rem;
}

.sub-albums-title {
  font-size: 1.2rem;
  font-weight: 400;
  color: #183a63;
  margin-bottom: 1rem;
}

</style>
