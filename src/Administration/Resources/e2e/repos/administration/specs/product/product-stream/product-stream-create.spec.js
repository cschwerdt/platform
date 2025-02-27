const productStreamPage = require('administration/page-objects/module/sw-product-stream.page-object.js');

module.exports = {
    '@tags': ['product', 'product-stream', 'product-stream-create', 'create'],
    'navigate to product stream and click on add product stream': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/product/stream/index',
                mainMenuId: 'sw-catalogue',
                subMenuId: 'sw-product-stream'
            })
            .click('.sw-product-stream-list__create-action');
    },
    'create new product stream with basic condition': (browser) => {
        const page = productStreamPage(browser);

        browser.expect.element(page.elements.smartBarHeader).to.have.text.that.equals('New product group');
        browser.assert.urlContains('#/sw/product/stream/create');

        page.createBasicProductStream('Product stream 1st', 'My first product stream');
    },
    'check if new product stream exists in overview': (browser) => {
        const page = productStreamPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/product/stream/index',
                mainMenuId: 'sw-catalogue',
                subMenuId: 'sw-product-stream'
            })
            .refresh()
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('Product groups');
        browser.assert.urlContains('#/sw/product/stream/index');

        browser.expect.element(`${page.elements.dataGridRow}--0 `).to.have.text.that.contains('Product stream 1st');
    },
    'verify product stream details': (browser) => {
        const page = productStreamPage(browser);

        browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw_product_stream_list__edit-action',
                scope: `${page.elements.dataGridRow}--0`
            })
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('Product stream 1st');
    }
};
