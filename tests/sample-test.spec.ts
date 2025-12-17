import { test, expect } from './fixtures/axe.fixture';
import type { AxeResults, Result } from 'axe-core';
import HomePage from './Pages/homePage';

let homePage: HomePage;
const url = 'localhost';

//Load homepage before each test
test.beforeEach(async ({ page }) => {
    homePage = new HomePage(page);
    await homePage.page.goto(url);
});

//close page after each test
test.afterEach(async ({ page }) => {
    await page.close();
});

//case management homepage and navigate to 2nd page in data table
test('Case Management Pagination Test', async ({ page, axe }) => {
    await expect(page).toHaveTitle("Welcome! | Ofqual");
    await homePage.verifyPaginationText('1–10 of 14 items');
    await homePage.gotoNextPage();
    await homePage.verifyPaginationText('11–14 of 14 items');
    
const { results, reportPath } = await axe.scan(page, {
      projectKey: 'PlaywrightHomepage',
      reportFile: 'playwright-homepage.html',
    //   tags: ['wcag2a', 'wcag2aa'], // optional: limit axe rules by tags
      addTimestamp: true,          // helps avoid overwriting
    });

    // Fail the test if violations exist, and point to the report
    expect(
      results.violations,
      `Accessibility violations found.\nSee HTML report: ${reportPath}\n` +
      results.violations
        .map((v: Result) => `- ${v.id}: ${v.help} (${v.impact})\n  Nodes: ${v.nodes.length}`)
        .join('\n')
    ).toEqual([]);

});

//case management search by Title
test('Case Management Search by Title Test', async ({ page }) => {
    await homePage.search('Statement');
    await homePage.verifySearchResultsCount(1);
    await homePage.verifyCaseIdInResults('OFQ-');
    await homePage.closeSearch();
    await homePage.verifyPaginationRowsCount(10);
});

//case management search by status
test('Case Management Search by Status Test', async ({ page }) => {
    await homePage.search('Triage');
    await homePage.verifySearchResultsCount(2);
});