import { defineConfig } from 'vite';
import * as path from 'path';
import { fileURLToPath } from 'url';

// Helper to define __dirname equivalent in ES module context
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Path is relative to the current working directory
const baseDir = './components';


/**
 * Vite now exports a function that receives the command and mode.
 * We use the 'mode' flag (passed via the package.json loop script)
 * to determine which single component to build. This configuration is
 * built to execute successfully for a SINGLE component in IIFE format.
 */
export default defineConfig(({ mode }) => {
  // The component name is passed via the --mode flag (e.g., 'ui_shell').
  const componentName = mode;

  // Fallback/Guard: If 'mode' is a standard Vite mode or unset, return a minimal config.
  if (componentName === 'development' || componentName === 'production' || !componentName) {
    return {
      resolve: {
        alias: {
          '@carbon/icons/es': path.resolve(__dirname, 'node_modules', '@carbon', 'icons', 'es'),
        },
      },
      // Prevent the main build command from running if not in the loop.
      build: { rollupOptions: { input: {} } }
    };
  }

  const entryFile = `./${baseDir}/${componentName}/src/${componentName}.js`;
  const outputFileName = `${componentName}/${componentName}.js`;

  return {
    // Add path resolution for deep imports from @carbon/icons
    resolve: {
      alias: {
        '@carbon/icons/es': path.resolve(__dirname, 'node_modules', '@carbon', 'icons', 'es'),
      },
    },

    build: {
      outDir: baseDir,
      emptyOutDir: false, // Important: Don't delete the whole components directory!

      // Use lib mode to enforce single-file IIFE output, which requires a single entry.
      // This is the definitive fix for the IIFE/UMD error with multiple component files.
      lib: {
        entry: entryFile,
        // Create a safe, unique global name for the IIFE wrapper (e.g., 'ui_shell' -> 'ui_shell')
        name: componentName.replace(/[^a-zA-Z0-9]/g, '_'),
        formats: ['iife'], // Forces the classic JavaScript output format
        fileName: () => outputFileName,
      },

      // Rollup options are included to ensure no code splitting occurs.
      rollupOptions: {
        output: {
          manualChunks: false,
          preserveModules: false,
        },
      },
    },
  };
});
