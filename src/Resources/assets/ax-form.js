import {AxPlugSubmitter, AxPlugValidation} from "./ax-plugs";
import {Modal} from 'bootstrap';
import Swal from "sweetalert2";

/**
 * Contains a list of bind buttons
 * @type {string}
 * @private
 */

const AxFormModal = '#ax-form-modal'
const AxFormContainer = '#ax-form-content'
const AxFormSubmitBtn = '#ax-form-ajax-submit'
let globAxModal = null


export class AxForm {

    bindContainer = [];
    formLoaded = null;
    th
    modal = null
    formLink = null

    btn = null

    onReadyCallback = null;

    /**
     *
     * @param {string|HTMLElement|jQuery} clickFrom
     */
    constructor(clickFrom) {

        this.th = $(AxFormContainer)
        this.#resolvePreloader()

        this.btn = $(clickFrom);
        if(!this.modal)
            globAxModal = this.modal = new Modal(AxFormModal)


        this.openForm()
    }

    onReady(callback) {
        this.onReadyCallback = callback
    }

    /**
     *
     * @returns {jQuery}
     */
    getContent() {
        return this.th
    }

    /**
     *
     * @returns {jQuery}
     */
    getButton() {
        return this.btn
    }

    getModal() {
        return this.modal
    }

    /**
     * Focus the first input filed
     */
    #focusFirstField() {
        let $first = $('input:visible:enabled:not([readonly]),textarea:visible:enabled:not([readonly]),select:visible:enabled:not([readonly])', this.th).first()
        if($first.val()) {
            let strLength = $first.val().length * 2;
            $first.focus();
            if($first.attr('type') !== "checkbox" && $first.attr('type') !== "radio" && $first.attr('date')) {
                $first[0].setSelectionRange(strLength, strLength);
            }
        }
    }

    /**
     * Provides option to use several steps from axForm
     */
    #ajaxSubmitSteps() {

        let $next = this.th.find(AxFormSubmitBtn);

        if($next.length === 0) return;
        console.info('Found steps form ...')

        let clicks = $next.data('clicks'), $submit = this.th.find('[type="submit"]')

        //
        // Somehow click $next does not work,
        //  that's way is used document listener for click.
        $(document).on('click', AxFormSubmitBtn, () => {
            let step = $next.data('step');
            let click = step ?? 0;

            console.info('Next step clicked...', click, clicks);
            if(click >= clicks) {
                $next.hide(300)
                $submit.show(300)
                console.info('Time to submit steps form ...');
            } else {
                let $form = this.th.find('form'), actionUrl = $form.attr('action');
                $.ajax({
                    type: "POST",
                    url: actionUrl,
                    data: $form.serialize(), // serializes the form's elements.
                    success: (data) => {
                        $next.data('step', click);
                        this.th.html(data);
                        this.#emitFormReadyEvent(data)
                        click++;
                    },
                });
            }

        })

    }

    /**
     * Listen form contents for custom modal sizes
     */
    #resolveModalSize() {

        //
        // Handle large modal
        const $dialog = this.th.parent(), $form = this.th.find('form');

        // console.log('probing modal size', $form, $form.hasClass('ax-form-wide-modal-required'))

        if($form.hasClass('ax-form-wide-modal-required')) {
            this.th.parent().addClass('modal-lg')
        } else $dialog.removeClass('modal-lg')

        //
        // Handle extra large
        if($form.hasClass('ax-form-extra-wide-modal-required')) {
            $dialog.addClass('modal-xl')
        } else $dialog.removeClass('modal-xl')


    }

    /**
     * Option to customize form load animation
     */
    #resolvePreloader() {

        if(this.th.data('loader')) {
            this.th.html(this.th.data('loader'))
            console.log("[AxForm] Custom Form data-loader: " + this.th.data('loader'));
        } else
            this.th.html(window.defLoader)

    }

    /**
     * Compiles form URL with options to append data
     * @returns {string}
     */
    #getFormUrl() {

        //
        // Fixes the Ajax/Vue changed values,
        //  data method retrieves only initial value
        const formLink = this.btn.attr('data-ax-form');

        console.log('AxForm link: ', formLink)
        //
        // Additional Query callback
        let query = ""
        let qCall = this.btn.attr('data-query-callback')

        if(qCall)
            console.log('[AxForm] Query callback:', qCall)

        let queryFunction = eval(qCall)
        if(typeof queryFunction == 'function') {
            query = formLink.includes('?') ? '&' : "?"
            query += queryFunction(this.th)
            console.log("[AxForm]  form capture query callback " + qCall + " push query " + query)
        }

        return formLink + query;
    }

    /**
     * Form opener...
     */
    openForm() {


        setTimeout(() => {
            this.modal.show()
        }, 270)

        console.log('[AxForm] Form opened:', this.th)
        //
        // General resolvers
        this.formLink = this.#getFormUrl();
        const json = this.btn.attr('data-json');

        if(json) {
            const decodedData = decodeURIComponent(json);
            if(!decodedData) {
                console.error('Decode data is empty or broken');
                return;
            }
            this.#jsonRequestForm(this.formLink, decodedData)
        } else {

            console.log('BS' + this.btn.data('uiv'))

            $.ajax({
                url: this.formLink,
                method: "GET",
                data: json,
                headers: {"X-UI-Version": this.btn.data('uiv') || 'bs4'}, // defines UI version
            }).always((data, status, request) => {
                this.th.html(data)
                // console.log("RESPONSE DATA", data)
                this.responseHandler(data, status, data)

            });

            /* Past version, kept as backup
                this.th.load(this.formLink, {limit: 250}, (data, status, request) => {
                this.responseHandler(data, status, request)
            });*/
        }


    }

    /**
     * JSON request for AxForm load
     * @param url
     * @param json
     */
    #jsonRequestForm(url, json) {
        $.ajax({
            url: url,
            method: "POST",
            data: json,
            headers: {"X-UI-Version": this.btn.data('uiv') || 'bs4'}, // defines UI version
            contentType: "application/json",

        }).always((data, status, request) => {

            this.th.html(data)
            // console.log("RESPONSE DATA", data)
            this.responseHandler(data, status, data)

        });
    }

    /**
     * Handle the response from the server
     * @param response
     * @param status
     * @param XMLHttpRequest
     * @returns {boolean}
     */
    responseHandler(response, status, XMLHttpRequest) {

        this.formLoaded = this.th;
        this.#handlePlugins()

        // XMLHttpRequest.response has the error info you want.
        if(status !== 'success') {


            //
            // Render error message
            renderError(XMLHttpRequest)
            return true;
        } else {

            this.#emitFormReadyEvent(response)
            this.#focusFirstField()

            //
            // Submit shortcut
            let self = this.th
            this.th.on('keydown', function (event) {
                if(event.ctrlKey && event.keyCode === 13) {
                    self.find('[type="submit"]').click()
                }
            })
            return false;
        }

    }

    /**
     * Additional integration of options
     */
    #handlePlugins() {

        //
        // Assuming plugins will not be changes,
        //      leaving data function to handle the contents on DOM value
        const pluginData = this.btn.data('ax-plugin');

        if(!pluginData) return;

        let plugins = pluginData.split(',')
        console.log('AX ', plugins, plugins.includes('ax-submit'))
        if(plugins.includes('ax-submit')) AxPlugSubmitter(this.th);
        if(plugins.includes('validation')) AxPlugValidation(this.th);
    }


    /**
     * Sends window event for refreshing
     */
    #emitFormReadyEvent(responseText) {

        this.#resolveModalSize()
        console.log('emit ready form event')

        this.#ajaxSubmitSteps(responseText)
        $(window).trigger('forms-ready', {
            'content': responseText,
            'form': this.th,
            'link': this.formLink,
            'id': this.th.attr('id'),
            'button': this.btn,
        });

        //dispatch event on the clicked dom element
        this.btn[0].dispatchEvent(new CustomEvent("form-ready", {
            detail: {
                content: responseText,
                form: this.th[0],
                link: this.formLink,
                id: this.th.attr('id'),
                modal: this.modal,
                button: this.btn[0],
            },
        }))

        if(this.onReadyCallback)
            this.onReadyCallback(this)
    }


}

/**
 * Close modal
 */
export function closeFormModal() {
    //
    // Close the modal
    globAxModal.hide();
    $(AxFormContainer).delay(200).html('')

}


/**
 * Renders server error
 * @param XMLHttpRequest
 */
export function renderError(XMLHttpRequest) {
    const error = XMLHttpRequest.responseText.split("<!DOCTYPE html>")[0];
    // alert(error.replace(/<!--|-->/gi, " "));

    Swal.fire({
        title: 'An error occur!',
        html: '<small>' + error.replace(/<!--|-->/gi, " ") + '</small>',
        icon: 'error',
        showCloseButton: true,
        didOpen: () => this.modal.hide(),
    }).then(() => closeFormModal())

}