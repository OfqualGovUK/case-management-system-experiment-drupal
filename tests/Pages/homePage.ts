import { expect, Locator, Page } from '@playwright/test';

class homePage{
    page: Page;
    searchInput: Locator;
    tableBody: Locator;
    rowsWithoutFilteredAttr: Locator;
    rowsWithoutHiddenAttr: Locator;
    paginationText: Locator;
    paginationNextButton: Locator;
    caseId: Locator;
    closeSearchButton: Locator;

    constructor(page: Page){
        this.page = page;
        this.searchInput = page.locator('.cds--search-input');
        this.tableBody = page.locator('cds-table-body'); 
        this.rowsWithoutFilteredAttr = this.tableBody.locator('cds-table-row:not([filtered])');
        this.rowsWithoutHiddenAttr = this.tableBody.locator('cds-table-row:not([hidden])');
        this.paginationText = page.locator('.cds--pagination__items-count');
        this.paginationNextButton = page.locator('button[aria-label="Next page"]');
        this.caseId = this.tableBody.locator('cds-table-row:not([filtered])').locator('cds-table-cell');
        this.closeSearchButton = page.locator('.cds--search-close');
    }

    async search(text: string){

        await this.searchInput.fill(text);
        await expect(this.searchInput).toHaveValue(text);
    }

    async verifySearchResultsCount(expected: number){
        await expect(this.rowsWithoutFilteredAttr).toHaveCount(expected);
    }

    async verifyPaginationRowsCount(expected: number){
        await expect(this.rowsWithoutHiddenAttr).toHaveCount(expected);
    }

    async verifyPaginationText(expectedText: string){
        await expect(this.paginationText).toHaveText(expectedText);
    }

    async gotoNextPage(){
        await this.paginationNextButton.click();
    }

    async verifyCaseIdInResults(expectedCaseId: string){
        await expect(this.caseId.first()).toContainText(expectedCaseId);
    }

    async closeSearch(){
        await this.closeSearchButton.click(); 
    }
}

export default homePage;