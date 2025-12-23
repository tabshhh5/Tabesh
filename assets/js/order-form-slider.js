/**
 * Tabesh Order Form Slider Integration
 * 
 * Modern multi-step form with Revolution Slider integration via custom events.
 * Emits 'tabesh:formStateChange' events on every field change for real-time slider updates.
 * 
 * @package Tabesh
 */

(function($) {
	'use strict';

	// Form state management
	const formState = {
		book_title: '',
		book_size: '',
		paper_type: '',
		paper_weight: '',
		print_type: '',
		page_count: 100,
		quantity: 10,
		binding_type: '',
		cover_weight: '',
		extras: [],
		notes: '',
		calculated_price: null,
		step: 1
	};

	// Current step tracking
	let currentStep = 1;
	const totalSteps = 3;

	/**
	 * Initialize form on document ready
	 */
	$(document).ready(function() {
		if ($('#tabesh-slider-form').length === 0) {
			return;
		}

		console.log('Initializing Tabesh Slider Form...');
		initializeForm();
	});

	/**
	 * Initialize form
	 */
	function initializeForm() {
		attachEventListeners();
		showStep(1);
		
		// Emit initial state
		emitFormStateChange('init');
	}

	/**
	 * Attach all event listeners
	 */
	function attachEventListeners() {
		// Field change listeners with event emission
		$('[data-event-field]').on('change input', function() {
			const fieldName = $(this).data('event-field');
			const fieldValue = $(this).is(':checkbox') ? 
				($(this).is(':checked') ? $(this).val() : null) : 
				$(this).val();

			updateFormState(fieldName, fieldValue);
		});

		// Book size selection
		$('input[name="book_size"]').on('change', function() {
			const bookSize = $(this).val();
			formState.book_size = bookSize;
			loadOptionsForBookSize(bookSize);
			emitFormStateChange('book_size');
		});

		// Paper type selection
		$('#slider_paper_type').on('change', function() {
			const paperType = $(this).val();
			formState.paper_type = paperType;
			loadPaperWeights(paperType);
			emitFormStateChange('paper_type');
		});

		// Paper weight selection
		$('#slider_paper_weight').on('change', function() {
			formState.paper_weight = $(this).val();
			loadPrintTypes();
			emitFormStateChange('paper_weight');
		});

		// Print type selection
		$('input[name="print_type"]').on('change', function() {
			formState.print_type = $(this).val();
			emitFormStateChange('print_type');
		});

		// Binding type selection
		$('#slider_binding_type').on('change', function() {
			const bindingType = $(this).val();
			formState.binding_type = bindingType;
			loadCoverWeights();
			loadExtras();
			emitFormStateChange('binding_type');
		});

		// Cover weight selection
		$('#slider_cover_weight').on('change', function() {
			formState.cover_weight = $(this).val();
			emitFormStateChange('cover_weight');
		});

		// Extras (dynamic checkboxes)
		$(document).on('change', '#slider_extras_container input[type="checkbox"]', function() {
			updateExtrasState();
			emitFormStateChange('extras');
		});

		// Navigation buttons
		$('#slider_prev_btn').on('click', handlePrevious);
		$('#slider_next_btn').on('click', handleNext);
		$('#slider_submit_btn').on('click', handleSubmit);

		// Calculate price button
		$('#slider_calculate_btn').on('click', calculatePrice);
	}

	/**
	 * Update form state
	 */
	function updateFormState(fieldName, fieldValue) {
		if (formState.hasOwnProperty(fieldName)) {
			formState[fieldName] = fieldValue;
			emitFormStateChange(fieldName);
		}
	}

	/**
	 * Emit form state change event
	 * 
	 * This is the core event system for Revolution Slider integration.
	 * The event contains complete form state and can be listened to from anywhere.
	 */
	function emitFormStateChange(changedField) {
		// Create event detail with complete state
		const eventDetail = {
			changed: changedField,
			timestamp: new Date().toISOString(),
			state: {
				book_title: formState.book_title,
				book_size: formState.book_size,
				paper_type: formState.paper_type,
				paper_weight: formState.paper_weight,
				print_type: formState.print_type,
				page_count: formState.page_count,
				quantity: formState.quantity,
				binding_type: formState.binding_type,
				cover_weight: formState.cover_weight,
				extras: formState.extras.slice(), // Copy array
				notes: formState.notes,
				calculated_price: formState.calculated_price,
				current_step: currentStep
			}
		};

		// Emit custom event on document (globally accessible)
		const event = new CustomEvent('tabesh:formStateChange', {
			detail: eventDetail,
			bubbles: true,
			cancelable: false
		});
		document.dispatchEvent(event);

		// Also emit on form element for scoped listeners
		$('#tabesh-slider-form').trigger('tabesh:formStateChange', [eventDetail]);

		// Debug logging
		if (typeof tabeshSliderForm !== 'undefined' && window.console && window.console.log) {
			console.log('Tabesh Form State Changed:', changedField, eventDetail.state);
		}
	}

	/**
	 * Show specific step
	 */
	function showStep(step) {
		// Update current step
		currentStep = step;
		formState.step = step;

		// Hide all steps
		$('.slider-form-step').removeClass('active');
		
		// Show current step
		$(`.slider-form-step[data-step="${step}"]`).addClass('active');
		
		// Update progress
		updateProgressBar(step);
		
		// Update navigation buttons
		updateNavigationButtons(step);

		// If final step, populate review
		if (step === 3) {
			populateOrderSummary();
		}

		// Emit step change event
		emitFormStateChange('step');
	}

	/**
	 * Update progress bar
	 */
	function updateProgressBar(step) {
		const percentage = ((step - 1) / (totalSteps - 1)) * 100;
		$('#sliderProgressBar').css('width', percentage + '%');

		// Update labels
		$('.progress-label').removeClass('active completed');
		for (let i = 1; i <= totalSteps; i++) {
			if (i < step) {
				$(`.progress-label[data-step="${i}"]`).addClass('completed');
			} else if (i === step) {
				$(`.progress-label[data-step="${i}"]`).addClass('active');
			}
		}
	}

	/**
	 * Update navigation buttons
	 */
	function updateNavigationButtons(step) {
		// Previous button
		if (step === 1) {
			$('#slider_prev_btn').hide();
		} else {
			$('#slider_prev_btn').show();
		}

		// Next/Submit buttons
		if (step === totalSteps) {
			$('#slider_next_btn').hide();
			$('#slider_submit_btn').show();
		} else {
			$('#slider_next_btn').show();
			$('#slider_submit_btn').hide();
		}
	}

	/**
	 * Handle next button
	 */
	function handleNext() {
		if (validateCurrentStep()) {
			if (currentStep < totalSteps) {
				showStep(currentStep + 1);
			}
		}
	}

	/**
	 * Handle previous button
	 */
	function handlePrevious() {
		if (currentStep > 1) {
			showStep(currentStep - 1);
		}
	}

	/**
	 * Validate current step
	 */
	function validateCurrentStep() {
		let isValid = true;
		let message = '';

		switch (currentStep) {
			case 1:
				if (!formState.book_title) {
					message = tabeshSliderForm.i18n.invalidField + ': ' + 'عنوان کتاب';
					isValid = false;
				} else if (!formState.book_size) {
					message = tabeshSliderForm.i18n.invalidField + ': ' + 'قطع کتاب';
					isValid = false;
				} else if (!formState.paper_type) {
					message = tabeshSliderForm.i18n.invalidField + ': ' + 'نوع کاغذ';
					isValid = false;
				} else if (!formState.paper_weight) {
					message = tabeshSliderForm.i18n.invalidField + ': ' + 'گرماژ کاغذ';
					isValid = false;
				} else if (!formState.print_type) {
					message = tabeshSliderForm.i18n.invalidField + ': ' + 'نوع چاپ';
					isValid = false;
				} else if (!formState.page_count || formState.page_count <= 0) {
					message = tabeshSliderForm.i18n.invalidField + ': ' + 'تعداد صفحات';
					isValid = false;
				} else if (!formState.quantity || formState.quantity <= 0) {
					message = tabeshSliderForm.i18n.invalidField + ': ' + 'تیراژ';
					isValid = false;
				}
				break;

			case 2:
				if (!formState.binding_type) {
					message = tabeshSliderForm.i18n.invalidField + ': ' + 'نوع صحافی';
					isValid = false;
				} else if (!formState.cover_weight) {
					message = tabeshSliderForm.i18n.invalidField + ': ' + 'گرماژ جلد';
					isValid = false;
				}
				break;

			case 3:
				if (!formState.calculated_price) {
					message = tabeshSliderForm.i18n.calculateFirst;
					isValid = false;
				}
				break;
		}

		if (!isValid) {
			showMessage(message, 'error');
		}

		return isValid;
	}

	/**
	 * Load options for selected book size
	 */
	function loadOptionsForBookSize(bookSize) {
		showLoading();

		$.ajax({
			url: tabeshSliderForm.apiUrl + '/get-allowed-options',
			method: 'POST',
			headers: {
				'X-WP-Nonce': tabeshSliderForm.nonce
			},
			contentType: 'application/json',
			data: JSON.stringify({
				book_size: bookSize,
				current_selection: {}
			}),
			success: function(response) {
				hideLoading();

				if (response.success && response.data) {
					populatePaperTypes(response.data.allowed_papers);
					populateBindingTypes(response.data.allowed_bindings);
				} else {
					showMessage(response.message || tabeshSliderForm.i18n.error, 'error');
				}
			},
			error: function(xhr) {
				hideLoading();
				showMessage(tabeshSliderForm.i18n.error, 'error');
				console.error('API Error:', xhr);
			}
		});
	}

	/**
	 * Populate paper types dropdown
	 */
	function populatePaperTypes(papers) {
		const $select = $('#slider_paper_type');
		$select.empty();
		$select.append('<option value="">' + tabeshSliderForm.i18n.selectFirst + '</option>');

		if (!papers || papers.length === 0) {
			$select.append('<option value="" disabled>' + tabeshSliderForm.i18n.noOptions + '</option>');
			return;
		}

		papers.forEach(function(paper) {
			$select.append(
				$('<option></option>')
					.val(paper.type)
					.text(paper.type)
					.data('weights', paper.weights)
			);
		});
	}

	/**
	 * Load paper weights for selected paper type
	 */
	function loadPaperWeights(paperType) {
		const $paperSelect = $('#slider_paper_type');
		const $weightSelect = $('#slider_paper_weight');
		const selectedOption = $paperSelect.find('option:selected');
		const weights = selectedOption.data('weights');

		$weightSelect.empty();
		$weightSelect.append('<option value="">' + tabeshSliderForm.i18n.selectFirst + '</option>');

		if (!weights || weights.length === 0) {
			$weightSelect.append('<option value="" disabled>' + tabeshSliderForm.i18n.noOptions + '</option>');
			return;
		}

		weights.forEach(function(weightInfo) {
			$weightSelect.append(
				$('<option></option>')
					.val(weightInfo.weight)
					.text(weightInfo.weight)
					.data('print-types', weightInfo.allowed_print_types)
			);
		});
	}

	/**
	 * Load print types (enable/disable based on weight selection)
	 */
	function loadPrintTypes() {
		const $weightSelect = $('#slider_paper_weight');
		const selectedOption = $weightSelect.find('option:selected');
		const allowedPrintTypes = selectedOption.data('print-types');

		// Enable/disable print type options
		$('input[name="print_type"]').each(function() {
			const printType = $(this).val();
			if (allowedPrintTypes && allowedPrintTypes.indexOf(printType) === -1) {
				$(this).prop('disabled', true).prop('checked', false);
				$(this).closest('.print-option-card').addClass('disabled');
			} else {
				$(this).prop('disabled', false);
				$(this).closest('.print-option-card').removeClass('disabled');
			}
		});
	}

	/**
	 * Populate binding types dropdown
	 */
	function populateBindingTypes(bindings) {
		const $select = $('#slider_binding_type');
		$select.empty();
		$select.append('<option value="">' + tabeshSliderForm.i18n.selectFirst + '</option>');

		if (!bindings || bindings.length === 0) {
			$select.append('<option value="" disabled>' + tabeshSliderForm.i18n.noOptions + '</option>');
			return;
		}

		bindings.forEach(function(binding) {
			$select.append(
				$('<option></option>')
					.val(binding.type)
					.text(binding.type)
			);
		});
	}

	/**
	 * Load cover weights
	 */
	function loadCoverWeights() {
		// For now, use default cover weights from global settings
		// In production, this would be filtered based on binding type
		const $select = $('#slider_cover_weight');
		$select.empty();
		$select.append('<option value="">' + tabeshSliderForm.i18n.selectFirst + '</option>');
		
		// Default cover weights (could be fetched from API)
		const defaultWeights = ['250', '300'];
		defaultWeights.forEach(function(weight) {
			$select.append(
				$('<option></option>')
					.val(weight)
					.text(weight)
			);
		});
	}

	/**
	 * Load extras (checkboxes)
	 */
	function loadExtras() {
		// Fetch extras from API based on book size and binding
		showLoading();

		$.ajax({
			url: tabeshSliderForm.apiUrl + '/get-allowed-options',
			method: 'POST',
			headers: {
				'X-WP-Nonce': tabeshSliderForm.nonce
			},
			contentType: 'application/json',
			data: JSON.stringify({
				book_size: formState.book_size,
				current_selection: {
					binding_type: formState.binding_type
				}
			}),
			success: function(response) {
				hideLoading();

				if (response.success && response.data && response.data.allowed_extras) {
					populateExtras(response.data.allowed_extras);
				}
			},
			error: function() {
				hideLoading();
			}
		});
	}

	/**
	 * Populate extras checkboxes
	 */
	function populateExtras(extras) {
		const $container = $('#slider_extras_container');
		$container.empty();

		if (!extras || extras.length === 0) {
			$container.append('<p class="no-extras">' + tabeshSliderForm.i18n.noOptions + '</p>');
			return;
		}

		extras.forEach(function(extra) {
			const $checkbox = $('<label class="extra-checkbox"></label>')
				.append(
					$('<input type="checkbox" name="extras[]">')
						.val(extra)
						.attr('data-event-field', 'extras')
				)
				.append(
					$('<span class="extra-label"></span>').text(extra)
				);
			
			$container.append($checkbox);
		});
	}

	/**
	 * Update extras state
	 */
	function updateExtrasState() {
		const selectedExtras = [];
		$('#slider_extras_container input[type="checkbox"]:checked').each(function() {
			selectedExtras.push($(this).val());
		});
		formState.extras = selectedExtras;
	}

	/**
	 * Calculate price
	 */
	function calculatePrice() {
		if (!validatePriceCalculation()) {
			return;
		}

		showLoading();

		const priceData = {
			book_size: formState.book_size,
			paper_type: formState.paper_type,
			paper_weight: formState.paper_weight,
			print_type: formState.print_type,
			page_count: parseInt(formState.page_count, 10),
			quantity: parseInt(formState.quantity, 10),
			binding_type: formState.binding_type,
			cover_paper_weight: formState.cover_weight,
			extras: formState.extras
		};

		$.ajax({
			url: tabeshSliderForm.apiUrl + '/calculate-price',
			method: 'POST',
			headers: {
				'X-WP-Nonce': tabeshSliderForm.nonce
			},
			contentType: 'application/json',
			data: JSON.stringify(priceData),
			success: function(response) {
				hideLoading();

				if (response.success && response.data) {
					formState.calculated_price = response.data;
					displayPrice(response.data);
					showMessage(tabeshSliderForm.i18n.priceCalculated, 'success');
					emitFormStateChange('price_calculated');
				} else {
					showMessage(response.message || tabeshSliderForm.i18n.error, 'error');
				}
			},
			error: function(xhr) {
				hideLoading();
				showMessage(tabeshSliderForm.i18n.error, 'error');
				console.error('Price calculation error:', xhr);
			}
		});
	}

	/**
	 * Validate data before price calculation
	 */
	function validatePriceCalculation() {
		if (!formState.book_size || !formState.paper_type || !formState.paper_weight ||
			!formState.print_type || !formState.binding_type || !formState.cover_weight) {
			showMessage(tabeshSliderForm.i18n.pleaseFillAllFields, 'error');
			return false;
		}
		return true;
	}

	/**
	 * Display calculated price
	 */
	function displayPrice(priceData) {
		const $priceDisplay = $('#slider_total_price');
		
		if (priceData && priceData.total_price) {
			const formattedPrice = new Intl.NumberFormat('fa-IR').format(priceData.total_price);
			$priceDisplay.html(`<strong>${formattedPrice}</strong> تومان`);
			$priceDisplay.addClass('calculated');
		}
	}

	/**
	 * Populate order summary
	 */
	function populateOrderSummary() {
		const $summary = $('#slider_order_summary');
		$summary.empty();

		const summaryItems = [
			{ label: 'عنوان کتاب', value: formState.book_title },
			{ label: 'قطع کتاب', value: formState.book_size },
			{ label: 'نوع کاغذ', value: formState.paper_type },
			{ label: 'گرماژ کاغذ', value: formState.paper_weight },
			{ label: 'نوع چاپ', value: formState.print_type === 'bw' ? 'سیاه و سفید' : 'رنگی' },
			{ label: 'تعداد صفحات', value: formState.page_count },
			{ label: 'تیراژ', value: formState.quantity },
			{ label: 'نوع صحافی', value: formState.binding_type },
			{ label: 'گرماژ جلد', value: formState.cover_weight }
		];

		if (formState.extras.length > 0) {
			summaryItems.push({ label: 'خدمات اضافی', value: formState.extras.join('، ') });
		}

		summaryItems.forEach(function(item) {
			if (item.value) {
				$summary.append(
					`<div class="summary-row">
						<span class="summary-label">${item.label}:</span>
						<span class="summary-value">${item.value}</span>
					</div>`
				);
			}
		});
	}

	/**
	 * Handle form submission
	 */
	function handleSubmit(e) {
		if (e) {
			e.preventDefault();
		}

		if (!formState.calculated_price) {
			showMessage(tabeshSliderForm.i18n.calculateFirst, 'error');
			return;
		}

		showLoading();

		const orderData = {
			book_title: formState.book_title,
			book_size: formState.book_size,
			paper_type: formState.paper_type,
			paper_weight: formState.paper_weight,
			print_type: formState.print_type,
			page_count: parseInt(formState.page_count, 10),
			quantity: parseInt(formState.quantity, 10),
			binding_type: formState.binding_type,
			cover_paper_weight: formState.cover_weight,
			extras: formState.extras,
			notes: formState.notes,
			total_price: formState.calculated_price.total_price
		};

		$.ajax({
			url: tabeshSliderForm.apiUrl + '/submit-order',
			method: 'POST',
			headers: {
				'X-WP-Nonce': tabeshSliderForm.nonce
			},
			contentType: 'application/json',
			data: JSON.stringify(orderData),
			success: function(response) {
				hideLoading();

				if (response.success) {
					showMessage(tabeshSliderForm.i18n.orderSubmitted, 'success');
					emitFormStateChange('order_submitted');
					
					// Redirect after short delay
					setTimeout(function() {
						window.location.href = tabeshSliderForm.userOrdersUrl;
					}, 1500);
				} else {
					showMessage(response.message || tabeshSliderForm.i18n.error, 'error');
				}
			},
			error: function(xhr) {
				hideLoading();
				showMessage(tabeshSliderForm.i18n.error, 'error');
				console.error('Order submission error:', xhr);
			}
		});
	}

	/**
	 * Show loading overlay
	 */
	function showLoading() {
		$('#slider_loading_overlay').fadeIn(200);
	}

	/**
	 * Hide loading overlay
	 */
	function hideLoading() {
		$('#slider_loading_overlay').fadeOut(200);
	}

	/**
	 * Show message
	 */
	function showMessage(message, type) {
		const $container = $('#slider_message_container');
		const $message = $('<div class="form-message"></div>')
			.addClass('message-' + type)
			.text(message);

		$container.empty().append($message).fadeIn();

		setTimeout(function() {
			$message.fadeOut(function() {
				$(this).remove();
			});
		}, 5000);
	}

	// Expose API for external access (e.g., Revolution Slider integration)
	window.TabeshSliderForm = {
		getState: function() {
			return Object.assign({}, formState);
		},
		addEventListener: function(callback) {
			document.addEventListener('tabesh:formStateChange', callback);
		},
		removeEventListener: function(callback) {
			document.removeEventListener('tabesh:formStateChange', callback);
		}
	};

})(jQuery);
