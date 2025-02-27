import { Component, Mixin } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-cms-layout-modal.html.twig';
import './sw-cms-layout-modal.scss';

Component.register('sw-cms-layout-modal', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            selected: null,
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            term: null,
            total: null,
            pages: []
        };
    },

    computed: {
        pageRepository() {
            return this.repositoryFactory.create('cms_page');
        }
    },

    methods: {
        getList() {
            this.isLoading = true;
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('previewMedia');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            if (this.term !== null) {
                criteria.setTerm(this.term);
            }

            return this.pageRepository.search(criteria, this.context).then((searchResult) => {
                this.total = searchResult.total;
                this.pages = searchResult.items;
                this.isLoading = false;
                return this.pages;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        selectLayout() {
            this.$emit('modal-layout-select', this.selected);
            this.closeModal();
        },

        selectItem(layout) {
            this.selected = layout;
        },

        onSearch(value) {
            if (!value.length || value.length <= 0) {
                this.term = null;
            } else {
                this.term = value;
            }

            this.page = 1;
            this.getList();
        },

        onSelection(selection) {
            this.selected = selection;
        },

        closeModal() {
            this.$emit('modal-close');
            this.selected = null;
            this.term = null;
        }
    }
});
