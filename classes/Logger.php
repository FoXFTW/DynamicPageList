<?php
/**
 * DynamicPageList3
 * DPL Logger Class
 *
 * @author IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license GPL
 * @package DynamicPageList3
 *
 **/

namespace DPL;

use DynamicPageListHooks;
use Psr\Log\LoggerInterface;

class Logger implements LoggerInterface {

	const LOG_MESSAGE_PREFIX = 'dpl_log_';
	const LOGGER_TEXT_PREFIX = 'Extension:DynamicPageList (DPL)';
	const LEVEL_DEBUG_SQL_NOWIKI = 10;
	const LEVEL_DEBUG_SQL = 9;
	const LEVEL_DEBUG = 8;
	const LEVEL_INFO = 7;
	const LEVEL_NOTICE = 6;
	const LEVEL_WARNING = 5;
	const LEVEL_ERROR = 4;
	const LEVEL_CRITICAL = 3;
	const LEVEL_ALERT = 2;
	const LEVEL_EMERGENCY = 1;
	const LEVEL_NONE = 0;

	/**
	 * @var \DPL\Logger
	 */
	private static $instance;
	/**
	 * Buffer of debug messages.
	 *
	 * @var array
	 */
	private $buffer = [];

	private function __construct() { }

	public static function getInstance() {
		if ( is_null( static::$instance ) ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Return the buffer of messages.
	 *
	 * @param bool $clearBuffer [Optional] Clear the message buffer.
	 * @return array Messages in the order added.
	 */
	public function getMessages( $clearBuffer = true ) {
		$buffer = $this->buffer;

		if ( $clearBuffer === true ) {
			$this->buffer = [];
		}

		return $buffer;
	}

	/**
	 * @param int $errorId
	 * @param array $options
	 * @return string
	 */
	private function getIntersectionErrorTextForId( $errorId, array $options ) {
		switch ( $errorId ) {
			case Error::CRITICAL_TOO_MANY_CATEGORIES:
				$text = wfMessage( 'intersection_toomanycats', $options )->text();
				break;

			case Error::CRITICAL_TOO_FEW_CATEGORIES:
				$text = wfMessage( 'intersection_toofewcats', $options )->text();
				break;

			case Error::WARN_NO_RESULTS:
				$text = wfMessage( 'intersection_noresults', $options )->text();
				break;

			case Error::CRITICAL_NO_SELECTION;
				$text = wfMessage( 'intersection_noincludecats', $options )->text();
				break;

			default:
				$text = '';
				break;
		}

		return $text;
	}

	/**
	 * System is unusable.
	 *
	 * @param string $message
	 * @param array $context
	 *
	 * @return void
	 */
	public function emergency( $message, array $context = [] ) {
		$this->log( static::LEVEL_EMERGENCY, $message, $context );
	}

	/**
	 * Add a log entry to the message buffer
	 * If $message is not a string wfMessage will be invoked with 'dpl_log_'.$message
	 *
	 * @param int $level
	 * @param string|int $message
	 * @param array $context
	 *
	 * @return void
	 */
	public function log( $level, $message, array $context = [] ) {
		$context = array_map( 'htmlspecialchars', $context );

		if ( is_numeric( $message ) ) {
			if (DynamicPageListHooks::isLikeIntersection()) {
				$message = $this->getIntersectionErrorTextForId($message, $context);
			} else {
				$message = wfMessage( static::LOG_MESSAGE_PREFIX . $message, $context)->text();
			}
		} else {
			$message = $message . ' (' . implode( ', ', $context ) . ')';
		}

		if ( DynamicPageListHooks::getDebugLevel() >= $level) {
			$this->buffer[] =
				sprintf( '<p>%s, version %s: %s</p>', static::LOGGER_TEXT_PREFIX, DPL_VERSION,
					$message );
		}
	}

	/**
	 * Action must be taken immediately.
	 *
	 * Example: Entire website down, database unavailable, etc. This should
	 * trigger the SMS alerts and wake you up.
	 *
	 * @param string $message
	 * @param array $context
	 *
	 * @return void
	 */
	public function alert( $message, array $context = [] ) {
		$this->log( static::LEVEL_ALERT, $message, $context );
	}

	/**
	 * Critical conditions.
	 *
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param string $message
	 * @param array $context
	 *
	 * @return void
	 */
	public function critical( $message, array $context = [] ) {
		$this->log( static::LEVEL_CRITICAL, $message, $context );
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message
	 * @param array $context
	 *
	 * @return void
	 */
	public function error( $message, array $context = [] ) {
		$this->log( static::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things
	 * that are not necessarily wrong.
	 *
	 * @param string $message
	 * @param array $context
	 *
	 * @return void
	 */
	public function warning( $message, array $context = [] ) {
		$this->log( static::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string $message
	 * @param array $context
	 *
	 * @return void
	 */
	public function notice( $message, array $context = [] ) {
		$this->log( static::LEVEL_NOTICE, $message, $context );
	}

	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message
	 * @param array $context
	 *
	 * @return void
	 */
	public function info( $message, array $context = [] ) {
		$this->log( static::LEVEL_INFO, $message, $context );
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string $message
	 * @param array $context
	 *
	 * @return void
	 */
	public function debug( $message, array $context = [] ) {
		$this->log( static::LEVEL_DEBUG, $message, $context );
	}
}
