import {renderError, closeFormModal} from "./ax-form";
import Swal from "sweetalert2";
import toast from "@app/services/toasts";

export function AxPlugValidation($th) {

    $th.on('click', '[type=submit]', function (event) {

        event.preventDefault();
        const $btn = $(this)
        $btn.off()
        //
        // Custom form loader
        $th.find('.modal-body').addClass('ajax-updating-content')

        console.log('Ajax submitting form for validation...');

        let $form = $btn.closest('form')

        $.ajax({
            url: $form.attr('action'),
            type: $form.attr('method'),
            data: $form.serialize(),
            headers: {"X-Form-validation": 'yes'},
            statusCode: {
                // Nonstandard response to handler form validation
                207: function (html) {
                    console.log('Form error response');
                    $th.find('.modal-body').removeClass('ajax-updating-content')
                    $(window).trigger('forms-ready', html);
                    $th.html(html);
                },
                // Just try to capture redirect response without any luck
                // 302: function (data, textStatus, request) {
                //     window.location = data;
                // },
                // not logged in handler
                401: function () {
                    window.location.reload();
                },
            },
            success: function (data, textStatus, xhr) {
                //
                // Check validation is OK
                if(data.valid) {
                    $form.submit()
                }

            },
        });
        return false;
    })

}


export function AxPlugSubmitter($th) {

    console.log('[AxForm] Ajax submit plugin is active!')
    const $submit = $th.find('[type=submit]'), $eraser = $th.find('a#ax-form-erase-button')
    //
    // Clearing the default propaganda to confirm
    $eraser.removeClass('confirm-require')

    //
    // Erase button
    $eraser.on('click', function (event) {

        event.preventDefault();

        let message = "You won't be able to revert this!", $th = $(this);
        if($th.data('message')) {
            message = $th.data('message')
        }

        Swal.fire({
            title: 'Are you sure you want to delete this element?',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Do it!',
            buttonsStyling: false,
            customClass: {
                cancelButton: 'btn btn-danger',
                confirmButton: 'btn btn-success me-3'
            }
        }).then(result => {
            if (!result.isConfirmed)
                return;

            const link = $th.attr('href'), type = 'json'
            $.ajax({
                url: link, method: 'GET', dataType: type, headers: {"AxForm-erasing": type},
                success: function (data) {
                    closeFormModal()
                    // The response has been parsed as JSON and is available as a JavaScript object
                    // dialog.close()
                    console.log('element removed from link ', link)
                    toast.success('The item is no longer there', 'Removed');
                },
                error: function (xhr, status, error) {
                    closeFormModal()
                    console.error(xhr, error, status)
                    alert('Something went wrong, please check is everything right. ');
                },
            })

        })

    })

    //
    // Submit
    $submit.on('click', function (event) {
        const $btn = $(this)
        $btn.off()
        event.preventDefault();
        //
        // Custom form loader
        $th.find('.modal-body').addClass('ajax-updating-content')

        $submit.prop('disabled', true);

        let $form = $btn.closest('form')


        const model = {
            form: $form,
            path: $form.attr('action'),
            type: $form.attr('method'),
            data: $form.serialize(),
        }


        $.ajax({
            url: model.path,
            type: model.type,
            data: model.data,
            headers: {"X-Form-validation": 'yes'},
            statusCode: {
                // Nonstandard response to handler form validation
                207: function (html) {
                    console.log('Form error response');

                    $(window).trigger('forms-ready', html);
                    $th.html(html);
                },
                // Just try to capture redirect response without any luck
                // 302: function (data, textStatus, request) {
                //     window.location = data;
                // },
                // not logged in handler
                401: function () {
                    window.location.reload();
                },
            },
            success: function (data, textStatus, xhr) {
                //
                // Check validation is OK
                if(data.valid) {

                    let opt = {
                        url: model.path,
                        type: model.type,
                        data: model.data,
                        success: function (data) {
                            closeFormModal()
                        },
                    }
                    console.log('AJAX SUBMIT REQUIRE')

                    //
                    // Setup for uploads in ajax form
                    //      Note for this form files should be NOT be required!
                    if($form.attr('enctype') === 'multipart/form-data') {

                        console.log('Form with FILES are detected...')

                        let formData = new FormData($form[0]);
                        opt.processData = false
                        opt.contentType = false
                        opt.enctype = 'multipart/form-data'

                        opt.data = formData
                    }


                    $.ajax(opt).fail(function (XMLHttpRequest) {
                        renderError(XMLHttpRequest)
                    })
                }
            },
        }).fail(function (XMLHttpRequest) {
            renderError(XMLHttpRequest)
        }).always(function () {
            $submit.prop('disabled', false)
            $th.find('.modal-body').removeClass('ajax-updating-content')
        });
        return false;
    })

}