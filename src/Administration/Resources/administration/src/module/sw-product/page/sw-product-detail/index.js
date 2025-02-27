import { Component, Mixin, State } from 'src/core/shopware';
import { mapState, mapGetters } from 'vuex';
import { mapPageErrors } from 'src/app/service/map-errors.service';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-product-detail.html.twig';
import swProductDetailState from './state';
import errorConfiguration from './error.cfg.json';

Component.register('sw-product-detail', {
    template,

    inject: ['mediaService', 'repositoryFactory', 'context', 'numberRangeService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel'
    },

    props: {
        productId: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            productNumberPreview: '',
            isSaveSuccessful: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'localMode'
        ]),

        ...mapGetters('swProductDetail', [
            'productRepository',
            'isLoading',
            'isChild',
            'defaultCurrency'
        ]),

        ...mapPageErrors(errorConfiguration),

        identifier() {
            return this.placeholder(this.product, 'name');
        },

        productTitle() {
            // when product is variant
            if (this.isChild && this.product && this.product.options && this.product.options.items) {
                // return each option
                return Object.values(this.product.options.items).map(option => {
                    return option.translated.name || option.name;
                }).join(' – ');
            }

            // return name
            return this.placeholder(this.product, 'name', this.$tc('sw-product.detail.textHeadline'));
        },

        languageStore() {
            return State.getStore('language');
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        taxRepository() {
            return this.repositoryFactory.create('tax');
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        mediaRepository() {
            if (this.product && this.product.media) {
                return this.repositoryFactory.create(
                    this.product.media.entity,
                    this.product.media.source
                );
            }
            return null;
        },

        defaultCriteria() {
            return new Criteria(1, 500);
        },

        mediaCriteria() {
            const criteria = new Criteria(1, 50);
            criteria.addSorting(
                Criteria.sort('position', 'ASC')
            );
            return criteria;
        },

        tagsCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addSorting(
                Criteria.sort('name', 'ASC')
            );
            return criteria;
        },

        pricesCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addSorting(
                Criteria.sort('quantityStart', 'ASC', true)
            );
            return criteria;
        },

        visibilitiesCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addAssociation('salesChannel', this.defaultCriteria);

            return criteria;
        },

        propertyCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addSorting(
                Criteria.sort('name', 'ASC')
            );
            return criteria;
        },

        configuratorSettingsCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addAssociation('option', this.defaultCriteria);

            return criteria;
        },

        productCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('media', this.mediaCriteria);
            criteria.addAssociation('properties', this.propertyCriteria);
            criteria.addAssociation('visibilities', this.visibilitiesCriteria);
            criteria.addAssociation('prices', this.pricesCriteria);
            criteria.addAssociation('tags', this.tagsCriteria);
            criteria.addAssociation('categories', this.defaultCriteria);
            criteria.addAssociation('options', this.defaultCriteria);
            criteria.addAssociation('configuratorSettings', this.configuratorSettingsCriteria);
            return criteria;
        },

        customFieldCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addSorting(
                Criteria.sort('config.customFieldPosition')
            );
            return criteria;
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, 100);
            criteria
                .addFilter(
                    Criteria.equals('relations.entityName', 'product')
                )
                .addAssociation('customFields', this.customFieldCriteria);
            return criteria;
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light'
            };
        }
    },

    beforeCreate() {
        this.$store.registerModule('swProductDetail', swProductDetailState);
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.$store.unregisterModule('swProductDetail');
    },

    destroyed() {
        this.destroyedComponent();
    },

    watch: {
        productId() {
            this.destroyedComponent();
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            // when create
            if (!this.productId) {
                // set language to system language
                if (this.languageStore.getCurrentId() !== this.languageStore.systemLanguageId) {
                    this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
                }
            }

            // initialize default state
            this.initState();

            this.$root.$on('sidebar-toggle-open', this.openMediaSidebar);
            this.$root.$on('media-remove', (mediaId) => {
                this.removeMediaItem(mediaId);
            });
            this.$root.$on('product-reload', () => {
                this.loadAll();
            });
        },

        destroyedComponent() {
            this.$root.$off('sw-product-media-form-open-sidebar');
            this.$root.$off('media-remove');
            this.$root.$off('product-reload');
        },

        initState() {
            this.$store.commit('swProductDetail/setContext', this.context);

            // when product exists
            if (this.productId) {
                return this.loadState();
            }

            // When no product id exists init state and new product with the repositoryFactory
            return this.createState().then(() => {
                // create new product number
                this.numberRangeService.reserve('product', '', true).then((response) => {
                    this.productNumberPreview = response.number;
                    this.product.productNumber = response.number;
                });
            });
        },

        loadState() {
            this.$store.commit('swProductDetail/setLocalMode', false);
            this.$store.commit('swProductDetail/setProductId', this.productId);

            return this.loadAll();
        },

        loadAll() {
            return Promise.all([
                this.loadProduct(),
                this.loadCurrencies(),
                this.loadTaxes(),
                this.loadAttributeSet()
            ]);
        },

        createState() {
            // set local mode
            this.$store.commit('swProductDetail/setLocalMode', true);

            this.$store.commit('swProductDetail/setLoading', ['product', true]);

            // create empty product
            this.$store.commit('swProductDetail/setProduct', this.productRepository.create(this.context));
            this.$store.commit('swProductDetail/setProductId', this.product.id);

            // fill empty data
            this.product.active = true;
            this.product.taxId = null;

            this.product.metaTitle = '';
            this.product.additionalText = '';

            return Promise.all([
                this.loadCurrencies(),
                this.loadTaxes(),
                this.loadAttributeSet()
            ]).then(() => {
                // set default product price
                this.product.price = [{
                    currencyId: this.defaultCurrency.id,
                    net: null,
                    linked: true,
                    gross: null
                }];

                this.$store.commit('swProductDetail/setLoading', ['product', false]);
            });
        },

        loadProduct() {
            this.$store.commit('swProductDetail/setLoading', ['product', true]);

            this.productRepository.get(this.productId || this.product.id, this.context, this.productCriteria).then((res) => {
                this.$store.commit('swProductDetail/setProduct', res);

                if (this.product.parentId) {
                    this.loadParentProduct();
                }

                this.$store.commit('swProductDetail/setLoading', ['product', false]);
            });
        },

        loadParentProduct() {
            this.$store.commit('swProductDetail/setLoading', ['parentProduct', true]);

            return this.productRepository.get(this.product.parentId, this.context, this.productCriteria).then((res) => {
                this.$store.commit('swProductDetail/setParentProduct', res);
            }).then(() => {
                this.$store.commit('swProductDetail/setLoading', ['parentProduct', false]);
            });
        },

        loadCurrencies() {
            this.$store.commit('swProductDetail/setLoading', ['currencies', true]);

            return this.currencyRepository.search(this.defaultCriteria, this.context).then((res) => {
                this.$store.commit('swProductDetail/setCurrencies', res);
            }).then(() => {
                this.$store.commit('swProductDetail/setLoading', ['currencies', false]);
            });
        },

        loadTaxes() {
            this.$store.commit('swProductDetail/setLoading', ['taxes', true]);

            return this.taxRepository.search(this.defaultCriteria, this.context).then((res) => {
                this.$store.commit('swProductDetail/setTaxes', res);
            }).then(() => {
                this.$store.commit('swProductDetail/setLoading', ['taxes', false]);
            });
        },

        loadAttributeSet() {
            this.$store.commit('swProductDetail/setLoading', ['customFieldSets', true]);

            return this.customFieldSetRepository.search(this.customFieldSetCriteria, this.context).then((res) => {
                this.$store.commit('swProductDetail/setAttributeSet', res);
            }).then(() => {
                this.$store.commit('swProductDetail/setLoading', ['customFieldSets', false]);
            });
        },

        abortOnLanguageChange() {
            return this.$store.getters['swProductDetail/hasChanges'];
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage(languageId) {
            this.context.languageId = languageId;
            this.initState();
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        saveFinish() {
            this.isSaveSuccessful = false;

            if (!this.productId) {
                this.$router.push({ name: 'sw.product.detail', params: { id: this.product.id } });
            }
        },

        onSave() {
            if (!this.productId) {
                if (this.productNumberPreview === this.product.productNumber) {
                    this.numberRangeService.reserve('product').then((response) => {
                        this.productNumberPreview = 'reserved';
                        this.product.productNumber = response.number;
                    });
                }
            }

            this.isSaveSuccessful = false;

            return this.saveProduct().then(this.onSaveFinished);
        },

        onSaveFinished(response) {
            switch (response) {
                case 'empty': {
                    const titleSaveWarning = this.$tc('sw-product.detail.titleSaveWarning');
                    const messageSaveWarning = this.$tc('sw-product.detail.messageSaveWarning');

                    this.createNotificationWarning({
                        title: titleSaveWarning,
                        message: messageSaveWarning
                    });
                    this.$store.commit('resetApiErrors');
                    break;
                }

                case 'success': {
                    this.isSaveSuccessful = true;

                    break;
                }

                default: {
                    const productName = this.product.translated ? this.product.translated.name : this.product.name;
                    const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
                    const messageSaveError = this.$tc(
                        'global.notification.notificationSaveErrorMessage', 0, { entityName: productName }
                    );

                    this.createNotificationError({
                        title: titleSaveError,
                        message: messageSaveError
                    });
                    break;
                }
            }
        },

        onCancel() {
            this.$router.push({ name: 'sw.product.index' });
        },

        saveProduct() {
            this.$store.commit('swProductDetail/setLoading', ['product', true]);

            return new Promise((resolve) => {
                // check if product exists
                if (!this.productRepository.hasChanges(this.product)) {
                    this.$store.commit('swProductDetail/setLoading', ['product', false]);
                    resolve('empty');
                    this.$store.commit('swProductDetail/setLoading', ['product', false]);
                    return;
                }

                // save product
                this.productRepository.save(this.product, this.context).then(() => {
                    this.loadAll().then(() => {
                        this.$store.commit('swProductDetail/setLoading', ['product', false]);
                        resolve('success');
                    });
                }).catch((response) => {
                    this.$store.commit('swProductDetail/setLoading', ['product', false]);
                    resolve(response);
                });
            });
        },

        onAddItemToProduct(mediaItem) {
            if (this._checkIfMediaIsAlreadyUsed(mediaItem.id)) {
                this.createNotificationInfo({
                    message: this.$tc('sw-product.mediaForm.errorMediaItemDuplicated')
                });
                return false;
            }

            this.addMedia(mediaItem).then((mediaId) => {
                this.$root.$emit('media-added', mediaId);
                return true;
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-product.mediaForm.errorHeadline'),
                    message: this.$tc('sw-product.mediaForm.errorMediaItemDuplicated')
                });

                return false;
            });
            return true;
        },

        addMedia(mediaItem) {
            this.$store.commit('swProductDetail/setLoading', ['media', true]);

            // return error if media exists
            if (this.product.media.has(mediaItem.id)) {
                this.$store.commit('swProductDetail/setLoading', ['media', false]);
                // eslint-disable-next-line prefer-promise-reject-errors
                return Promise.reject('A media item with this id exists');
            }

            const newMedia = this.mediaRepository.create(this.context, mediaItem.id);
            newMedia.mediaId = mediaItem.id;

            return new Promise((resolve) => {
                this.product.media.add(newMedia);

                this.$store.commit('swProductDetail/setLoading', ['media', false]);

                resolve(newMedia.mediaId);
                return true;
            });
        },

        removeMediaItem(state, mediaId) {
            this.product.media.remove(mediaId);
        },

        _checkIfMediaIsAlreadyUsed(mediaId) {
            return Object.values(this.product.media).some((productMedia) => {
                return productMedia.mediaId === mediaId;
            });
        }
    }
});
