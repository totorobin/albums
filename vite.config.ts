import { defineConfig, loadEnv } from "vite";
import vue from "@vitejs/plugin-vue";
import { resolve } from "path";
import { readdirSync, readFileSync, statSync, existsSync } from "fs";

const IMAGE_EXTENSIONS = /\.(jpe?g|png|gif|webp|bmp|svg)$/i;

function scanAlbums(photosRoot: string, baseUrl: string, prefix = ""): any[] {
  if (!existsSync(photosRoot)) return [];

  const entries = readdirSync(photosRoot).filter((name) => {
    const full = resolve(photosRoot, name);
    return statSync(full).isDirectory() && name !== "thumbs";
  });

  const albums: any[] = [];

  for (const dir of entries) {
    const dirPath = resolve(photosRoot, dir);
    const albumId = prefix ? `${prefix}/${dir}` : dir;
    const albumUrl = `${baseUrl}/${encodeURIComponent(dir)}`;

    // Priorité : _info.md (nouveau nom), fallback info.md (rétrocompatibilité)
    const infoPath = existsSync(resolve(dirPath, "_info.md"))
      ? resolve(dirPath, "_info.md")
      : resolve(dirPath, "info.md");
    let info = "";
    if (existsSync(infoPath)) {
      info = readFileSync(infoPath, "utf-8");
    }

    const images: string[] = [];
    const imageNames: string[] = [];
    const thumbs: (string | null)[] = [];
    const thumbsDir = resolve(dirPath, "thumbs");
    const hasThumbs = existsSync(thumbsDir) && statSync(thumbsDir).isDirectory();

    for (const file of readdirSync(dirPath)) {
      if (IMAGE_EXTENSIONS.test(file) && statSync(resolve(dirPath, file)).isFile()) {
        images.push(`${albumUrl}/${encodeURIComponent(file)}`);
        imageNames.push(file);
        if (hasThumbs && existsSync(resolve(thumbsDir, file))) {
          thumbs.push(`${albumUrl}/thumbs/${encodeURIComponent(file)}`);
        } else {
          thumbs.push(null);
        }
      }
    }

    const titleMatch = info.match(/^#\s+(.+)$/m);
    const title = titleMatch ? titleMatch[1] : dir;

    // Extraire date, auteur, lieu depuis info.md
    const dateMatch = info.match(/^-\s*\*\*Date\*\*\s*:\s*(.+)$/im);
    const authorMatch = info.match(/^-\s*\*\*Auteur\*\*\s*:\s*(.+)$/im);
    const lieuMatch = info.match(/^-\s*\*\*Lieu\*\*\s*:\s*(.+)$/im);
    const date = dateMatch ? dateMatch[1].trim() : null;
    const author = authorMatch ? authorMatch[1].trim() : null;
    const lieu = lieuMatch ? lieuMatch[1].trim() : null;

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
      thumbs,
      hasThumbs,
      date,
      author,
      lieu,
    });

    // Sous-dossiers récursifs
    const subAlbums = scanAlbums(dirPath, albumUrl, albumId);
    albums.push(...subAlbums);
  }

  return albums;
}

const useDockerPhp = process.env.USE_DOCKER_PHP === "true";
const dockerPhpUrl = process.env.DOCKER_PHP_URL || "http://localhost:8080";

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), "");
  const photosPath = env.VITE_PHOTOS_PATH || "./photos";
  const photosRoot = resolve(process.cwd(), photosPath);

  const proxyConfig = useDockerPhp
    ? {
        "/api.php": { target: dockerPhpUrl, changeOrigin: true },
        "/generate_thumbs.php": { target: dockerPhpUrl, changeOrigin: true },
        "/photos": { target: dockerPhpUrl, changeOrigin: true },
      }
    : undefined;

  const devApiPlugin = !useDockerPhp
    ? {
        name: "dev-api",
        configureServer(server: any) {
          server.middlewares.use("/api.php", (_req: any, res: any) => {
            const albums = scanAlbums(photosRoot, "photos");
            res.setHeader("Content-Type", "application/json; charset=utf-8");
            res.end(JSON.stringify(albums));
          });

          server.middlewares.use("/generate_thumbs.php", (req: any, res: any) => {
            const url = new URL(req.url || "", "http://localhost");
            const file = url.searchParams.get("file") || "";
            res.setHeader("Content-Type", "application/json; charset=utf-8");
            res.end(JSON.stringify({ status: "skipped", file }));
          });

          server.middlewares.use("/photos", (req: any, res: any, next: any) => {
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
      }
    : null;

  return {
    plugins: [vue(), ...(devApiPlugin ? [devApiPlugin] : [])],
    base: "./",
    server: {
      proxy: proxyConfig,
    },
    resolve: {
      alias: {
        "@": resolve(__dirname, "src"),
        "@brand-css": resolve(__dirname, `src/brands/${env.VITE_BRAND || "maud"}.css`),
      },
    },
    build: {
      outDir: "dist",
    },
  };
});
