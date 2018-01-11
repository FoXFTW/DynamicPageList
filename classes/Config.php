<?php
/**
 * DynamicPageList3
 * DPL Config Class
 *
 * @author        IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license        GPL
 * @package        DynamicPageList3
 *
 **/

namespace DPL;

use MWException;

class Config {
	/**
	 * Configuration Settings
	 *
	 * @var array
	 */
	static private $settings = [];

	/**
	 * Initialize the static object with settings.
	 *
	 * @param bool $settings Settings to initialize for DPL.
	 * @return void
	 * @throws \MWException
	 */
	static public function init( $settings = false ) {
		if ( $settings === false ) {
			global $wgDplSettings;

			$settings = $wgDplSettings;
		}

		if ( !is_array( $settings ) ) {
			throw new MWException( __METHOD__ . ": Invalid settings passed." );
		}

		self::$settings = array_merge( self::$settings, $settings );
	}

	/**
	 * Return a single setting.
	 *
	 * @param string $setting Setting Key
	 * @return mixed The setting's actual setting or null if it does not exist.
	 */
	static public function getSetting( $setting ) {
		return ( array_key_exists( $setting, self::$settings ) ? self::$settings[$setting] : null );
	}

	/**
	 * Return a all settings.
	 *
	 * @return array All settings
	 */
	static public function getAllSettings() {
		return self::$settings;
	}

	/**
	 * Set a single setting.
	 *
	 * @param string $setting Setting Key
	 * @param mixed $value [Optional] Appropriate value for the setting key.
	 * @return void
	 * @throws \MWException
	 */
	static public function setSetting( $setting, $value = null ) {
		if ( empty( $setting ) || !is_string( $setting ) ) {
			throw new MWException( __METHOD__ . ": Setting keys can not be blank." );
		}
		self::$settings[$setting] = $value;
	}
}
