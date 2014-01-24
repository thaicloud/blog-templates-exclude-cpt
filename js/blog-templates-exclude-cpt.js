jQuery(document).ready(function($){
	// When template dropdown is changed, reload checkboxes via Ajax
	$('select#template-exclude-cpt-settings').on('change', function() {
		if(!this.value == ''){
			$('#bte-loading').show();
			$('select#template-exclude-cpt-settings').attr('disabled', true);
			data = {
				action: 'exclude_cpt_ajax_populate',
				blog_id: this.value,
				bte_nonce: bte_vars.bte_nonce
			};

			$.post( ajaxurl, data, function( response ){
				$('#exclude-cpt-ajax').html( response );
				$('#bte-loading').hide();
				$('#template-exclude-cpt-settings').attr('disabled', false);
			});
		}else{
			$('#exclude-cpt-ajax').html( ' ' );
		}
	});
});