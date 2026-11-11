<template>
  <div v-if="modelValue" class="lightbox" @click.self="close">
    <div class="lb-topbar">
      <span class="lb-counter">{{ currentIndex + 1 }} / {{ images.length }}</span>
      <div class="lb-actions">
        <button class="lb-icon-btn" @click.stop="toggleExif" title="Infos EXIF">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        </button>
        <a class="lb-icon-btn" :href="images[currentIndex]" :download="imageNames[currentIndex]" @click.stop title="Télécharger">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </a>
        <button class="lb-icon-btn" @click="close" title="Fermer">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
    </div>
    <button class="lb-prev" @click="prevPhoto">‹</button>
    <img class="lb-photo" :src="images[currentIndex]" :alt="imageNames[currentIndex]" @click.self="close" @touchstart="onTouchStart" @touchend="onTouchEnd" style="view-transition-name: lb-photo" />
    <button class="lb-next" @click="nextPhoto">›</button>
    <transition name="slide-right">
      <div v-if="showExif" class="exif-panel">
        <h4 class="exif-title">Informations</h4>
        <p class="exif-filename">{{ imageNames[currentIndex] }}</p>
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
</template>

<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, shallowRef, nextTick } from "vue";

const props = defineProps<{
  modelValue: boolean;
  images: string[];
  imageNames: string[];
  startIndex?: number;
}>();

const emit = defineEmits<{
  (e: "update:modelValue", value: boolean): void;
}>();

const currentIndex = ref(0);
const showExif = ref(false);
const exifData = shallowRef<Record<string, string> | null>(null);
const exifLoading = ref(false);

watch(() => props.startIndex, (val) => {
  if (val !== undefined) currentIndex.value = val;
});

watch(() => props.modelValue, (val) => {
  if (val && props.startIndex !== undefined) {
    currentIndex.value = props.startIndex;
    showExif.value = false;
    exifData.value = null;
  }
});

function close() {
  emit("update:modelValue", false);
  showExif.value = false;
  exifData.value = null;
}

function navigate(newIndex: number, dir: 'next' | 'prev') {
  document.documentElement.dataset.lbDir = dir;
  if ('startViewTransition' in document) {
    (document as any).startViewTransition(async () => {
      currentIndex.value = newIndex;
      await nextTick();
    });
  } else {
    currentIndex.value = newIndex;
  }
}

function nextPhoto() {
  navigate((currentIndex.value + 1) % props.images.length, 'next');
}

function prevPhoto() {
  navigate((currentIndex.value - 1 + props.images.length) % props.images.length, 'prev');
}

let touchStartX = 0;
let touchStartY = 0;

function onTouchStart(e: TouchEvent) {
  touchStartX = e.changedTouches[0].clientX;
  touchStartY = e.changedTouches[0].clientY;
}

function onTouchEnd(e: TouchEvent) {
  const dx = e.changedTouches[0].clientX - touchStartX;
  const dy = e.changedTouches[0].clientY - touchStartY;
  if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 50) {
    if (dx < 0) nextPhoto();
    else prevPhoto();
  }
}

function onKeydown(e: KeyboardEvent) {
  if (!props.modelValue) return;
  if (e.key === "Escape") close();
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
  exifLoading.value = true;
  exifData.value = null;
  try {
    const url = props.images[currentIndex.value];
    const resp = await fetch(url, { headers: { Range: "bytes=0-65535" } });
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
  if (view.getUint16(0) !== 0xFFD8) return null;

  let offset = 2;
  while (offset < view.byteLength - 4) {
    const marker = view.getUint16(offset);
    if (marker === 0xFFE1) {
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
  if (view.getUint32(0) !== 0x45786966 || view.getUint16(4) !== 0x0000) return null;

  const tiffStart = start + 6;
  const tiffView = new DataView(buffer, tiffStart);
  const littleEndian = tiffView.getUint16(0) === 0x4949;

  const ifdOffset = tiffView.getUint32(4, littleEndian);
  const result: Record<string, string> = {};

  readIFD(buffer, tiffStart, ifdOffset, littleEndian, result);

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
      if (type === 2) {
        const bytes = new Uint8Array(buffer, tiffStart + (numValues > 4 ? valueOffset : entryOff + 8), numValues - 1);
        val = new TextDecoder().decode(bytes);
      } else if (type === 3) {
        val = String(view.getUint16(entryOff + 8, le));
      } else if (type === 4) {
        val = String(valueOffset);
      } else if (type === 5) {
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

onMounted(() => {
  window.addEventListener("keydown", onKeydown);
});

onUnmounted(() => {
  window.removeEventListener("keydown", onKeydown);
});
</script>

<style scoped>
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

<style>
@keyframes lb-slide-out-left {
  to { transform: translateX(-25%); opacity: 0; }
}
@keyframes lb-slide-in-right {
  from { transform: translateX(25%); opacity: 0; }
}
@keyframes lb-slide-out-right {
  to { transform: translateX(25%); opacity: 0; }
}
@keyframes lb-slide-in-left {
  from { transform: translateX(-25%); opacity: 0; }
}

::view-transition-old(lb-photo),
::view-transition-new(lb-photo) {
  animation-duration: 0.25s;
  animation-timing-function: ease;
}

:root[data-lb-dir="next"] ::view-transition-old(lb-photo) {
  animation-name: lb-slide-out-left;
}
:root[data-lb-dir="next"] ::view-transition-new(lb-photo) {
  animation-name: lb-slide-in-right;
}
:root[data-lb-dir="prev"] ::view-transition-old(lb-photo) {
  animation-name: lb-slide-out-right;
}
:root[data-lb-dir="prev"] ::view-transition-new(lb-photo) {
  animation-name: lb-slide-in-left;
}
</style>
