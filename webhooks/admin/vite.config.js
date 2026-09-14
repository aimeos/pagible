import { fileURLToPath, URL } from "node:url";
import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";

export default defineConfig({
  plugins: [vue()],
  build: {
    emptyOutDir: true,
    lib: {
      entry: fileURLToPath(new URL("./src/index.js", import.meta.url)),
      formats: ["es"],
      fileName: () => "WebhookList.js",
    },
    rollupOptions: {
      external: ["graphql-tag", "vue"],
    },
  },
});
