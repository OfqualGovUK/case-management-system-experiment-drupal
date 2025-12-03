import { test, expect } from '@playwright/test';
import HomePage from './Pages/homePage';

let homePage;
const url = 'localhost';

//case management homepage
test('Case Management Home Page Test', async ({ page }) => {
    homePage = new HomePage(page);
    await homePage.page.goto(url);
    await expect(page).toHaveTitle("Welcome! | Ofqual");

});