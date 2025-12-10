
import { test as base, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { createHtmlReport } from 'axe-html-reporter';
import fs from 'fs';
import path from 'path';

type AxeScanOptions = {
  /** File name for the HTML report; a timestamp will be added before the extension if addTimestamp=true */
  reportFile?: string; // e.g., 'accessibility-report.html'
  /** Folder to store reports */
  reportDir?: string;  // default: 'build/reports'
  /** Label for the report header */
  projectKey?: string; // e.g., 'PlaywrightHomepage'
  /** Add timestamp to file name to avoid overwrites */
  addTimestamp?: boolean;
  /**
   * Optional tags to limit rules (e.g., ['wcag2a', 'wcag2aa'])
   * Note: tags filter the rules axe runs, not the report contents.
   */
  tags?: string[];
};

type AxeScanResult = {
  results: any;         // axe results object
  reportPath: string;   // path to the generated HTML report
};

export const test = base.extend<{
  axe: {
    /**
     * Run axe against the current page and generate an HTML report.
     */
    scan: (page: import('@playwright/test').Page, options?: AxeScanOptions) => Promise<AxeScanResult>;
  }
}>({
  axe: async ({}, use) => {
    // Provide a reusable scan function
    await use({
      scan: async (page, options?: AxeScanOptions) => {
        const {
          reportDir = 'build/reports',
          reportFile = 'accessibility-report.html',
          projectKey = 'Accessibility',
          addTimestamp = true,
          tags = []
        } = options || {};

        // Prepare file name with optional timestamp
        const timestampSuffix = addTimestamp
          ? `-${new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19)}`
          : '';
        const parsed = path.parse(reportFile);
        const finalFileName = `${parsed.name}${timestampSuffix}${parsed.ext || '.html'}`;

        // Configure axe
        let builder = new AxeBuilder({ page });
        if (tags.length) {
          builder = builder.withTags(tags);
        }

        // Run axe
        const results = await builder.analyze();

        // Generate HTML report
        const reportHTML = createHtmlReport({
          results,
          options: {
            projectKey,
          },
        });

        // Ensure output directory and write file
        fs.mkdirSync(reportDir, { recursive: true });
        const reportPath = path.join(reportDir, finalFileName);
        fs.writeFileSync(reportPath, reportHTML);

        return { results, reportPath };
      },
    });
  },
});

export { expect };
