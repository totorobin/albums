<template>
  <div class="lazy-picture" ref="containerRef">
    <img
      v-if="visible"
      :src="src"
      :alt="alt"
      decoding="async"
      @error="onError"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from "vue";

const props = defineProps<{
  src: string;
  alt?: string;
  fallbackSrc?: string;
}>();

const containerRef = ref<HTMLElement | null>(null);
const visible = ref(false);
let observer: IntersectionObserver | null = null;

function onError(e: Event) {
  if (props.fallbackSrc) {
    (e.target as HTMLImageElement).src = props.fallbackSrc;
  }
}

onMounted(() => {
  if (!containerRef.value) return;
  observer = new IntersectionObserver(
    (entries) => {
      if (entries[0].isIntersecting) {
        visible.value = true;
        observer?.disconnect();
        observer = null;
      }
    },
    { rootMargin: "400px" }
  );
  observer.observe(containerRef.value);
});

onUnmounted(() => {
  observer?.disconnect();
});
</script>

<style scoped>
.lazy-picture {
  width: 100%;
  height: 100%;
}

.lazy-picture img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
</style>
