const { test: base, expect } = require('@playwright/test');
const { Module } = require('./module');
const { Email } = require('./email');
const { config } = require('./config');

exports.test = base.extend({
    modulePage: async ({ context }, use) => {
        const page = await context.newPage();
        const module = new Module(page, {
            redcapVersion: config.redcapVersion,
            baseUrl: config.redcapUrl,
            username: config.users.AdminUser.username,
            password: config.users.AdminUser.password,
            module: config.module
        });
        await module.logIn();
        await use(module);
    },
    emailPage: async ({ context }, use) => {
        const page = await context.newPage();
        const module = new Email(page, {
            url: config.emailUrl
        });
        await page.goto(config.emailUrl);
        await use(module);
    }
});
exports.expect = expect;