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
              <span class="album-count">{{ sub.images.length }} photo(s)</span>
            </div>
          </router-link>
        </div>
      </div>

      <div class="photos-grid">
        <div
          v-for="(img, index) in album.images"
          :key="img"
          class="photo-card"
          @click="openLightbox(index)"
        >
          <img :src="img" :alt="album.imageNames[index]" loading="lazy" />
          <span class="photo-name">{{ album.imageNames[index] }}</span>
        </div>
      </div>
    </template>

    <!-- Lightbox -->
    <div v-if="lightboxOpen && album" class="lightbox" @click.self="closeLightbox">
      <button class="lb-close" @click="closeLightbox">✕</button>
      <button class="lb-prev" @click="prevPhoto">‹</button>
      <div class="lb-content">
        <img :src="album.images[currentIndex]" :alt="album.imageNames[currentIndex]" />
        <div class="lb-toolbar">
          <p class="lb-caption">
            {{ album.imageNames[currentIndex] }}
            <span class="lb-counter">{{ currentIndex + 1 }} / {{ album.images.length }}</span>
          </p>
          <div class="lb-actions">
            <button class="lb-action-btn" @click.stop="toggleExif" title="Infos EXIF">ℹ️</button>
            <a class="lb-action-btn" :href="album.images[currentIndex]" :download="album.imageNames[currentIndex]" @click.stop title="Télécharger">⬇️</a>
          </div>
        </div>
        <div v-if="showExif" class="exif-panel">
          <p v-if="exifLoading" class="exif-loading">Chargement des données EXIF…</p>
          <p v-else-if="!exifData" class="exif-loading">Aucune donnée EXIF disponible.</p>
          <table v-else class="exif-table">
            <tr v-for="(value, key) in exifData" :key="key">
              <td class="exif-key">{{ key }}</td>
              <td class="exif-value">{{ value }}</td>
            </tr>
          </table>
        </div>
      </div>
      <button class="lb-next" @click="nextPhoto">›</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from "vue";
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
    return parentId === props.id;
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
const exifData = ref<Record<string, string> | null>(null);
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
    const resp = await fetch(url);
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
        const num = view.getUint32(tiffStart - tiffStart + valueOffset, le);
        const den = view.getUint32(tiffStart - tiffStart + valueOffset + 4, le);
        // Use DataView on full buffer for rational
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

onMounted(() => window.addEventListener("keydown", onKeydown));
onUnmounted(() => window.removeEventListener("keydown", onKeydown));
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


.photos-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1rem;
}

.photo-card {
  background: #fff;
  border: 1px solid #e8e0d0;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}

.photo-card:hover {
  transform: scale(1.03);
  box-shadow: 0 4px 16px rgba(24, 58, 99, 0.1);
}

.photo-card img {
  width: 100%;
  height: 180px;
  object-fit: cover;
  display: block;
}

.photo-name {
  display: block;
  padding: 0.5rem;
  font-size: 0.8rem;
  color: #888;
  text-align: center;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
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

.album-count {
  font-size: 0.8rem;
  color: #888;
}

/* Lightbox */
.lightbox {
  position: fixed;
  inset: 0;
  background: rgba(24, 58, 99, 0.95);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.lb-content {
  max-width: 90vw;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.lb-content img {
  max-width: 90vw;
  max-height: 80vh;
  object-fit: contain;
  border-radius: 4px;
}

.lb-toolbar {
  margin-top: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.lb-caption {
  color: #d4bfa0;
  font-size: 0.9rem;
  text-align: center;
  margin: 0;
}

.lb-actions {
  display: flex;
  gap: 0.5rem;
}

.lb-action-btn {
  background: rgba(255,255,255,0.15);
  border: 1px solid rgba(255,255,255,0.3);
  color: #fff;
  border-radius: 6px;
  padding: 0.3rem 0.6rem;
  cursor: pointer;
  font-size: 1rem;
  text-decoration: none;
  transition: background 0.2s;
}

.lb-action-btn:hover {
  background: rgba(184,154,90,0.4);
}

.exif-panel {
  margin-top: 0.75rem;
  background: rgba(0,0,0,0.4);
  border-radius: 8px;
  padding: 0.75rem 1rem;
  max-height: 40vh;
  overflow-y: auto;
  max-width: 500px;
}

.exif-loading {
  color: #d4bfa0;
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
  color: #d4bfa0;
  font-size: 0.8rem;
  padding: 0.25rem 0.5rem 0.25rem 0;
  white-space: nowrap;
  font-weight: 500;
}

.exif-value {
  color: #fff;
  font-size: 0.8rem;
  padding: 0.25rem 0;
}

.lb-counter {
  margin-left: 1rem;
  color: rgba(255, 255, 255, 0.6);
}

.lb-close,
.lb-prev,
.lb-next {
  position: fixed;
  background: none;
  border: none;
  color: #fff;
  font-size: 2rem;
  cursor: pointer;
  padding: 1rem;
  z-index: 1001;
  opacity: 0.7;
  transition: opacity 0.2s;
}

.lb-close:hover,
.lb-prev:hover,
.lb-next:hover {
  opacity: 1;
  color: #d4bfa0;
}

.lb-close {
  top: 1rem;
  right: 1rem;
}

.lb-prev {
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  font-size: 3rem;
}

.lb-next {
  right: 1rem;
  top: 50%;
  transform: translateY(-50%);
  font-size: 3rem;
}
</style>
