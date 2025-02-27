const customerPage = require('administration/page-objects/module/sw-customer.page-object.js');

module.exports = {
    '@tags': ['customer-edit-address', 'customer-edit', 'customer', 'edit', 'address'],
    before: (browser, done) => {
        global.CustomerFixtureService.setCustomerFixture({
            email: 'test-again@example.com'
        }).then(() => {
            done();
        });
    },
    'open customer listing and look for customer to be edited': (browser) => {
        const page = customerPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/customer/index',
                mainMenuId: 'sw-customer'
            })
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(1)');

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-customer-list__view-action',
                scope: `${page.elements.dataGridRow}--0`
            })
            .expect.element(`${page.elements.customerMetaData}-customer-name`).to.have.text.that.equals('Mr. Pep Eroni');
    },
    'activate edit mode': (browser) => {
        browser
            .click('.sw-customer-detail__open-edit-mode-action')
            .waitForElementVisible('.sw-customer-detail__save-action');
    },
    'change customer data and exit edit mode': (browser) => {
        browser
            .clearValueManual('input[name=sw-field--customer-firstName]')
            .fillField('input[name=sw-field--customer-firstName]', 'Cran', true)
            .clearValueManual('input[name=sw-field--customer-lastName]')
            .fillField('input[name=sw-field--customer-lastName]', 'Berry', true)
            .tickCheckbox('input[name=sw-field--customer-active]', false);
    },
    'open address tab with first address': (browser) => {
        const page = customerPage(browser);

        browser
            .click('.sw-customer-detail__tab-addresses')
            .expect.element(`${page.elements.dataGridRow}--0`).to.have.text.that.contains('Eroni');
    },
    'ensure that a default shipping and billing address is given and cannot be deleted': (browser) => {
        const page = customerPage(browser);

        browser
            .waitForElementVisible('.icon--default-shopping-cart')
            .waitForElementVisible(`${page.elements.dataGridRow}--0`)
            .expect.element('#defaultShippingAddress-0').to.be.selected;
        browser.expect.element('#defaultBillingAddress-0').to.be.selected;

        browser
            .click(page.elements.contextMenuButton)
            .waitForElementVisible(page.elements.contextMenu)
            .waitForElementVisible(`${page.elements.contextMenu}-item--danger.is--disabled`);
    },
    'add second address': (browser) => {
        const page = customerPage(browser);

        browser
            .click('.sw-customer-detail-addresses__add-address-action');

        page.createBasicAddress();

        browser.waitForElementNotPresent(page.elements.modal);
    },
    'swap default billing and shipping address': (browser) => {
        const page = customerPage(browser);

        browser
            .waitForElementVisible('.icon--default-shopping-cart')
            .click('.sw-data-grid__cell--3')
            .waitForElementPresent(`${page.elements.dataGridRow}--1 #defaultShippingAddress-0:checked`)
            .click(`${page.elements.dataGridRow}--0 #defaultShippingAddress-0`)
            .click(`${page.elements.dataGridRow}--1 #defaultBillingAddress-0`)
            .expect.element(`${page.elements.dataGridRow}--0 #defaultShippingAddress-0`).to.be.selected;
        browser.expect.element(`${page.elements.dataGridRow}--1 #defaultBillingAddress-0`).to.be.selected;
    },
    'save customer': (browser) => {
        browser
            .click('.sw-customer-detail__save-action')
            .waitForElementVisible('.icon--small-default-checkmark-line-medium')
            .waitForElementNotPresent('.icon--small-default-checkmark-line-medium');
    },
    'remove address': (browser) => {
        const page = customerPage(browser);

        browser
            .refresh()
            .waitForElementVisible('.sw-customer-detail__open-edit-mode-action')
            .expect.element('.sw-customer-detail__open-edit-mode-action').to.not.have.attribute('disabled');

        browser
            .click('.sw-customer-detail__open-edit-mode-action')
            .expect.element('.sw-customer-detail__save-action').to.not.have.attribute('disabled');

        browser
            .waitForElementVisible('.sw-customer-detail__save-action');

        browser
            .click('.sw-data-grid__cell--3')
            .waitForElementPresent(`${page.elements.dataGridRow}--0 #defaultShippingAddress-0:checked`)
            .expect.element(`${page.elements.dataGridRow}--1`).to.have.text.that.contains('Eroni');

        browser
            .waitForElementPresent(`${page.elements.dataGridRow}--0`)
            .click(`${page.elements.dataGridRow}--0 #defaultShippingAddress-0`)
            .click(`${page.elements.dataGridRow}--0 #defaultBillingAddress-0`);

        browser.expect.element(`${page.elements.dataGridRow}--1 #defaultShippingAddress-0`).to.be.not.selected;
        browser.expect.element(`${page.elements.dataGridRow}--1 #defaultBillingAddress-0`).to.be.not.selected;

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: `${page.elements.contextMenu}-item--danger`,
                scope: `${page.elements.dataGridRow}--1`
            }).expect.element('.sw-customer-detail-addresses__confirm-delete-text').to.have.text.that.equals('Are you sure you want to delete this address?');

        browser
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .waitForElementNotPresent(page.elements.modal)
            .waitForElementNotPresent(`${page.elements.dataGridRow}--1`);

        browser
            .click('.sw-customer-detail__save-action')
            .waitForElementVisible('.icon--small-default-checkmark-line-medium')
            .waitForElementNotPresent('.icon--small-default-checkmark-line-medium');
    },
    'verify changed customer data': (browser) => {
        const page = customerPage(browser);

        browser
            .click('.sw-customer-detail__tab-general')
            .waitForElementVisible(page.elements.customerMetaData)
            .assert.containsText(`${page.elements.customerMetaData}-customer-name`, 'Mr. Cran Berry')
            .assert.containsText('.sw-address__full-name', 'Mr. Harry Potter')
            .assert.containsText('.sw-customer-base__label-is-active', 'Inactive');
    }
};
