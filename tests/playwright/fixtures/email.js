import { expect } from '@playwright/test';

// @ts-check
export class Email {
    /**
     * @param {import('@playwright/test').Page} page
     * @param {Object} settings - The settings to be used for the module
     * @param {string} settings.url - The URL of the mail server, e.g. 'http://localhost:13745/mailhog'
     */
    constructor(page, settings) {
        this.page = page;
        this.settings = settings;
        this.url = this.settings.url;
    }

    async openInbox() {
        await this.page.goto(this.url);
        await this.page.waitForLoadState('domcontentloaded');
    }

    async deleteAllMessages() {
        await this.page.locator('a', { hasText: 'Delete all messages' }).click();
        await this.page.locator('div#confirm-delete-all button', { hasText: 'Delete all messages' }).click();
        await this.page.reload();
    }

    async findEmailBySubject(subject) {
        const email = await this.page.locator('div.msglist-message', {
            has: this.page.locator('span.subject', { hasText: subject })
        });
        return email;
    }
}