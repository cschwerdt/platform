const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');
const productPage = require('administration/page-objects/module/sw-product.page-object.js');

module.exports = {
    '@tags': ['settings', 'language-create', 'translation', 'language', 'create'],
    before: (browser, done) => {
        global.ProductFixtureService.setProductFixture().then(() => {
            done();
        });
    },
    'open language module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-language')
            .assert.urlContains('#/sw/settings/language/index');
    },
    'create new language': (browser) => {
        const page = settingsPage(browser);

        browser
            .click('a[href="#/sw/settings/language/create"]')
            .expect.element(page.elements.cardTitle).to.have.text.that.contains('Settings');

        browser
            .assert.urlContains('#/sw/settings/language/create')
            .fillField('input[name=sw-field--language-name]', 'Philippine English')
            .fillSwSelect('.sw-settings-language-detail__select-locale', { value: 'English, Philippines', searchTerm: 'en-PH' })
            .fillSwSelect('.sw-settings-language-detail__select-parent', { value: 'English' })
            .click(page.elements.languageSaveAction)
            .waitForElementVisible('.icon--small-default-checkmark-line-medium')
            .waitForElementNotPresent('.icon--small-default-checkmark-line-medium')
            .assert.urlContains('#/sw/settings/language/detail');
    },
    'go back to listing and verify creation': (browser) => {
        const page = settingsPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .expect.element(`${page.elements.dataGridRow}--2 ${page.elements.languageColumnName}`).to.have.text.that.contains(global.LanguageFixtureService.getLanguageName());
    },
    'check if language can be selected as translation': (browser) => {
        const page = productPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/product/index',
                mainMenuId: 'sw-catalogue',
                subMenuId: 'sw-product'
            })
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-entity-listing__context-menu-edit-action',
                scope: `${page.elements.dataGridRow}--0`
            });
        page.changeTranslation('Product name', 'Philippine English', 3);

        browser.expect.element('.sw-language-info').to.have.text.that.contains('"Product name" displayed in the language "Philippine English", which inherits from "English".');
    }
};
