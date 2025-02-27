import Plugin from 'src/script/helper/plugin/plugin.class';
import DomAccess from 'src/script/helper/dom-access.helper';
import ViewportDetection from 'src/script/helper/viewport-detection.helper';
import Iterator from 'src/script/helper/iterator.helper';

export default class CollapseFooterColumnsPlugin extends Plugin {

    static options = {
        collapseShowClass: 'show',
        collapseColumnSelector: '.js-footer-column',
        collapseColumnTriggerSelector: '.js-collapse-footer-column-trigger',
        collapseColumnContentSelector: '.js-footer-column-content'
    };

    init() {
        this._columns = this.el.querySelectorAll(this.options.collapseColumnSelector);

        this._registerEvents();
    }

    /**
     * Register event listeners
     * @private
     */
    _registerEvents() {
        document.addEventListener(ViewportDetection.EVENT_VIEWPORT_HAS_CHANGED(), this._onViewportHasChanged.bind(this));
    }

    /**
     * If viewport has changed verify whether to add event listeners to the
     * column headlines for triggering collapse toggling or not
     * @private
     */
    _onViewportHasChanged() {
        const event = 'click';

        Iterator.iterate(this._columns, column => {
            const trigger = DomAccess.querySelector(column, this.options.collapseColumnTriggerSelector);

            // remove possibly existing event listeners
            trigger.removeEventListener(event, this._onClickCollapseTrigger);

            // add event listener if currently in an allowed viewport
            if (this._isInAllowedViewports()) {
                trigger.addEventListener(event, this._onClickCollapseTrigger.bind(this));
            }
        });
    }

    /**
     * On clicking the collapse trigger (column headline) the columns
     * content area shall be toggled open/close
     * @param {Event} event
     * @private
     */
    _onClickCollapseTrigger(event) {
        const trigger = event.target;
        const collapse = trigger.parentNode.querySelector(this.options.collapseColumnContentSelector);
        const $collapse = $(collapse);
        const collapseShowClass = this.options.collapseShowClass;

        $collapse.collapse('toggle');

        $collapse.on('shown.bs.collapse', function () {
            trigger.classList.add(collapseShowClass);
        });

        $collapse.on('hidden.bs.collapse', function () {
            trigger.classList.remove(collapseShowClass);
        });
    }

    /**
     * Returns if the browser is in the allowed viewports
     * @returns {boolean}
     * @private
     */
    _isInAllowedViewports() {
        return (ViewportDetection.isXS() || ViewportDetection.isSM());
    }

}
