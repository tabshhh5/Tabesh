/**
 * Product Pricing Admin JavaScript
 * Handles interactive features for the pricing configuration interface
 * 
 * @package Tabesh
 * @since 1.0.5
 */

(function($) {
	'use strict';

	/**
	 * Initialize when document is ready
	 */
	$(document).ready(function() {
		initExtraTypeToggles();
	});

	/**
	 * Initialize extras type select handlers
	 * Shows/hides step input based on selected type
	 */
	function initExtraTypeToggles() {
		// Handle change event on extras type select
		$('.extra-type-select').on('change', function() {
			var $row = $(this).closest('.extra-row');
			var $stepInput = $row.find('.extra-step-input');
			var $stepHelp = $row.find('.step-help');
			var selectedType = $(this).val();
			
			if (selectedType === 'page_based') {
				$stepInput.show();
				$stepHelp.show();
			} else {
				$stepInput.hide();
				$stepHelp.hide();
			}
		});
		
		// Initialize on page load - trigger change for all selects
		$('.extra-type-select').each(function() {
			$(this).trigger('change');
		});
	}

})(jQuery);
