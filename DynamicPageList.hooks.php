<?php
/**
 *
 * @file
 * @ingroup Extensions
 * @link http://www.mediawiki.org/wiki/Extension:DynamicPageList_(third-party)	Documentation
 * @author n:en:User:IlyaHaykinson
 * @author n:en:User:Amgine
 * @author w:de:Benutzer:Unendlich
 * @author m:User:Dangerman <cyril.dangerville@gmail.com>
 * @author m:User:Algorithmix <gero.scholz@gmx.de>
 * @author m:User:FoXFTW <foxftw@octofox.de>
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

use DPL\Config;
use DPL\LST;
use DPL\Parse;
use DPL\Variables;

class DynamicPageListHooks {
	public static $fixedCategories = [];

	/**
	 * the links created by DPL are collected here;
	 * they can be removed during the final ouput
	 * phase of the MediaWiki parser
	 *
	 * @var array
	 */
	public static $createdLinks;

	/**
	 * DPL acting like Extension:Intersection
	 *
	 * @var bool
	 */
	private static $likeIntersection = false;

	/**
	 * Debugging Level
	 *
	 * @var integer
	 */
	private static $debugLevel = 0;

	/**
	 * Handle special on extension registration bits.
	 *
	 * @return void
	 */
	public static function onRegistration() {
		if ( !defined( 'DPL_VERSION' ) ) {
			define( 'DPL_VERSION', '3.1.1' );
		}
	}

	/**
	 * Sets up this extension's parser functions.
	 *
	 * @param \Parser $parser Parser object passed as a reference.
	 * @return bool true
	 * @throws \MWException
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		self::init();

		//DPL offers the same functionality as Intersection.  So we register the <DynamicPageList> tag in case LabeledSection Extension is not installed so that the section markers are removed.
		if ( Config::getSetting( 'handleSectionTag' ) ) {
			$parser->setHook( 'section', [ __CLASS__, 'dplTag' ] );
		}

		$parser->setHook( 'DPL', [ __CLASS__, 'dplTag' ] );
		$parser->setHook( 'DynamicPageList', [ __CLASS__, 'intersectionTag' ] );

		$parser->setFunctionHook( 'dpl', [ __CLASS__, 'dplParserFunction' ] );
		$parser->setFunctionHook( 'dplnum', [ __CLASS__, 'dplNumParserFunction' ] );
		$parser->setFunctionHook( 'dplvar', [ __CLASS__, 'dplVarParserFunction' ] );
		$parser->setFunctionHook( 'dplarray', [ __CLASS__, 'dplArrayParserFunction' ] );
		$parser->setFunctionHook( 'dplreplace', [ __CLASS__, 'dplReplaceParserFunction' ] );
		$parser->setFunctionHook( 'dplchapter', [ __CLASS__, 'dplChapterParserFunction' ] );
		$parser->setFunctionHook( 'dplmatrix', [ __CLASS__, 'dplMatrixParserFunction' ] );

		return true;
	}

	/**
	 * Common initializer for usage from parser entry points.
	 *
	 * @return void
	 * @throws \MWException
	 */
	private static function init() {
		Config::init();

		if ( !isset( self::$createdLinks ) ) {
			self::$createdLinks = [
				'resetLinks' => false,
				'resetTemplates' => false,
				'resetCategories' => false,
				'resetImages' => false,
				'resetdone' => false,
				'elimdone' => false,
			];
		}
	}

	/**
	 * Sets up this extension's parser functions for migration from Intersection.
	 *
	 * @param \Parser $parser Parser object passed as a reference.
	 * @return bool true
	 * @throws \MWException
	 */
	public static function setupMigration( Parser &$parser ) {
		$parser->setHook( 'Intersection', [ __CLASS__, 'intersectionTag' ] );

		self::init();

		return true;
	}

	/**
	 * Is like intersection?
	 *
	 * @return bool Behaving Like Intersection
	 */
	public static function isLikeIntersection() {
		return (bool)self::$likeIntersection;
	}

	/**
	 * Tag <section> entry point.
	 *
	 * @param string $input Raw User Input
	 * @param array $args Arguments on the tag.
	 * @param \Parser $parser Parser object.
	 * @param \PPFrame $frame PPFrame object.
	 * @return string HTML
	 * @throws \MWException
	 */
	public static function intersectionTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		self::setLikeIntersection( true );

		return self::executeTag( $input, $args, $parser, $frame );
	}

	/**
	 * Set to behave like intersection.
	 *
	 * @param bool $mode Behave Like Intersection
	 * @return void
	 */
	private static function setLikeIntersection( $mode = false ) {
		self::$likeIntersection = $mode;
	}

	/**
	 * The callback function wrapper for converting the input text to HTML output
	 *
	 * @param string $input Raw User Input
	 * @param array $args Arguments on the tag.(While not used, it is left here for future
	 *  compatibility.)
	 * @param \Parser $parser Parser object.
	 * @param \PPFrame $frame PPFrame object.
	 * @return string HTML
	 * @throws \MWException
	 */
	private static function executeTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		// entry point for user tag <dpl>  or  <DynamicPageList>
		// create list and do a recursive parse of the output
		$saveTemplates = [];
		$saveCategories = [];
		$saveImages = [];

		$parse = new Parse();

		if ( Config::getSetting( 'recursiveTagParse' ) ) {
			$input = $parser->recursiveTagParse( $input, $frame );
		}

		$text = $parse->parse( $input, $parser, $reset, $eliminate, true );

		if ( isset( $reset['templates'] ) &&
		     $reset['templates'] ) {    // we can remove the templates by save/restore
			$saveTemplates = $parser->mOutput->mTemplates;
		}

		if ( isset( $reset['categories'] ) &&
		     $reset['categories'] ) {    // we can remove the categories by save/restore
			$saveCategories = $parser->mOutput->mCategories;
		}

		if ( isset( $reset['images'] ) &&
		     $reset['images'] ) {    // we can remove the images by save/restore
			$saveImages = $parser->mOutput->mImages;
		}

		$parsedDPL = $parser->recursiveTagParse( $text );

		if ( isset( $reset['templates'] ) && $reset['templates'] ) {
			$parser->mOutput->mTemplates = $saveTemplates;
		}

		if ( isset( $reset['categories'] ) && $reset['categories'] ) {
			$parser->mOutput->mCategories = $saveCategories;
		}

		if ( isset( $reset['images'] ) && $reset['images'] ) {
			$parser->mOutput->mImages = $saveImages;
		}

		return $parsedDPL;
	}

	/**
	 * Tag <dpl> entry point.
	 *
	 * @param string $input Raw User Input
	 * @param array $args Arguments on the tag.
	 * @param \Parser $parser Parser object.
	 * @param \PPFrame $frame PPFrame object.
	 * @return string HTML
	 * @throws \MWException
	 */
	public static function dplTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		self::setLikeIntersection( false );

		return self::executeTag( $input, $args, $parser, $frame );
	}

	/**
	 * The #dpl parser tag entry point.
	 *
	 * @param \Parser $parser Parser object passed as a reference.
	 * @return array|string Wiki Text
	 * @throws \MWException
	 */
	public static function dplParserFunction( Parser &$parser ) {
		self::setLikeIntersection( false );

		// callback for the parser function {{#dpl:	  or   {{DynamicPageList::
		$input = "";

		$numArgs = func_num_args();

		if ( $numArgs < 2 ) {
			$input = "#dpl: no arguments specified";

			return str_replace( '§', '<', '§pre>§nowiki>' . $input . '§/nowiki>§/pre>' );
		}

		// fetch all user-provided arguments (skipping $parser)
		$arg_list = func_get_args();

		for ( $i = 1; $i < $numArgs; $i ++ ) {
			$p1 = $arg_list[$i];
			$input .= str_replace( "\n", "", $p1 ) . "\n";
		}

		$parse = new Parse();
		$dplResult = $parse->parse( $input, $parser, $reset, $eliminate, false );

		return [ // parser needs to be coaxed to do further recursive processing
		         $parser->getPreprocessor()
			         ->preprocessToObj( $dplResult, Parser::PTD_FOR_INCLUSION ),
		         'isLocalObj' => true,
		         'title' => $parser->getTitle(),
		];

	}

	/**
	 * The #dplnum parser tag entry point.
	 * From the old documentation: "Tries to guess a number that is buried in the text.
	 * Uses a set of heuristic rules which may work or not.
	 * The idea is to extract the number so that it can be used as a sorting value in the column of a DPL table output."
	 *
	 * @param \Parser $parser Parser object passed as a reference.
	 * @param string $text
	 * @return string Wiki Text
	 */
	public static function dplNumParserFunction( Parser &$parser, $text = '' ) {
		$num = str_replace( '&#160;', ' ', $text );
		$num = str_replace( '&nbsp;', ' ', $text );
		$num = preg_replace( '/([0-9])([.])([0-9][0-9]?[^0-9,])/', '\1,\3', $num );
		$num = preg_replace( '/([0-9.]+),([0-9][0-9][0-9])\s*Mrd/', '\1\2 000000 ', $num );
		$num = preg_replace( '/([0-9.]+),([0-9][0-9])\s*Mrd/', '\1\2 0000000 ', $num );
		$num = preg_replace( '/([0-9.]+),([0-9])\s*Mrd/', '\1\2 00000000 ', $num );
		$num = preg_replace( '/\s*Mrd/', '000000000 ', $num );
		$num = preg_replace( '/([0-9.]+),([0-9][0-9][0-9])\s*Mio/', '\1\2 000 ', $num );
		$num = preg_replace( '/([0-9.]+),([0-9][0-9])\s*Mio/', '\1\2 0000 ', $num );
		$num = preg_replace( '/([0-9.]+),([0-9])\s*Mio/', '\1\2 00000 ', $num );
		$num = preg_replace( '/\s*Mio/', '000000 ', $num );
		$num = preg_replace( '/[. ]/', '', $num );
		$num = preg_replace( '/^[^0-9]+/', '', $num );
		$num = preg_replace( '/[^0-9].*/', '', $num );

		return $num;
	}

	/**
	 * @param Parser $parser
	 * @param string $cmd
	 * @return mixed|string
	 */
	public static function dplVarParserFunction( Parser &$parser, $cmd ) {
		$args = func_get_args();

		// Unset Parser and $cmd as they are not used
		array_shift( $args );
		array_shift( $args );

		// Unset First empty Argument for compatibility reasons
		if ( empty( $args[0] ) ) {
			array_shift( $args );
		}

		switch ( $cmd ) {
			case 'set':
				Variables::setVar( $args );

				return null;

			case 'default':
				Variables::setVarDefault( $args );

				return null;
		}

		return Variables::getVar( $cmd );
	}

	/**
	 * @param Parser $parser
	 * @param string $cmd
	 * @return mixed|string
	 */
	public static function dplArrayParserFunction( Parser &$parser, $cmd ) {
		$args = func_get_args();

		// Unset Parser and $cmd as they are not used
		array_shift( $args );
		array_shift( $args );

		// Unset First empty Argument for compatibility reasons
		if ( empty( $args[0] ) ) {
			array_shift( $args );
		}

		switch ( $cmd ) {
			case 'set':
				Variables::setArray( $args );

				return null;

			case 'print':
				return Variables::printArray( $args );
		}

		return Variables::dumpArray( $cmd );
	}

	/**
	 * @param Parser $parser
	 * @param string $text
	 * @param string $pat
	 * @param string $replace
	 * @return null|string|string[]
	 */
	public static function dplReplaceParserFunction(
		Parser &$parser, $text, $pat = '', $replace = ''
	) {
		if ( $text == '' || $pat == '' ) {
			return '';
		}

		# convert \n to a real newline character
		$replace = str_replace( '\n', "\n", $replace );

		# replace
		if ( !self::isRegexp( $pat ) ) {
			$pat = '`' . str_replace( '`', '\`', $pat ) . '`';
		}

		return @preg_replace( $pat, $replace, $text );
	}

	/**
	 * @param string $needle
	 * @return bool
	 */
	private static function isRegexp( $needle ) {
		if ( strlen( $needle ) < 3 ) {
			return false;
		}

		if ( ctype_alnum( $needle[0] ) ) {
			return false;
		}

		$netToNeedle = preg_replace( '/[ismu]*$/', '', $needle );

		if ( strlen( $netToNeedle ) < 2 ) {
			return false;
		}

		if ( $needle[0] == $netToNeedle[strlen( $netToNeedle ) - 1] ) {
			return true;
		}

		return false;
	}

	/**
	 * @param \Parser $parser
	 * @param string $text
	 * @param string $heading
	 * @param int $maxLength
	 * @param string $page
	 * @param string $link
	 * @param bool $trim
	 * @return mixed
	 */
	public static function dplChapterParserFunction(
		Parser &$parser, $text = '', $heading = ' ', $maxLength = - 1, $page = '?page?',
		$link = 'default', $trim = false
	) {
		$output =
			LST::extractHeadingFromText( $parser, $page, '?title?', $text, $heading, '',
				$sectionHeading, true, $maxLength, $link, $trim );

		return $output[0];
	}

	/**
	 * @param \Parser $parser
	 * @param string $name
	 * @param string $yes
	 * @param string $no
	 * @param string $flip
	 * @param string $matrix
	 * @return string
	 */
	public static function dplMatrixParserFunction(
		Parser &$parser, $name = '', $yes = '', $no = '', $flip = '', $matrix = ''
	) {
		$lines = explode( "\n", $matrix );
		$m = [];
		$sources = [];
		$targets = [];
		$from = '';

		if ( $flip == '' | $flip == 'normal' ) {
			$flip = false;
		} else {
			$flip = true;
		}

		if ( $name == '' ) {
			$name = '&#160;';
		}

		if ( $yes == '' ) {
			$yes = ' x ';
		}

		if ( $no == '' ) {
			$no = '&#160;';
		}

		if ( $no[0] == '-' ) {
			$no = " $no ";
		}

		foreach ( $lines as $line ) {
			if ( strlen( $line ) <= 0 ) {
				continue;
			}

			if ( $line[0] != ' ' ) {
				$from = preg_split( ' *\~\~ *', trim( $line ), 2 );

				if ( !array_key_exists( $from[0], $sources ) ) {
					if ( count( $from ) < 2 || $from[1] == '' ) {
						$sources[$from[0]] = $from[0];
					} else {
						$sources[$from[0]] = $from[1];
					}

					$m[$from[0]] = [];
				}
			} elseif ( trim( $line ) != '' ) {
				$to = preg_split( ' *\~\~ *', trim( $line ), 2 );

				if ( count( $to ) < 2 || $to[1] == '' ) {
					$targets[$to[0]] = $to[0];
				} else {
					$targets[$to[0]] = $to[1];
				}

				$m[$from[0]][$to[0]] = true;
			}
		}

		ksort( $targets );

		$header = "\n";

		if ( $flip ) {
			foreach ( $sources as $from => $fromName ) {
				$header .= "![[$from|" . $fromName . "]]\n";
			}

			foreach ( $targets as $to => $toName ) {
				$targets[$to] = "[[$to|$toName]]";

				foreach ( $sources as $from => $fromName ) {
					if ( array_key_exists( $to, $m[$from] ) ) {
						$targets[$to] .= "\n|$yes";
					} else {
						$targets[$to] .= "\n|$no";
					}
				}

				$targets[$to] .= "\n|--\n";
			}

			return "{|class=dplmatrix\n|$name" . "\n" . $header . "|--\n!" .
			       join( "\n!", $targets ) . "\n|}";
		} else {
			foreach ( $targets as $to => $toName ) {
				$header .= "![[$to|" . $toName . "]]\n";
			}

			foreach ( $sources as $from => $fromName ) {
				$sources[$from] = "[[$from|$fromName]]";

				foreach ( $targets as $to => $toName ) {
					if ( array_key_exists( $to, $m[$from] ) ) {
						$sources[$from] .= "\n|$yes";
					} else {
						$sources[$from] .= "\n|$no";
					}
				}

				$sources[$from] .= "\n|--\n";
			}

			return "{|class=dplmatrix\n|$name" . "\n" . $header . "|--\n!" .
			       join( "\n!", $sources ) . "\n|}";
		}
	}

	/**
	 * @param $in
	 * @param array $assocArgs
	 * @param null $parser
	 * @return string empty
	 */
	public static function removeSectionMarkers( $in, $assocArgs = [], $parser = null ) {
		return '';
	}

	/**
	 * remove section markers in case the LabeledSectionTransclusion extension is not installed.
	 *
	 * @param string $cat Category
	 */
	public static function fixCategory( $cat ) {
		if ( $cat != '' ) {
			self::$fixedCategories[$cat] = 1;
		}
	}

	/**
	 * Return Debugging Level
	 *
	 * @return int
	 */
	public static function getDebugLevel() {
		return self::$debugLevel;
	}

	/**
	 * Set Debugging Level
	 *
	 * @param int $level Debug Level
	 * @return void
	 */
	public static function setDebugLevel( $level ) {
		self::$debugLevel = intval( $level );
	}

	/**
	 * @param \Parser $parser
	 * @param $text
	 * @return bool
	 */
	public static function endReset( Parser &$parser, $text ) {
		if ( !self::$createdLinks['resetdone'] ) {
			self::$createdLinks['resetdone'] = true;

			foreach ( $parser->mOutput->mCategories as $key => $val ) {
				if ( array_key_exists( $key, self::$fixedCategories ) ) {
					self::$fixedCategories[$key] = $val;
				}
			}

			// $text .= self::dumpParsedRefs($parser,"before final reset");
			if ( self::$createdLinks['resetLinks'] ) {
				$parser->mOutput->mLinks = [];
			}

			if ( self::$createdLinks['resetCategories'] ) {
				$parser->mOutput->mCategories = self::$fixedCategories;
			}

			if ( self::$createdLinks['resetTemplates'] ) {
				$parser->mOutput->mTemplates = [];
			}

			if ( self::$createdLinks['resetImages'] ) {
				$parser->mOutput->mImages = [];
			}

			// $text .= self::dumpParsedRefs($parser,"after final reset");
			self::$fixedCategories = [];
		}

		return true;
	}

	/**
	 * reset everything; some categories may have been fixed, however via  fixcategory=
	 *
	 * @param \Parser $parser
	 * @param string $text
	 * @return bool
	 */
	public static function endEliminate( Parser &$parser, &$text ) {
		// called during the final output phase; removes links created by DPL
		if ( isset( self::$createdLinks ) ) {
			// self::dumpParsedRefs($parser,"before final eliminate");
			if ( array_key_exists( 0, self::$createdLinks ) ) {
				foreach ( $parser->mOutput->getLinks() as $nsp => $link ) {
					if ( !array_key_exists( $nsp, self::$createdLinks[0] ) ) {
						continue;
					}

					// echo ("<pre> elim: created Links [$nsp] = ". count(DynamicPageListHooks::$createdLinks[0][$nsp])."</pre>\n");
					// echo ("<pre> elim: parser  Links [$nsp] = ". count($parser->mOutput->mLinks[$nsp])			 ."</pre>\n");
					$parser->mOutput->mLinks[$nsp] =
						array_diff_assoc( $parser->mOutput->mLinks[$nsp],
							self::$createdLinks[0][$nsp] );

					// echo ("<pre> elim: parser  Links [$nsp] nachher = ". count($parser->mOutput->mLinks[$nsp])	  ."</pre>\n");
					if ( count( $parser->mOutput->mLinks[$nsp] ) == 0 ) {
						unset( $parser->mOutput->mLinks[$nsp] );
					}
				}
			}
			if ( isset( self::$createdLinks ) && array_key_exists( 1, self::$createdLinks ) ) {
				foreach ( $parser->mOutput->mTemplates as $nsp => $tpl ) {
					if ( !array_key_exists( $nsp, self::$createdLinks[1] ) ) {
						continue;
					}

					// echo ("<pre> elim: created Tpls [$nsp] = ". count(DynamicPageListHooks::$createdLinks[1][$nsp])."</pre>\n");
					// echo ("<pre> elim: parser  Tpls [$nsp] = ". count($parser->mOutput->mTemplates[$nsp])			."</pre>\n");
					$parser->mOutput->mTemplates[$nsp] =
						array_diff_assoc( $parser->mOutput->mTemplates[$nsp],
							self::$createdLinks[1][$nsp] );

					// echo ("<pre> elim: parser  Tpls [$nsp] nachher = ". count($parser->mOutput->mTemplates[$nsp])	 ."</pre>\n");
					if ( count( $parser->mOutput->mTemplates[$nsp] ) == 0 ) {
						unset( $parser->mOutput->mTemplates[$nsp] );
					}
				}
			}

			if ( isset( self::$createdLinks ) && array_key_exists( 2, self::$createdLinks ) ) {
				$parser->mOutput->mCategories =
					array_diff_assoc( $parser->mOutput->mCategories, self::$createdLinks[2] );
			}

			if ( isset( self::$createdLinks ) && array_key_exists( 3, self::$createdLinks ) ) {
				$parser->mOutput->mImages =
					array_diff_assoc( $parser->mOutput->mImages, self::$createdLinks[3] );
			}
			// $text .= self::dumpParsedRefs($parser,"after final eliminate".$parser->mTitle->getText());
		}

		//self::$createdLinks=array(
		//		  'resetLinks'=> false, 'resetTemplates' => false,
		//		  'resetCategories' => false, 'resetImages' => false, 'resetdone' => false );
		return true;
	}

	/**
	 * Setups and Modifies Database Information
	 *
	 * @param DatabaseUpdater $updater [Optional] DatabaseUpdater Object
	 * @return bool true
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater = null ) {
		$updater->addExtensionUpdate( [ [ __CLASS__, 'createDPLTemplate' ] ] );

		return true;
	}

	/**
	 * Creates the DPL template when updating.
	 *
	 * @return void
	 * @throws \MWException
	 */
	public static function createDPLTemplate() {
		//Make sure page "Template:Extension DPL" exists
		$title = Title::newFromText( 'Template:Extension DPL' );

		if ( !$title->exists() ) {
			$page = WikiPage::factory( $title );
			$pageContent =
				ContentHandler::makeContent( "<noinclude>This page was automatically created. It serves as an anchor page for all '''[[Special:WhatLinksHere/Template:Extension_DPL|invocations]]''' of [http://mediawiki.org/wiki/Extension:DynamicPageList Extension:DynamicPageList (DPL)].</noinclude>",
					$title );
			$page->doEditContent( $pageContent, $title, EDIT_NEW | EDIT_FORCE_BOT );
		}
	}

	/**
	 * Only used in Development
	 *
	 * @param \Parser $parser
	 * @param string $label
	 */
	private static function dumpParsedRefs( Parser $parser, $label ) {
		//if (!preg_match("/Query Q/",$parser->mTitle->getText())) return '';
		echo '<pre>parser mLinks: ';
		ob_start();
		var_dump( $parser->mOutput->mLinks );
		$a = ob_get_contents();
		ob_end_clean();
		echo htmlspecialchars( $a, ENT_QUOTES );
		echo '</pre>';
		echo '<pre>parser mTemplates: ';
		ob_start();
		var_dump( $parser->mOutput->mTemplates );
		$a = ob_get_contents();
		ob_end_clean();
		echo htmlspecialchars( $a, ENT_QUOTES );
		echo '</pre>';
	}
}
