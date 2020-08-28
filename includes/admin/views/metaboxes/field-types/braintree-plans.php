<?php
/**
 * Display the table of Braintree plans.
 *
 * @author    Eric Daams
 * @package   Charitable Braintree/Admin Views/Settings
 * @copyright Copyright (c) 2020, Studio 164a
 * @since     1.0.0
 * @version   1.0.0
 */

if ( ! wp_style_is( 'charitable-braintree-admin-styles', 'enqueued' ) ) {
	wp_enqueue_style( 'charitable-braintree-admin-styles' );
}

if ( ! wp_script_is( 'charitable-braintree-admin-script', 'enqueued' ) ) {
	wp_enqueue_script( 'charitable-braintree-admin-script' );
}

$plans         = new Charitable_Braintree_Plans( $view_args['test_mode'] );
$values        = $view_args['value'];
$new_plan_link = charitable_braintree_get_new_plan_link( $view_args['test_mode'] );
$defaults      = charitable_braintree_get_default_plans( $view_args['test_mode'] );

if ( ! is_array( $values ) || empty( $values ) ) {
	$values = $defaults;
}

?>
<fieldset id="<?php echo esc_attr( $view_args['wrapper_id'] ); ?>" class="<?php echo esc_attr( $view_args['wrapper_class'] ); ?>" <?php echo charitable_get_arbitrary_attributes( $view_args ); ?>>
	<?php if ( isset( $view_args['label'] ) ) : ?>
		<legend><?php echo esc_html( $view_args['label'] ); ?></legend>
	<?php endif; ?>
	<table class="widefat charitable-braintree-plan-settings" id="<?php echo esc_attr( $view_args['id'] ); ?>">
		<tbody>
			<?php
			foreach ( charitable_braintree_get_billing_periods() as $period ) :
				$default_choice = '' == $defaults[ $period ] ? '' : __( 'Use default plan', 'charitable-braintree' );
				$period_plans   = $plans->get_plans_by_period( $period, true, $default_choice );
				?>
				<tr data-period="<?php echo esc_attr( $period ); ?>">
					<th><?php echo ucfirst( charitable_recurring_get_donation_period_adverb( $period ) ); ?></th>
					<td>
						<?php if ( ! is_null( $period_plans ) && count( $period_plans ) ) : ?>
							<select name="<?php echo esc_attr( $view_args['key'] ); ?>[<?php echo esc_attr( $period ); ?>]">
								<?php
								foreach ( $period_plans as $plan_id => $plan_name ) :
									if ( $plan_id == $defaults[ $period ] ) :
										$plan_name .= __( ' - default', 'charitable-braintree' );
									endif;
									?>
									<option value="<?php echo esc_attr( $plan_id ); ?>" <?php selected( $plan_id, $values[ $period ] ); ?>><?php echo $plan_name; ?></option>
								<?php endforeach; ?>
							</select>
						<?php else : ?>
							<?php $frequency = $plans->get_billing_frequency_for_period( $period ); ?>
							<input type="hidden" name="<?php echo esc_attr( $view_args['key'] ); ?>[<?php echo esc_attr( $period ); ?>]" value="" />
							<span class="charitable-help">
								<?php
								printf(
									/* translators: %1$s: link to Braintree; %2$d: billing frequency number */
									_n(
										'<a href="%1$s" target="_blank">Create a plan in Braintree</a> and set the billing cycle to repeat <strong>every %2$d month</strong>.',
										'<a href="%1$s" target="_blank">Create a plan in Braintree</a> and set the billing cycle to repeat <strong>every %2$d months</strong>.',
										$frequency,
										'charitable-braintree'
									),
									$new_plan_link,
									$frequency
								);
								?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( isset( $view_args['description'] ) ) : ?>
		<span class="charitable-helper"><?php echo esc_html( $view_args['description'] ); ?></span>
	<?php endif ?>
</fieldset>
