<?php
/**
 * Local development wp-config additions.
 *
 * Copy these constants to wp-config.php if needed.
 *
 * @package CFR2OffLoad
 */

// Debug settings.
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', true );
define( 'SCRIPT_DEBUG', true );
define( 'SAVEQUERIES', true );

// Development URLs.
define( 'WP_HOME', 'http://localhost:8080' );
define( 'WP_SITEURL', 'http://localhost:8080' );

// Disable auto-updates.
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_AUTO_UPDATE_CORE', false );

// Memory.
define( 'WP_MEMORY_LIMIT', '256M' );

// Disable file editing in admin.
define( 'DISALLOW_FILE_EDIT', true );
