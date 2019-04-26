( function( $ ) {

	var $plans_el = $( '.charitable-braintree-plans-wrap' ),
		$mode_el = $( '[name=_campaign_recurring_donation_mode]' ),
		mode = $mode_el.val(),
		$period_el = $( '[name=_campaign_recurring_donation_period]' )
		period = $period_el.val();

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
		$plans_el.show().find( 'tr' ).each( function() {
			if ( period === $( this ).data( 'period' ) ) {
				$( this ).show();
			} else {
				$( this ).hide();
			}
		} );
	}

	// Update tables of plans.
	var update_plan_tables = function() {
		switch ( mode ) {
			case 'disabled':
				return hide_all_plans();
			case 'variable':
				return show_all_plans();
			default:
				return show_period_plans();
		}
	}

	$mode_el.on( 'change', function() {
		mode = $mode_el.val();
		update_plan_tables();
	} );

	$period_el.on( 'change', function() {
		period = $period_el.val();
		update_plan_tables();
	} );

	$( document ).ready( update_plan_tables );

} )( jQuery );