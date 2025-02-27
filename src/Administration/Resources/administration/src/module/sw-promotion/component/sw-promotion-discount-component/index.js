import { Component, Mixin, State } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-promotion-discount-component.html.twig';
import './sw-promotion-discount-component.scss';
import DiscountTypes from './../../common/discount-type';
import DiscountScopes from './../../common/discount-scope';
import DiscountHandler from './handler';

const discountHandler = new DiscountHandler();

Component.register('sw-promotion-discount-component', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        discount: {
            type: Object,
            required: true,
            default: {}
        }
    },
    data() {
        return {
            itemAddNewRule: {
                index: -1,
                id: 'addNewRule'
            },
            showRuleModal: false

        };
    },
    watch: {
        'discount.type': {
            handler() {
                this.verifyValueMax();
            }
        },
        'discount.value': {
            handler() {
                this.verifyValueMax();
            }
        }
    },
    computed: {

        rulesStore() {
            return State.getStore('rule');
        },

        ruleFilter() {
            return Criteria.equalsAny(
                'conditions.type',
                [
                    'cartLineItem', 'cartLineItemTag', 'cartLineItemTotalPrice',
                    'cartLineItemUnitPrice', 'cartLineItemWithQuantity'
                ]
            );
        }

    },
    methods: {
        // Gets a list of all predefined scopes for the dropdown.
        // We use a method for this, because values are not translated when switching languages.
        // By using methods, they do get translated and reloaded correctly.
        getScopes() {
            return [
                { key: DiscountScopes.CART, name: this.$tc('sw-promotion.detail.main.discounts.valueScopeCart') }
            ];
        },
        // Gets a list of all predefined types for the dropdown.
        // We use a method for this, because values are not translated when switching languages.
        // By using methods, they do get translated and reloaded correctly.
        getTypes() {
            return [
                { key: DiscountTypes.ABSOLUTE, name: this.$tc('sw-promotion.detail.main.discounts.valueTypeAbsolute') },
                { key: DiscountTypes.PERCENTAGE, name: this.$tc('sw-promotion.detail.main.discounts.valueTypePercentage') }
            ];
        },
        getValueSuffix() {
            return discountHandler.getValueSuffix(this.discount.type);
        },
        getValueMin() {
            return discountHandler.getMinValue();
        },
        getValueMax() {
            return discountHandler.getMaxValue(this.discount.type);
        },
        // This function verifies the currently set value
        // depending on the discount type, and fixes it if
        // the min or maximum thresholds have been exceeded.
        verifyValueMax() {
            this.discount.value = discountHandler.getFixedValue(this.discount.value, this.discount.type);
        },
        onSaveRule(rule) {
            this.$refs.productRuleSelector.addItem({ item: rule });
        },
        onOptionClick(event) {
            if (event.item.index === -1) {
                this.openCreateRuleModal();
            }
        },
        openCreateRuleModal() {
            this.showRuleModal = true;
        },
        onCloseRuleModal() {
            this.$refs.productRuleSelector.remove('addNewRule');
            this.showRuleModal = false;
        }
    }

});
