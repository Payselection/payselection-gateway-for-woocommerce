(function($){

    $(document).ready(function() {
        if ( $('body').hasClass('woocommerce-checkout')) {

            let checkoutForm = $('form.checkout'),
                widgetUrl = localStorage.payselectionWidgetUrl,
                widgetError = localStorage.payselectionWidgetError;

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
    });

}(jQuery));