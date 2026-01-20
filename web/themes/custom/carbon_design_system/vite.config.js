import { defineConfig } from 'vite';
import * as path from 'path';
import { fileURLToPath } from 'url';
import { copyFileSync, mkdirSync } from 'fs';

// Helper to define __dirname equivalent in ES module context
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Path is relative to the current working directory for components
const componentsBaseDir = './components';

/**
 * @param {{ mode: string }} env
 * Vite configuration dynamically selects between:
 * 1. Building a single component (if mode is a component name, e.g., 'ui_shell').
 * 2. Building JavaScript files from src/js (if mode is 'js').
 * 3. Building static theme assets (if mode is 'production' or 'development').
 */
export default defineConfig(({ mode }) => {
  // Check if the mode is a specific component name passed by a build script loop.
  const isComponentBuild = !['development', 'production', 'js'].includes(mode) && !!mode;
  const componentName = mode;

  // --- 1. CONFIGURATION FOR BUILDING INDIVIDUAL COMPONENTS (IIFE) ---
  if (isComponentBuild) {
    const entryFile = `./${componentsBaseDir}/${componentName}/src/${componentName}.js`;
    const outputFileName = `${componentName}/${componentName}.js`;

    return {
      resolve: {
        alias: {
          // Add path resolution for deep imports from @carbon/icons
          '@carbon/icons/es': path.resolve(__dirname, 'node_modules', '@carbon', 'icons', 'es'),
        },
      },

      build: {
        outDir: componentsBaseDir,
        emptyOutDir: false, // Don't delete the whole components directory!

        // Use lib mode for single-file IIFE output
        lib: {
          entry: entryFile,
          // Create a safe, unique global name for the IIFE wrapper
          name: componentName.replace(/[^a-zA-Z0-9]/g, '_'),
          formats: ['iife'],
          fileName: () => outputFileName,
        },

        rollupOptions: {
          output: {
            manualChunks: false,
            preserveModules: false,
          },
        },
      },
    };
  }

  // --- 2. CONFIGURATION FOR BUILDING src/js FILES ---
  if (mode === 'js') {
    return {
      resolve: {
        alias: {
          '@carbon/icons/es': path.resolve(__dirname, 'node_modules', '@carbon', 'icons', 'es'),
        },
      },

      build: {
        outDir: 'assets/js',
        emptyOutDir: true,

        rollupOptions: {
          input: {
            // Only include files that need to be processed/bundled
            'pagination-handler': path.resolve(__dirname, 'src/js/pagination-handler.js'),
            'autologout': path.resolve(__dirname, 'src/js/autologout.js'),
          },
          output: {
            // Output JS files with their name directly (no hash)
            entryFileNames: '[name].js',
            chunkFileNames: '[name].js',
            assetFileNames: '[name][extname]',
          },
        },
      },

      plugins: [
        {
          name: 'copy-legacy-scripts',
          closeBundle() {
            // Ensure output directory exists
            const outputDir = path.resolve(__dirname, 'assets/js');
            mkdirSync(outputDir, { recursive: true });

            // Copy files that should not be processed by Vite
            const filesToCopy = [
              {
                src: path.resolve(__dirname, 'src/js/js.cookie.min.js'),
                dest: path.resolve(outputDir, 'js.cookie.min.js')
              }
            ];

            filesToCopy.forEach(({ src, dest }) => {
              try {
                copyFileSync(src, dest);
                console.log(`Copied ${path.basename(src)} to assets/js/`);
              } catch (error) {
                console.error(`Failed to copy ${path.basename(src)}:`, error.message);
              }
            });
          }
        }
      ]
    };
  }

  // --- 3. CONFIGURATION FOR BUILDING ASSETS (CSS, FONTS, ETC.) ---
  return {
    base: './',
    // Settings for SCSS compilation
    css: {
      preprocessorOptions: {
        scss: {
          // Include node_modules for Carbon Design System SCSS imports
          includePaths: [path.resolve(__dirname, 'node_modules')],
          // Suppress deprecation warnings from Carbon Design System's legacy Sass code
          silenceDeprecations: ['legacy-js-api', 'import'],
          // Quieter warnings overall
          quietDeps: true
        }
      }
    },
    build: {
      manifest: true,
      outDir: 'assets',
      emptyOutDir: false, // Don't delete the whole assets directory (js folder stays)
      rollupOptions: {
        input: {
          // Define the entry point for your main styles
          styles: path.resolve(__dirname, 'src/scss/main.scss')
        },
        output: {
          // Organize assets into subdirectories
          assetFileNames: (assetInfo) => {
            const info = assetInfo.name.split('.');
            const ext = info[info.length - 1];

            // CSS files go in css/ directory
            if (ext === 'css') {
              return 'css/style.css';
            }

            // Font files go in fonts/ directory
            if (/woff|woff2|ttf|otf|eot/.test(ext)) {
              return 'fonts/[name][extname]';
            }

            // Images go in images/ directory
            if (/png|jpe?g|svg|gif|webp/.test(ext)) {
              return 'images/[name][extname]';
            }

            // Everything else in root of assets
            return '[name][extname]';
          },
          entryFileNames: 'js/[name].js', // This won't be used for CSS-only builds
          chunkFileNames: 'js/[name].js',
        }
      }
    }
  };
});
