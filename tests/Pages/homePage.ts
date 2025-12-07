import { expect, Locator, Page } from '@playwright/test';

class homePage{
    page: Page;
    searchInput: Locator;
    tableBody: Locator;
    rowsWithoutFilteredAttr: Locator;

    constructor(page: Page){
        this.page = page;
        this.searchInput = page.locator('.cds--search-input');
        this.tableBody = page.locator('cds-table-body'); 
        this.rowsWithoutFilteredAttr = this.tableBody.locator('cds-table-row:not([filtered])');
    }

    async search(text: string){

        await this.searchInput.fill(text);
        await expect(this.searchInput).toHaveValue(text);
    }

    async searchResultsCount(expected: number){
        await expect(this.rowsWithoutFilteredAttr).toHaveCount(expected);
    }
}

export default homePage;