import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  build: {
    manifest: true,
    outDir: 'assets',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        styles: resolve(__dirname, 'src/scss/main.scss')
      },
      output: {
        assetFileNames: (chunk) => {
          if (chunk.name && chunk.name.endsWith('.css')) return 'style.css';
          if (chunk.name === 'styles.css') return 'style.css';
          return '[name][extname]';
        }
      }
    }
  }
});
