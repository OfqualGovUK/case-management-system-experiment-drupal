import { defineConfig } from 'vite';
import * as path from 'path';
import { fileURLToPath } from 'url';

// Helper to define __dirname equivalent in ES module context
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Path is relative to the current working directory for components
const componentsBaseDir = './components';


/**
 * @param {{ mode: string }} env
 * Vite configuration dynamically selects between:
 * 1. Building a single component (if mode is a component name, e.g., 'ui_shell').
 * 2. Building static theme assets (if mode is 'production' or 'development').
 */
export default defineConfig(({ mode }) => {
  // Check if the mode is a specific component name passed by a build script loop.
  const isComponentBuild = !['development', 'production'].includes(mode) && !!mode;
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

  return {
    base: './',
    // Settings for SCSS compilation
    css: {
      preprocessorOptions: {
        scss: {
          // Include node_modules for Carbon Design System SCSS imports
          includePaths: [path.resolve(__dirname, 'node_modules')]
        }
      }
    },
    build: {
      manifest: true,
      outDir: 'assets', // Output SCSS/static assets here
      emptyOutDir: true,
      rollupOptions: {
        input: {
          // Define the entry point for your main styles
          styles: path.resolve(__dirname, 'src/scss/main.scss')
        },
        output: {
          // Custom output filename logic to ensure the final CSS file is named 'style.css'
          assetFileNames: (chunk) => {
            if (chunk.name && chunk.name.endsWith('.css')) return 'style.css';
            if (chunk.name === 'styles.css') return 'style.css';
            return '[name][extname]';
          }
        }
      }
    }
  };
});
