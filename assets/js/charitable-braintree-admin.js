( function( $ ) {

	/**
	 * Conditionally hide and show plans in campaign settings meta box.
	 */
	var Braintree_Plans = function() {
		var $plans_el = $( '.charitable-braintree-plans-wrap' );

		// Get field.
		var get_field = function( name ) {
			var $el = $( 'select[name=' + name + ']' );
			return $el.length ? $el : $( '[name=' + name + '][type=radio]:checked' );
		}

		// Get mode.
		var get_mode = function() {
			return get_field( '_campaign_recurring_donation_mode' ).val();
		}

		// Get frequency choice.
		var get_frequency_choice = function() {
			var field = get_field( '_campaign_recurring_donation_frequency_mode' );
			if ( field.length ) {
				return field.val();
			}

			return get_field( '_campaign_recurring_donation_period_mode' ).val();
		}

		// Get period.
		var get_period = function() {
			return get_field( '_campaign_recurring_donation_period' ).val();
		}

		// Hide all plans.
		var hide_all_plans = function() {
			$plans_el.hide();
		}

		// Show all plans.
		var show_all_plans = function() {
			$plans_el.show().find( 'tr' ).show();
		}

		// Show a single row of plans.
		var show_period_plans = function() {
			var period = get_period();

			$plans_el.show().find( 'tr' ).each( function() {
				$( this ).toggle( $( this ).data( 'period' ) === period );
			} );
		}

		// Update tables of plans.
		var update_plan_tables = function() {
			var mode = get_mode();

			if ( 'disabled' === mode ) {
				return hide_all_plans();
			}

			if ( 'variable' === mode || 'variable' === get_frequency_choice() ) {
				return show_all_plans();
			}

			return show_period_plans();
		}

		// Update list of donation periods, convering unavailable options to 'disabled'.
		var update_period_list = function() {
			var $field    = $( '#charitable-campaign-recurring-donation-period-wrap' );
			var available = $field.data( 'available-periods' );

			$field.find( '[name=_campaign_recurring_donation_period]' ).each( function() {
				if ( -1 === available.indexOf( this.value ) ) {
					this.disabled = true;
					if ( this.checked ) {
						$field.find( '[name=_campaign_recurring_donation_period][value=' + available[0] + ']' )[0].checked = true;
					}
				}
			} );
		}

		// Init.
		update_period_list();
		update_plan_tables();

		// Set up event listeners.
		$( '[name=_campaign_recurring_donation_mode]' ).on( 'change', update_plan_tables );
		$( '[name=_campaign_recurring_donation_frequency_mode]' ).on( 'change', update_plan_tables );
		$( '[name=_campaign_recurring_donation_period_mode]' ).on( 'change', update_plan_tables );
		$( '[name=_campaign_recurring_donation_period]' ).on( 'change', update_plan_tables );
	};

	/**
	 * Merchant Account setting field management.
	 *
	 * The field is hidden untill the merchant ID, private key and public key are
	 * added. When they are added, we attempt to fetch the merchant accounts via
	 * the API to allow the user to select one.
	 */
	var Braintree_Merchant_Account_Settings = function( test_mode, merchant_account_el, merchant_id_el, public_key_el, private_key_el ) {
		var self = this;

		this.test_mode = test_mode;
		this.merchant_account_el = merchant_account_el;
		this.merchant_id_el = merchant_id_el;
		this.public_key_el = public_key_el;
		this.private_key_el = private_key_el;

		// Conditionally reveal or hide a merchant account setting.
		var update_merchant_account_setting = function() {
			if ( ! ( self.merchant_id_el.val().length && self.public_key_el.val().length && self.private_key_el.val().length ) ) {
				return self.merchant_account_el.parents( 'tr' ).hide();
			};

			// Display a loading dashicon icon.
			$( '<span class="dashicons dashicons-update" style="-webkit-animation: rotation 2s infinite linear;animation: rotation 2s infinite linear;"></span>' )
				.insertBefore( self.merchant_account_el );

			// Hide the select field.
			self.merchant_account_el.hide();

			// Reveal the field.
			self.merchant_account_el.parents( 'tr' ).show();

			// Attempt to get the merchant accounts via AJAX.
			$.ajax({
				type: "POST",
				data: {
					action: 'charitable_braintree_get_merchant_accounts',
					merchant_id: self.merchant_id_el.val(),
					public_key: self.public_key_el.val(),
					private_key: self.private_key_el.val(),
					test_mode: self.test_mode,
					field_name: self.merchant_account_el.attr( 'name' ),
					field_classes: self.merchant_account_el.attr( 'class' ),
					braintree_settings_nonce: $( '#braintree_settings_nonce' ).val(),
				},
				url: ajaxurl,
				xhrFields: {
					withCredentials: true
				},
				success: function( response ) {
					if ( window.console && window.console.log ) {
						console.log( response );
					}

					self.merchant_account_el.parent( 'td' ).html( response.data );

				}
			}).fail(function (data) {
				if ( window.console && window.console.log ) {
					console.log( data );
				}
			});
		};

		// Init.
		( function() {
			self.merchant_id_el.on( 'change', update_merchant_account_setting );
			self.public_key_el.on( 'change', update_merchant_account_setting );
			self.private_key_el.on( 'change', update_merchant_account_setting );

			update_merchant_account_setting();
		} )();
	};

	/**
	 * Run init for our main functions.
	 */
	$( document ).ready( function() {
		if ( $( '#charitable_settings_gateways_braintree_live_merchant_account_id' ).length ) {
			new Braintree_Merchant_Account_Settings(
				false,
				$( '#charitable_settings_gateways_braintree_live_merchant_account_id' ),
				$( '#charitable_settings_gateways_braintree_live_merchant_id' ),
				$( '#charitable_settings_gateways_braintree_live_public_key' ),
				$( '#charitable_settings_gateways_braintree_live_private_key' )
			);

			new Braintree_Merchant_Account_Settings(
				true,
				$( '#charitable_settings_gateways_braintree_test_merchant_account_id' ),
				$( '#charitable_settings_gateways_braintree_test_merchant_id' ),
				$( '#charitable_settings_gateways_braintree_test_public_key' ),
				$( '#charitable_settings_gateways_braintree_test_private_key' )
			);
		}

		Braintree_Plans();
	} );

} )( jQuery );