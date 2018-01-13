<?php
/**
 * DynamicPageList3
 * DPL Config Class
 *
 * @author IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license GPL
 * @package DynamicPageList3
 **/

namespace DPL;

use MWException;

class Config {
	/**
	 * Configuration Settings
	 *
	 * @var array
	 */
	private static $settings = [];

	/**
	 * Initialize the static object with settings.
	 *
	 * @param array|bool $settings Settings to initialize for DPL.
	 *  If false, uses global $wgDplSettings
	 * @return void
	 * @throws \MWException
	 */
	public static function init( $settings = false ) {
		if ( $settings === false ) {
			global $wgDplSettings;

			$settings = $wgDplSettings;
		}

		if ( !is_array( $settings ) ) {
			throw new MWException( __METHOD__ . ": Invalid settings passed." );
		}

		self::$settings = $settings;
	}

	/**
	 * Return a single setting.
	 *
	 * @param string $setting Setting Key
	 * @return mixed The setting's actual setting or null if it does not exist.
	 */
	public static function getSetting( $setting ) {
		if ( array_key_exists( $setting, self::$settings ) ) {
			return self::$settings[$setting];
		}

		return null;
	}

	/**
	 * Return all settings.
	 *
	 * @return array All settings
	 */
	public static function getAllSettings() {
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
	public static function setSetting( $setting, $value = null ) {
		if ( empty( $setting ) || !is_string( $setting ) ) {
			throw new MWException( __METHOD__ . ": Setting keys can not be blank." );
		}
		self::$settings[$setting] = $value;
	}
}
