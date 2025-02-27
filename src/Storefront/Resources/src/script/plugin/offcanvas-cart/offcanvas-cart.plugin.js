import Plugin from 'src/script/helper/plugin/plugin.class';
import PluginManager from 'src/script/helper/plugin/plugin.manager';
import DomAccess from 'src/script/helper/dom-access.helper';
import HttpClient from 'src/script/service/http-client.service';
import AjaxOffCanvas from 'src/script/plugin/offcanvas/ajax-offcanvas.plugin';
import DeviceDetection from 'src/script/helper/device-detection.helper';
import FormSerializeUtil from 'src/script/utility/form/form-serialize.util';
import Iterator from 'src/script/helper/iterator.helper';
import OffCanvas from 'src/script/plugin/offcanvas/offcanvas.plugin';
import ElementLoadingIndicatorUtil from 'src/script/utility/loading-indicator/element-loading-indicator.util';

export default class OffCanvasCartPlugin extends Plugin {

    static options = {
        removeProductTriggerSelector: '.js-offcanvas-cart-remove-product',
        changeProductQuantityTriggerSelector: '.js-offcanvas-cart-change-quantity',
        addPromotionTriggerSelector: '.js-offcanvas-cart-add-promotion',
        cartItemSelector: '.js-cart-item',
        cartPromotionSelector: '.js-offcanvas-cart-promotion',
        offcanvasPosition: 'right',
    };

    init() {
        this.client = new HttpClient(window.accessKey, window.contextToken);
        this._registerOpenTriggerEvents();
    }

    /**
     * public method to open the offCanvas
     *
     * @param {string} url
     * @param {{}|FormData} data
     * @param {function|null} callback
     */
    openOffCanvas(url, data, callback) {
        AjaxOffCanvas.open(url, data, this._onOffCanvasOpened.bind(this, callback), this.options.offcanvasPosition);
    }

    /**
     * Register events to handle opening the Cart OffCanvas
     * by clicking a defined trigger selector
     *
     * @private
     */
    _registerOpenTriggerEvents() {
        const event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        this.el.addEventListener(event, this._onOpenOffCanvasCart.bind(this));
    }

    /**
     * On clicking the trigger item the OffCanvas shall open and the current
     * cart template may be fetched and shown inside the OffCanvas
     *
     * @param {Event} event
     * @private
     */
    _onOpenOffCanvasCart(event) {
        event.preventDefault();

        this.openOffCanvas(window.router['frontend.cart.offcanvas'], false);
    }

    /**
     * Register events to handle removing a product from the cart
     *
     * @private
     */
    _registerRemoveProductTriggerEvents() {
        const forms = DomAccess.querySelectorAll(document, this.options.removeProductTriggerSelector, false);
        if (forms) {
            Iterator.iterate(forms, form => form.addEventListener('submit', this._onRemoveProductFromCart.bind(this)));
        }
    }

    /**
     * Register events to handle changing the quantity of a product from the cart
     *
     * @private
     */
    _registerChangeQuantityProductTriggerEvents() {
        const selects = DomAccess.querySelectorAll(document, this.options.changeProductQuantityTriggerSelector, false);
        if (selects) {
            Iterator.iterate(selects, select => select.addEventListener('change', this._onChangeProductQuantity.bind(this)));
        }
    }

    /**
     * Register events to handle adding a promotion to the cart
     *
     * @private
     */
    _registeraddPromotionTriggerEvents() {
        const forms = DomAccess.querySelectorAll(document, this.options.addPromotionTriggerSelector, false);

        if (forms) {
            Iterator.iterate(forms, form => form.addEventListener('submit', this._onAddPromotionToCart.bind(this)));
        }
    }

    /**
     * Register all needed events
     *
     * @private
     */
    _registerEvents() {
        this._registerRemoveProductTriggerEvents();
        this._registerChangeQuantityProductTriggerEvents();
        this._registeraddPromotionTriggerEvents();
    }

    /**
     * default callback when the offcanvas has opened
     *
     * @param {function|null} callback
     * @param {string} response
     *
     * @private
     */
    _onOffCanvasOpened(callback, response) {
        if (typeof callback === 'function') callback(response);
        this._fetchCartWidgets();
        this._registerEvents();
    }

    /**
     * Fire the ajax request for the form
     *
     * @param {HTMLElement} form
     * @param {string} selector
     *
     * @private
     */
    _fireRequest(form, selector) {
        ElementLoadingIndicatorUtil.create(form.closest(selector));

        const requestUrl = DomAccess.getAttribute(form, 'action');
        const data = FormSerializeUtil.serialize(form);

        this.client.post(requestUrl.toLowerCase(), data, this._onOffCanvasOpened.bind(this, this._updateOffCanvasContent.bind(this)));
    }

    /**
     * Submit the delete form inside the Offcanvas
     *
     * @param {Event} event
     *
     * @private
     */
    _onRemoveProductFromCart(event) {
        event.preventDefault();
        const form = event.target;
        const selector = this.options.cartItemSelector;

        this._fireRequest(form, selector);
    }

    /**
     * Submit the change quantity form inside the Offcanvas
     *
     * @param {Event} event
     *
     * @private
     */
    _onChangeProductQuantity(event) {
        const select = event.target;
        const form = select.closest('form');
        const selector = this.options.cartItemSelector;

        this._fireRequest(form, selector);
    }


    /**
     * Submit the add form inside the Offcanvas
     *
     * @param {Event} event
     *
     * @private
     */
    _onAddPromotionToCart(event) {
        event.preventDefault();
        const form = event.target;
        const selector = this.options.cartPromotionSelector;

        this._fireRequest(form, selector);
    }

    /**
     * Update all registered cart widgets
     *
     * @private
     */
    _fetchCartWidgets() {
        const CartWidgetPluginInstances = PluginManager.getPluginInstances('CartWidget');
        Iterator.iterate(CartWidgetPluginInstances, instance => instance.fetch());
    }

    /**
     * Update the OffCanvas content
     *
     * @private
     */
    _updateOffCanvasContent(response) {
        OffCanvas.setContent(response, false, this._registerEvents.bind(this));
    }
}
