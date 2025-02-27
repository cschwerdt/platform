import { Component, Mixin, State } from 'src/core/shopware';
import { mapState } from 'vuex';
import { mapFormErrors } from 'src/app/service/map-errors.service';
import template from './sw-product-basic-form.html.twig';

Component.register('sw-product-basic-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            isTitleRequired: true
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'loading'
        ]),

        ...mapFormErrors('product', ['name', 'active', 'description', 'productNumber', 'manufacturerId', 'tags']),

        languageStore() {
            return State.getStore('language');
        }
    },

    watch: {
        product: {
            handler() {
                this.updateIsTitleRequired();
            },
            immediate: true,
            deep: true
        }
    },

    methods: {
        updateIsTitleRequired() {
            // TODO: Refactor when there is a possibility to check if the title field is inherited
            this.isTitleRequired = this.languageStore.getCurrentLanguage().id === '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
        },

        getInheritValue(firstKey, secondKey) {
            const p = this.parentProduct;

            if (p[firstKey]) {
                return p[firstKey].hasOwnProperty(secondKey) ? p[firstKey][secondKey] : p[firstKey];
            }
            return null;
        }
    }
});
