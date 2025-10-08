<?php
/**
 * Plugin Settings class (Backward Compatibility Wrapper)
 *
 * This class now extends the new base settings class for backward compatibility
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WCFST_Settings extends WCFST_Settings_Base {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
    }
}