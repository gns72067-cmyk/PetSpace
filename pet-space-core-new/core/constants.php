<?php
defined( 'ABSPATH' ) || exit;

define( 'PSC_VERSION',     '1.0.0' );
define( 'PSC_PLUGIN_FILE', __FILE__ );
define( 'PSC_PLUGIN_DIR',  plugin_dir_path( dirname( __FILE__ ) ) );
define( 'PSC_PLUGIN_URL',  plugin_dir_url(  dirname( __FILE__ ) ) );
define( 'PSC_CORE_DIR',    PSC_PLUGIN_DIR . 'core/' );
define( 'PSC_MODULES_DIR', PSC_PLUGIN_DIR . 'modules/' );
define( 'PSC_CORE_URL',    PSC_PLUGIN_URL . 'core/' );
define( 'PSC_MODULES_URL', PSC_PLUGIN_URL . 'modules/' );
