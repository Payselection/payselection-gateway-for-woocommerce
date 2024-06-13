(function($){

    $(document).ready(function() {
        if ( $('body').hasClass('woocommerce-checkout')) {

            let checkoutForm = $('form.checkout').length > 0 ? $('form.checkout') : $('.wp-block-woocommerce-checkout >.wc-block-components-notices');
            if (checkoutForm.length) {
                
                let widgetUrl = localStorage.payselectionWidgetUrl;
                let widgetErrorCode = localStorage.payselectionWidgetErrorCode;
                let errorMeggage = localStorage.errorMessage ? ' - '+localStorage.errorMessage : '';
                let widgetError = payselection.payselection_error + payselection.payselection_widget_errors[widgetErrorCode] + errorMeggage || widgetErrorCode

                if ( typeof widgetUrl !== 'undefined' 
                    && typeof widgetError !== 'undefined'
                    && document.referrer === localStorage.payselectionWidgetUrl ) {
                        
                        if ( widgetError && widgetError !== 'PAY_WIDGET:CLOSE_AFTER_FAIL' ) {
                            $(checkoutForm).prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><ul class="woocommerce-error" role="alert"><li>' + widgetError + '</li></ul></div>' );
                        }

                        localStorage.payselectionWidgetUrl = '';
                        localStorage.payselectionWidgetError = '';
                }

            }
            
        }
    });

}(jQuery));