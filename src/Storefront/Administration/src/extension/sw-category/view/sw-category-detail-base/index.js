import { Component } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-category-detail-base.html.twig';

Component.override('sw-category-detail-base', {
    template,

    data() {
        return {
            seoUrlStore: null,
            seoUrls: []
        };
    },

    created() {
        this.initSeoUrls();

        this.$root.$on('on-change-application-language', this.initSeoUrls);
    },

    methods: {
        initSeoUrls() {
            this.seoUrlStore = this.category.getAssociation('seoUrls');
            const params = {
                page: 1,
                limit: 50,
                criteria: CriteriaFactory.equals('isCanonical', true)
            };
            this.seoUrlStore.getList(params).then((response) => {
                this.seoUrls = response.items;
            });
        }
    }
});
