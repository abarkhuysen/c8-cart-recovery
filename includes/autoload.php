<?php
/**
 * PSR-4 Autoloader for Creative8\CartRecovery namespace
 *
 * @package C8_Cart_Recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	function ( $class ) {
		// Class map for explicit file mappings.
		$class_map = array(
			'Creative8\\CartRecovery\\Plugin'           => 'class-plugin.php',
			'Creative8\\CartRecovery\\CartTracker'      => 'class-cart-tracker.php',
			'Creative8\\CartRecovery\\CartRecovery'     => 'class-cart-recovery.php',
			'Creative8\\CartRecovery\\CronHandler'      => 'class-cron-handler.php',
			'Creative8\\CartRecovery\\Admin\\Admin'     => 'admin/class-admin.php',
			'Creative8\\CartRecovery\\Admin\\ListTable' => 'admin/class-list-table.php',
			'Creative8\\CartRecovery\\Email\\AbandonedCart' => 'emails/class-abandoned-cart.php',
		);

		// Check if class is in our map.
		if ( ! isset( $class_map[ $class ] ) ) {
			return;
		}

		$file = C8CR_PLUGIN_PATH . 'includes/' . $class_map[ $class ];

		// If the file exists, require it.
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
