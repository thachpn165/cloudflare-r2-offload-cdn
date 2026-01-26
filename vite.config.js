import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  build: {
    outDir: 'assets',
    emptyOutDir: false,
    sourcemap: true,
    rollupOptions: {
      input: {
        admin: resolve(__dirname, 'assets/src/js/admin.js'),
        public: resolve(__dirname, 'assets/src/js/public.js'),
      },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'css/[name][extname]';
          }
          return 'images/[name][extname]';
        },
      },
    },
  },
  css: {
    devSourcemap: true,
  },
});
