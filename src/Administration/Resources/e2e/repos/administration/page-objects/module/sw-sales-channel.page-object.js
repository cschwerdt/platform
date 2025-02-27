const GeneralPageObject = require('../sw-general.page-object');

class SalesChannelPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements,
            ...{
                salesChannelMenuName: '.sw-admin-menu__sales-channel-item',
                salesChannelModal: '.sw-sales-channel-modal',
                salesChannelNameInput: 'input[name=sw-field--salesChannel-name]',
                salesChannelMenuTitle: '.sw-admin-menu__sales-channel-item .collapsible-text',
                apiAccessKeyField: 'input[name=sw-field--salesChannel-accessKey]',
                salesChannelSaveAction: '.sw-sales-channel-detail__save-action'
            }
        };

        this.accessKeyId = '';
        this.newAccessKeyId = '';
    }

    createBasicSalesChannel(salesChannelName) {
        this.browser
            .fillField(this.elements.salesChannelNameInput, salesChannelName)

            .fillSwSelect('.sw-sales-channel-detail__select-payment-method', { value: 'Invoice', isMulti: true })
            .fillSwSelect('.sw-sales-channel-detail__assign-payment-method', { value: 'Invoice' })

            .fillSwSelect('.sw-sales-channel-detail__select-shipping-method', { value: 'Standard', isMulti: true })
            .fillSwSelect('.sw-sales-channel-detail__assign-shipping-method', { value: 'Standard' })

            .fillSwSelect('.sw-sales-channel-detail__select-countries', { value: 'Germany', isMulti: true })
            .fillSwSelect('.sw-sales-channel-detail__assign-countries', { value: 'Germany' })

            .fillSwSelect('.sw-sales-channel-detail__select-currencies', { value: 'Euro', isMulti: true })
            .fillSwSelect('.sw-sales-channel-detail__assign-currencies', { value: 'Euro' })

            .fillSwSelect('.sw-sales-channel-detail__select-languages', { value: 'Deutsch', isMulti: true })
            .fillSwSelect('.sw-sales-channel-detail__assign-languages', { value: 'Deutsch' })

            .fillSwSelect('.sw-sales-channel-detail__select-customer-group', { value: 'Standard customer group', resultPosition: 1 })
            .fillSwSelect('.sw-sales-channel-detail__select-navigation-category-id', { value: 'Catalogue #1', resultPosition: 1 })

            .waitForElementNotPresent('.icon--small-default-checkmark-line-medium')
            .click(this.elements.salesChannelSaveAction)
            .waitForElementVisible('.icon--small-default-checkmark-line-medium');
    }

    openSalesChannel(salesChannelName, position = 0) {
        this.browser
            .expect.element(`${this.elements.salesChannelMenuName}--${position} > a`).to.have.text.that.equals(salesChannelName);

        this.browser
            .click(`${this.elements.salesChannelMenuName}--${position}`)
            .expect.element(this.elements.smartBarHeader).to.have.text.that.contains(salesChannelName);
    }

    deleteSingleSalesChannel(salesChannelName) {
        this.browser
            .getLocationInView(this.elements.dangerButton)
            .click(this.elements.dangerButton)
            .waitForElementVisible(this.elements.modal)
            .assert.containsText(
                `${this.elements.modal}__body .sw-sales-channel-detail-base__delete-modal-confirm-text`,
                'Are you sure you want to delete this sales channel?'
            )
            .assert.containsText(
                `${this.elements.modal}__body .sw-sales-channel-detail-base__delete-modal-name`,
                salesChannelName
            );

        this.browser
            .click(`${this.elements.modal}__footer button${this.elements.dangerButton}`)
            .waitForElementNotPresent(this.elements.modal)
            .expect.element('.sw-admin-menu__body').to.have.text.that.not.contains(salesChannelName);
    }

    checkClipboard() {
        const me = this;

        this.browser
            .waitForElementPresent(this.elements.apiAccessKeyField)
            .getValue(me.elements.apiAccessKeyField, (result) => {
                me.accessKeyId = result.value;

                me.browser
                    .waitForElementPresent(me.elements.apiAccessKeyField)
                    .getLocationInView(me.elements.apiAccessKeyField)
                    .waitForElementVisible('.sw-field-copyable:nth-of-type(1)')
                    .click('.sw-field-copyable:nth-of-type(1)')
                    .checkNotification('Text has been copied to clipboard.')
                    .getLocationInView(me.elements.salesChannelNameInput)
                    .clearValueManual(me.elements.salesChannelNameInput)
                    .setValue(me.elements.salesChannelNameInput, ['', me.browser.Keys.CONTROL, 'v'])
                    .expect.element(me.elements.salesChannelNameInput).value.that.equals(me.accessKeyId);
            });
    }

    changeApiCredentials(salesChannelName) {
        const me = this;

        this.browser.getValue(this.elements.apiAccessKeyField, (result) => {
            me.newAccessKeyId = result.value;

            me.browser
                .getLocationInView(me.elements.apiAccessKeyField)
                .click('.sw-sales-channel-detail-base__button-generate-keys')
                .expect.element(me.elements.apiAccessKeyField).value.that.equals(me.newAccessKeyId);

            me.browser
                .fillField(me.elements.salesChannelNameInput, salesChannelName, true, 'input')
                .waitForElementNotPresent('.icon--small-default-checkmark-line-medium')
                .click('.sw-sales-channel-detail__save-action')
                .waitForElementVisible('.icon--small-default-checkmark-line-medium');
        });
    }

    verifyChangedApiCredentials() {
        const me = this;

        this.browser.getValue(this.elements.apiAccessKeyField, (result) => {
            me.newAccessKeyId = result.value;

            me.browser
                .waitForElementPresent(me.elements.apiAccessKeyField)
                .expect.element(me.elements.apiAccessKeyField).value.that.equals(me.newAccessKeyId);
        });
    }
}

module.exports = (browser) => {
    return new SalesChannelPageObject(browser);
};
