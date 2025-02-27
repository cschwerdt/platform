import { Component } from 'src/core/shopware';
import template from './sw-login-recovery-info.html.twig';

Component.register('sw-login-recovery-info', {
    template,

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.$emit('not-loading');
        }
    }
});
