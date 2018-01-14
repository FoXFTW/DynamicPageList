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

class Logger {
	const LOGGER_TEXT_PREFIX = 'Extension:DynamicPageList (DPL)';

	/**
	 * Buffer of debug messages.
	 *
	 * @var array
	 */
	private $buffer = [];

	/**
	 * Function Documentation
	 *
	 * @param $errorId
	 * @return void
	 */
	public function addMessage( $errorId ) {
		$args = func_get_args();
		$args = array_map( 'htmlspecialchars', $args );

		call_user_func_array( [ $this, 'msg' ], $args );
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
	 * Get a message, with optional parameters
	 * Parameters from user input must be escaped for HTML *before* passing to this function
	 *
	 * @param int $errorId Message ID
	 * @return void
	 */
	public function msg() {
		$args = func_get_args();
		$errorId = array_shift( $args );
		$errorLevel = floor( $errorId / 1000 );
		$errorMessageId = $errorId % 1000;

		if ( DynamicPageListHooks::getDebugLevel() >= $errorLevel ) {
			$text = '';

			if ( DynamicPageListHooks::isLikeIntersection() ) {
				$text = $this->getMessageTextForErrorId( $errorId, $args );
			}

			if ( empty( $text ) ) {
				$text = wfMessage( 'dpl_log_' . $errorMessageId, $args )->text();
			}

			$this->buffer[] = sprintf(
				'<p>%s, version %s: %s</p>',
				static::LOGGER_TEXT_PREFIX,
				DPL_VERSION,
				$text
			);
		}
	}

	/**
	 * @param int $errorId
	 * @param array $messageParameters
	 * @return string
	 */
	private function getMessageTextForErrorId( $errorId, array $messageParameters ) {
		switch ( $errorId ) {
			case Error::FATAL_TOO_MANY_CATEGORIES:
				$text = wfMessage( 'intersection_toomanycats', $messageParameters )->text();
				break;

			case Error::FATAL_TOO_FEW_CATEGORIES:
				$text = wfMessage( 'intersection_toofewcats', $messageParameters )->text();
				break;

			case Error::WARN_NO_RESULTS:
				$text = wfMessage( 'intersection_noresults', $messageParameters )->text();
				break;

			case Error::FATAL_NO_SELECTION;
				$text = wfMessage( 'intersection_noincludecats', $messageParameters )->text();
				break;

			default:
				$text = '';
				break;
		}

		return $text;
	}
}
