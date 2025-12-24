/**
 * Revolution Slider Integration - Order Form with Event Dispatching
 * 
 * Extends the V2 form with real-time event dispatching for Revolution Slider.
 * Dispatches custom events on every field change for seamless slider integration.
 * 
 * @package Tabesh
 */

(function($) {
	'use strict';

	// Current step tracking
	let currentStep = 1;
	const totalSteps = 4;

	// Form state
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
		calculated_price: null
	};

	// Check if slider events are enabled
	let sliderEventsEnabled = true;
	let sliderId = '';

	/**
	 * Dispatch custom event for Revolution Slider integration
	 * 
	 * Sends the complete form state to any listening Revolution Slider
	 * or other integration. The event can be safely dispatched even
	 * without a listener - it will be ignored if no one is listening.
	 */
	function dispatchSliderEvent() {
		if (!sliderEventsEnabled) {
			return;
		}

		// Create a clean copy of form state for external consumption
		const eventData = {
			book_size: formState.book_size,
			paper_type: formState.paper_type,
			paper_weight: formState.paper_weight,
			print_type: formState.print_type,
			page_count: formState.page_count,
			quantity: formState.quantity,
			binding_type: formState.binding_type,
			cover_weight: formState.cover_weight,
			extras: formState.extras ? [...formState.extras] : [],
			calculated_price: formState.calculated_price,
			slider_id: sliderId
		};

		// Dispatch native JavaScript custom event
		const event = new CustomEvent('tabeshSliderUpdate', {
			detail: eventData,
			bubbles: true,
			cancelable: false
		});
		document.dispatchEvent(event);

		// Also make it available globally for direct access
		if (window.TabeshSlider === undefined) {
			window.TabeshSlider = {};
		}
		window.TabeshSlider.currentState = eventData;

		// Log for debugging (only in development)
		if (console && typeof console.log === 'function') {
			console.log('Tabesh Slider Event Dispatched:', eventData);
		}
	}

	/**
	 * Initialize wizard on document ready
	 */
	$(document).ready(function() {
		initWizard();
	});

	/**
	 * Initialize wizard
	 */
	function initWizard() {
		if ($('#tabesh-wizard-form').length === 0) {
			return;
		}

		console.log('Initializing Tabesh Slider Integration Wizard...');

		// Read slider configuration from container
		const $container = $('.tabesh-wizard-container.tabesh-slider-integration');
		if ($container.length > 0) {
			sliderEventsEnabled = $container.data('slider-events') !== 'false';
			sliderId = $container.data('slider-id') || '';
			console.log('Slider events enabled:', sliderEventsEnabled, 'Slider ID:', sliderId);
		}

		// Attach event listeners
		attachEventListeners();

		// Initialize first step
		showStep(1);

		// Dispatch initial state
		dispatchSliderEvent();
	}

	/**
	 * Attach all event listeners
	 */
	function attachEventListeners() {
		// Navigation buttons
		$('#nextBtn').on('click', handleNext);
		$('#prevBtn').on('click', handlePrevious);
		$('#submitBtn').on('click', handleSubmit);

		// Form field changes
		$('#book_title_wizard').on('input', function() {
			formState.book_title = $(this).val();
			dispatchSliderEvent();
		});

		$('input[name="book_size"]').on('change', function() {
			const bookSize = $(this).val();
			formState.book_size = bookSize;
			loadAllowedOptions({ book_size: bookSize });
			dispatchSliderEvent();
		});

		$('#paper_type_wizard').on('change', function() {
			const paperType = $(this).val();
			formState.paper_type = paperType;
			loadPaperWeights(paperType);
			dispatchSliderEvent();
		});

		$('#paper_weight_wizard').on('change', function() {
			formState.paper_weight = $(this).val();
			loadPrintTypes();
			dispatchSliderEvent();
		});

		$('input[name="print_type"]').on('change', function() {
			formState.print_type = $(this).val();
			dispatchSliderEvent();
		});

		$('#page_count_wizard').on('input', function() {
			formState.page_count = parseInt($(this).val(), 10);
			dispatchSliderEvent();
		});

		$('#quantity_wizard').on('input', function() {
			formState.quantity = parseInt($(this).val(), 10);
			dispatchSliderEvent();
		});

		$('#binding_type_wizard').on('change', function() {
			const bindingType = $(this).val();
			formState.binding_type = bindingType;
			loadCoverWeights();
			loadExtras();
			dispatchSliderEvent();
		});

		$('#cover_weight_wizard').on('change', function() {
			formState.cover_weight = $(this).val();
			dispatchSliderEvent();
		});

		$(document).on('change', '#extras_container_wizard input[type="checkbox"]', function() {
			updateExtrasState();
			dispatchSliderEvent();
		});

		$('#notes_wizard').on('input', function() {
			formState.notes = $(this).val();
			dispatchSliderEvent();
		});

		// Calculate price button
		$('#calculate_price_btn').on('click', function() {
			calculatePrice();
		});
	}

	/**
	 * Show specific step
	 */
	function showStep(step) {
		// Hide all steps
		$('.wizard-step').removeClass('active');
		
		// Show current step
		$(`.wizard-step[data-step="${step}"]`).addClass('active');
		
		// Update progress
		updateProgress(step);
		
		// Update navigation buttons
		updateNavigation(step);
		
		// Update current step
		currentStep = step;

		// If step 4, update review
		if (step === 4) {
			updateOrderReview();
		}
	}

	/**
	 * Update progress bar and steps
	 */
	function updateProgress(step) {
		const progress = (step / totalSteps) * 100;
		$('#progressBar').css('width', progress + '%');

		// Update step indicators
		$('.progress-step').each(function() {
			const stepNum = parseInt($(this).data('step'), 10);
			$(this).removeClass('active completed');
			
			if (stepNum < step) {
				$(this).addClass('completed');
			} else if (stepNum === step) {
				$(this).addClass('active');
			}
		});
	}

	/**
	 * Update navigation buttons
	 */
	function updateNavigation(step) {
		// Previous button
		if (step === 1) {
			$('#prevBtn').hide();
		} else {
			$('#prevBtn').show();
		}

		// Next button
		if (step === totalSteps) {
			$('#nextBtn').hide();
			$('#submitBtn').show();
		} else {
			$('#nextBtn').show();
			$('#submitBtn').hide();
		}
	}

	/**
	 * Handle next button click
	 */
	function handleNext() {
		if (!validateStep(currentStep)) {
			return;
		}

		if (currentStep < totalSteps) {
			showStep(currentStep + 1);
		}
	}

	/**
	 * Handle previous button click
	 */
	function handlePrevious() {
		if (currentStep > 1) {
			showStep(currentStep - 1);
		}
	}

	/**
	 * Validate current step
	 */
	function validateStep(step) {
		let isValid = true;
		let message = '';

		switch (step) {
			case 1:
				if (!formState.book_title) {
					message = 'لطفاً عنوان کتاب را وارد کنید';
					isValid = false;
				} else if (!formState.book_size) {
					message = 'لطفاً قطع کتاب را انتخاب کنید';
					isValid = false;
				}
				break;

			case 2:
				if (!formState.paper_type) {
					message = 'لطفاً نوع کاغذ را انتخاب کنید';
					isValid = false;
				} else if (!formState.paper_weight) {
					message = 'لطفاً گرماژ کاغذ را انتخاب کنید';
					isValid = false;
				} else if (!formState.print_type) {
					message = 'لطفاً نوع چاپ را انتخاب کنید';
					isValid = false;
				} else if (!formState.page_count || formState.page_count <= 0) {
					message = 'لطفاً تعداد صفحات معتبر وارد کنید';
					isValid = false;
				} else if (!formState.quantity || formState.quantity <= 0) {
					message = 'لطفاً تیراژ معتبر وارد کنید';
					isValid = false;
				}
				break;

			case 3:
				if (!formState.binding_type) {
					message = 'لطفاً نوع صحافی را انتخاب کنید';
					isValid = false;
				} else if (!formState.cover_weight) {
					message = 'لطفاً گرماژ جلد را انتخاب کنید';
					isValid = false;
				}
				break;

			case 4:
				if (!formState.calculated_price) {
					message = 'لطفاً ابتدا قیمت را محاسبه کنید';
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
	 * Load allowed options from API
	 */
	function loadAllowedOptions(selection) {
		showLoading();

		$.ajax({
			url: tabeshOrderFormV2.apiUrl + '/get-allowed-options',
			method: 'POST',
			headers: {
				'X-WP-Nonce': tabeshOrderFormV2.nonce
			},
			contentType: 'application/json',
			data: JSON.stringify({
				book_size: selection.book_size,
				current_selection: {}
			}),
			success: function(response) {
				hideLoading();

				if (response.success && response.data) {
					populatePaperTypes(response.data.allowed_papers);
					populateBindingTypes(response.data.allowed_bindings);
				} else {
					showMessage(response.message || 'خطا در بارگذاری گزینه‌ها', 'error');
				}
			},
			error: function() {
				hideLoading();
				showMessage('خطا در ارتباط با سرور', 'error');
			}
		});
	}

	/**
	 * Populate paper types dropdown
	 */
	function populatePaperTypes(papers) {
		const $select = $('#paper_type_wizard');
		$select.empty();
		$select.append('<option value="">انتخاب کنید...</option>');

		if (!papers || papers.length === 0) {
			$select.append('<option value="" disabled>هیچ نوع کاغذی در دسترس نیست</option>');
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
	 * Load paper weights
	 */
	function loadPaperWeights(paperType) {
		const $paperSelect = $('#paper_type_wizard');
		const $weightSelect = $('#paper_weight_wizard');
		const selectedOption = $paperSelect.find('option:selected');
		const weights = selectedOption.data('weights');

		$weightSelect.empty();
		$weightSelect.append('<option value="">انتخاب کنید...</option>');

		if (!weights || weights.length === 0) {
			$weightSelect.append('<option value="" disabled>هیچ گرماژی در دسترس نیست</option>');
			return;
		}

		// Store available print types data with each weight option for frontend filtering.
		// This data comes from the backend filtering logic that checks for non-zero prices.
		weights.forEach(function(weightInfo) {
			const $option = $('<option></option>')
				.val(weightInfo.weight)
				.text(weightInfo.weight + ' گرم')
				.data('available_prints', weightInfo.available_prints || []);
			$weightSelect.append($option);
		});
	}

	/**
	 * Load and filter print types based on selected paper weight availability
	 */
	function loadPrintTypes() {
		// First, try to use locally cached data from weight selection
		const $weightSelect = $('#paper_weight_wizard');
		const selectedOption = $weightSelect.find('option:selected');
		const availablePrints = selectedOption.data('available_prints') || [];

		// Get all print type radio buttons
		const $bwOption = $('input[name="print_type"][value="bw"]');
		const $colorOption = $('input[name="print_type"][value="color"]');
		const $bwCard = $bwOption.closest('.print-option');
		const $colorCard = $colorOption.closest('.print-option');

		// Reset both options first
		$bwOption.prop('disabled', false);
		$colorOption.prop('disabled', false);
		$bwCard.removeClass('disabled');
		$colorCard.removeClass('disabled');

		// If we have specific availability data from the weight option, apply it
		if (availablePrints.length > 0) {
			// Disable options that are not available (price = 0)
			if (!availablePrints.includes('bw')) {
				$bwOption.prop('disabled', true).prop('checked', false);
				$bwCard.addClass('disabled');
			}
			if (!availablePrints.includes('color')) {
				$colorOption.prop('disabled', true).prop('checked', false);
				$colorCard.addClass('disabled');
			}

			// Auto-select if only one option is available
			if (availablePrints.length === 1) {
				const onlyAvailable = availablePrints[0];
				if (onlyAvailable === 'bw') {
					$bwOption.prop('checked', true);
					formState.print_type = 'bw';
				} else if (onlyAvailable === 'color') {
					$colorOption.prop('checked', true);
					formState.print_type = 'color';
				}
			}
		} else if (formState.paper_type && formState.paper_weight) {
			// Fallback: Query the API to get allowed print types
			// This ensures we always have the correct data even if cached data is missing
			$.ajax({
				url: tabeshOrderFormV2.apiUrl + '/get-allowed-options',
				method: 'POST',
				headers: {
					'X-WP-Nonce': tabeshOrderFormV2.nonce
				},
				contentType: 'application/json',
				data: JSON.stringify({
					book_size: formState.book_size,
					current_selection: {
						paper_type: formState.paper_type,
						paper_weight: formState.paper_weight
					}
				}),
				success: function(response) {
					if (response.success && response.data && response.data.allowed_print_types) {
						const allowedTypes = response.data.allowed_print_types.map(function(pt) {
							return pt.type;
						});
						
						// Apply the restrictions
						if (!allowedTypes.includes('bw')) {
							$bwOption.prop('disabled', true).prop('checked', false);
							$bwCard.addClass('disabled');
						}
						if (!allowedTypes.includes('color')) {
							$colorOption.prop('disabled', true).prop('checked', false);
							$colorCard.addClass('disabled');
						}

						// Auto-select if only one option
						if (allowedTypes.length === 1) {
							if (allowedTypes[0] === 'bw') {
								$bwOption.prop('checked', true);
								formState.print_type = 'bw';
							} else if (allowedTypes[0] === 'color') {
								$colorOption.prop('checked', true);
								formState.print_type = 'color';
							}
						}
					}
				},
				error: function() {
					// On error, don't restrict anything - let user select
					console.log('Failed to load print types restrictions');
				}
			});
		}
	}

	/**
	 * Populate binding types
	 */
	function populateBindingTypes(bindings) {
		const $select = $('#binding_type_wizard');
		$select.empty();
		$select.append('<option value="">انتخاب کنید...</option>');

		if (!bindings || bindings.length === 0) {
			$select.append('<option value="" disabled>هیچ نوع صحافی در دسترس نیست</option>');
			return;
		}

		bindings.forEach(function(binding) {
			$select.append(
				$('<option></option>')
					.val(binding.type)
					.text(binding.type)
					.data('cover_weights', binding.cover_weights)
			);
		});
	}

	/**
	 * Load cover weights
	 */
	function loadCoverWeights() {
		const $bindingSelect = $('#binding_type_wizard');
		const $coverSelect = $('#cover_weight_wizard');
		const selectedOption = $bindingSelect.find('option:selected');
		const coverWeights = selectedOption.data('cover_weights');

		$coverSelect.empty();
		$coverSelect.append('<option value="">انتخاب کنید...</option>');

		if (!coverWeights || coverWeights.length === 0) {
			$coverSelect.append('<option value="" disabled>هیچ گرماژ جلدی در دسترس نیست</option>');
			return;
		}

		coverWeights.forEach(function(weightInfo) {
			$coverSelect.append(
				$('<option></option>')
					.val(weightInfo.weight)
					.text(weightInfo.weight + ' گرم')
			);
		});
	}

	/**
	 * Load extras
	 */
	function loadExtras() {
		showLoading();

		$.ajax({
			url: tabeshOrderFormV2.apiUrl + '/get-allowed-options',
			method: 'POST',
			headers: {
				'X-WP-Nonce': tabeshOrderFormV2.nonce
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
				} else {
					populateExtras([]);
				}
			},
			error: function() {
				hideLoading();
				populateExtras([]);
			}
		});
	}

	/**
	 * Populate extras
	 */
	function populateExtras(extras) {
		const $container = $('#extras_container_wizard');
		$container.empty();

		if (!extras || extras.length === 0) {
			$container.append('<p class="loading-text">هیچ خدمت اضافی برای این نوع صحافی موجود نیست</p>');
			return;
		}

		extras.forEach(function(extra) {
			const $label = $('<label></label>').addClass('extra-option');
			
			const $checkbox = $('<input>')
				.attr('type', 'checkbox')
				.attr('name', 'extras[]')
				.attr('value', extra.name)
				.data('price', extra.price)
				.data('type', extra.type);
			
			const $card = $('<span></span>').addClass('extra-card');
			const $check = $('<span></span>').addClass('extra-check');
			const $name = $('<span></span>').addClass('extra-name').text(extra.name);
			
			$card.append($check).append($name);
			$label.append($checkbox).append($card);
			$container.append($label);
		});
	}

	/**
	 * Update extras state
	 */
	function updateExtrasState() {
		formState.extras = [];
		$('#extras_container_wizard input[type="checkbox"]:checked').each(function() {
			formState.extras.push($(this).val());
		});
	}

	/**
	 * Calculate price
	 */
	function calculatePrice() {
		showLoading();
		updateExtrasState();

		// Get page count distribution
		const pageDistribution = {
			page_count_color: formState.print_type === 'color' ? formState.page_count : 0,
			page_count_bw: formState.print_type === 'bw' ? formState.page_count : 0
		};

		const priceData = {
			book_size: formState.book_size,
			paper_type: formState.paper_type,
			paper_weight: formState.paper_weight,
			print_type: formState.print_type,
			page_count_color: pageDistribution.page_count_color,
			page_count_bw: pageDistribution.page_count_bw,
			quantity: formState.quantity,
			binding_type: formState.binding_type,
			cover_paper_weight: formState.cover_weight,
			cover_weight: formState.cover_weight,
			license_type: 'دارم',
			extras: formState.extras
		};

		$.ajax({
			url: tabeshOrderFormV2.apiUrl + '/calculate-price',
			method: 'POST',
			headers: {
				'X-WP-Nonce': tabeshOrderFormV2.nonce
			},
			contentType: 'application/json',
			data: JSON.stringify(priceData),
			success: function(response) {
				hideLoading();

				if (response.success && response.data) {
					displayPrice(response.data);
					formState.calculated_price = response.data;
					showMessage('قیمت با موفقیت محاسبه شد', 'success');
					dispatchSliderEvent(); // Notify slider of price update
				} else {
					showMessage(response.message || 'خطا در محاسبه قیمت', 'error');
				}
			},
			error: function() {
				hideLoading();
				showMessage('خطا در محاسبه قیمت', 'error');
			}
		});
	}

	/**
	 * Display calculated price
	 */
	function displayPrice(priceData) {
		const formatPrice = function(price) {
			return new Intl.NumberFormat('fa-IR').format(price) + ' تومان';
		};

		$('#price_per_book').text(formatPrice(priceData.price_per_book));
		$('#price_quantity').text(priceData.quantity);
		$('#price_total').text(formatPrice(priceData.total_price));
	}

	/**
	 * Update order review
	 */
	function updateOrderReview() {
		const $review = $('#order_review');
		$review.empty();

		const items = [
			{ label: 'عنوان کتاب', value: formState.book_title },
			{ label: 'قطع کتاب', value: formState.book_size },
			{ label: 'نوع کاغذ', value: formState.paper_type },
			{ label: 'گرماژ کاغذ', value: formState.paper_weight + ' گرم' },
			{ label: 'نوع چاپ', value: formState.print_type === 'bw' ? 'سیاه و سفید' : 'رنگی' },
			{ label: 'تعداد صفحات', value: formState.page_count },
			{ label: 'تیراژ', value: formState.quantity },
			{ label: 'نوع صحافی', value: formState.binding_type },
			{ label: 'گرماژ جلد', value: formState.cover_weight + ' گرم' }
		];

		if (formState.extras.length > 0) {
			items.push({ label: 'خدمات اضافی', value: formState.extras.join('، ') });
		}

		items.forEach(function(item) {
			const $item = $('<div></div>').addClass('review-item');
			$item.append($('<span></span>').addClass('review-label').text(item.label));
			$item.append($('<span></span>').addClass('review-value').text(item.value));
			$review.append($item);
		});
	}

	/**
	 * Handle form submission
	 */
	function handleSubmit() {
		if (!validateStep(4)) {
			return;
		}

		if (!formState.calculated_price) {
			showMessage('لطفاً ابتدا قیمت را محاسبه کنید', 'error');
			return;
		}

		showLoading();

		// Get page count distribution
		const pageDistribution = {
			page_count_color: formState.print_type === 'color' ? formState.page_count : 0,
			page_count_bw: formState.print_type === 'bw' ? formState.page_count : 0
		};

		const orderData = {
			book_title: formState.book_title,
			book_size: formState.book_size,
			paper_type: formState.paper_type,
			paper_weight: formState.paper_weight,
			print_type: formState.print_type,
			page_count_color: pageDistribution.page_count_color,
			page_count_bw: pageDistribution.page_count_bw,
			quantity: formState.quantity,
			binding_type: formState.binding_type,
			cover_paper_weight: formState.cover_weight,
			cover_weight: formState.cover_weight,
			license_type: 'دارم',
			lamination_type: 'براق',
			extras: formState.extras,
			notes: formState.notes
		};

		$.ajax({
			url: tabeshOrderFormV2.apiUrl + '/submit-order',
			method: 'POST',
			headers: {
				'X-WP-Nonce': tabeshOrderFormV2.nonce
			},
			contentType: 'application/json',
			data: JSON.stringify(orderData),
			success: function(response) {
				hideLoading();

				if (response.success) {
					showMessage('سفارش شما با موفقیت ثبت شد!', 'success');
					setTimeout(function() {
						const redirectUrl = response.data?.redirect_url || 
							(typeof tabeshOrderFormV2.userOrdersUrl !== 'undefined' ? tabeshOrderFormV2.userOrdersUrl : window.location.origin);
						window.location.href = redirectUrl;
					}, 2000);
				} else {
					showMessage(response.message || 'خطا در ثبت سفارش', 'error');
				}
			},
			error: function(xhr) {
				hideLoading();
				const errorMessage = xhr.responseJSON?.message || 'خطا در ثبت سفارش';
				showMessage(errorMessage, 'error');
			}
		});
	}

	/**
	 * Show loading overlay
	 */
	function showLoading() {
		$('#wizard-loading').fadeIn(200);
	}

	/**
	 * Hide loading overlay
	 */
	function hideLoading() {
		$('#wizard-loading').fadeOut(200);
	}

	/**
	 * Show message
	 */
	function showMessage(message, type) {
		const $messages = $('#wizard-messages');
		const $message = $('<div></div>')
			.addClass('wizard-message')
			.addClass(type)
			.text(message);
		
		$messages.append($message);

		setTimeout(function() {
			$message.fadeOut(300, function() {
				$(this).remove();
			});
		}, 5000);
	}

})(jQuery);
