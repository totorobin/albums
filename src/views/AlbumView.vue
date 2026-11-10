<template>
  <div class="album-view">
    <router-link :to="parentRoute" class="back-link">← {{ parentLabel }}</router-link>

    <p v-if="!album" class="empty">Album introuvable.</p>

    <template v-else>
      <h1 class="album-title">{{ album.title }}</h1>


      <!-- Sous-albums -->
      <div v-if="subAlbums.length > 0" class="sub-albums">
        <h2 class="sub-albums-title">Sous-albums</h2>
        <div class="albums-grid">
          <router-link
            v-for="sub in subAlbums"
            :key="sub.id"
            :to="{ name: 'album', params: { id: sub.id } }"
            class="album-card"
          >
            <div class="album-thumb">
              <img v-if="sub.thumbnail" :src="sub.thumbnail" :alt="sub.title" />
              <div v-else class="no-thumb">📁</div>
            </div>
            <div class="album-card-info">
              <h3>{{ sub.title }}</h3>
              <div class="album-meta">
                <span v-if="sub.date" class="album-meta-item">📅 {{ sub.date }}</span>
                <span v-if="sub.author" class="album-meta-item">👤 {{ sub.author }}</span>
                <span v-if="sub.lieu" class="album-meta-item">📍 {{ sub.lieu }}</span>
              </div>
              <span class="album-count">{{ sub.images.length }} photo(s)</span>
            </div>
          </router-link>
        </div>
      </div>

      <div class="photos-grid">
        <div
          v-for="(img, index) in visibleImages"
          :key="img"
          class="photo-card"
          @click="openLightbox(index)"
        >
          <img :src="thumbSrc(img, index)" :alt="album.imageNames[index]" loading="lazy" decoding="async" />
        </div>
      </div>
      <div ref="sentinelRef" class="scroll-sentinel"></div>
    </template>

    <!-- Lightbox -->
    <div v-if="lightboxOpen && album" class="lightbox" @click.self="closeLightbox">
      <div class="lb-topbar">
        <span class="lb-counter">{{ currentIndex + 1 }} / {{ album.images.length }}</span>
        <div class="lb-actions">
          <button class="lb-icon-btn" @click.stop="toggleExif" title="Infos EXIF">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          </button>
          <a class="lb-icon-btn" :href="album.images[currentIndex]" :download="album.imageNames[currentIndex]" @click.stop title="Télécharger">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          </a>
          <button class="lb-icon-btn" @click="closeLightbox" title="Fermer">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
      </div>
      <button class="lb-prev" @click="prevPhoto">‹</button>
      <img class="lb-photo" :src="album.images[currentIndex]" :alt="album.imageNames[currentIndex]" @click.self="closeLightbox" />
      <button class="lb-next" @click="nextPhoto">›</button>
      <transition name="slide-right">
        <div v-if="showExif" class="exif-panel">
          <h4 class="exif-title">Informations</h4>
          <p class="exif-filename">{{ album.imageNames[currentIndex] }}</p>
          <p v-if="exifLoading" class="exif-loading">Chargement…</p>
          <p v-else-if="!exifData" class="exif-loading">Aucune donnée EXIF disponible.</p>
          <table v-else class="exif-table">
            <tr v-for="(value, key) in exifData" :key="key">
              <td class="exif-key">{{ key }}</td>
              <td class="exif-value">{{ value }}</td>
            </tr>
          </table>
        </div>
      </transition>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch, nextTick, shallowRef } from "vue";
import type { Album } from "@/types";
import { albums } from "@/albums";

const props = defineProps<{ id: string }>();

const album = computed<Album | null>(() =>
  albums.value.find((a: Album) => a.id === props.id) || null,
);

const subAlbums = computed(() =>
  albums.value.filter((a) => {
    if (!a.id.includes("/")) return false;
    const parentId = a.id.substring(0, a.id.lastIndexOf("/"));
    if (parentId !== props.id) return false;
    // Masquer les sous-albums vides (sans photos et sans sous-albums)
    const hasSubAlbums = albums.value.some((s) => s.id.startsWith(a.id + "/") && !s.id.substring(a.id.length + 1).includes("/"));
    return a.images.length > 0 || hasSubAlbums;
  }),
);

const parentRoute = computed(() => {
  if (props.id.includes("/")) {
    const parentId = props.id.substring(0, props.id.lastIndexOf("/"));
    return { name: "album", params: { id: parentId } };
  }
  return "/";
});

const parentLabel = computed(() => {
  if (props.id.includes("/")) {
    const parentId = props.id.substring(0, props.id.lastIndexOf("/"));
    const parent = albums.value.find((a) => a.id === parentId);
    return `Retour à ${parent ? parent.title : "l'album parent"}`;
  }
  return "Retour aux albums";
});
const lightboxOpen = ref(false);
const currentIndex = ref(0);
const showExif = ref(false);
const exifData = shallowRef<Record<string, string> | null>(null);
const exifLoading = ref(false);

function openLightbox(index: number) {
  currentIndex.value = index;
  lightboxOpen.value = true;
  showExif.value = false;
  exifData.value = null;
}

function closeLightbox() {
  lightboxOpen.value = false;
  showExif.value = false;
  exifData.value = null;
}

function nextPhoto() {
  if (!album.value) return;
  currentIndex.value = (currentIndex.value + 1) % album.value.images.length;
}

function prevPhoto() {
  if (!album.value) return;
  currentIndex.value =
    (currentIndex.value - 1 + album.value.images.length) % album.value.images.length;
}

function onKeydown(e: KeyboardEvent) {
  if (!lightboxOpen.value) return;
  if (e.key === "Escape") closeLightbox();
  if (e.key === "ArrowRight") nextPhoto();
  if (e.key === "ArrowLeft") prevPhoto();
}

function toggleExif() {
  showExif.value = !showExif.value;
  if (showExif.value && !exifData.value) {
    loadExif();
  }
}

async function loadExif() {
  if (!album.value) return;
  exifLoading.value = true;
  exifData.value = null;
  try {
    const url = album.value.images[currentIndex.value];
    const resp = await fetch(url, { headers: { Range: 'bytes=0-65535' } });
    const buf = await resp.arrayBuffer();
    const parsed = parseExif(buf);
    exifData.value = parsed && Object.keys(parsed).length > 0 ? parsed : null;
  } catch {
    exifData.value = null;
  }
  exifLoading.value = false;
}

function parseExif(buffer: ArrayBuffer): Record<string, string> | null {
  const view = new DataView(buffer);
  if (view.byteLength < 4) return null;
  // Check JPEG SOI
  if (view.getUint16(0) !== 0xFFD8) return null;

  let offset = 2;
  while (offset < view.byteLength - 4) {
    const marker = view.getUint16(offset);
    if (marker === 0xFFE1) {
      // APP1 - EXIF
      const length = view.getUint16(offset + 2);
      return parseExifBlock(buffer, offset + 4, length - 2);
    }
    if ((marker & 0xFF00) !== 0xFF00) break;
    const segLen = view.getUint16(offset + 2);
    offset += 2 + segLen;
  }
  return null;
}

function parseExifBlock(buffer: ArrayBuffer, start: number, length: number): Record<string, string> | null {
  const view = new DataView(buffer, start, length);
  // "Exif\0\0"
  if (view.getUint32(0) !== 0x45786966 || view.getUint16(4) !== 0x0000) return null;

  const tiffStart = start + 6;
  const tiffView = new DataView(buffer, tiffStart);
  const littleEndian = tiffView.getUint16(0) === 0x4949;

  const ifdOffset = tiffView.getUint32(4, littleEndian);
  const result: Record<string, string> = {};

  readIFD(buffer, tiffStart, ifdOffset, littleEndian, result);

  // Check for SubIFD (EXIF IFD)
  const subIfdTag = findTagValue(buffer, tiffStart, ifdOffset, littleEndian, 0x8769);
  if (subIfdTag !== null) {
    readIFD(buffer, tiffStart, subIfdTag, littleEndian, result);
  }

  return result;
}

const EXIF_TAGS: Record<number, string> = {
  0x010F: "Fabricant", 0x0110: "Modèle", 0x0112: "Orientation",
  0x829A: "Temps d'exposition", 0x829D: "Ouverture (f/)",
  0x8827: "ISO", 0x9003: "Date de prise", 0x9004: "Date numérisée",
  0x920A: "Focale (mm)", 0xA405: "Focale éq. 35mm",
  0xA001: "Espace colorimétrique", 0xA002: "Largeur", 0xA003: "Hauteur",
  0x0131: "Logiciel", 0x013B: "Artiste", 0x8298: "Copyright",
};

function findTagValue(buffer: ArrayBuffer, tiffStart: number, ifdOffset: number, le: boolean, targetTag: number): number | null {
  const view = new DataView(buffer, tiffStart);
  const count = view.getUint16(ifdOffset, le);
  for (let i = 0; i < count; i++) {
    const entryOff = ifdOffset + 2 + i * 12;
    const tag = view.getUint16(entryOff, le);
    if (tag === targetTag) {
      return view.getUint32(entryOff + 8, le);
    }
  }
  return null;
}

function readIFD(buffer: ArrayBuffer, tiffStart: number, ifdOffset: number, le: boolean, result: Record<string, string>) {
  const view = new DataView(buffer, tiffStart);
  if (ifdOffset + 2 > view.byteLength) return;
  const count = view.getUint16(ifdOffset, le);

  for (let i = 0; i < count; i++) {
    const entryOff = ifdOffset + 2 + i * 12;
    if (entryOff + 12 > view.byteLength) break;
    const tag = view.getUint16(entryOff, le);
    const type = view.getUint16(entryOff + 2, le);
    const numValues = view.getUint32(entryOff + 4, le);
    const valueOffset = view.getUint32(entryOff + 8, le);

    const label = EXIF_TAGS[tag];
    if (!label) continue;

    let val = "";
    try {
      if (type === 2) { // ASCII
        const strOff = numValues > 4 ? valueOffset : (entryOff + 8 - ifdOffset);
        const bytes = new Uint8Array(buffer, tiffStart + (numValues > 4 ? valueOffset : entryOff + 8), numValues - 1);
        val = new TextDecoder().decode(bytes);
      } else if (type === 3) { // SHORT
        val = String(view.getUint16(entryOff + 8, le));
      } else if (type === 4) { // LONG
        val = String(valueOffset);
      } else if (type === 5) { // RATIONAL
        const fullView = new DataView(buffer);
        const rNum = fullView.getUint32(tiffStart + valueOffset, le);
        const rDen = fullView.getUint32(tiffStart + valueOffset + 4, le);
        if (rDen === 1) val = String(rNum);
        else if (rDen === 0) val = "0";
        else if (tag === 0x829A) val = rDen > rNum ? `1/${Math.round(rDen / rNum)}s` : `${(rNum / rDen).toFixed(1)}s`;
        else if (tag === 0x829D) val = `f/${(rNum / rDen).toFixed(1)}`;
        else if (tag === 0x920A) val = `${(rNum / rDen).toFixed(0)} mm`;
        else val = `${rNum}/${rDen}`;
      }
    } catch { val = ""; }

    if (val) result[label] = val;
  }
}

watch(currentIndex, () => {
  if (showExif.value) {
    exifData.value = null;
    loadExif();
  }
});

function thumbSrc(img: string, index: number): string {
  if (!album.value) return img;
  const thumb = album.value.thumbs?.[index];
  return thumb || img;
}

function triggerThumbGeneration() {
  if (!album.value || album.value.hasThumbs) return;
  if (album.value.images.length === 0) return;
  fetch(`generate_thumbs.php?album=${encodeURIComponent(album.value.id)}`)
    .catch(() => {});
}

const BATCH_SIZE = 20;
const visibleCount = ref(BATCH_SIZE);
const sentinelRef = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

const visibleImages = computed(() => {
  if (!album.value) return [];
  return album.value.images.slice(0, visibleCount.value);
});

function setupObserver() {
  if (observer) observer.disconnect();
  if (!sentinelRef.value) return;
  observer = new IntersectionObserver(
    (entries) => {
      if (entries[0].isIntersecting && album.value && visibleCount.value < album.value.images.length) {
        visibleCount.value = Math.min(visibleCount.value + BATCH_SIZE, album.value.images.length);
      }
    },
    { rootMargin: "400px" }
  );
  observer.observe(sentinelRef.value);
}

watch(() => props.id, () => {
  visibleCount.value = BATCH_SIZE;
  nextTick(() => setupObserver());
});

watch(album, (val) => {
  if (val) {
    nextTick(() => setupObserver());
    triggerThumbGeneration();
  }
});

onMounted(() => {
  window.addEventListener("keydown", onKeydown);
  nextTick(() => setupObserver());
});
onUnmounted(() => {
  window.removeEventListener("keydown", onKeydown);
  if (observer) observer.disconnect();
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

.scroll-sentinel {
  height: 1px;
  width: 100%;
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

.albums-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 1.2rem;
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

  .album-card-info h3 {
    font-size: 0.85rem;
  }
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

/* Lightbox */
.lightbox {
  position: fixed;
  inset: 0;
  background: #000;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.lb-photo {
  max-width: 100vw;
  max-height: 100vh;
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.lb-topbar {
  position: fixed;
  top: 0;
  right: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  z-index: 1002;
}

.lb-counter {
  color: rgba(255,255,255,0.6);
  font-size: 0.85rem;
  margin-right: 0.5rem;
}

.lb-actions {
  display: flex;
  gap: 0.25rem;
}

.lb-icon-btn {
  background: none;
  border: none;
  color: #fff;
  cursor: pointer;
  padding: 0.4rem;
  opacity: 0.8;
  transition: opacity 0.2s;
  text-decoration: none;
  display: flex;
  align-items: center;
}

.lb-icon-btn:hover {
  opacity: 1;
}

.exif-panel {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: 320px;
  max-width: 80vw;
  background: rgba(0,0,0,0.7);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  padding: 3.5rem 1.25rem 1.25rem;
  overflow-y: auto;
  z-index: 1001;
}

.exif-title {
  color: #fff;
  font-size: 1rem;
  font-weight: 500;
  margin: 0 0 0.25rem;
}

.exif-filename {
  color: rgba(255,255,255,0.5);
  font-size: 0.8rem;
  margin: 0 0 1rem;
  word-break: break-all;
}

.exif-loading {
  color: rgba(255,255,255,0.6);
  font-size: 0.85rem;
  margin: 0;
}

.exif-table {
  width: 100%;
  border-collapse: collapse;
}

.exif-table tr {
  border-bottom: 1px solid rgba(255,255,255,0.1);
}

.exif-key {
  color: rgba(255,255,255,0.6);
  font-size: 0.8rem;
  padding: 0.3rem 0.5rem 0.3rem 0;
  white-space: nowrap;
  font-weight: 500;
}

.exif-value {
  color: #fff;
  font-size: 0.8rem;
  padding: 0.3rem 0;
}

/* Slide-right transition */
.slide-right-enter-active,
.slide-right-leave-active {
  transition: transform 0.3s ease, opacity 0.3s ease;
}
.slide-right-enter-from,
.slide-right-leave-to {
  transform: translateX(100%);
  opacity: 0;
}

.lb-prev,
.lb-next {
  position: fixed;
  background: none;
  border: none;
  color: #fff;
  font-size: 3rem;
  cursor: pointer;
  padding: 1rem;
  z-index: 1001;
  opacity: 0.5;
  transition: opacity 0.2s;
  top: 50%;
  transform: translateY(-50%);
}

.lb-prev { left: 0.5rem; }
.lb-next { right: 0.5rem; }

.lb-prev:hover,
.lb-next:hover {
  opacity: 1;
}

@media (max-width: 768px) {
  .lb-prev,
  .lb-next {
    font-size: 2rem;
    padding: 0.5rem;
  }

  .lb-topbar {
    padding: 0.5rem;
  }

  .lb-counter {
    font-size: 0.75rem;
  }

  .exif-panel {
    width: 100%;
    max-width: 100vw;
  }
}
</style>
