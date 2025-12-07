import { test, expect } from '@playwright/test';
import HomePage from './Pages/homePage';

let homePage: HomePage;
const url = 'localhost';

//Load homepage before each test
test.beforeEach(async ({ page }) => {
    homePage = new HomePage(page);
    await homePage.page.goto(url);
});

//case management homepage
test('Case Management Home Page Test', async ({ page }) => {
    await expect(page).toHaveTitle("Welcome! | Ofqual");
});

//case management search by Title
test('Case Management Search by Title Test', async ({ page }) => {
    await homePage.search('Statement');
    await homePage.searchResultsCount(1);
});

//case management search by status
test('Case Management Search by Status Test', async ({ page }) => {
    await homePage.search('Triage');
    await homePage.searchResultsCount(2);
});