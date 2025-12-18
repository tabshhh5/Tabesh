/**
 * Order Form V2 - Dynamic Dependency Mapping
 *
 * Handles cascading form logic, dynamic option loading,
 * and real-time price calculation using V2 pricing matrix.
 *
 * @package Tabesh
 */

(function($) {
	'use strict';

	// Form state management
	const formState = {
		book_size: '',
		paper_type: '',
		paper_weight: '',
		print_type: '',
		page_count: 100,
		quantity: 10,
		binding_type: '',
		cover_weight: '',
		extras: [],
		book_title: '',
		notes: ''
	};

	// Cache for allowed options to reduce API calls
	const optionsCache = {};

	/**
	 * Initialize form on document ready
	 */
	$(document).ready(function() {
		initFormV2();
	});

	/**
	 * Initialize form V2
	 */
	function initFormV2() {
		// Check if form exists
		if ($('#tabesh-order-form-v2').length === 0) {
			return;
		}

		console.log('Initializing Tabesh Order Form V2...');

		// Attach event listeners
		attachEventListeners();

		// Set initial quantity from form
		const initialQuantity = parseInt($('#quantity_v2').val(), 10);
		if (initialQuantity) {
			formState.quantity = initialQuantity;
		}
	}

	/**
	 * Attach all event listeners
	 */
	function attachEventListeners() {
		// Book title input
		$('#book_title_v2').on('change', function() {
			formState.book_title = $(this).val();
		});

		// Book size selection - triggers cascade
		$('#book_size_v2').on('change', function() {
			const bookSize = $(this).val();
			console.log('Book size selected:', bookSize);

			if (!bookSize) {
				hideStepsAfter(2);
				return;
			}

			formState.book_size = bookSize;
			loadAllowedOptions({ book_size: bookSize });
		});

		// Paper type selection - loads weights and print types
		$('#paper_type_v2').on('change', function() {
			const paperType = $(this).val();
			console.log('Paper type selected:', paperType);

			if (!paperType) {
				hideStepsAfter(3);
				return;
			}

			formState.paper_type = paperType;
			loadPaperWeights(paperType);
		});

		// Paper weight selection - triggers print type update
		$('#paper_weight_v2').on('change', function() {
			const paperWeight = $(this).val();
			console.log('Paper weight selected:', paperWeight);

			if (!paperWeight) {
				hideStepsAfter(3);
				return;
			}

			formState.paper_weight = paperWeight;
			loadPrintTypes();
		});

		// Print type selection
		$('#print_type_v2').on('change', function() {
			const printType = $(this).val();
			console.log('Print type selected:', printType);

			if (!printType) {
				hideStepsAfter(4);
				return;
			}

			formState.print_type = printType;
			showStep(5); // Show page count
		});

		// Page count input
		$('#page_count_v2').on('change', function() {
			const pageCount = parseInt($(this).val(), 10);
			if (pageCount > 0) {
				formState.page_count = pageCount;
				showStep(6); // Show quantity
			}
		});

		// Quantity input
		$('#quantity_v2').on('change', function() {
			const quantity = parseInt($(this).val(), 10);
			if (quantity > 0) {
				formState.quantity = quantity;
				loadBindingTypes();
			}
		});

		// Binding type selection
		$('#binding_type_v2').on('change', function() {
			const bindingType = $(this).val();
			console.log('Binding type selected:', bindingType);

			if (!bindingType) {
				hideStepsAfter(7);
				return;
			}

			formState.binding_type = bindingType;
			loadCoverWeights();
			loadExtras();
		});

		// Cover weight selection
		$('#cover_weight_v2').on('change', function() {
			const coverWeight = $(this).val();
			console.log('Cover weight selected:', coverWeight);

			if (!coverWeight) {
				hideStepsAfter(8);
				return;
			}

			formState.cover_weight = coverWeight;
			showStep(9); // Show extras
			showStep(10); // Show notes
			enablePriceCalculation();
		});

		// Extras checkboxes (delegated event for dynamic content)
		$(document).on('change', '#extras_container_v2 input[type="checkbox"]', function() {
			updateExtrasState();
		});

		// Calculate price button
		$('#calculate-price-v2').on('click', function() {
			calculatePrice();
		});

		// Submit order button
		$('#submit-order-v2').on('click', function() {
			submitOrder();
		});
	}

	/**
	 * Load allowed options for the selected book size
	 */
	function loadAllowedOptions(selection) {
		showLoading();

		const cacheKey = JSON.stringify(selection);
		if (optionsCache[cacheKey]) {
			console.log('Using cached options for:', cacheKey);
			populateOptionsFromCache(optionsCache[cacheKey]);
			hideLoading();
			return;
		}

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
				console.log('Allowed options response:', response);
				hideLoading();

				if (response.success && response.data) {
					optionsCache[cacheKey] = response.data;
					populatePaperTypes(response.data.allowed_papers);
					populateBindingTypes(response.data.allowed_bindings);
					showStep(3);
				} else {
					showError(response.message || 'خطا در بارگذاری گزینه‌ها');
				}
			},
			error: function(xhr, status, error) {
				console.error('AJAX error:', error);
				hideLoading();
				showError('خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.');
			}
		});
	}

	/**
	 * Populate paper types dropdown
	 */
	function populatePaperTypes(papers) {
		const $select = $('#paper_type_v2');
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
	 * Load paper weights for selected paper type
	 */
	function loadPaperWeights(paperType) {
		const $paperSelect = $('#paper_type_v2');
		const $weightSelect = $('#paper_weight_v2');
		const selectedOption = $paperSelect.find('option:selected');
		const weights = selectedOption.data('weights');

		$weightSelect.empty();
		$weightSelect.append('<option value="">انتخاب کنید...</option>');

		if (!weights || weights.length === 0) {
			$weightSelect.append('<option value="" disabled>هیچ گرماژی در دسترس نیست</option>');
			return;
		}

		weights.forEach(function(weightInfo) {
			$weightSelect.append(
				$('<option></option>')
					.val(weightInfo.weight)
					.text(weightInfo.weight + ' گرم')
			);
		});
	}

	/**
	 * Load print types based on current selection
	 */
	function loadPrintTypes() {
		showLoading();

		const selection = {
			book_size: formState.book_size,
			paper_type: formState.paper_type,
			paper_weight: formState.paper_weight
		};

		$.ajax({
			url: tabeshOrderFormV2.apiUrl + '/get-allowed-options',
			method: 'POST',
			headers: {
				'X-WP-Nonce': tabeshOrderFormV2.nonce
			},
			contentType: 'application/json',
			data: JSON.stringify({
				book_size: selection.book_size,
				current_selection: {
					paper_type: selection.paper_type
				}
			}),
			success: function(response) {
				hideLoading();

				if (response.success && response.data && response.data.allowed_print_types) {
					populatePrintTypes(response.data.allowed_print_types);
					showStep(4);
				} else {
					showError('خطا در بارگذاری انواع چاپ');
				}
			},
			error: function(xhr, status, error) {
				console.error('AJAX error:', error);
				hideLoading();
				showError('خطا در ارتباط با سرور.');
			}
		});
	}

	/**
	 * Populate print types dropdown
	 */
	function populatePrintTypes(printTypes) {
		const $select = $('#print_type_v2');
		$select.empty();
		$select.append('<option value="">انتخاب کنید...</option>');

		if (!printTypes || printTypes.length === 0) {
			$select.append('<option value="" disabled>هیچ نوع چاپی در دسترس نیست</option>');
			return;
		}

		printTypes.forEach(function(printType) {
			$select.append(
				$('<option></option>')
					.val(printType.type)
					.text(printType.label)
			);
		});
	}

	/**
	 * Populate binding types dropdown
	 */
	function populateBindingTypes(bindings) {
		const $select = $('#binding_type_v2');
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
	 * Load binding types when quantity is set
	 */
	function loadBindingTypes() {
		// Binding types are already loaded from initial book size selection
		// Just show the step
		showStep(7);
	}

	/**
	 * Load cover weights for selected binding
	 */
	function loadCoverWeights() {
		const $bindingSelect = $('#binding_type_v2');
		const $coverSelect = $('#cover_weight_v2');
		const selectedOption = $bindingSelect.find('option:selected');
		const coverWeights = selectedOption.data('cover_weights');

		$coverSelect.empty();
		$coverSelect.append('<option value="">انتخاب کنید...</option>');

		if (!coverWeights || coverWeights.length === 0) {
			$coverSelect.append('<option value="" disabled>هیچ گرماژ جلدی در دسترس نیست</option>');
			showStep(8);
			return;
		}

		coverWeights.forEach(function(weightInfo) {
			$coverSelect.append(
				$('<option></option>')
					.val(weightInfo.weight)
					.text(weightInfo.weight + ' گرم')
			);
		});

		showStep(8);
	}

	/**
	 * Load extras based on binding selection
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
			error: function(xhr, status, error) {
				console.error('AJAX error:', error);
				hideLoading();
				populateExtras([]);
			}
		});
	}

	/**
	 * Populate extras checkboxes
	 */
	function populateExtras(extras) {
		const $container = $('#extras_container_v2');
		$container.empty();

		if (!extras || extras.length === 0) {
			$container.append('<p class="no-extras">هیچ خدمت اضافی برای این نوع صحافی موجود نیست.</p>');
			return;
		}

		extras.forEach(function(extra) {
			const $label = $('<label></label>')
				.addClass('tabesh-checkbox-v2');
			
			const $checkbox = $('<input>')
				.attr('type', 'checkbox')
				.attr('name', 'extras[]')
				.attr('value', extra.name)
				.data('price', extra.price)
				.data('type', extra.type);
			
			const $span = $('<span></span>').text(extra.name);
			
			$label.append($checkbox).append($span);
			$container.append($label);
		});
	}

	/**
	 * Update extras state from checkboxes
	 */
	function updateExtrasState() {
		formState.extras = [];
		$('#extras_container_v2 input[type="checkbox"]:checked').each(function() {
			formState.extras.push($(this).val());
		});
		console.log('Extras updated:', formState.extras);
	}

	/**
	 * Calculate price
	 */
	function calculatePrice() {
		showLoading();
		updateExtrasState();

		const priceData = {
			book_size: formState.book_size,
			paper_type: formState.paper_type,
			paper_weight: formState.paper_weight,
			print_type: formState.print_type,
			page_count: formState.page_count,
			quantity: formState.quantity,
			binding_type: formState.binding_type,
			cover_weight: formState.cover_weight,
			extras: formState.extras
		};

		console.log('Calculating price for:', priceData);

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
				console.log('Price response:', response);

				if (response.success && response.data) {
					displayPrice(response.data);
					$('#submit-order-v2').show();
				} else {
					showError(response.message || 'خطا در محاسبه قیمت');
				}
			},
			error: function(xhr, status, error) {
				console.error('Price calculation error:', error);
				hideLoading();
				showError('خطا در محاسبه قیمت. لطفاً دوباره تلاش کنید.');
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

		$('#price-per-book-v2').text(formatPrice(priceData.price_per_book));
		$('#price-quantity-v2').text(priceData.quantity);
		$('#price-total-v2').text(formatPrice(priceData.total_price));

		// Show breakdown if available
		if (priceData.breakdown) {
			displayPriceBreakdown(priceData.breakdown);
		}
	}

	/**
	 * Display price breakdown
	 */
	function displayPriceBreakdown(breakdown) {
		const $container = $('#breakdown-content-v2');
		$container.empty();

		const formatPrice = function(price) {
			return new Intl.NumberFormat('fa-IR').format(price) + ' تومان';
		};

		if (breakdown.page_cost) {
			$container.append(`<div class="breakdown-row"><span>هزینه صفحات:</span><span>${formatPrice(breakdown.page_cost)}</span></div>`);
		}
		if (breakdown.binding_cost) {
			$container.append(`<div class="breakdown-row"><span>هزینه صحافی:</span><span>${formatPrice(breakdown.binding_cost)}</span></div>`);
		}
		if (breakdown.extras_cost) {
			$container.append(`<div class="breakdown-row"><span>هزینه خدمات اضافی:</span><span>${formatPrice(breakdown.extras_cost)}</span></div>`);
		}

		$('#price-breakdown-v2').show();
	}

	/**
	 * Submit order
	 */
	function submitOrder() {
		if (!validateForm()) {
			return;
		}

		showLoading();
		updateExtrasState();

		const orderData = {
			book_title: formState.book_title || $('#book_title_v2').val(),
			book_size: formState.book_size,
			paper_type: formState.paper_type,
			paper_weight: formState.paper_weight,
			print_type: formState.print_type,
			page_count: formState.page_count,
			quantity: formState.quantity,
			binding_type: formState.binding_type,
			cover_weight: formState.cover_weight,
			extras: formState.extras,
			notes: $('#notes_v2').val()
		};

		console.log('Submitting order:', orderData);

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
				console.log('Order submission response:', response);

				if (response.success) {
					showSuccess('سفارش شما با موفقیت ثبت شد!');
					// Optionally redirect to user orders page
					setTimeout(function() {
						window.location.href = response.redirect_url || '/';
					}, 2000);
				} else {
					showError(response.message || 'خطا در ثبت سفارش');
				}
			},
			error: function(xhr, status, error) {
				console.error('Order submission error:', error);
				hideLoading();
				showError('خطا در ثبت سفارش. لطفاً دوباره تلاش کنید.');
			}
		});
	}

	/**
	 * Validate form before submission
	 */
	function validateForm() {
		const requiredFields = {
			'book_title_v2': 'عنوان کتاب',
			'book_size_v2': 'قطع کتاب',
			'paper_type_v2': 'نوع کاغذ',
			'paper_weight_v2': 'گرماژ کاغذ',
			'print_type_v2': 'نوع چاپ',
			'page_count_v2': 'تعداد صفحات',
			'quantity_v2': 'تیراژ',
			'binding_type_v2': 'نوع صحافی',
			'cover_weight_v2': 'گرماژ جلد'
		};

		for (const [fieldId, fieldName] of Object.entries(requiredFields)) {
			const $field = $('#' + fieldId);
			if (!$field.val()) {
				showError(`لطفاً ${fieldName} را وارد کنید.`);
				$field.focus();
				return false;
			}
		}

		return true;
	}

	/**
	 * Show step
	 */
	function showStep(stepNumber) {
		$('[data-step="' + stepNumber + '"]').fadeIn(300);
	}

	/**
	 * Hide steps after given step number
	 */
	function hideStepsAfter(stepNumber) {
		$('[data-step]').each(function() {
			const step = parseInt($(this).data('step'), 10);
			if (step > stepNumber) {
				$(this).hide();
			}
		});
	}

	/**
	 * Enable price calculation button
	 */
	function enablePriceCalculation() {
		$('#calculate-price-v2').prop('disabled', false);
	}

	/**
	 * Populate options from cache
	 */
	function populateOptionsFromCache(data) {
		populatePaperTypes(data.allowed_papers);
		populateBindingTypes(data.allowed_bindings);
		showStep(3);
	}

	/**
	 * Show loading overlay
	 */
	function showLoading() {
		$('#form-loading-v2').fadeIn(200);
	}

	/**
	 * Hide loading overlay
	 */
	function hideLoading() {
		$('#form-loading-v2').fadeOut(200);
	}

	/**
	 * Show error message
	 */
	function showError(message) {
		const $messages = $('#form-messages-v2');
		$messages.html('<div class="tabesh-message error"><p>' + message + '</p></div>');
		$messages.fadeIn(300);
		setTimeout(function() {
			$messages.fadeOut(300);
		}, 5000);
	}

	/**
	 * Show success message
	 */
	function showSuccess(message) {
		const $messages = $('#form-messages-v2');
		$messages.html('<div class="tabesh-message success"><p>' + message + '</p></div>');
		$messages.fadeIn(300);
	}

})(jQuery);;
