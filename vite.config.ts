import { defineConfig, loadEnv } from "vite";
import vue from "@vitejs/plugin-vue";
import { resolve } from "path";
import { readdirSync, readFileSync, statSync, existsSync } from "fs";

const IMAGE_EXTENSIONS = /\.(jpe?g|png|gif|webp|bmp|svg)$/i;

function scanAlbums(photosRoot: string, baseUrl: string, prefix = ""): any[] {
  if (!existsSync(photosRoot)) return [];

  const entries = readdirSync(photosRoot).filter((name) => {
    const full = resolve(photosRoot, name);
    return statSync(full).isDirectory();
  });

  const albums: any[] = [];

  for (const dir of entries) {
    const dirPath = resolve(photosRoot, dir);
    const albumId = prefix ? `${prefix}/${dir}` : dir;
    const albumUrl = `${baseUrl}/${encodeURIComponent(dir)}`;

    const infoPath = resolve(dirPath, "info.md");
    let info = "";
    if (existsSync(infoPath)) {
      info = readFileSync(infoPath, "utf-8");
    }

    const images: string[] = [];
    const imageNames: string[] = [];
    for (const file of readdirSync(dirPath)) {
      if (IMAGE_EXTENSIONS.test(file) && statSync(resolve(dirPath, file)).isFile()) {
        images.push(`${albumUrl}/${encodeURIComponent(file)}`);
        imageNames.push(file);
      }
    }

    const titleMatch = info.match(/^#\s+(.+)$/m);
    const title = titleMatch ? titleMatch[1] : dir;

    const thumbMatch = info.match(/^-\s*\*\*thumbnail\*\*\s*:\s*(.+)$/im);
    let thumbnail: string | null = null;
    if (thumbMatch) {
      const thumbFile = thumbMatch[1].trim();
      const idx = imageNames.indexOf(thumbFile);
      thumbnail = idx >= 0 ? images[idx] : (images[0] || null);
    } else {
      thumbnail = images.length > 0 ? images[0] : null;
    }

    albums.push({
      id: albumId,
      title,
      info,
      thumbnail,
      images,
      imageNames,
    });

    // Sous-dossiers récursifs
    const subAlbums = scanAlbums(dirPath, albumUrl, albumId);
    albums.push(...subAlbums);
  }

  return albums;
}

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), "");
  const photosPath = env.VITE_PHOTOS_PATH || "./photos";
  const photosRoot = resolve(process.cwd(), photosPath);

  return {
    plugins: [
      vue(),
      {
        name: "dev-api",
        configureServer(server) {
          // Serve api.php equivalent in dev
          server.middlewares.use("/api.php", (_req, res) => {
            const albums = scanAlbums(photosRoot, "photos");
            res.setHeader("Content-Type", "application/json; charset=utf-8");
            res.end(JSON.stringify(albums));
          });

          // Serve photo files
          server.middlewares.use("/photos", (req, res, next) => {
            const urlPath = decodeURIComponent(req.url || "").replace(/^\//, "");
            const filePath = resolve(photosRoot, urlPath);
            if (existsSync(filePath) && statSync(filePath).isFile()) {
              const ext = filePath.split(".").pop()?.toLowerCase() || "";
              const mimeTypes: Record<string, string> = {
                jpg: "image/jpeg", jpeg: "image/jpeg", png: "image/png",
                gif: "image/gif", webp: "image/webp", bmp: "image/bmp",
                svg: "image/svg+xml",
              };
              res.setHeader("Content-Type", mimeTypes[ext] || "application/octet-stream");
              res.end(readFileSync(filePath));
            } else {
              next();
            }
          });
        },
      },
    ],
    base: "./",
    resolve: {
      alias: {
        "@": resolve(__dirname, "src"),
      },
    },
    build: {
      outDir: "dist",
    },
  };
});
