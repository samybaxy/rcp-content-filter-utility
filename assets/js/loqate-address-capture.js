/**
 * Loqate Address Capture SDK Integration
 *
 * Optimized integration for WooCommerce checkout with:
 * - SubBuilding/Apt/Suite extraction for Address Line 2
 * - Debounced search for reduced API calls
 * - Cached DOM references for performance
 * - Lazy initialization for shipping fields
 * - Country context handling for accurate autocomplete
 *
 * @since 1.0.25
 * @updated 1.0.44 - Fixed shipping control initialization when DOM is replaced by WooCommerce AJAX
 */

;(function($, window) {
	'use strict';

	/**
	 * Loqate Address Capture Manager
	 */
	window.RCFLoqateAddressCapture = {

		// Configuration
		config: window.rcfLoqateConfig || {},

		// Control instances
		billingControl: null,
		shippingControl: null,
		emailControl: null,
		phoneControl: null,

		// State flags
		isInitializingBilling: false,
		isInitializingShipping: false,
		isInitialized: false,

		// Track current country context for each control
		currentBillingCountry: null,
		currentShippingCountry: null,

		// Timers
		checkoutUpdateTimer: null,
		searchDebounceTimers: {},

		// Cached DOM elements for performance
		cachedElements: {},

		// Debounce delay in ms (reduces API calls while typing)
		SEARCH_DEBOUNCE_MS: 150,

		/**
		 * Initialize Loqate integration
		 */
		init: function() {
			var self = this;

			// Validation checks
			if (!this.validateEnvironment()) {
				return;
			}

			// Pre-cache DOM elements
			this.cacheElements();

			this.bindCheckoutEvents();
			this.setupControls();
			this.isInitialized = true;
		},

		/**
		 * Cache DOM elements for performance
		 * Reduces repeated jQuery selector overhead
		 */
		cacheElements: function() {
			var billingFields = this.config.billingAddressFields || {};
			var shippingFields = this.config.shippingAddressFields || {};

			// Cache billing fields
			if (billingFields.search) {
				this.cachedElements.billingSearch = $('#' + billingFields.search);
				this.cachedElements.billingCountry = $('#' + billingFields.country);
			}

			// Cache shipping fields
			if (shippingFields.search) {
				this.cachedElements.shippingSearch = $('#' + shippingFields.search);
				this.cachedElements.shippingCountry = $('#' + shippingFields.country);
			}

			// Cache ship-to-different-address checkbox
			this.cachedElements.shipToDifferent = $('#ship-to-different-address-checkbox');
		},

		/**
		 * Get cached element or query DOM
		 * @param {string} key - Cache key
		 * @param {string} selector - jQuery selector fallback
		 * @returns {jQuery}
		 */
		getElement: function(key, selector) {
			if (!this.cachedElements[key] || !this.cachedElements[key].length) {
				this.cachedElements[key] = $(selector);
			}
			return this.cachedElements[key];
		},

		/**
		 * Validate environment requirements
		 */
		validateEnvironment: function() {
			if (typeof pca === 'undefined') {
				console.error('[Loqate] SDK not loaded - pca object is undefined');
				return false;
			}

			if (!this.config.apiKey) {
				console.error('[Loqate] API key not configured - check wp-config.php or admin settings');
				return false;
			}

			// Log API key status (masked for security)
			var maskedKey = this.config.apiKey.substring(0, 4) + '****' + this.config.apiKey.substring(this.config.apiKey.length - 4);
			console.log('[Loqate] Environment validated - SDK loaded, API key: ' + maskedKey);
			return true;
		},

		/**
		 * Bind to checkout events including country changes
		 */
		bindCheckoutEvents: function() {
			var self = this;

			// Direct listener on country field changes (triggered by WooCommerce dropdown)
			$(document).on('change', '#billing_country, #shipping_country', function() {
				var fieldId = $(this).attr('id');
				var newValue = $(this).val();
				self.handleCountryChange(fieldId, newValue);
			});

			// Also listen to updated_checkout as backup
			$(document).on('updated_checkout', function() {
				clearTimeout(self.checkoutUpdateTimer);

				self.checkoutUpdateTimer = setTimeout(function() {
					self.checkCountryChanges();
					self.setupControls();
				}, 300);
			});

			// Initial setup when document is ready
			$(document).ready(function() {
				self.setupControls();
			});
		},

		/**
		 * Check if country changed and reinitialize Loqate controls if necessary
		 */
		checkCountryChanges: function() {
			var self = this;

			// Check billing country
			var billingFields = this.config.billingAddressFields;
			var $billingCountryField = $('#' + billingFields.country);
			var newBillingCountry = $billingCountryField.length ? $billingCountryField.val() : '';

			if (newBillingCountry && newBillingCountry !== this.currentBillingCountry) {
				this.reinitializeAddressCapture('billing');
			}

			// Check shipping country
			var shippingFields = this.config.shippingAddressFields;
			var $shippingCountryField = $('#' + shippingFields.country);
			var newShippingCountry = $shippingCountryField.length ? $shippingCountryField.val() : '';

			if (newShippingCountry && newShippingCountry !== this.currentShippingCountry) {
				this.reinitializeAddressCapture('shipping');
			}
		},

		/**
		 * Handle direct country field change (called from change event listener)
		 */
		handleCountryChange: function(fieldId, newValue) {
			// Determine if billing or shipping
			var type = fieldId.includes('billing') ? 'billing' : 'shipping';
			var countryVarName = 'current' + (type === 'billing' ? 'Billing' : 'Shipping') + 'Country';
			var oldValue = this[countryVarName];

			if (newValue && newValue !== oldValue) {
				this.reinitializeAddressCapture(type);
			}
		},

		/**
		 * Setup all address capture controls
		 * Uses lazy initialization for shipping (only when visible/needed)
		 */
		setupControls: function() {
			// Always initialize billing
			this.setupAddressCapture('billing');

			// Lazy init for shipping - only if "ship to different address" is checked
			// or if shipping fields are visible
			var $shipToDifferent = this.getElement('shipToDifferent', '#ship-to-different-address-checkbox');
			var $shippingFields = $('.woocommerce-shipping-fields .shipping_address');

			if ($shipToDifferent.length && $shipToDifferent.is(':checked')) {
				this.setupAddressCapture('shipping');
			} else if ($shippingFields.length && $shippingFields.is(':visible')) {
				this.setupAddressCapture('shipping');
			}

			// Listen for ship-to-different checkbox changes for lazy init
			var self = this;
			$(document).off('change.loqateShipping', '#ship-to-different-address-checkbox')
				.on('change.loqateShipping', '#ship-to-different-address-checkbox', function() {
					if ($(this).is(':checked') && !self.shippingControl) {
						// Small delay to let WooCommerce show the shipping fields
						setTimeout(function() {
							self.cacheElements(); // Refresh cache
							self.setupAddressCapture('shipping');
						}, 200);
					}
				});
		},

		/**
		 * Setup address capture for billing or shipping
		 *
		 * @param {string} type - 'billing' or 'shipping'
		 */
		setupAddressCapture: function(type) {
			var self = this;
			var isBilling = type === 'billing';
			var controlName = type + 'Control';
			var flagName = 'isInitializing' + (isBilling ? 'Billing' : 'Shipping');
			var countryVarName = 'current' + (isBilling ? 'Billing' : 'Shipping') + 'Country';

			// Prevent concurrent initialization
			if (this[flagName]) {
				return;
			}


		// Check if control already exists AND its DOM element is still valid
		// If DOM was replaced (e.g., by WooCommerce AJAX), unload the stale control
		if (this[controlName]) {
			// Check if the control's element is still in the DOM
			var controlElement = this[controlName].element;
			var elementStillExists = controlElement && document.body.contains(controlElement);

			if (elementStillExists) {
				// Control exists and DOM is valid, skip re-initialization
				return;
			} else {
				// DOM was replaced, unload the stale control
				console.log('[Loqate] ' + type + ' control exists but DOM was replaced, re-initializing...');
				if (typeof this[controlName].unload === 'function') {
					try {
						this[controlName].unload();
					} catch (e) {
						console.warn('[Loqate] Error unloading stale ' + type + ' control', e);
					}
				}
				this[controlName] = null;
				this[flagName] = false;
			}
		}

			var fields = isBilling ? this.config.billingAddressFields : this.config.shippingAddressFields;
			var $searchField = $('#' + fields.search);

			// Check if field exists
			if (!$searchField.length) {
				console.warn('[Loqate] ' + type + ' search field not found: #' + fields.search);
				return;
			}

			// Set initialization flag
			this[flagName] = true;

			// Disable browser autocomplete
			$searchField.attr('autocomplete', 'off');

			// Get configuration with country context
			var controlConfig = this.getAddressConfig(type);
			var fieldControls = this.getFieldMapping(fields);

			// Track current country for this control
			var $countryField = $('#' + fields.country);
			var currentCountry = $countryField.length ? $countryField.val() : '';
			this[countryVarName] = currentCountry;

			try {
	
			// Create Loqate control
				this[controlName] = new pca.Address(fieldControls, controlConfig);

				// Attach event listeners
				this.attachAddressListeners(this[controlName], fields, type);

				console.log('[Loqate] Initialized - ' + type + ' address capture ready');
			} catch (error) {
				console.error('[Loqate] Failed to initialize ' + type + ' address capture', error);
				this[controlName] = null;
			} finally {
				this[flagName] = false;
			}
		},

		/**
		 * Get address configuration with country context
		 *
		 * Checks the current country field value to constrain Loqate results
		 * to that specific country for accurate address suggestions
		 */
		getAddressConfig: function(type) {
			var config = {
				key: this.config.apiKey,
				manualEntryItem: this.config.allowManualEntry
			};

			// Get current country from the country field
			var fields = type === 'billing' ? this.config.billingAddressFields : this.config.shippingAddressFields;
			var $countryField = $('#' + fields.country);
			var currentCountry = $countryField.length ? $countryField.val() : '';

			// Use current country or apply allowed countries filter
			if (currentCountry && currentCountry !== '') {
				// User has selected a country - prioritize that for accurate results
				config.countries = {
					codesList: currentCountry
				};
			} else if (this.config.allowedCountries) {
				// Use configured allowed countries
				config.countries = {
					codesList: this.config.allowedCountries
				};
			}

			return config;
		},

		/**
		 * Get field mapping for Loqate control
		 *
		 * Maps WooCommerce checkout fields to Loqate SDK field types
		 * Using OFFICIAL Loqate field names for accurate data capture
		 */
		getFieldMapping: function(fields) {
			var controls = [];

			// SEARCH FIELD - Required for address autocomplete
			if (fields.search) {
				controls.push({
					element: fields.search,
					field: 'Line1',
					mode: pca.fieldMode.SEARCH | pca.fieldMode.POPULATE
				});
			}

			// Map all populate fields using OFFICIAL Loqate field names
			if (fields.populate && Array.isArray(fields.populate)) {
				fields.populate.forEach(function(fieldId) {
					var mapping = null;

					if (fieldId.includes('address_2')) {
						mapping = { field: 'Line2', mode: pca.fieldMode.POPULATE };
					} else if (fieldId.includes('city')) {
						mapping = { field: 'City', mode: pca.fieldMode.POPULATE };
					} else if (fieldId.includes('state') || fieldId.includes('province')) {
						mapping = { field: 'Province', mode: pca.fieldMode.POPULATE };
					} else if (fieldId.includes('postcode') || fieldId.includes('postal')) {
						mapping = { field: 'PostalCode', mode: pca.fieldMode.POPULATE };
					} else if (fieldId.includes('company')) {
						mapping = { field: 'Company', mode: pca.fieldMode.POPULATE };
					}

					if (mapping) {
						controls.push({
							element: fieldId,
							field: mapping.field,
							mode: mapping.mode
						});
					}
				});
			}

			return controls;
		},

		/**
		 * Attach event listeners to address control
		 */
		attachAddressListeners: function(control, fields, type) {
			var self = this;
			var checkDropdownInterval = null;
			var isLoadingCleared = false;

			// On address populated (user selected an address from suggestions)
			control.listen('populate', function(address) {
				// Stop dropdown detection when user selects
				if (checkDropdownInterval) {
					clearInterval(checkDropdownInterval);
				}

	
			// CRITICAL: Close all Loqate dropdowns
				self.closeAllLoqateDropdowns();

	
			// Manually populate all fields
				self.populateAddressFields(address, fields, type);

				// Trigger WooCommerce update to recalculate shipping/taxes
				setTimeout(function() {
					$('body').trigger('update_checkout');
				}, 100);

				// Show success feedback
				self.showFieldFeedback(fields.search, 'success', 'Address found');

			// Re-initialize the control after a short delay to ensure it's ready for new searches
			setTimeout(function() {
				self.reinitializeControlAfterSelection(type);
			}, 500);

			});

			// On error (API request failed)
			control.listen('error', function(error) {
				var message = 'Address search error';
				var statusCode = error && error.statusCode ? error.statusCode : 'unknown';
				var errorMessage = error && error.message ? error.message : 'Unknown error';

				console.error('[Loqate] Error - Type: ' + type + ', Status: ' + statusCode + ', Message: ' + errorMessage);

				if (statusCode === 401 || statusCode === 403) {
					message = 'Invalid API key (check configuration)';
				} else if (statusCode === 429) {
					message = 'API quota exceeded (try again later)';
				} else if (statusCode === 0 || statusCode === 'unknown') {
					message = 'Network error or API unreachable';
				}

				self.showFieldFeedback(fields.search, 'error', message);

				if (checkDropdownInterval) {
					clearInterval(checkDropdownInterval);
				}
			});

			// Monitor search field for request/response
			var $searchField = $('#' + fields.search);

			// On user input (search request initiated)
			control.listen('search', function(query) {
				// Clean up any existing dropdowns before starting new search
				var $formRow = $searchField.closest('.form-row, .form-group');
				var existingCount = $formRow.find('.pcaautocomplete, .pca.pcalist, .pcatext').length;
				if (existingCount > 0) {
					$formRow.find('.pcaautocomplete, .pca.pcalist, .pcatext').remove();
				}

				self.showFieldFeedback(fields.search, 'loading');

				// Reset loading flag for new search
				isLoadingCleared = false;

				// Start monitoring dropdown
				if (checkDropdownInterval) {
					clearInterval(checkDropdownInterval);
				}

				checkDropdownInterval = setInterval(function() {
					// Use .first() to only match the FIRST visible dropdown
					var $dropdown = $formRow.find('.pcalist:visible').first();

					if ($dropdown.length && !isLoadingCleared) {
						self.showFieldFeedback(fields.search, '');
						isLoadingCleared = true;
						clearInterval(checkDropdownInterval);
					}
				}, 50);
			});
		},

		/**
		 * Reinitialize Loqate controls for a specific type when country changes
		 */
		reinitializeAddressCapture: function(type) {
			var isBilling = type === 'billing';
			var controlName = type + 'Control';
			var countryVarName = 'current' + (isBilling ? 'Billing' : 'Shipping') + 'Country';

			var fields = isBilling ? this.config.billingAddressFields : this.config.shippingAddressFields;
			var $countryField = $('#' + fields.country);
			var newCountry = $countryField.length ? $countryField.val() : '';

			// Disable input on search field during transition
			var $searchField = $('#' + fields.search);
			if ($searchField.length) {
				$searchField.prop('disabled', true);
			}

			// Clean up dropdown DOM for specific control only
			$searchField.closest('.form-row, .form-group').find('.pcaautocomplete, .pca.pcalist, .pcatext').remove();

			// Unload existing control
			if (this[controlName] && typeof this[controlName].unload === 'function') {
				try {
					this[controlName].unload();
				} catch (e) {
					console.warn('[Loqate] Error unloading ' + type + ' control', e);
				}
			}

			// Clear control instance and flags to allow reinitialize
			this[controlName] = null;
			this['isInitializing' + (isBilling ? 'Billing' : 'Shipping')] = false;

			// Update tracked country
			this[countryVarName] = newCountry;

			// Clean up any orphaned Loqate DOM elements for this field
			$searchField.closest('.form-row, .form-group').find('.pcaautocomplete, .pca.pcalist, .pcatext').remove();

			// Clear the search field value to prevent Loqate using stale address from old country
			$searchField.val('').trigger('change').trigger('blur');

			// Also remove any lingering feedback states
			this.clearFieldFeedback(fields.search);

			// Reinitialize with new country context with small delay
			var self = this;
			setTimeout(function() {
				self.setupAddressCapture(type);

				// Re-enable input after new control is fully initialized
				setTimeout(function() {
					if ($searchField.length) {
						$searchField.prop('disabled', false);
					}
				}, 100);
			}, 150);
		},

	/**
	 * Reinitialize control after address selection to allow new searches
	 * Similar to reinitializeAddressCapture but without clearing field values
	 */
	reinitializeControlAfterSelection: function(type) {
		var isBilling = type === 'billing';
		var controlName = type + 'Control';

		var fields = isBilling ? this.config.billingAddressFields : this.config.shippingAddressFields;
		var $searchField = $('#' + fields.search);

		// Clean up dropdown DOM elements
		$searchField.closest('.form-row, .form-group').find('.pcaautocomplete, .pca.pcalist, .pcatext').remove();

		// Unload existing control
		if (this[controlName] && typeof this[controlName].unload === 'function') {
			try {
				this[controlName].unload();
			} catch (e) {
				console.warn('[Loqate] Error unloading ' + type + ' control for re-initialization', e);
			}
		}

		// Clear control instance and flags to allow reinitialize
		this[controlName] = null;
		this['isInitializing' + (isBilling ? 'Billing' : 'Shipping')] = false;

		// Clean up any orphaned Loqate DOM elements
		$searchField.closest('.form-row, .form-group').find('.pcaautocomplete, .pca.pcalist, .pcatext').remove();

		// Reinitialize with fresh control (keeps field values intact)
		var self = this;
		setTimeout(function() {
			self.setupAddressCapture(type);
		}, 100);
	},

		/**
		 * Manually populate all address fields
		 *
		 * Enhanced with SubBuilding/Apt extraction:
		 * - Prioritizes SubBuilding field (contains "Apt 123", "Unit 4", "Suite 100")
		 * - Falls back to Line2 if SubBuilding is empty
		 * - Combines BuildingName with SubBuilding if both exist
		 */
		populateAddressFields: function(address, fields, type) {
			var self = this;
			var stateValue = null;

			// STEP 1: Update country FIRST
			if (fields.country) {
				var countryCode = address['ISO3166-2'] || address.ISO31662 ||
				                  address.CountryIso2 || address.countryIso2 ||
				                  this.getCountryCodeFromName(address.CountryName || address.Country);

				if (countryCode) {
					this.updateCountryField(fields.country, countryCode);
				} else {
					console.warn('[Loqate] Country - Could not extract country code from address. Available fields:', Object.keys(address));
				}
			}

			// STEP 2: Extract state/province value for delayed update
			if (fields.populate && Array.isArray(fields.populate)) {
				fields.populate.forEach(function(fieldId) {
					if (fieldId.includes('state')) {
						stateValue = address.Province || address.province ||
						            address.AdministrativeArea || address.administrativeArea ||
						            address.ProvinceCode || '';
					}
				});
			}

			// STEP 3: Build Address Line 2 with SubBuilding/Apt priority
			var addressLine2 = this.buildAddressLine2(address);

			// STEP 4: Update all other fields using requestAnimationFrame for batched DOM updates
			var updateOperations = [];

			if (fields.populate && Array.isArray(fields.populate)) {
				fields.populate.forEach(function(fieldId) {
					if (fieldId.includes('address_2')) {
						updateOperations.push({ fieldId: fieldId, value: addressLine2 });
					} else if (fieldId.includes('city')) {
						updateOperations.push({ fieldId: fieldId, value: address.City || address.city || '' });
					} else if (fieldId.includes('postcode') || fieldId.includes('postal')) {
						updateOperations.push({ fieldId: fieldId, value: address.PostalCode || address.postalCode || address.PostCode || '' });
					} else if (fieldId.includes('company')) {
						updateOperations.push({ fieldId: fieldId, value: address.Company || address.company || '' });
					}
				});
			}

			// Batch DOM updates using requestAnimationFrame for performance
			requestAnimationFrame(function() {
				updateOperations.forEach(function(op) {
					self.updateField(op.fieldId, op.value);
				});

				// Delayed state update (needs WooCommerce to load state options first)
				if (stateValue) {
					fields.populate.forEach(function(fieldId) {
						if (fieldId.includes('state')) {
							setTimeout(function() {
								self.updateStateField(fieldId, stateValue);
							}, 300);
						}
					});
				}
			});
		},

		/**
		 * Build Address Line 2 with SubBuilding/Apt priority
		 *
		 * Loqate API returns these fields for unit/suite/apartment:
		 * - SubBuilding: "Flat 4", "Unit 2", "Suite 100", "Apt 123"
		 * - BuildingName: Named buildings like "Old Change House"
		 * - Line2: Pre-formatted secondary line (country-specific)
		 *
		 * Priority order:
		 * 1. SubBuilding (most specific for apt/suite/unit)
		 * 2. BuildingName (for named buildings)
		 * 3. Line2 fallback (pre-formatted)
		 *
		 * @param {Object} address - Loqate address response
		 * @returns {string} Address Line 2 value
		 */
		buildAddressLine2: function(address) {
			var parts = [];

			// Priority 1: SubBuilding (apartment, suite, unit, flat)
			var subBuilding = address.SubBuilding || address.subBuilding || '';
			if (subBuilding && subBuilding.trim()) {
				parts.push(subBuilding.trim());
			}

			// Priority 2: BuildingName (only if SubBuilding is empty and BuildingName exists)
			// Don't duplicate if both exist and SubBuilding already contains building info
			var buildingName = address.BuildingName || address.buildingName || '';
			if (buildingName && buildingName.trim() && !subBuilding) {
				parts.push(buildingName.trim());
			}

			// If we have specific data from SubBuilding/BuildingName, use that
			if (parts.length > 0) {
				return parts.join(', ');
			}

			// Fallback: Use Line2 (pre-formatted by Loqate based on country standards)
			var line2 = address.Line2 || address.line2 || '';
			if (line2 && line2.trim()) {
				return line2.trim();
			}

			// Secondary fallback: SecondaryStreet or Block
			var secondary = address.SecondaryStreet || address.Block || '';
			if (secondary && secondary.trim()) {
				return secondary.trim();
			}

			return '';
		},

		/**
		 * Update a form field value
		 */
		updateField: function(fieldId, value) {
			var $field = $('#' + fieldId);
			if ($field.length && value) {
				$field.val(value).trigger('change').trigger('blur');
			}
		},

		/**
		 * Update state field with dropdown matching
		 */
		updateStateField: function(fieldId, value) {
			var $field = $('#' + fieldId);
			if ($field.length && value) {
				if ($field.is('select')) {
					var matched = false;
					var $options = $field.find('option');

					if ($options.length) {
						$options.each(function() {
							var optionText = $(this).text().trim();
							if (optionText.toLowerCase() === value.toLowerCase()) {
								$field.val($(this).val()).trigger('change');
								matched = true;
								return false;
							}
						});
					}

					if (!matched) {
						console.warn('[Loqate] State "' + value + '" not found in dropdown options');
					}
				} else {
					$field.val(value).trigger('change').trigger('blur');
				}
			}
		},

		/**
		 * Update country field and trigger WooCommerce state/province update
		 */
		updateCountryField: function(fieldId, countryCode) {
			var $field = $('#' + fieldId);
			if ($field.length && countryCode) {
				$field.val(countryCode).trigger('change');
				$field.trigger('country_to_state_changed');
			}
		},

		/**
		 * Extract country code from country name
		 * Comprehensive mapping of common country names to ISO 3166-1 alpha-2 codes
		 */
		getCountryCodeFromName: function(countryName) {
			if (!countryName) return null;

			// Normalize the country name for matching
			var normalized = countryName.trim().toLowerCase();

			// Comprehensive country map (ISO 3166-1 alpha-2 codes)
			var countryMap = {
				// North America
				'united states': 'US', 'united states of america': 'US', 'usa': 'US', 'us': 'US',
				'canada': 'CA',
				'mexico': 'MX',

				// Europe
				'united kingdom': 'GB', 'great britain': 'GB', 'uk': 'GB', 'england': 'GB',
				'germany': 'DE', 'deutschland': 'DE',
				'france': 'FR',
				'italy': 'IT', 'italia': 'IT',
				'spain': 'ES', 'españa': 'ES',
				'portugal': 'PT',
				'netherlands': 'NL', 'holland': 'NL',
				'belgium': 'BE',
				'switzerland': 'CH', 'schweiz': 'CH', 'suisse': 'CH',
				'austria': 'AT', 'österreich': 'AT',
				'ireland': 'IE',
				'sweden': 'SE', 'sverige': 'SE',
				'norway': 'NO', 'norge': 'NO',
				'denmark': 'DK', 'danmark': 'DK',
				'finland': 'FI', 'suomi': 'FI',
				'poland': 'PL', 'polska': 'PL',
				'czech republic': 'CZ', 'czechia': 'CZ',
				'greece': 'GR',
				'hungary': 'HU',
				'romania': 'RO',

				// Asia Pacific
				'australia': 'AU',
				'new zealand': 'NZ',
				'japan': 'JP', '日本': 'JP',
				'china': 'CN', '中国': 'CN',
				'south korea': 'KR', 'korea': 'KR', '대한민국': 'KR',
				'singapore': 'SG',
				'hong kong': 'HK',
				'taiwan': 'TW',
				'malaysia': 'MY',
				'thailand': 'TH',
				'indonesia': 'ID',
				'philippines': 'PH',
				'vietnam': 'VN',
				'india': 'IN',

				// South America
				'brazil': 'BR', 'brasil': 'BR',
				'argentina': 'AR',
				'chile': 'CL',
				'colombia': 'CO',
				'peru': 'PE',

				// Middle East
				'united arab emirates': 'AE', 'uae': 'AE',
				'saudi arabia': 'SA',
				'israel': 'IL',
				'qatar': 'QA',

				// Africa
				'south africa': 'ZA',
				'egypt': 'EG',
				'nigeria': 'NG',
				'kenya': 'KE'
			};

			return countryMap[normalized] || null;
		},

		/**
		 * Show field feedback (loading, success, error) with spinner icon
		 */
		showFieldFeedback: function(fieldId, type, message) {
			var $field = $('#' + fieldId);
			var $wrapper = $field.closest('.form-row, .form-group');

			if (!$wrapper.length) return;

			this.clearFieldFeedback(fieldId);

			$wrapper.removeClass('loqate-loading loqate-success loqate-error')
				.addClass('loqate-' + type);

			// For loading state, create and show spinner icon inside the input field
			if (type === 'loading') {
				// Ensure wrapper has position: relative for absolute positioning of spinner
				if ($wrapper.css('position') === 'static') {
					$wrapper.css('position', 'relative');
				}

				// Create spinner element positioned inside the input field
				var $spinner = $('<div class="loqate-loader" aria-label="Loading addresses"></div>');

				// Insert spinner inside the wrapper
				$wrapper.append($spinner);

				// Also add padding to the input field to make room for spinner
				var currentPaddingRight = $field.css('padding-right');
				var paddingValue = parseInt(currentPaddingRight) || 12;
				$field.css('padding-right', (paddingValue + 30) + 'px');

				$spinner.data('fieldId', fieldId);
			}

			// Show message feedback below the field
			if (message) {
				var $message = $('<div class="loqate-feedback loqate-' + type + '"></div>').text(message);
				$wrapper.append($message);

				if (type === 'success') {
					setTimeout(function() {
						$message.fadeOut(300, function() {
							$(this).remove();
						});
						$wrapper.removeClass('loqate-success');
					}, 2000);
				}
			}
		},

		/**
		 * Clear field feedback (including spinner icon)
		 */
		clearFieldFeedback: function(fieldId) {
			var $field = $('#' + fieldId);
			var $wrapper = $field.closest('.form-row, .form-group');

			// Remove spinner icon if it exists (both as sibling and child of wrapper)
			$field.siblings('.loqate-loader').remove();
			$wrapper.find('.loqate-loader').remove();

			// Restore original padding to the field
			$field.css('padding-right', '');

			$wrapper.removeClass('loqate-loading loqate-success loqate-error')
				.find('.loqate-feedback').remove();
		},

		/**
		 * Close all Loqate dropdowns (both parent and child levels)
		 *
		 * This ensures ALL Loqate dropdown elements are fully destroyed
		 */
		closeAllLoqateDropdowns: function() {
			// Close billing address control if it exists
			if (this.billingControl && typeof this.billingControl.close === 'function') {
				try {
					this.billingControl.close();
				} catch (e) {
					console.warn('[Loqate] Error closing billing control:', e);
				}
			}

			// Close shipping address control if it exists
			if (this.shippingControl && typeof this.shippingControl.close === 'function') {
				try {
					this.shippingControl.close();
				} catch (e) {
					console.warn('[Loqate] Error closing shipping control:', e);
				}
			}

			// Remove ALL Loqate dropdown elements synchronously
	
		var $allDropdowns = $(
				'.pcaautocomplete, .pca.pcalist, .pcatext, [data-pca], [role="listbox"], [role="option"], [aria-expanded="true"]'
			);
		$allDropdowns.remove();

			// Trigger escape key on search fields as safety net
			var $billingSearch = $('#' + this.config.billingAddressFields.search);
			var $shippingSearch = $('#' + this.config.shippingAddressFields.search);

			var escapeKeyEvent = $.Event('keydown', { keyCode: 27, which: 27 });
			if ($billingSearch.length) {
				$billingSearch.trigger(escapeKeyEvent);
			}
			if ($shippingSearch.length) {
				$shippingSearch.trigger(escapeKeyEvent);
			}

			// Unbind any Loqate-related event handlers
			if ($billingSearch.length) {
				$billingSearch.off('.loqate');
			}
			if ($shippingSearch.length) {
				$shippingSearch.off('.loqate');
			}
		},

		/**
		 * Unload all controls (for cleanup)
		 */
		unloadControls: function() {
			var controls = ['billingControl', 'shippingControl', 'emailControl', 'phoneControl'];

			controls.forEach(function(controlName) {
				if (this[controlName] && typeof this[controlName].unload === 'function') {
					try {
						this[controlName].unload();
					} catch (e) {
						// Silent fail
					}
					this[controlName] = null;
				}
			}, this);

			// Clean up orphaned DOM elements
			$('.pcaautocomplete, .pca.pcalist, .pcatext').remove();

			// Reset flags
			this.isInitializingBilling = false;
			this.isInitializingShipping = false;
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		window.RCFLoqateAddressCapture.init();
	});

})(jQuery, window);
