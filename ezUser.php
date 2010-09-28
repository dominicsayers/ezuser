<?php
/**
 * Enables user registration and authentication for a website
 * 
 * This code has three principle design goals:
 * 
 *     1. To make it easy for people to register and sign in to your site.
 *     2. To make it easy for you to add this functionality to your site.
 *     3. To make it easy for you to administer the user database on your site.
 * 
 * Other design goals, such as run-time efficiency, are important but secondary to
 * these.
 * 
 * Copyright (c) 2008-2010, Dominic Sayers							<br>
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 * 
 *     - Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *     - Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *     - Neither the name of Dominic Sayers nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 * 
 * @package	ezUser
 * @author	Dominic Sayers <dominic@sayers.cc>
 * @copyright	2008-2010 Dominic Sayers
 * @license	http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link	http://code.google.com/p/ezuser/
 * @version	0.27.5 - PHPLint is even tighter, so some code style changes were necessary
 */

// The quality of this code has been improved greatly by using PHPLint
// PHPLint is copyright (c) 2009-2010 Umberto Salsi
// PHPLint is free software; see the license for copying conditions.
// More info: http://www.icosaedro.it/phplint/
/*.
require_module 'dom';
require_module 'pcre';
require_module 'hash';
require_module 'session';
.*/

/* Comment out profiling statements if not needed
function ezuser_time() {list($usec, $sec) = explode(" ",microtime()); return ((float)$usec + (float)$sec);}
$ezuser_profile			= array();
$ezuser_profile['REQUEST_TIME']	= $_SERVER['REQUEST_TIME'];
$ezuser_profile['received']	= ezuser_time();
*/

/**
 * Get and set application settings
 *
 * @package ezUser
 * @version 1.18 (revision number of this common functions class only)
 */
interface I_ezUser_settings {
	const	TYPE_HTML	= 'html',
		TYPE_XML	= 'xml',
		TYPE_JSON	= 'json',
		TYPE_TEXT	= 'text',
		TYPE_ARRAY	= 'array',
		TYPE_FIELDSET	= 'fieldset',

		SETTINGS	= 'settings',
		REQUEST		= 'request';

	public /*.array[string]string.*/	function	get_all	();
	public /*.boolean.*/			function	exists	(/*.string.*/ $name);
	public /*.string.*/			function	get	(/*.string.*/ $name);
	public /*.string.*/			function	set	(/*.string.*/ $name, /*.string.*/ $value);
	public /*.mixed.*/			function	REST	(/*.array[string]mixed.*/ $get, /*.string.*/ $type = self::TYPE_HTML);
}

/**
 * Get and set application settings
 *
 * @package ezUser
 */
class ezUser_settings implements I_ezUser_settings {
	private /*.string.*/			$filename;
	private /*.array[string]string.*/	$settings;
	private static /*.string.*/		function normalize	(/*.string.*/ $name)	{return preg_replace(array('/^"|"$/', '/ |\\./'), array('', '_'), $name);} // Strips quotes and replaces dot and space with underscore (so $name can be a PHP variable)
	private static /*.boolean.*/		function is_tag		(/*.string.*/ $name)	{return ($name === htmlentities($name, ENT_QUOTES));}

	public /*.void.*/ function __construct($filename = '') {
// Revision 1.7: $filename parameter added
		if ($filename === '') $filename	= '.ezuser-settings.php';
		$this->filename	= $filename;
		$settings	= /*.(array[string]string).*/ array();

		if (is_file($filename)) {
			$contents	= @file($filename, FILE_SKIP_EMPTY_LINES);

			foreach ($contents as $line) {
				$split			= strpos($line, '='); if ($split === false) continue;
				$name			= self::normalize(trim(substr($line, 0, $split - 1)));
				$value			= trim(substr($line, $split + 1));

				if (self::is_tag($name)) $settings[$name] = $value;
			}
		}

		$this->settings = $settings;
	}

	public /*.void.*/ function _destruct() {
		$content	= '<?php header("Location: /"); ?'.">\n";
		$settings	= $this->settings;

		foreach ($settings as $name => $value) $content	.= "\t$name\t= $value\n";

		$handle = @fopen($this->filename, 'wb');
		if (is_bool($handle)) exit("Can't create settings file");
		fwrite($handle, $content);
		fclose($handle);
		chmod($this->filename, 0600);
	}

	public /*.array[string]string.*/	function	get_all	()			{return $this->settings;}
	public /*.boolean.*/			function	exists	(/*.string.*/ $name)	{return array_key_exists($name, $this->settings);}
	public /*.string.*/			function	get	(/*.string.*/ $name)	{return (self::is_tag($name) && $this->exists($name)) ? $this->settings[$name] : '';}

	public /*.string.*/ function set(/*.string.*/ $name, /*.string.*/ $value) {
		$name	= strtolower(self::normalize(trim($name)));
		$value	= self::normalize(trim($value));

		if (!self::is_tag($name)) {
			return '(illegal characters in setting name)';
		} else if ($name === self::REQUEST) {
			return '(Can\'t use "' . self::REQUEST . '" as a setting name)';
		} else if ($value === '' || $value === '-') {
			unset($this->settings[$name]);
			return '(deleted)';
		} else {
			$this->settings[$name] = $value;
			return $value;
		}
	}

	private static /*.string.*/ function array_to_text(/*.array[string]string.*/ $output, /*.array[string]string.*/ $updated) {
		if (count($output) === 1) return (string) array_pop($output);

		$text	= '';

		foreach ($output as $name => $value) {
			$updatedMarker	= (array_key_exists($name, $updated)) ? "\t*" : '';
			$text .= "$name\t$value$updatedMarker\n";
		}

		return $text;
	}

	private static /*.string.*/ function array_to_HTML(/*.array[string]string.*/ $output, /*.array[string]string.*/ $updated) {
		$html	= "\n<dl>\n";

		foreach ($output as $name => $value) {
			$updatedMarker	= (array_key_exists($name, $updated)) ? ' style="font-weight:bold;"' : '';
			$html		.= "\t<dt>$name</dt><dd$updatedMarker>$value</dd>\n";
		}

		$html .= "</dl>\n";
		return $html;
	}

	private static /*.DOMDocument.*/ function array_to_XML(/*.array[string]string.*/ $output, /*.array[string]string.*/ $updated) {
		$xml	= "\n<settings>\n";

		foreach ($output as $name => $value) {
			$updatedMarker	= (array_key_exists($name, $updated)) ? ' updated="true"' : '';
			$xml	.= "\t<$name$updatedMarker>$value</$name>\n";
		}

		$xml		.= "</settings>\n";
		$document	= new DOMDocument();

		$document->loadXML($xml);
		return $document;
	}

	private static /*.string.*/ function array_to_JSON(/*.array[string]string.*/ $output, /*.array[string]string.*/ $updated) {
		$json		= '{';
		$delimiter	= '';

		foreach ($output as $name => $value) {
			// Canonical JSON doesn't support comments so we'll omit the Updated marker
			$json		.= "$delimiter$name: \"$value\"";
			$delimiter	= ', ';
		}

		$json .= '}';
		return $json;
	}

	private static /*.string.*/ function array_to_fieldset(/*.array[string]string.*/ $output, /*.array[string]string.*/ $updated) {
		$actionSettings	= self::SETTINGS;
		$tagGroup	= "ezuser-$actionSettings";
		$tabIndex	= 0;

		$html		= <<<HTML

<fieldset id="$tagGroup-fieldset" class="$tagGroup-fieldset">

HTML;

		foreach ($output as $name => $value) {
			$updatedMarker	= (array_key_exists($name, $updated)) ? " $tagGroup-updated" : '';
			$html		.= <<<HTML
	<label	class		=	"ezuser-label $tagGroup-label$updatedMarker"
		for		=	"$tagGroup-$name"	>
		$name
	</label>
	<input	id		=	"$tagGroup-$name"
		class		=	"ezuser-input $tagGroup-input$updatedMarker"
		tabindex	=	"$tabIndex"
		value		=	"$value"
		type		=	"text"	/>

HTML;

			$tabIndex++;
		}

		$html .= "</fieldset>\n";
		return $html;
	}

	public /*.mixed.*/ function REST(/*.array[string]mixed.*/ $get, /*.string.*/ $type = self::TYPE_TEXT, $show_all = false) {
		$output		= /*.(array[string]string).*/ array();
		$updated	= /*.(array[string]string).*/ array();

		foreach ($get as $name => $value) {
			$name = self::normalize($name);
			if ($name === self::REQUEST) continue;

			if (self::is_tag($name)) {
				if ($value !== '') $updated[$name] = $this->set($name, (string) $value);
				$output[$name] = $updated[$name];
			} else {
				$output['ERROR'] = "(Illegal characters in setting name $name)";
			}
		}

		if (count($output) === 0) $output = $this->settings;

		switch (strtolower($type)) {
		case self::TYPE_ARRAY:		return ($show_all) ? $output : $updated;
		case self::TYPE_TEXT:		return self::array_to_text	($output, $updated);
		case self::TYPE_HTML:		return self::array_to_HTML	($output, $updated);
		case self::TYPE_XML:		return self::array_to_XML	($output, $updated);
		case self::TYPE_JSON:		return self::array_to_JSON	($output, $updated);
		case self::TYPE_FIELDSET:	return self::array_to_fieldset	($output, $updated);
		default:			return false;
		}
	}
}
// End of class ezUser_settings


// PHPLint
/*.unchecked.*/ class ezUserException extends Exception {}
/*.forward mixed function cast(string $type, mixed $variable);.*/

/**
 * Common utility functions
 *
 * @package ezUser
 * @version 1.22 (revision number of this common functions class only)
 */

interface I_ezUser_common {
	const	HASH_FUNCTION			= 'SHA256',
		URL_SEPARATOR			= '/',

		// Behaviour settings for strleft()
		STRLEFT_MODE_NONE		= 0,
		STRLEFT_MODE_ALL		= 1,

		// Behaviour settings for getURL()
		URL_MODE_PROTOCOL		= 1,
		URL_MODE_HOST			= 2,
		URL_MODE_PORT			= 4,
		URL_MODE_PATH			= 8,
		URL_MODE_ALL			= 15,

		// Extra GLOB constant for safe_glob()
		GLOB_NODIR			= 256,
		GLOB_PATH			= 512,
		GLOB_NODOTS			= 1024,
		GLOB_RECURSE			= 2048,

		// Email validation constants
		// No errors
		ISEMAIL_VALID			= 0,
		// Warnings (valid address but unlikely in the real world)
		ISEMAIL_WARNING			= 64,
		ISEMAIL_TLD			= 65,
		ISEMAIL_TLDNUMERIC		= 66,
		ISEMAIL_QUOTEDSTRING		= 67,
		ISEMAIL_COMMENTS		= 68,
		ISEMAIL_FWS			= 69,
		ISEMAIL_ADDRESSLITERAL		= 70,
		ISEMAIL_UNLIKELYINITIAL		= 71,
		ISEMAIL_SINGLEGROUPELISION	= 72,
		ISEMAIL_DOMAINNOTFOUND		= 73,
		ISEMAIL_MXNOTFOUND		= 74,
		// Errors (invalid address)
		ISEMAIL_ERROR			= 128,
		ISEMAIL_TOOLONG			= 129,
		ISEMAIL_NOAT			= 130,
		ISEMAIL_NOLOCALPART		= 131,
		ISEMAIL_NODOMAIN		= 132,
		ISEMAIL_ZEROLENGTHELEMENT	= 133,
		ISEMAIL_BADCOMMENT_START	= 134,
		ISEMAIL_BADCOMMENT_END		= 135,
		ISEMAIL_UNESCAPEDDELIM		= 136,
		ISEMAIL_EMPTYELEMENT		= 137,
		ISEMAIL_UNESCAPEDSPECIAL	= 138,
		ISEMAIL_LOCALTOOLONG		= 139,
		ISEMAIL_IPV4BADPREFIX		= 140,
		ISEMAIL_IPV6BADPREFIXMIXED	= 141,
		ISEMAIL_IPV6BADPREFIX		= 142,
		ISEMAIL_IPV6GROUPCOUNT		= 143,
		ISEMAIL_IPV6DOUBLEDOUBLECOLON	= 144,
		ISEMAIL_IPV6BADCHAR		= 145,
		ISEMAIL_IPV6TOOMANYGROUPS	= 146,
		ISEMAIL_DOMAINEMPTYELEMENT	= 147,
		ISEMAIL_DOMAINELEMENTTOOLONG	= 148,
		ISEMAIL_DOMAINBADCHAR		= 149,
		ISEMAIL_DOMAINTOOLONG		= 150,
		ISEMAIL_IPV6SINGLECOLONSTART	= 151,
		ISEMAIL_IPV6SINGLECOLONEND	= 152,
		// Unexpected errors
		ISEMAIL_BADPARAMETER		= 190,
		ISEMAIL_NOTDEFINED		= 191;

	// Basic utility functions
	public static /*.string.*/			function strleft(/*.string.*/ $haystack, /*.string.*/ $needle);
	public static /*.mixed.*/			function reescape(/*.mixed.*/ $literal);
	public static /*.string.*/			function gettype(/*.mixed.*/ $variable);
	public static /*.mixed.*/			function getInnerHTML(/*.string.*/ $html, /*.string.*/ $tag);
	public static /*.array[string][string]string.*/	function meta_to_array(/*.string.*/ $html);
	public static /*.string.*/			function var_dump_to_HTML(/*.string.*/ $var_dump, $offset = 0);
	public static /*.string.*/			function array_to_HTML(/*.array[]mixed.*/ $source = NULL);

	// Session functions
	public static /*.void.*/			function checkSession();	// Version 1.18: Added

	// Environment functions
	public static /*.string.*/			function getURL($mode = self::URL_MODE_PATH, $filename = '');
	public static /*.string.*/			function docBlock_to_HTML(/*.string.*/ $php);

	// File system functions
	public static /*.mixed.*/			function safe_glob(/*.string.*/ $pattern, /*.int.*/ $flags = 0);
	public static /*.string.*/			function getFileContents(/*.string.*/ $filename, /*.int.*/ $flags = 0, /*.object.*/ $context = NULL, /*.int.*/ $offset = -1, /*.int.*/ $maxLen = -1);
	public static /*.string.*/			function findIndexFile(/*.string.*/ $folder);
	public static /*.string.*/			function findTarget(/*.string.*/ $target);

	// Data functions
	public static /*.string.*/			function makeId();
	public static /*.string.*/			function makeUniqueKey(/*.string.*/ $id);
	public static /*.string.*/			function mt_shuffle(/*.string.*/ $str, /*.int.*/ $seed = 0);
//	public static /*.void.*/			function mt_shuffle_array(/*.array.*/ &$shuffle, /*.int.*/ $seed = 0);
	public static /*.string.*/			function prkg(/*.int.*/ $index, /*.int.*/ $length = 6, /*.int.*/ $base = 34, /*.int.*/ $seed = 0);

	// Validation functions
	public static /*.mixed.*/			function is_email(/*.string.*/ $email, $checkDNS = false, /*.mixed.*/ $errorlevel = false);	// Revision 1.20: Parameter name changed
}

/**
 * Common utility functions
 */
abstract class ezUser_common implements I_ezUser_common {
/**
 * Return the beginning of a string, up to but not including the search term.
 *
 * @param string $haystack The string containing the search term
 * @param string $needle The end point of the returned string. In other words, if <var>needle</var> is found then the begging of <var>haystack</var> is returned up to the character before <needle>.
 * @param int $mode If <var>needle</var> is not found then <pre>FALSE</pre> will be returned.
 */
	public static /*.string.*/ function strleft(/*.string.*/ $haystack, /*.string.*/ $needle, /*.int.*/ $mode = self::STRLEFT_MODE_NONE) {
		$posNeedle = strpos($haystack, $needle);

		if ($posNeedle === false) {
			if ($mode === self::STRLEFT_MODE_ALL)
				return $haystack;
			else
				return (string) $posNeedle;
		} else
			return substr($haystack, 0, $posNeedle);
	}

/**
 * Re-escape a string, replacing tabs and carriage returns etc. with their \t, \n equivalent
 *
 * @param mixed $literal The string or array of strings to be re-escaped
 */
	public static /*.mixed.*/ function reescape(/*.mixed.*/ $literal) {
		$search		= array("\t",	"\r",	"\n");
		$replace	= array('\\t',	'\\r',	'\\n');

		return str_replace($search, $replace, $literal);
	}

/**
 * Return the type or class of a variable
 *
 * @param mixed $variable The variable to be typed
 */
	public static /*.string.*/ function gettype(/*.mixed.*/ $variable) {
		if (is_object($variable)) {
			/*.object.*/ $typed = cast('object', $variable);
			$type = get_class($typed);
		} else {
			$type = gettype($variable);
		}

		return $type;
	}

/**
 * Return the contents of an HTML element, the first one matching the <var>tag</var> parameter.
 *
 * @param string $html The string containing the html to be searched
 * @param string $tag The type of element to search for. The contents of first matching element will be returned. If the element doesn't exist then <var>false</var> is returned.
 */
	public static /*.mixed.*/ function getInnerHTML(/*.string.*/ $html, /*.string.*/ $tag) {
		$pos_tag_open_start	= stripos($html, "<$tag")				; if ($pos_tag_open_start	=== false) return false;
		$pos_tag_open_end	= strpos($html, '>',		$pos_tag_open_start)	; if ($pos_tag_open_end		=== false) return false;
		$pos_tag_close		= stripos($html, "</$tag>",	$pos_tag_open_end)	; if ($pos_tag_close		=== false) return false;
		return substr($html, $pos_tag_open_end + 1, $pos_tag_close - $pos_tag_open_end - 1);
	}

/**
 * Return the <var>meta</var> tags from an HTML document as an array.
 *
 * The array returned will have a 'key' element which is an array of name/value pairs representing all the metadata
 * from the HTML document. If there are any <var>name</var> or <var>http-equiv</var> meta elements
 * these will be in their own sub-array. The 'key' sub-array combines all meta tags.
 *
 * Qualifying attributes such as <var>lang</var> and <var>scheme</var> have their own sub-arrays with the same key
 * as the main sub-array.
 *
 * Here are some example meta tags:
 *
 * <pre>
 * <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
 * <meta name="description" content="Free Web tutorials" />
 * <meta name="keywords" content="HTML,CSS,XML,JavaScript" />
 * <meta name="author" content="Hege Refsnes" />
 * <meta http-equiv="Content-Type" content="text/html;charset=ISO-8859-1" />
 * <META NAME="ROBOTS" CONTENT="NOYDIR">
 * <META NAME="Slurp" CONTENT="NOYDIR">
 * <META name="author" content="John Doe">
 *   <META name ="copyright" content="&copy; 1997 Acme Corp.">
 *   <META name= "keywords" content="corporate,guidelines,cataloging">
 *   <META name = "date" content="1994-11-06T08:49:37+00:00">
 *       <meta name="DC.title" lang="en" content="Services to Government" >
 *     <meta name="DCTERMS.modified" scheme="XSD.date" content="2007-07-22" >
 * <META http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
 * <META name="geo.position" content="26.367559;-80.12172">
 * <META name="geo.region" content="US-FL">
 * <META name="geo.placename" content="Boca Raton, FL">
 * <META name="ICBM" content="26.367559, -80.12172">
 * <META name="DC.title" content="THE NAME OF YOUR SITE">
 * </pre>
 *
 * Here is a dump of the returned array:
 *
 * <pre>
 * array (
 *   'key' =>
 *   array (
 *     'Content-Type' => 'text/html; charset=iso-8859-1',
 *     'description' => 'Free Web tutorials',
 *     'keywords' => 'corporate,guidelines,cataloging',
 *     'author' => 'John Doe',
 *     'ROBOTS' => 'NOYDIR',
 *     'Slurp' => 'NOYDIR',
 *     'copyright' => '&copy; 1997 Acme Corp.',
 *     'date' => '1994-11-06T08:49:37+00:00',
 *     'DC.title' => 'THE NAME OF YOUR SITE',
 *     'DCTERMS.modified' => '2007-07-22',
 *     'geo.position' => '26.367559;-80.12172',
 *     'geo.region' => 'US-FL',
 *     'geo.placename' => 'Boca Raton, FL',
 *     'ICBM' => '26.367559, -80.12172',
 *   ),
 *   'http-equiv' =>
 *   array (
 *     'Content-Type' => 'text/html; charset=iso-8859-1',
 *   ),
 *   'name' =>
 *   array (
 *     'description' => 'Free Web tutorials',
 *     'keywords' => 'corporate,guidelines,cataloging',
 *     'author' => 'John Doe',
 *     'ROBOTS' => 'NOYDIR',
 *     'Slurp' => 'NOYDIR',
 *     'copyright' => '&copy; 1997 Acme Corp.',
 *     'date' => '1994-11-06T08:49:37+00:00',
 *     'DC.title' => 'THE NAME OF YOUR SITE',
 *     'DCTERMS.modified' => '2007-07-22',
 *     'geo.position' => '26.367559;-80.12172',
 *     'geo.region' => 'US-FL',
 *     'geo.placename' => 'Boca Raton, FL',
 *     'ICBM' => '26.367559, -80.12172',
 *   ),
 *   'lang' =>
 *   array (
 *     'DC.title' => 'en',
 *   ),
 *   'scheme' =>
 *   array (
 *     'DCTERMS.modified' => 'XSD.date',
 *   ),
 * </pre>
 *
 * Note how repeated tags cause the previous value to be overwritten in the resulting array
 * (for example the <var>Content-Type</var> and <var>keywords</var> tags appear twice but the
 * final array only has one element for each - the lowest one in the original list).
 *
 * @param string $html The string containing the html to be parsed
 */
	public static /*.array[string][string]string.*/ function meta_to_array(/*.string.*/ $html) {
		$keyAttributes	= array('name', 'http-equiv', 'charset', 'itemprop');
		$tags		= /*.(array[int][int]string).*/ array();
		$query		= '?';

		preg_match_all("|<meta.+/$query>|i", $html, $tags);

		$meta		= /*.(array[string][string]string).*/ array();
		$key_type	= '';
		$key		= '';
		$content	= '';

		foreach ($tags[0] as $tag) {
			$attributes	= array();
			$wip		= /*.(array[string]string).*/ array();

			preg_match_all('|\\s(\\S+?)\\s*=\\s*"(.*?)"|', $tag, $attributes);


			unset($key_type);
			unset($key);
			unset($content);

			for ($i = 0; $i < count($attributes[1]); $i++) {
				$attribute	= strtolower($attributes[1][$i]);
				$value		= $attributes[2][$i];

				if (in_array($attribute, $keyAttributes)) {
					$key_type		= $attribute;
					$key			= $value;
				} elseif ($attribute === 'content') {
					$content		= $value;
				} else {
					$wip[$attribute]	= $value;
				}
			}

			if (isset($key_type)) {
				$meta['key'][$key]	= $content;
				$meta[$key_type][$key]	= $content;

				foreach ($wip as $attribute => $value) {
					$meta[$attribute][$key] = $value;
				}
			}
		}

		return $meta;
	}

/**
 * Return the contents of a captured var_dump() as HTML. This is a recursive function.
 *
 * @param string $var_dump The captured <var>var_dump()</var>.
 * @param int $offset Whereabouts to start in the captured string. Defaults to the beginning of the string.
 */
	public static /*.string.*/ function var_dump_to_HTML(/*.string.*/ $var_dump, $offset = 0) {
		$indent	= '';
		$value	= '';

		while ((boolean) ($posStart = strpos($var_dump, '(', $offset))) {
			$type	= substr($var_dump, $offset, $posStart - $offset);
			$nests	= strrpos($type, ' ');

			if ($nests === false) $nests = 0; else $nests = intval(($nests + 1) / 2);

			$indent = str_pad('', $nests * 3, "\t");
			$type	= trim($type);
			$offset	= ++$posStart;
			$posEnd	= strpos($var_dump, ')', $offset); if ($posEnd === false) break;
			$offset	= $posEnd + 1;
			$value	= substr($var_dump, $posStart, $posEnd - $posStart);

			switch ($type) {
			case 'string':
				$length	= (int) $value;
				$value	= '<pre>' . htmlspecialchars(substr($var_dump, $offset + 2, $length)) . '</pre>';
				$offset	+= $length + 3;
				break;
			case 'array':
				$elementTellTale	= "\n" . str_pad('', ($nests + 1) * 2) . '['; // Not perfect but the best var_dump will allow
				$elementCount		= (int) $value;
				$value			= "\n$indent<table>\n";

				for ($i = 1; $i <= $elementCount; $i++) {
					$posStart	= strpos($var_dump, $elementTellTale, $offset);	if ($posStart	=== false) break;
					$posStart	+= ($nests + 1) * 2 + 2;
					$offset		= $posStart;
					$posEnd		= strpos($var_dump, ']', $offset);		if ($posEnd	=== false) break;
					$offset		= $posEnd + 4; // Read past the =>\n
					$key		= substr($var_dump, $posStart, $posEnd - $posStart);

					if (!is_numeric($key)) $key = substr($key, 1, strlen($key) - 2); // Strip off the double quotes

					$search		= ($i === $elementCount) ? "\n" . str_pad('', $nests * 2) . '}' : $elementTellTale;
					$posStart	= strpos($var_dump, $search, $offset);		if ($posStart	=== false) break;
					$next		= substr($var_dump, $offset, $posStart - $offset);
					$offset		= $posStart;
					$inner_value	= self::var_dump_to_HTML($next);

					$value		.= "$indent\t<tr>\n";
					$value		.= "$indent\t\t<td>$key</td>\n";
					$value		.= "$indent\t\t<td>$inner_value</td>\n";
					$value		.= "$indent\t</tr>\n";
				}

				$value			.= "$indent</table>\n";
				break;
			case 'object':
				if ($value === '__PHP_Incomplete_Class') {
					$posStart	= strpos($var_dump, '(', $offset);	if ($posStart	=== false) break;
					$offset		= ++$posStart;
echo "$indent Corrected \$offset = $offset\n"; // debug
					$posEnd		= strpos($var_dump, ')', $offset);	if ($posEnd	=== false) break;
					$offset		= $posEnd + 1;
echo "$indent Corrected \$offset = $offset\n"; // debug
					$value		= substr($var_dump, $posStart, $posEnd - $posStart);
				}

				break;
			default:
				break;
			}

		}

		return $value;
	}

/**
 * Return the contents of an array as HTML (like <var>var_dump()</var> on steroids), including object members
 *
 * @param array[]mixed $source The array to export. If it's empty then $GLOBALS is exported.
 */
	public static /*.string.*/ function array_to_HTML($source = NULL) {
// If no specific array is passed we will export $GLOBALS to HTML
// Unfortunately, this means we have to use var_dump() because var_export() barfs on $GLOBALS
// In fact var_dump is easier to walk than var_export anyway so this is no bad thing.

		ob_start();
		if (empty($source)) var_dump($GLOBALS); else var_dump($source);
		$var_dump = ob_get_clean();

		return self::var_dump_to_HTML($var_dump);
	}

// Version 1.18: Added checkSession()
/**
 * Check session is running. If not start one.
 */
	public static /*.void.*/ function checkSession() {if (!isset($_SESSION) || !is_array($_SESSION) || (session_id() === '')) session_start();}

/**
 * Return all or part of the URL of the current script.
 *
 * @param int $mode One of the <var>URL_MODE_XXX</var> predefined constants defined in this class
 * @param string $filename If this is not empty then the returned script name is forced to be this filename.
 */
	public static /*.string.*/ function getURL($mode = self::URL_MODE_PATH, $filename = '') {
// Version 1.14: PACKAGE & VERSION now hard-coded by build process.
// Version 1.16: Filename default is now '', was 'ezUser'
		$portInteger = array_key_exists('SERVER_PORT', $_SERVER) ? (int) $_SERVER['SERVER_PORT'] : 0;

		if (array_key_exists('HTTPS', $_SERVER) && $_SERVER['HTTPS'] === 'on') {
			$protocolType = 'https';
		} else if (array_key_exists('SERVER_PROTOCOL', $_SERVER)) {
			$protocolType = strtolower(self::strleft($_SERVER['SERVER_PROTOCOL'], self::URL_SEPARATOR, self::STRLEFT_MODE_ALL));
		} else if ($portInteger === 443) {
			$protocolType = 'https';
		} else {
			$protocolType = 'http';
		}

		if ($portInteger === 0) $portInteger = ($protocolType === 'https') ? 443 : 80;

		// Protocol
		if ((boolean) ($mode & self::URL_MODE_PROTOCOL)) {
			$protocol = ($mode === self::URL_MODE_PROTOCOL) ? $protocolType : "$protocolType://";
		} else {
			$protocol = '';
		}

		// Host
		if ((boolean) ($mode & self::URL_MODE_HOST)) {
			$host = array_key_exists('HTTP_HOST', $_SERVER) ? self::strleft($_SERVER['HTTP_HOST'], ':', self::STRLEFT_MODE_ALL) : '';
		} else {
			$host = '';
		}

		// Port
		if ((boolean) ($mode & self::URL_MODE_PORT)) {
			$port = (string) $portInteger;

			if ($mode !== self::URL_MODE_PORT)
				$port = (($protocolType === 'http' && $portInteger === 80) || ($protocolType === 'https' && $portInteger === 443)) ? '' : ":$port";
		} else {
			$port = '';
		}

		// Path
		if ((boolean) ($mode & self::URL_MODE_PATH)) {
			$includePath	= __FILE__;
			$scriptPath	= realpath($_SERVER['SCRIPT_FILENAME']);

			if (DIRECTORY_SEPARATOR !== self::URL_SEPARATOR) {
				$includePath	= (string) str_replace(DIRECTORY_SEPARATOR, self::URL_SEPARATOR , $includePath);
				$scriptPath	= (string) str_replace(DIRECTORY_SEPARATOR, self::URL_SEPARATOR , $scriptPath);
			}

/*
echo "<pre>\n"; // debug
echo "\$_SERVER['SCRIPT_FILENAME'] = " . $_SERVER['SCRIPT_FILENAME'] . "\n"; // debug
echo "\$_SERVER['SCRIPT_NAME'] = " . $_SERVER['SCRIPT_NAME'] . "\n"; // debug
echo "dirname(\$_SERVER['SCRIPT_NAME']) = " . dirname($_SERVER['SCRIPT_NAME']) . "\n"; // debug
echo "\$includePath = $includePath\n"; // debug
echo "\$scriptPath = $scriptPath\n"; // debug
//echo self::array_to_HTML(); // debug
echo "</pre>\n"; // debug
*/

			$start	= strpos(strtolower($scriptPath), strtolower($_SERVER['SCRIPT_NAME']));
			$path	= ($start === false) ? dirname($_SERVER['SCRIPT_NAME']) : dirname(substr($includePath, $start));
			$path	.= self::URL_SEPARATOR . $filename;
		} else {
			$path = '';
		}

		return $protocol . $host . $port . $path;
	}

/**
 * Convert a DocBlock to HTML (see http://java.sun.com/j2se/javadoc/writingdoccomments/index.html)
 *
 * @param string $php Some PHP code containing a valid DocBlock.
 */
	public static /*.string.*/ function docBlock_to_HTML($php) {
// Updated in version 1.12 (bug fixes and formatting)
		$eol		= "\r\n";
		$tagStart	= strpos($php, "/**$eol * ");

		if ($tagStart === false) return 'Development version';

		// Get summary and long description
		$tagStart	+= 8;
		$tagEnd		= strpos($php, $eol, $tagStart);
		$summary	= substr($php, $tagStart, $tagEnd - $tagStart);
		$tagStart	= $tagEnd + 7;
		$tagPos		= strpos($php, "$eol * @") + 2;
		$description	= substr($php, $tagStart, $tagPos - $tagStart - 7);
		$description	= (string) str_replace(' * ', '' , $description);
		$package	= '(no package specified)';	// Revision 1.22: $package might not get set

		// Get tags and values from DocBlock
		do {
			$tagStart	= $tagPos + 4;
			$tagEnd		= strpos($php, "\t", $tagStart);
			$tag		= substr($php, $tagStart, $tagEnd - $tagStart);
			$offset		= $tagEnd + 1;
			$tagPos		= strpos($php, $eol, $offset);
			$value		= htmlspecialchars(substr($php, $tagEnd + 1, $tagPos - $tagEnd - 1));
			$tagPos		= strpos($php, " * @", $offset);

			switch ($tag) {
			case 'package':		$package	= $value; break; // Version 1.19: Remembered this one. Oops.
			case 'license':		$license	= $value; break;
			case 'author':		$author		= $value; break;
			case 'link':		$link		= $value; break;
			case 'version':		$version	= $value; break;
			case 'copyright':	$copyright	= $value; break;
			default:		$value		= $value;
			}
		} while ((boolean) $tagPos);

		// Add some links
		// 1. License
		if (isset($license) && (boolean) strpos($license, '://')) {
			$tagPos		= strpos($license, ' ');
			$license	= '<a href="' . substr($license, 0, $tagPos) . '">' . substr($license, $tagPos + 1) . '</a>';
		}

		// 2. Author
		if (isset($author) && preg_match('/&lt;.+@.+&gt;/', $author) > 0) {
			$tagStart	= strpos($author, '&lt;') + 4;
			$tagEnd		= strpos($author, '&gt;', $tagStart);
			$author		= '<a href="mailto:' . substr($author, $tagStart, $tagEnd - $tagStart) . '">' . substr($author, 0, $tagStart - 5) . '</a>';
		}

		// 3. Link
		if (isset($link) && (boolean) strpos($link, '://')) {
			$link		= '<a href="' . $link . '">' . $link . '</a>';
		}

		// Build the HTML
		// Version 1.16 changed heading to \$package from ezUser
		$html = <<<HTML
	<h1>$package</h1>
	<h2>$summary</h2>
	<pre>$description</pre>
	<hr />
	<table>

HTML;
// Version 1.14: PACKAGE & VERSION now hard-coded by build process.

		if (isset($version))	$html .= "\t\t<tr><td>Version</td><td>$version</td></tr>\n";
		if (isset($copyright))	$html .= "\t\t<tr><td>Copyright</td><td>$copyright</td></tr>\n";
		if (isset($license))	$html .= "\t\t<tr><td>License</td><td>$license</td></tr>\n";
		if (isset($author))	$html .= "\t\t<tr><td>Author</td><td>$author</td></tr>\n";
		if (isset($link))	$html .= "\t\t<tr><td>Link</td><td>$link</td></tr>\n";

		$html .= "\t</table>";
		return $html;
	}

/**
 * glob() replacement (in case glob() is disabled).
 *
 * Function glob() is prohibited on some server (probably in safe mode)
 * (Message "Warning: glob() has been disabled for security reasons in
 * (script) on line (line)") for security reasons as stated on:
 * http://seclists.org/fulldisclosure/2005/Sep/0001.html
 *
 * safe_glob() intends to replace glob() using readdir() & fnmatch() instead.
 * Supported flags: GLOB_MARK, GLOB_NOSORT, GLOB_ONLYDIR
 * Additional flags: GLOB_NODIR, GLOB_PATH, GLOB_NODOTS, GLOB_RECURSE
 * (these were not original glob() flags)

 * @author BigueNique AT yahoo DOT ca
 * @param string $pattern
 * @param int $flags
 */
	public static /*.mixed.*/ function safe_glob($pattern, $flags = 0) {
		$split	= explode('/', (string) str_replace('\\', '/', $pattern));
		$mask	= (string) array_pop($split);
		$path	= (count($split) === 0) ? '.' : implode('/', $split);
		$dir	= @opendir($path);

		if ($dir === false) return false;

		$glob		= /*.(array[int]).*/ array();
		$sub_glob	= /*.(array[int]).*/ array();

		do {
			$filename = readdir($dir);
			if ($filename === false) break;

			$is_dir	= is_dir("$path/$filename");
			$is_dot	= in_array($filename, array('.', '..'));

			// Recurse subdirectories (if GLOB_RECURSE is supplied)
			if ($is_dir && !$is_dot && (($flags & self::GLOB_RECURSE) !== 0)) {
				$sub_glob	= cast('array[int]', self::safe_glob($path.'/'.$filename.'/'.$mask,  $flags));
//				array_prepend($sub_glob, ((boolean) ($flags & self::GLOB_PATH) ? '' : $filename.'/'));
				$glob		= cast('array[int]', array_merge($glob, $sub_glob));
			}

			// Match file mask
			if (fnmatch($mask, $filename)) {
				if (	((($flags & GLOB_ONLYDIR) === 0)	|| $is_dir)
				&&	((($flags & self::GLOB_NODIR) === 0)	|| !$is_dir)
				&&	((($flags & self::GLOB_NODOTS) === 0)	|| !$is_dot)
				)
					$glob[] = (($flags & self::GLOB_PATH) !== 0 ? $path.'/' : '') . $filename . (($flags & GLOB_MARK) !== 0 ? '/' : '');
			}
		} while(true);

		closedir($dir);
		if (($flags & GLOB_NOSORT) === 0) sort($glob);

		return $glob;
	}

/**
 * Return file contents as a string. Fail silently if the file can't be opened.
 *
 * The parameters are the same as the built-in PHP function {@link http://www.php.net/file_get_contents file_get_contents}
 *
 * @param string $filename
 * @param int $flags
 * @param object $context
 * @param int $offset
 * @param int $maxlen
 */
	public static /*.string.*/ function getFileContents(/*.string.*/ $filename, /*.int.*/ $flags = 0, /*.object.*/ $context = NULL, /*.int.*/ $offset = -1, /*.int.*/ $maxlen = -1) {
		// From the documentation of file_get_contents:
		// Note: The default value of maxlen is not actually -1; rather, it is an internal PHP value which means to copy the entire stream until end-of-file is reached. The only way to specify this default value is to leave it out of the parameter list.
		if ($maxlen === -1) {
			$contents = @file_get_contents($filename, $flags, $context, $offset);
		} else {
			$contents = @file_get_contents($filename, $flags, $context, $offset, $maxlen);
// version 1.9 - remembered the @s
		}

		if ($contents === false) $contents = '';
		return $contents;
	}

/**
 * Return the name of the index file (e.g. <var>index.php</var>) from a folder
 *
 * @param string $folder The folder to look for the index file. If not a folder or no index file can be found then an empty string is returned.
 */
	public static /*.string.*/ function findIndexFile(/*.string.*/ $folder) {
		if (!is_dir($folder)) return '';
		$filelist = array('index.php', 'index.pl', 'index.cgi', 'index.asp', 'index.shtml', 'index.html', 'index.htm', 'default.php', 'default.pl', 'default.cgi', 'default.asp', 'default.shtml', 'default.html', 'default.htm', 'home.php', 'home.pl', 'home.cgi', 'home.asp', 'home.shtml', 'home.html', 'home.htm');

		foreach ($filelist as $filename) {
			$target = $folder . DIRECTORY_SEPARATOR . $filename;
			if (is_file($target)) return $target;
		}

		return '';
	}

/**
 * Return the name of the target file from a string that might be a directory or just a basename without a suffix. If it's a directory then look for an index file in the directory.
 *
 * @param string $target The file to look for or folder to look in. If no file can be found then an empty string is returned.
 */
	public static /*.string.*/ function findTarget(/*.string.*/ $target) {
		// Is it actually a file? If so, look no further
		if (is_file($target)) return $target;

		// Added in version 1.7
		// Is it a basename? i.e. can we find $target.html or something?
		$suffixes = array('shtml', 'html', 'php', 'pl', 'cgi', 'asp', 'htm');

		foreach ($suffixes as $suffix) {
			$filename = "$target.$suffix";
			if (is_file($filename)) return $filename;
		}

		// Otherwise, let's assume it's a directory and try to find an index file in that directory
		return self::findIndexFile($target);
	}

/**
 * Make a unique ID based on the current date and time
 */
	public static /*.string.*/ function makeId() {
// Note could also try this: return md5(uniqid(mt_rand(), true));
		list($usec, $sec) = explode(" ", (string) microtime());
		return base_convert($sec, 10, 36) . base_convert((string) mt_rand(0, 35), 10, 36) . str_pad(base_convert(($usec * 1000000), 10, 36), 4, '_', STR_PAD_LEFT);
	}

/**
 * Make a unique hash key from a string (usually an ID)
 *
 * @param string $id
 */
	public static /*.string.*/ function makeUniqueKey(/*.string.*/ $id) {
		return hash(self::HASH_FUNCTION, $_SERVER['REQUEST_TIME'] . $id);
	}

// Added in version 1.10
/**
 * Shuffle a string using the Mersenne Twist PRNG (can be deterministically seeded)
 *
 * @param string $str The string to be shuffled
 * @param int $seed The seed for the PRNG means this can be used to shuffle the string in the same order every time
 */
	public static /*.string.*/ function mt_shuffle(/*.string.*/ $str, /*.int.*/ $seed = 0) {
		$count	= strlen($str);
		$result	= $str;

		// Seed the RNG with a deterministic seed
		mt_srand($seed);

		// Shuffle the digits
		for ($element = $count - 1; $element >= 0; $element--) {
			$shuffle		= mt_rand(0, $element);

			$value			= $result[$shuffle];
//			$result[$shuffle]	= $result[$element];
//			$result[$element]	= $value;		// PHPLint doesn't like this syntax, so...

			substr_replace($result, $result[$element], $shuffle, 1);
			substr_replace($result, $value, $element, 1);
		}

		return $result;
	}

// Added in version 1.10
// Revision 1.22: More specific about array type (just for PHPLint)
/**
 * Shuffle an array using the Mersenne Twist PRNG (can be deterministically seeded)
 *
 * @param array[string]string &$shuffle
 * @param int $seed
 */
	private static /*.void.*/ function mt_shuffle_array(/*.array[string]string.*/ &$shuffle, /*.int.*/ $seed = 0) {
		$count = count($shuffle);
		/*.array[int]string.*/ $keys = cast('array[int]string', array_keys($shuffle));

		// Seed the RNG with a deterministic seed
		mt_srand($seed);

		// Shuffle the digits
		for ($element = $count - 1; $element >= 0; $element--) {
			$random		= mt_rand(0, $element);

			$key_shuffle		= $keys[$random];
			$key_element		= $keys[$element];

			$value			= $shuffle[$key_shuffle];
			$shuffle[$key_shuffle]	= $shuffle[$key_element];
			$shuffle[$key_element]	= $value;
		}
	}

// Added in version 1.10
/**
 * The Pseudo-Random Key Generator returns an apparently random key of
 * length $length and comprising digits specified by $base. However, for
 * a given seed this key depends only on $index.
 *
 * In other words, if you keep the $seed constant then you'll get a
 * non-repeating series of keys as you increment $index but these keys
 * will be returned in a pseudo-random order.
 *
 * The $seed parameter is available in case you want your series of keys
 * to come out in a different order to mine.
 *
 * Comparison of bases:
 * <pre>
 * +------+----------------+---------------------------------------------+
 * |      | Max keys       |                                             |
 * |      | (based on      |                                             |
 * | Base | $length = 6)   | Notes                                       |
 * +------+----------------+---------------------------------------------+
 * | 2    | 64             | Uses digits 0 and 1 only                    |
 * | 8    | 262,144        | Uses digits 0-7 only                        |
 * | 10   | 1,000,000      | Good choice if you need integer keys        |
 * | 16   | 16,777,216     | Good choice if you need hex keys            |
 * | 26   | 308,915,776    | Good choice if you need purely alphabetic   |
 * |      |                | keys (case-insensitive)                     |
 * | 32   | 1,073,741,824  | Smallest base that gives you a billion keys |
 * |      |                | in 6 digits                                 |
 * | 34   | 1,544,804,416  | (default) Good choice if you want to        |
 * |      |                | maximise your keyset size but still         |
 * |      |                | generate keys that are unambiguous and      |
 * |      |                | case-insensitive (no confusion between 1, I |
 * |      |                | and l for instance)                         |
 * | 36   | 2,176,782,336  | Same digits as base-34 but includes 'O' and |
 * |      |                | 'I' (may be confused with '0' and '1' in    |
 * |      |                | some fonts)                                 |
 * | 52   | 19,770,609,664 | Good choice if you need purely alphabetic   |
 * |      |                | keys (case-sensitive)                       |
 * | 62   | 56,800,235,584 | Same digits as other URL shorteners         |
 * |      |                | (e.g bit.ly)                                |
 * | 66   | 82,653,950,016 | Includes all legal URI characters           |
 * |      |                | (http://tools.ietf.org/html/rfc3986)        |
 * |      |                | This is the maximum size of keyset that     |
 * |      |                | results in a legal URL for a given length   |
 * |      |                | of key.                                     |
 * +------+----------------+---------------------------------------------+
 * </pre>
 * @param int $index The number to be converted into a key
 * @param int $length The length of key to be returned. Along with the $base this determines the size of the keyset
 * @param int $base The number of distinct characters that can be included in the key to be returned. Along with the $length this determines the size of the keyset
 * @param int $seed The seed for the PRNG means this can be used to generate keys in the same sequence every time
 */
	public static /*.string.*/ function prkg($index, $length = 6, $base = 34, $seed = 0) {
		/*
		To return a pseudo-random key, we will take $index, convert it
		to base $base, then randomize the order of the digits. In
		addition we will give each digit a random offset.

		All the randomization operations are deterministic (based on
		$seed) so each time the function is called we will get the
		same shuffling of digits and the same offset for each digit.
		*/
		$digits	= '0123456789ABCDEFGHJKLMNPQRSTUVWXYZIOabcdefghijklmnopqrstuvwxyz-._~';
		//					    ^ base 34 recommended

		// Is $base in range?
		if ($base < 2)			{exit('Base must be greater than or equal to 2');}
		if ($base > 66)			{exit('Base must be less than or equal to 66');}

		// Is $length in range?
		if ($length < 1)		{exit('Length must be greater than or equal to 1');}
		// Max length depends on arithmetic functions of PHP

		// Is $index in range?
		$max_index = (int) pow($base, $length);
		if ($index < 0)			{exit('Index must be greater than or equal to 0');}
		if ($index > $max_index)	{exit('Index must be less than or equal to ' . $max_index);}

		// Seed the RNG with a deterministic seed
		mt_srand($seed);

		// Convert to $base
		$remainder	= $index;
		$digit		= 0;
		$result		= '';

		while ($digit < $length) {
			$unit		= (int) pow($base, $length - $digit++ - 1);
			$value		= (int) floor($remainder / $unit);
			$remainder	= $remainder - ($value * $unit);

			// Shift the digit
			$value		= ($value + mt_rand(0, $base - 1)) % $base;
			$result		.= $digits[$value];
		}

		// Shuffle the digits
		$result	= self::mt_shuffle($result, $seed);

		// We're done
		return $result;
	}

// Updated in version 1.20
// Revision numbers in this function refer to the is_email() version itself,
// which is maintained separately here: http://isemail.googlecode.com
/**
 * Check that an email address conforms to RFCs 5321, 5322 and others
 *
 * @param string	$email		The email address to check
 * @param boolean	$checkDNS	If true then a DNS check for A and MX records will be made
 * @param mixed		$errorlevel	If true then return an integer error or warning number rather than true or false
 */
	public static /*.mixed.*/ function is_email ($email, $checkDNS = false, $errorlevel = false) {
		// Check that $email is a valid address. Read the following RFCs to understand the constraints:
		// 	(http://tools.ietf.org/html/rfc5321)
		// 	(http://tools.ietf.org/html/rfc5322)
		// 	(http://tools.ietf.org/html/rfc4291#section-2.2)
		// 	(http://tools.ietf.org/html/rfc1123#section-2.1)
		// 	(http://tools.ietf.org/html/rfc3696) (guidance only)

		//	$errorlevel	Behaviour
		//	---------------	---------------------------------------------------------------------------
		//	E_ERROR		Return validation failures only. For technically valid addresses return
		//			ISEMAIL_VALID
		//	E_WARNING	Return warnings for unlikely but technically valid addresses. This includes
		//			addresses at TLDs (e.g. johndoe@com), addresses with FWS and comments,
		//			addresses that are quoted and addresses that contain no alphabetic or
		//			numeric characters.
		//	true		Same as E_ERROR
		//	false		Return true for valid addresses, false for invalid ones. No warnings.
		//
		//	Errors can be distinguished from warnings if ($return_value > self::ISEMAIL_ERROR)
	// version 2.0: Enhance $diagnose parameter to $errorlevel

		if (is_bool($errorlevel)) {
			if ((bool) $errorlevel) {
				$diagnose	= true;
				$warn		= false;
			} else {
				$diagnose	= false;
				$warn		= false;
			}
		} else {
			switch ((int) $errorlevel) {
			case E_WARNING:
				$diagnose	= true;
				$warn		= true;
				break;
			case E_ERROR:
				$diagnose	= true;
				$warn		= false;
				break;
			default:
				$diagnose	= false;
				$warn		= false;
			}
		}

		if ($diagnose) /*.mixed.*/ $return_status = self::ISEMAIL_VALID; else $return_status = true;

	// version 2.0: Enhance $diagnose parameter to $errorlevel

		// the upper limit on address lengths should normally be considered to be 254
		// 	(http://www.rfc-editor.org/errata_search.php?rfc=3696)
		// 	NB My erratum has now been verified by the IETF so the correct answer is 254
		//
		// The maximum total length of a reverse-path or forward-path is 256
		// characters (including the punctuation and element separators)
		// 	(http://tools.ietf.org/html/rfc5321#section-4.5.3.1.3)
		//	NB There is a mandatory 2-character wrapper round the actual address
		$emailLength = strlen($email);
	// revision 1.17: Max length reduced to 254 (see above)
		if ($emailLength > 254)			if ($diagnose) return self::ISEMAIL_TOOLONG;		else return false;	// Too long

		// Contemporary email addresses consist of a "local part" separated from
		// a "domain part" (a fully-qualified domain name) by an at-sign ("@").
		// 	(http://tools.ietf.org/html/rfc3696#section-3)
		$atIndex = strrpos($email,'@');

		if ($atIndex === false)			if ($diagnose) return self::ISEMAIL_NOAT;		else return false;	// No at-sign
		if ($atIndex === 0)			if ($diagnose) return self::ISEMAIL_NOLOCALPART;	else return false;	// No local part
		if ($atIndex === $emailLength - 1)	if ($diagnose) return self::ISEMAIL_NODOMAIN;		else return false;	// No domain part
	// revision 1.14: Length test bug suggested by Andrew Campbell of Gloucester, MA

		// Sanitize comments
		// - remove nested comments, quotes and dots in comments
		// - remove parentheses and dots from quoted strings
		$braceDepth	= 0;
		$inQuote	= false;
		$escapeThisChar	= false;

		for ($i = 0; $i < $emailLength; ++$i) {
			$char = $email[$i];
			$replaceChar = false;

			if ($char === '\\') 	$escapeThisChar = !$escapeThisChar;			// Escape the next character?
			else {
				switch ($char) {
				case '(':
					if	($escapeThisChar)	$replaceChar	= true;
					else if	($inQuote)		$replaceChar	= true;
					else if	($braceDepth++ > 0)	$replaceChar	= true;		// Increment brace depth

					break;
				case ')':
					if	($escapeThisChar)	$replaceChar	= true;
					else if	($inQuote)		$replaceChar	= true;
					else {
						if (--$braceDepth > 0)	$replaceChar	= true;		// Decrement brace depth
						if ($braceDepth < 0)	$braceDepth	= 0;
					}

					break;
				case '"':
					if	($escapeThisChar)	$replaceChar	= true;
					else if ($braceDepth === 0)	$inQuote	= !$inQuote;	// Are we inside a quoted string?
					else				$replaceChar	= true;

					break;
				case '.':
					if	($escapeThisChar)	$replaceChar	= true;		// Dots don't help us either
					else if	($braceDepth > 0)	$replaceChar	= true;

					break;
				default:
				}

				$escapeThisChar = false;
	//			if ($replaceChar) $email[$i] = 'x';					// Replace the offending character with something harmless
	// revision 1.12: Line above replaced because PHPLint doesn't like that syntax
				if ($replaceChar) $email = (string) substr_replace($email, 'x', $i, 1);	// Replace the offending character with something harmless
			}
		}

		$localPart	= substr($email, 0, $atIndex);
		$domain		= substr($email, $atIndex + 1);
		$FWS		= "(?:(?:(?:[ \\t]*(?:\\r\\n))?[ \\t]+)|(?:[ \\t]+(?:(?:\\r\\n)[ \\t]+)*))";	// Folding white space
		$dotArray	= /*. (array[]) .*/ array();

		// Let's check the local part for RFC compliance...
		//
		// local-part      =       dot-atom / quoted-string / obs-local-part
		// obs-local-part  =       word *("." word)
		// 	(http://tools.ietf.org/html/rfc5322#section-3.4.1)
		//
		// Problem: need to distinguish between "first.last" and "first"."last"
		// (i.e. one element or two). And I suck at regexes.
		$dotArray	= preg_split('/\\.(?=(?:[^\\"]*\\"[^\\"]*\\")*(?![^\\"]*\\"))/m', $localPart);
		$partLength	= 0;

		foreach ($dotArray as $arrayMember) {
			$element = (string) $arrayMember;
			// Remove any leading or trailing FWS
			$new_element = preg_replace("/^$FWS|$FWS\$/", '', $element);
			if ($warn && ($element !== $new_element)) $return_status = self::ISEMAIL_FWS;	// FWS is unlikely in the real world
			$element = $new_element;
	// version 2.3: Warning condition added
			$elementLength	= strlen($element);

			if ($elementLength === 0)								if ($diagnose) return self::ISEMAIL_ZEROLENGTHELEMENT;	else return false;	// Can't have empty element (consecutive dots or dots at the start or end)
	// revision 1.15: Speed up the test and get rid of "unitialized string offset" notices from PHP

			// We need to remove any valid comments (i.e. those at the start or end of the element)
			if ($element[0] === '(') {
				if ($warn) $return_status = self::ISEMAIL_COMMENTS;	// Comments are unlikely in the real world
	// version 2.0: Warning condition added
				$indexBrace = strpos($element, ')');
				if ($indexBrace !== false) {
					if (preg_match('/(?<!\\\\)[\\(\\)]/', substr($element, 1, $indexBrace - 1)) > 0)
														if ($diagnose) return self::ISEMAIL_BADCOMMENT_START;	else return false;	// Illegal characters in comment
					$element	= substr($element, $indexBrace + 1, $elementLength - $indexBrace - 1);
					$elementLength	= strlen($element);
				}
			}

			if ($element[$elementLength - 1] === ')') {
				if ($warn) $return_status = self::ISEMAIL_COMMENTS;	// Comments are unlikely in the real world
	// version 2.0: Warning condition added
				$indexBrace = strrpos($element, '(');
				if ($indexBrace !== false) {
					if (preg_match('/(?<!\\\\)(?:[\\(\\)])/', substr($element, $indexBrace + 1, $elementLength - $indexBrace - 2)) > 0)
														if ($diagnose) return self::ISEMAIL_BADCOMMENT_END;	else return false;	// Illegal characters in comment
					$element	= substr($element, 0, $indexBrace);
					$elementLength	= strlen($element);
				}
			}

			// Remove any remaining leading or trailing FWS around the element (having removed any comments)
			$new_element = preg_replace("/^$FWS|$FWS\$/", '', $element);
			if ($warn && ($element !== $new_element)) $return_status = self::ISEMAIL_FWS;	// FWS is unlikely in the real world
			$element = $new_element;
	// version 2.0: Warning condition added

			// What's left counts towards the maximum length for this part
			if ($partLength > 0) $partLength++;	// for the dot
			$partLength += strlen($element);

			// Each dot-delimited component can be an atom or a quoted string
			// (because of the obs-local-part provision)
			if (preg_match('/^"(?:.)*"$/s', $element) > 0) {
				// Quoted-string tests:
				if ($warn) $return_status = self::ISEMAIL_QUOTEDSTRING;	// Quoted string is unlikely in the real world
	// version 2.0: Warning condition added
				// Remove any FWS
				$element = preg_replace("/(?<!\\\\)$FWS/", '', $element);	// A warning condition, but we've already raised self::ISEMAIL_QUOTEDSTRING
				// My regex skillz aren't up to distinguishing between \" \\" \\\" \\\\" etc.
				// So remove all \\ from the string first...
				$element = preg_replace('/\\\\\\\\/', ' ', $element);
				if (preg_match('/(?<!\\\\|^)["\\r\\n\\x00](?!$)|\\\\"$|""/', $element) > 0)	if ($diagnose) return self::ISEMAIL_UNESCAPEDDELIM;	else return false;	// ", CR, LF and NUL must be escaped
	// version 2.0: allow ""@example.com because it's technically valid
			} else {
				// Unquoted string tests:
				//
				// Period (".") may...appear, but may not be used to start or end the
				// local part, nor may two or more consecutive periods appear.
				// 	(http://tools.ietf.org/html/rfc3696#section-3)
				//
				// A zero-length element implies a period at the beginning or end of the
				// local part, or two periods together. Either way it's not allowed.
				if ($element === '')								if ($diagnose) return self::ISEMAIL_EMPTYELEMENT;	else return false;	// Dots in wrong place

				// Any ASCII graphic (printing) character other than the
				// at-sign ("@"), backslash, double quote, comma, or square brackets may
				// appear without quoting.  If any of that list of excluded characters
				// are to appear, they must be quoted
				// 	(http://tools.ietf.org/html/rfc3696#section-3)
				//
				// Any excluded characters? i.e. 0x00-0x20, (, ), <, >, [, ], :, ;, @, \, comma, period, "
				if (preg_match('/[\\x00-\\x20\\(\\)<>\\[\\]:;@\\\\,\\."]/', $element) > 0)	if ($diagnose) return self::ISEMAIL_UNESCAPEDSPECIAL;	else return false;	// These characters must be in a quoted string
				if ($warn && (preg_match('/^\\w+/', $element) === 0)) $return_status = self::ISEMAIL_UNLIKELYINITIAL;	// First character is an odd one
			}
		}

		if ($partLength > 64)										if ($diagnose) return self::ISEMAIL_LOCALTOOLONG;	else return false;	// Local part must be 64 characters or less

		// Now let's check the domain part...

		// The domain name can also be replaced by an IP address in square brackets
		// 	(http://tools.ietf.org/html/rfc3696#section-3)
		// 	(http://tools.ietf.org/html/rfc5321#section-4.1.3)
		// 	(http://tools.ietf.org/html/rfc4291#section-2.2)
		if (preg_match('/^\\[(.)+]$/', $domain) === 1) {
			// It's an address-literal
			if ($warn) $return_status = self::ISEMAIL_ADDRESSLITERAL;	// Quoted string is unlikely in the real world
	// version 2.0: Warning condition added
			$addressLiteral = substr($domain, 1, strlen($domain) - 2);
			$groupMax	= 8;
	// revision 2.1: new IPv6 testing strategy
			$matchesIP	= array();

			// Extract IPv4 part from the end of the address-literal (if there is one)
			if (preg_match('/\\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $addressLiteral, $matchesIP) > 0) {
				$index = strrpos($addressLiteral, $matchesIP[0]);

				if ($index === 0) {
					// Nothing there except a valid IPv4 address, so...
					if ($diagnose) return $return_status; else return true;
	// version 2.0: return warning if one is set
				} else {
	//-				// Assume it's an attempt at a mixed address (IPv6 + IPv4)
	//-				if ($addressLiteral[$index - 1] !== ':')				if ($diagnose) return self::ISEMAIL_IPV4BADPREFIX;	else return false;	// Character preceding IPv4 address must be ':'
	// revision 2.1: new IPv6 testing strategy
					if (substr($addressLiteral, 0, 5) !== 'IPv6:')				if ($diagnose) return self::ISEMAIL_IPV6BADPREFIXMIXED;	else return false;	// RFC5321 section 4.1.3
	//-
	//-				$IPv6		= substr($addressLiteral, 5, ($index === 7) ? 2 : $index - 6);
	//-				$groupMax	= 6;
	// revision 2.1: new IPv6 testing strategy
					$IPv6		= substr($addressLiteral, 5, $index - 5) . '0000:0000'; // Convert IPv4 part to IPv6 format
				}
			} else {
				// It must be an attempt at pure IPv6
				if (substr($addressLiteral, 0, 5) !== 'IPv6:')					if ($diagnose) return self::ISEMAIL_IPV6BADPREFIX;	else return false;	// RFC5321 section 4.1.3
				$IPv6 = substr($addressLiteral, 5);
	//-			$groupMax = 8;
	// revision 2.1: new IPv6 testing strategy
			}
	//echo "\n<br /><pre>\$IPv6 = $IPv6</pre>\n"; // debug
			$groupCount	= preg_match_all('/^[0-9a-fA-F]{0,4}|\\:[0-9a-fA-F]{0,4}|(.)/', $IPv6, $matchesIP);
			$index		= strpos($IPv6,'::');

	//echo "\n<br /><pre>\$matchesIP[0] = " . var_export($matchesIP[0], true) . "</pre>\n"; // debug
			if ($index === false) {
				// We need exactly the right number of groups
				if ($groupCount !== $groupMax)							if ($diagnose) return self::ISEMAIL_IPV6GROUPCOUNT;	else return false;	// RFC5321 section 4.1.3
			} else {
				if ($index !== strrpos($IPv6,'::'))						if ($diagnose) return self::ISEMAIL_IPV6DOUBLEDOUBLECOLON; else return false;	// More than one '::'
				if ($index === 0 || $index === (strlen($IPv6) - 2)) $groupMax++;	// RFC 4291 allows :: at the start or end of an address with 7 other groups in addition
	//echo "\n<br /><pre>\$groupMax = $groupMax</pre>\n"; // debug
				if ($groupCount > $groupMax)							if ($diagnose) return self::ISEMAIL_IPV6TOOMANYGROUPS;	else return false;	// Too many IPv6 groups in address
				if ($groupCount === $groupMax) $return_status = self::ISEMAIL_SINGLEGROUPELISION;	// Eliding a single group with :: is deprecated by RFCs 5321 & 5952
			}

			// Check for single : at start and end of address
			if (($matchesIP[0][0] === '') && ($matchesIP[0][1] !== ':'))				if ($diagnose) return self::ISEMAIL_IPV6SINGLECOLONSTART; else return false;	// Address starts with a single colon
			if (($matchesIP[0][$groupCount - 1] === ':') && ($matchesIP[0][$groupCount - 2] !== ':')) if ($diagnose) return self::ISEMAIL_IPV6SINGLECOLONEND; else return false;	// Address ends with a single colon

			// Check for unmatched characters
			array_multisort($matchesIP[1], SORT_DESC);
			if ($matchesIP[1][0] !== '') {
	//echo "\n<br /><pre>\$matchesIP[1] = " . var_export($matchesIP[1], true) . "</pre>\n"; // debug
			if ($diagnose) return self::ISEMAIL_IPV6BADCHAR; else return false;	// Illegal characters in address
	} // debug
			// It's a valid IPv6 address, so...
			if ($diagnose) return $return_status; else return true;
	// revision 2.1: bug fix: now correctly return warning status
		} else {
			// It's a domain name...

			// The syntax of a legal Internet host name was specified in RFC-952
			// One aspect of host name syntax is hereby changed: the
			// restriction on the first character is relaxed to allow either a
			// letter or a digit.
			// 	(http://tools.ietf.org/html/rfc1123#section-2.1)
			//
			// NB RFC 1123 updates RFC 1035, but this is not currently apparent from reading RFC 1035.
			//
			// Most common applications, including email and the Web, will generally not
			// permit...escaped strings
			// 	(http://tools.ietf.org/html/rfc3696#section-2)
			//
			// the better strategy has now become to make the "at least one period" test,
			// to verify LDH conformance (including verification that the apparent TLD name
			// is not all-numeric)
			// 	(http://tools.ietf.org/html/rfc3696#section-2)
			//
			// Characters outside the set of alphabetic characters, digits, and hyphen MUST NOT appear in domain name
			// labels for SMTP clients or servers
			// 	(http://tools.ietf.org/html/rfc5321#section-4.1.2)
			//
			// RFC5321 precludes the use of a trailing dot in a domain name for SMTP purposes
			// 	(http://tools.ietf.org/html/rfc5321#section-4.1.2)
			$dotArray	= preg_split('/\\.(?=(?:[^\\"]*\\"[^\\"]*\\")*(?![^\\"]*\\"))/m', $domain);
			$partLength	= 0;
			$element	= ''; // Since we use $element after the foreach loop let's make sure it has a value
	// revision 1.13: Line above added because PHPLint now checks for Definitely Assigned Variables

			if ($warn && (count($dotArray) === 1))	$return_status = self::ISEMAIL_TLD;	// The mail host probably isn't a TLD
	// version 2.0: downgraded to a warning

			foreach ($dotArray as $arrayMember) {
				$element = (string) $arrayMember;
				// Remove any leading or trailing FWS
				$new_element	= preg_replace("/^$FWS|$FWS\$/", '', $element);
				if ($warn && ($element !== $new_element)) $return_status = self::ISEMAIL_FWS;	// FWS is unlikely in the real world
				$element = $new_element;
	// version 2.0: Warning condition added
				$elementLength	= strlen($element);

				// Each dot-delimited component must be of type atext
				// A zero-length element implies a period at the beginning or end of the
				// local part, or two periods together. Either way it's not allowed.
				if ($elementLength === 0)							if ($diagnose) return self::ISEMAIL_DOMAINEMPTYELEMENT;	else return false;	// Dots in wrong place
	// revision 1.15: Speed up the test and get rid of "unitialized string offset" notices from PHP

				// Then we need to remove all valid comments (i.e. those at the start or end of the element
				if ($element[0] === '(') {
					if ($warn) $return_status = self::ISEMAIL_COMMENTS;	// Comments are unlikely in the real world
	// version 2.0: Warning condition added
					$indexBrace = strpos($element, ')');
					if ($indexBrace !== false) {
						if (preg_match('/(?<!\\\\)[\\(\\)]/', substr($element, 1, $indexBrace - 1)) > 0)
														if ($diagnose) return self::ISEMAIL_BADCOMMENT_START;	else return false;	// Illegal characters in comment
	// revision 1.17: Fixed name of constant (also spotted by turboflash - thanks!)
						$element	= substr($element, $indexBrace + 1, $elementLength - $indexBrace - 1);
						$elementLength	= strlen($element);
					}
				}

				if ($element[$elementLength - 1] === ')') {
					if ($warn) $return_status = self::ISEMAIL_COMMENTS;	// Comments are unlikely in the real world
	// version 2.0: Warning condition added
					$indexBrace = strrpos($element, '(');
					if ($indexBrace !== false) {
						if (preg_match('/(?<!\\\\)(?:[\\(\\)])/', substr($element, $indexBrace + 1, $elementLength - $indexBrace - 2)) > 0)
														if ($diagnose) return self::ISEMAIL_BADCOMMENT_END;	else return false;	// Illegal characters in comment
	// revision 1.17: Fixed name of constant (also spotted by turboflash - thanks!)
						$element	= substr($element, 0, $indexBrace);
						$elementLength	= strlen($element);
					}
				}

				// Remove any leading or trailing FWS around the element (inside any comments)
				$new_element	= preg_replace("/^$FWS|$FWS\$/", '', $element);
				if ($warn && ($element !== $new_element)) $return_status = self::ISEMAIL_FWS;	// FWS is unlikely in the real world
				$element = $new_element;
	// version 2.0: Warning condition added

				// What's left counts towards the maximum length for this part
				if ($partLength > 0) $partLength++;	// for the dot
				$partLength += strlen($element);

				// The DNS defines domain name syntax very generally -- a
				// string of labels each containing up to 63 8-bit octets,
				// separated by dots, and with a maximum total of 255
				// octets.
				// 	(http://tools.ietf.org/html/rfc1123#section-6.1.3.5)
				if ($elementLength > 63)							if ($diagnose) return self::ISEMAIL_DOMAINELEMENTTOOLONG;	else return false;	// Label must be 63 characters or less

				// Any ASCII graphic (printing) character other than the
				// at-sign ("@"), backslash, double quote, comma, or square brackets may
				// appear without quoting.  If any of that list of excluded characters
				// are to appear, they must be quoted
				// 	(http://tools.ietf.org/html/rfc3696#section-3)
				//
				// If the hyphen is used, it is not permitted to appear at
				// either the beginning or end of a label.
				// 	(http://tools.ietf.org/html/rfc3696#section-2)
				//
				// Any excluded characters? i.e. 0x00-0x20, (, ), <, >, [, ], :, ;, @, \, comma, period, "
				if (preg_match('/[\\x00-\\x20\\(\\)<>\\[\\]:;@\\\\,\\."]|^-|-$/', $element) > 0) if ($diagnose) return self::ISEMAIL_DOMAINBADCHAR;	else return false;	// Illegal character in domain name
			}

			if ($partLength > 255) 									if ($diagnose) return self::ISEMAIL_DOMAINTOOLONG;	else return false;	// Domain part must be 255 characters or less (http://tools.ietf.org/html/rfc1123#section-6.1.3.5)

			if ($warn && (preg_match('/^[0-9]+$/', $element) > 0))	$return_status = self::ISEMAIL_TLDNUMERIC;	// TLD probably isn't all-numeric (http://www.apps.ietf.org/rfc/rfc3696.html#sec-2)
	// version 2.0: Downgraded to a warning

			// Check DNS?
			if ($diagnose && ($return_status === self::ISEMAIL_VALID) && $checkDNS && function_exists('checkdnsrr')) {
				if (!(checkdnsrr($domain, 'A')))	$return_status = self::ISEMAIL_DOMAINNOTFOUND;	// 'A' record for domain can't be found
				if (!(checkdnsrr($domain, 'MX')))	$return_status = self::ISEMAIL_MXNOTFOUND;		// 'MX' record for domain can't be found
			}
		}

		// Eliminate all other factors, and the one which remains must be the truth.
		// 	(Sherlock Holmes, The Sign of Four)
		if ($diagnose) return $return_status; else return true;
	// version 2.0: return warning if one is set
	}
}
// End of class ezUser_common

// PHPLint needs this function to exist outside the class
/**
 *	throws ezUserException
 *
 *	Checks at runtime for the type of the value.
 *	If the value matches the specified type, then this value is returned.
 *	Otherwise a ezUserException is thrown. This function is "magic" in the
 *	sense that it is handled in special way by PHPLint: in fact the
 *	returned type always corresponds to what is specified in the $type
 *	argument; moreover, cast checks that the expression giving $type be
 *	a static expression of string type.
 *
 * @param string $type The target type
 * @param mixed $variable The variable to be cast
 */
/*.mixed.*/ function cast(/*.string.*/ $type, /*.mixed.*/ $variable) {
	if (!is_string($type))	throw new ezUserException('type is not a string');
	if ($type === '')	throw new ezUserException('type is empty');

	// Check non-array types:
	if (
		$variable instanceof $type
		or $type === 'boolean'	and is_bool($variable)
		or $type === 'int'	and is_int($variable)
		or $type === 'float'	and is_float($variable)
		or $type === 'string'	and (is_string($variable)	or is_null($variable))
		or $type === 'resource'	and (is_resource($variable)	or is_null($variable))
		or $type === 'object'	and (is_object($variable)	or is_null($variable))
	)
		return $variable;

	if ($type === 'array' or $type === 'array[]') {
		if (!(is_null($variable) or is_array($variable))) {
			throw new ezUserException('value is not an array: ' . ezUser_common::gettype($variable));
		}
		return $variable;
	}

	if (strlen($type) > 6 and substr($type, 0, 6) === 'array[') {

		if (!is_array($variable))
			throw new ezUserException('value is not an array: ' . ezUser_common::gettype($variable));

		// NULL or empty array matches any type of array:
		if (count($variable) === 0) return $variable;

		// Parse index type:
		$close = strpos($type, ']');
		// cast now guarantees ']' does exist.
		$index_type = substr($type, 6, $close - 6);
		// cast now guarantees $index_type is either 'int', 'string' or ''.

		// Parse element type:
		/*.mixed.*/ $result = substr($type, $close + 1, strlen($type) - $close - 1);

		$elem_type = (is_bool($result)) ? '' : (string) $result;

		if (strlen($elem_type) > 0 and $elem_type[0] === '[') $elem_type = "array$elem_type";
		// cast now garantees $elem_type does exist or it is ''

		// Now check all indexes and elements:
		foreach(/*.(array).*/ $variable as $k => &$v) {

			// Check index type:
			if ($index_type === 'int') {
				if (!is_int($k))	throw new ezUserException("found index of type string: $k");
			} else if ($index_type === 'string') {
				if (!is_string($k))	throw new ezUserException("found index of type int: $k");
			} else {
				// Any index.
			}

			// Check elem type:
			if ($elem_type !== '') /* $ignore = */ cast($elem_type, $v);
		}
		return $variable;
	}

	throw new ezUserException("value is not of type $type: " . ezUser_common::gettype($variable));
}


/**
 * Password reset handling for ezUser
 *
 * @package ezUser
 */
interface I_ezUser_reset {
// Methods may be commented out to reduce the attack surface when they are
// not required. Uncomment them if you need them.
//	public	/*.string.*/	function id();
	public	/*.string.*/	function resetKey();
//	public	/*.DateTime.*/	function expires();
	public	/*.string.*/	function data();
	public	/*.void.*/	function initialize();
	public	/*.void.*/	function setId(/*.string.*/ $id);
	public	/*.void.*/	function setData(/*.string.*/ $data);
}

/**
 * Password reset handling for ezUser
 *
 * @package ezUser
 */
class ezUser_reset extends ezUser_common implements I_ezUser_reset {
	private	/*.string.*/	$id;
	private /*.string.*/	$resetKey;
	private	/*.DateTime.*/	$expires;

// Methods may be commented out to reduce the attack surface when they are
// not required. Uncomment them if you need them.
//	public	/*.string.*/	function id()		{return $this->id;}
	public	/*.string.*/	function resetKey()	{return $this->resetKey;}
//	public	/*.DateTime.*/	function expires()	{return $this->expires;}
	public	/*.string.*/	function data()		{return serialize(array($this->id, $this->resetKey, serialize($this->expires)));}

	public /*.void.*/	function initialize() {
		$date = new DateTime();
		$date->modify('+1 day');

		$this->resetKey	= self::makeUniqueKey($this->id);
		$this->expires	= $date;
	}

	public /*.void.*/	function setId(/*.string.*/ $id) {
		$this->id = $id;
		$this->initialize();
	}

	public /*.void.*/	function setData(/*.string.*/ $data) {
		list($this->id, $this->resetKey, $expiresString) = cast('array[int]string', unserialize($data));
		$this->expires = cast('DateTime', unserialize($expiresString));
	}
}
// End of class ezUser_reset


/**
 * This class encapsulates all the functions needed for an app to interact
 * with a user. It has no knowledge of how user information is persisted.
 *
 * @package ezUser
 */
 interface I_ezUser_base extends I_ezUser_common {
		// REST interface actions
	const	ACTION			= 'action',
		ACTION_ABOUT		= 'about',
		ACTION_ABOUTTEXT	= 'abouttext',
		ACTION_ACCOUNT		= 'account',
		ACTION_ACCOUNTFORM	= 'accountform',
		ACTION_ACCOUNTWIZARD	= 'accountwizard',
		ACTION_BITMAP		= 'bitmap',
		ACTION_BODY		= 'body',
		ACTION_CANCEL		= 'cancel',
		ACTION_CONTAINER	= 'container',
		ACTION_CONTROLPANEL	= 'controlpanel',
		ACTION_JAVASCRIPT	= 'js',
		ACTION_RESEND		= 'resend',		// Resend verification email
		ACTION_RESET		= 'reset',		// Process password reset link
		ACTION_RESETPASSWORD	= 'resetpassword',	// Initiate password reset processing
		ACTION_RESETREQUEST	= 'resetrequest',	// Request password reset form
		ACTION_RESETREQUESTFORM	= 'resetrequestform',	// Request request password reset form (yeah, I know)
		ACTION_RESULTFORM	= 'resultform',
		ACTION_RESULTTEXT	= 'resulttext',
		ACTION_SIGNIN		= 'signin',
		ACTION_SIGNINFORM	= 'signinform',
		ACTION_SIGNOUT		= 'signout',
		ACTION_SOURCECODE	= 'source',
		ACTION_STATUSTEXT	= 'statustext',
		ACTION_STYLESHEET	= 'css',
		ACTION_VALIDATE		= 'validate',		// Validate registration form details
		ACTION_VERIFY		= 'verify',		// Verify verification email

		// Keys for the user data array members
		TAGNAME_CONFIRM		= 'confirm',
		TAGNAME_DATA		= 'data',
		TAGNAME_EMAIL		= 'email',
		TAGNAME_FIRSTNAME	= 'firstname',
		TAGNAME_FULLNAME	= 'fullname',
		TAGNAME_ID		= 'id',
		TAGNAME_LASTNAME	= 'lastname',
		TAGNAME_NEWUSER		= 'newuser',
		TAGNAME_PASSWORD	= 'password',
		TAGNAME_REMEMBERME	= 'rememberme',
		TAGNAME_RESETKEY	= 'resetkey',
		TAGNAME_RESETDATA	= 'resetdata',
		TAGNAME_SAVEDPASSWORD	= 'usesavedpassword',
		TAGNAME_STATUS		= 'status',
		TAGNAME_STAYSIGNEDIN	= 'staysignedin',
		TAGNAME_USER		= 'user',
		TAGNAME_USERNAME	= 'username',
		TAGNAME_VERIFICATIONKEY	= 'verificationkey',
		TAGNAME_VERBOSE		= 'verbose',
		TAGNAME_WIZARD		= 'wizard',

		// Registration status codes
		STATUS_UNKNOWN		= 0,
		STATUS_PENDING		= 1,
		STATUS_CONFIRMED	= 2,
		STATUS_INACTIVE		= 3,

		// Authentication result codes
		RESULT_UNDEFINED	= 0,
		RESULT_SUCCESS		= 1,
		RESULT_NOUSER		= 2,
		RESULT_UNKNOWNACTION	= 3,
		RESULT_NOACTION		= 4,
		RESULT_FAILEDAUTOSIGNIN	= 5,
		RESULT_FAIL		= 16,	// Passed to browser when RESULT_UNKNOWNUSER or RESULT_BADPASSWORD would reveal too much
		RESULT_UNKNOWNUSER	= 17,
		RESULT_BADPASSWORD	= 18,

		// Validation result codes
		RESULT_VALIDATED	= 32,
		RESULT_NOID		= 33,
		RESULT_NOUSERNAME	= 34,
		RESULT_NOEMAIL		= 35,
		RESULT_EMAILFORMATERR	= 36,
		RESULT_NOPASSWORD	= 37,
		RESULT_NULLPASSWORD	= 38,
		RESULT_STATUSNAN	= 39,
		RESULT_RESULTNAN	= 40,
		RESULT_CONFIGNOTARRAY	= 41,
		RESULT_USERNAMEEXISTS	= 42,
		RESULT_EMAILEXISTS	= 43,
		RESULT_NOTSIGNEDIN	= 44,
		RESULT_INCOMPLETE	= 45,

		// Result codes for session and environment issues
		RESULT_NOSESSION	= 64,
		RESULT_NOSESSIONCOOKIES	= 65,
		RESULT_STORAGEERR	= 66,
//-		RESULT_EMAILERR		= 67,
		RESULT_HEADERSSENT	= 68,

		// Result codes for verification process
		RESULT_ALREADYVERIFIED	= 96,
		RESULT_EMAILFAIL	= 97,

		// Miscellaneous constants
		DELIMITER_SPACE		= ' ',
		STRING_TRUE		= 'true',
		STRING_FALSE		= 'false';

	public /*.int.*/	function authenticate($passwordHash = '');

	public /*.string.*/	function username();
	public /*.string.*/	function firstName();
	public /*.string.*/	function lastName();
	public /*.string.*/	function fullName();
	public /*.string.*/	function email();
	public /*.int.*/	function status();
	public /*.boolean.*/	function authenticated();

	public /*.void.*/	function setFirstName(/*.string.*/ $name);
	public /*.void.*/	function setLastName(/*.string.*/ $name);
}
// End of interface I_ezUser_base

/**
 * This class encapsulates all the functions needed for an app to interact
 * with a user. It has no knowledge of how user information is persisted.
 *
 * @package ezUser
 */
class ezUser_base extends ezUser_common implements I_ezUser_base {
	// User data
	private	$keys	= array (
			      self::TAGNAME_USERNAME	,
			      self::TAGNAME_EMAIL	,
			      self::TAGNAME_ID		,
			      self::TAGNAME_PASSWORD	,
			      self::TAGNAME_STATUS	,
			      self::TAGNAME_FIRSTNAME	,
			      self::TAGNAME_LASTNAME	,
			      self::TAGNAME_FULLNAME	,
			      self::TAGNAME_VERIFICATIONKEY,
		      );

	private	$values	= array (
			      self::TAGNAME_USERNAME		=> '',
			      self::TAGNAME_EMAIL		=> '',
			      self::TAGNAME_ID			=> '',
			      self::TAGNAME_PASSWORD		=> '',
			      self::TAGNAME_STATUS		=> '0',
			      self::TAGNAME_FIRSTNAME		=> '',
			      self::TAGNAME_LASTNAME		=> '',
			      self::TAGNAME_FULLNAME		=> '',
			      self::TAGNAME_VERIFICATIONKEY	=> ''
		      );

	// State and derived data
	private		$authenticated		= false;					// User is signed in
	private		$usernameIsDefault	= true;						// username === firstName.lastName
	private		$isChanged		= false;					// Unsaved changes?
	private		$result			= self::RESULT_UNDEFINED;			// Result of any change operation
	private		$config			= /*.(array[string]string).*/	array();	// Configuration settings
	private		$errors			= /*.(array[string]string).*/	array();	// Validation errors
	private		$signOutActions		= /*.(array[int]string).*/	array();	// Things to do on signing out
	private		$manualSignOut		= false;					// User chose to sign out

// ---------------------------------------------------------------------------
// Helper methods
// ---------------------------------------------------------------------------
	private /*.boolean.*/ function setValue(/*.string.*/ $key, /*.string.*/ $value) {
		if ($value !== $this->values[$key]) {
			$this->values[$key]	= $value;
			$this->isChanged	= true;
			return true;
		} else {
			return false;
		}
	}

	private /*.string.*/ function getValue(/*.string.*/ $key) {
		$value = '';
		if (!in_array($key, $this->keys)) return $value;

		switch ($key) {
		case self::TAGNAME_VERIFICATIONKEY:
			if ((int) $this->getValue(self::TAGNAME_STATUS) === self::STATUS_PENDING) $value = $this->values[$key];
			break;
		case self::TAGNAME_ID:
			if ($this->values[$key] === '') $this->setValue($key, self::makeId());
			$value	= $this->values[$key];
			break;
		default:
			$value	= $this->values[$key];
			break;
		}

		return $value;
	}

// ---------------------------------------------------------------------------
// Authenticate
// ---------------------------------------------------------------------------
	public /*.int.*/ function authenticate($passwordHash = '') {
		if (empty($passwordHash)) {
			// Sign out
			$this->authenticated	= false;
			$this->manualSignOut	= true;
			$result = self::RESULT_SUCCESS;
		} else {
			// Sign in
			self::checkSession();
			$sessionHash = hash(self::HASH_FUNCTION, session_id() . hash(self::HASH_FUNCTION, $_SERVER['REMOTE_ADDR'] . $this->values[self::TAGNAME_PASSWORD]));
//error_log(date('Y-m-d H:i:s', time()) . "\t" . $_SERVER['REMOTE_ADDR'] . '|' . session_id() . '|' . $this->values[self::TAGNAME_PASSWORD] . '|' . hash(self::HASH_FUNCTION, $_SERVER['REMOTE_ADDR'] . $this->values[self::TAGNAME_PASSWORD]) . "|$sessionHash|$passwordHash\n", 3, dirname(__FILE__) . self::URL_SEPARATOR . '.ezuser-log.php'); // Debug
			$this->authenticated = ($passwordHash === $sessionHash);

			if ($this->authenticated) {
				$result			= self::RESULT_SUCCESS;
				$this->manualSignOut	= false;
			} else {
				$result			= self::RESULT_BADPASSWORD;
			}
		}

		$this->result = $result;
		return $result;
	}

// ---------------------------------------------------------------------------
// "Get" methods
// ---------------------------------------------------------------------------
	protected	/*.string.*/			function data()			{return serialize($this->values);}
	protected	/*.string.*/			function id()			{return $this->getValue(self::TAGNAME_ID);}
	public		/*.string.*/			function username()		{return $this->getValue(self::TAGNAME_USERNAME);}
	protected	/*.string.*/			function passwordHash()		{return $this->getValue(self::TAGNAME_PASSWORD);}
	public		/*.string.*/			function firstName()		{return $this->getValue(self::TAGNAME_FIRSTNAME);}
	public		/*.string.*/			function lastName()		{return $this->getValue(self::TAGNAME_LASTNAME);}
	public		/*.string.*/			function fullName()		{return $this->getValue(self::TAGNAME_FULLNAME);}
	public		/*.string.*/			function email()		{return $this->getValue(self::TAGNAME_EMAIL);}
	protected	/*.string.*/			function verificationKey()	{return $this->getValue(self::TAGNAME_VERIFICATIONKEY);}
	public		/*.int.*/			function status()		{return (int) $this->getValue(self::TAGNAME_STATUS);}
	public		/*.boolean.*/			function authenticated()	{return $this->authenticated;}
	protected	/*.int.*/			function result()		{return $this->result;}
	protected	/*.array[string]string.*/	function config()		{return $this->config;}
	protected	/*.array[string]string.*/	function errors()		{return $this->errors;}
	protected	/*.string.*/			function signOutActions()	{return implode(self::DELIMITER_SPACE, $this->signOutActions);}
	protected	/*.boolean.*/			function isChanged()		{return $this->isChanged;}
	protected	/*.boolean.*/			function manualSignOut()	{return $this->manualSignOut;}
	protected	/*.boolean.*/			function incomplete() {
		return	(	empty($this->values[self::TAGNAME_USERNAME])	||
				empty($this->values[self::TAGNAME_EMAIL])	||
				empty($this->values[self::TAGNAME_ID])
			);
	}


// ---------------------------------------------------------------------------
// Name manipulation
// ---------------------------------------------------------------------------
	private /*.string.*/ function getDefaultUsername() {
		$lastName	= $this->values[self::TAGNAME_LASTNAME];
		$firstName	= $this->values[self::TAGNAME_FIRSTNAME];
		$username	= strtolower($firstName . $lastName);
		$username	= preg_replace('/[^0-9a-z_-]/', '', $username);
		return $username;
	}

	private /*.void.*/ function setFullName() {
		$firstName	= $this->values[self::TAGNAME_FIRSTNAME];
		$lastName	= $this->values[self::TAGNAME_LASTNAME];
		$separator	= (empty($firstName) || empty($lastName)) ? '' : self::DELIMITER_SPACE;

		$this->setValue(self::TAGNAME_FULLNAME, $firstName . $separator . $lastName);

		if ($this->usernameIsDefault) {$this->setValue(self::TAGNAME_USERNAME, $this->getDefaultUsername());}
	}

	private /*.void.*/ function setNamePart(/*.string.*/ $key, /*.string.*/ $name) {
		$name = trim($name);
		if ($this->setValue($key, $name)) $this->setFullName();
	}

// ---------------------------------------------------------------------------
// "Set" methods
// ---------------------------------------------------------------------------
	protected /*.void.*/ function setData(/*.string.*/ $data) {
		$this->values = cast('array[string]string', unserialize($data));	// PHPLint typecasting
	}

	protected /*.void.*/ function clearErrors() {
		$this->errors = /*.(array[string]string).*/ array();
	}

	protected /*.int.*/ function setStatus(/*.int.*/ $status) {
		if (!is_numeric($status)) return self::RESULT_STATUSNAN;

		// If we're setting this user to Pending then generate a verification key
		if ($status === self::STATUS_PENDING && $this->status() !== self::STATUS_PENDING) {
			// Use the ID to generate a verification key
			$this->setValue(self::TAGNAME_VERIFICATIONKEY, self::makeUniqueKey($this->id()));
		}

		$this->setValue(self::TAGNAME_STATUS, (string) $status);
		return self::RESULT_VALIDATED;
	}

	protected /*.int.*/ function setResult(/*.int.*/ $result) {
		if (!is_numeric($result)) return self::RESULT_RESULTNAN;
		$this->result = $result;
		return self::RESULT_VALIDATED;
	}

	protected /*.int.*/ function setConfig(/*.array[string]string.*/ $config) {
		if (!is_array($config)) return self::RESULT_CONFIGNOTARRAY;
		$this->config = $config;
		return self::RESULT_VALIDATED;
	}

	public /*.void.*/ function setFirstName(/*.string.*/ $name)	{$this->setNamePart(self::TAGNAME_FIRSTNAME, $name);}
	public /*.void.*/ function setLastName(/*.string.*/ $name)	{$this->setNamePart(self::TAGNAME_LASTNAME, $name);}

	protected /*.int.*/ function setUsername($name = '') {
		$this->usernameIsDefault = empty($name);
		if ($this->usernameIsDefault) $name = $this->getDefaultUsername();
		if (empty($name)) return self::RESULT_NOUSERNAME;
		$this->setValue(self::TAGNAME_USERNAME, $name);
		return self::RESULT_VALIDATED;
	}

	protected /*.int.*/ function setEmail(/*.string.*/ $email) {
		if (empty($email)) return self::RESULT_NOEMAIL;

		if (!(boolean) self::is_email($email)) {
			$this->errors[self::TAGNAME_EMAIL] = $email;
			return self::RESULT_EMAILFORMATERR;
		}

		$this->setValue(self::TAGNAME_EMAIL, $email);
		return self::RESULT_VALIDATED;
	}

	protected /*.int.*/ function setPasswordHash(/*.string.*/ $passwordHash) {
		if (empty($passwordHash))				return self::RESULT_NOPASSWORD;
		if ($passwordHash === hash(self::HASH_FUNCTION, ''))	return self::RESULT_NULLPASSWORD;
		$this->setValue(self::TAGNAME_PASSWORD, $passwordHash);
		return self::RESULT_VALIDATED;
	}

	protected /*.void.*/ function addSignOutAction(/*.string.*/ $action) {
		if (!in_array($action, $this->signOutActions)) $this->signOutActions[] = $action;
	}

	protected /*.void.*/ function clearSignOutActions() {
		$this->signOutActions = /*.(array[int]string).*/ array();
	}

	protected /*.void.*/ function clearChanges() {
		$this->isChanged = false;
	}

// ---------------------------------------------------------------------------
// Password reset handling
// ---------------------------------------------------------------------------
	private $passwordResetFlag = false;
	private /*.ezUser_reset.*/ $passwordReset;

	public /*.boolean.*/ function hasPasswordReset() {return $this->passwordResetFlag;}

	public /*.ezUser_reset.*/ function passwordReset($terminate = false) {
		$passwordReset = new ezUser_reset();

		if ($terminate) {
			unset($this->passwordReset);
			$this->passwordResetFlag = false;
			return $passwordReset; // empty
		} else {
			$passwordReset->setId($this->id());
			$this->passwordReset = $passwordReset;
			$this->passwordResetFlag = true;
			return $this->passwordReset;
		}
	}
}
// End of class ezUser_base


/**
 * This class encapsulates all the functions needed to manage the collection
 * of stored users. It interacts with the storage mechanism (e.g. database or
 * XML file).
 *
 * @package ezUser
 */
interface I_ezUser_environment extends I_ezUser_base {
		// Cookie names
	const	COOKIE_USERNAME		= 'ezuser-1',
		COOKIE_PASSWORD		= 'ezuser-2',
		COOKIE_AUTOSIGN		= 'ezuser-3',

		// Storage locations
		STORAGE			= '.ezuser-data.php',
		LOG			= '.ezuser-log.php',

		// Keys for the configuration settings
		SETTINGS_ADMINEMAIL	= 'adminEmail',
		SETTINGS_PERSISTED	= 'persisted',
		SETTINGS_EMPTY		= 'empty',
		SETTINGS_ACCOUNTPAGE	= 'accountPage',
		SETTINGS_SECUREFOLDER	= 'secureFolder',

		// Miscellaneous constants
		DELIMITER_EMAIL		= '@';

	public static /*.ezUser_base.*/ function lookup			($needle = '', $tagName = '');
	public static /*.ezUser_base.*/ function save			(/*.array[string]mixed.*/ $userData);
	public static /*.ezUser_base.*/	function signIn			($userData = /*.(array[string]mixed).*/ array());
}

/**
 * This class encapsulates all the functions needed to manage the collection
 * of stored users. It interacts with the storage mechanism (e.g. database or
 * XML file).
 *
 * @package ezUser
 */
abstract class ezUser_environment extends ezUser_base implements I_ezUser_environment {

// ---------------------------------------------------------------------------
// Helper methods
// ---------------------------------------------------------------------------
	protected static /*.boolean.*/ function logMessage($message = 'Unknown') {
		$filename	= dirname(__FILE__) . self::URL_SEPARATOR . self::LOG;
		$logWhen	= date('Y-m-d H:i:s', time());

		return error_log("$logWhen\t$message\n", 3, $filename);
	}

	private static /*.void.*/ function saveStorage(/*.string.*/ $content, /*.string.*/ $storage_file) {
		/*.resource.*/ $handle;	// Declaration for PHPLint

		for ($attempt = 0; $attempt < 3; $attempt++) {
			$handle = @fopen($storage_file, 'wb');
			if (is_resource($handle)) break;
			sleep(1); // File may occasionally be locked by indexing/backups etc.
		}

		if (!is_resource($handle)) exit(self::RESULT_STORAGEERR);

		fwrite($handle, $content);
		fclose($handle);
		chmod($storage_file, 0600);
	}

	private static /*.void.*/ function createStorage(/*.string.*/ $storage_file) {
		$query = '?';
		$xml = <<<XML
<?php header("Location: /"); $query>
<users>
</users>
XML;

		self::saveStorage($xml, $storage_file);
	}

	private static /*.DOMDocument.*/ function openStorage() {
		// Connect to database or whatever our storage mechanism is in this version

		// Where is the storage container?
		$storage_file = realpath(dirname(__FILE__)) . self::URL_SEPARATOR . self::STORAGE;

		// If storage container doesn't exist then create it
		if (!is_file($storage_file)) self::createStorage($storage_file);

		// Open the container for use
		$storage = new DOMDocument();
		$storage->load($storage_file);

		return $storage;
	}

// ---------------------------------------------------------------------------
	private static /*.void.*/ function closeStorage(DOMDocument $storage) {
		$storage_file	= dirname(__FILE__) . self::URL_SEPARATOR . self::STORAGE;
		$xml		= $storage->saveXML($storage->documentElement);

		self::saveStorage($xml, $storage_file);
	}

// ---------------------------------------------------------------------------
	private static /*.DOMElement.*/ function findUser(DOMDocument $storage, $needle = '', $tagName = '') {
		if ($needle === '') return $storage->createElement(self::TAGNAME_USER);

		if ($tagName === '') $tagName = ((bool) strpos($needle,self::DELIMITER_EMAIL)) ? self::TAGNAME_EMAIL : self::TAGNAME_USERNAME;

		$nodeList	= $storage->getElementsByTagName($tagName);
		$found		= false;

		for ($i = 0; $i < $nodeList->length; $i++) {
			$node	= $nodeList->item($i);
			$found	= (strcasecmp($node->nodeValue, $needle) === 0);
			if ($found) break;
		}


		if ($found && isset($node)) {
			$userElement = cast('DOMElement', $node->parentNode);	// PHPLint-compliant typecasting
			return $userElement;
		} else {
			return $storage->createElement(self::TAGNAME_USER);
		}
	}

// ---------------------------------------------------------------------------
	public static /*.ezUser_base.*/ function lookup($needle = '', $tagName = '') {
		$ezUser = new ezUser_base();
		if ($needle === '') return $ezUser;

		if ($tagName === '' || $tagName === self::TAGNAME_USERNAME || $tagName === self::TAGNAME_EMAIL) {
			$ezUser->setUsername($needle); // Will get overwritten if we successfully find the user in the database
		}

		$storage	= self::openStorage();
		$record		= self::findUser($storage, $needle, $tagName);

		if ($record->hasChildNodes()) {
			$data = $record->getElementsByTagName(self::TAGNAME_DATA)->item(0)->nodeValue;
			if (!empty($data)) $ezUser->setData($data);

			$nodeList = $record->getElementsByTagName(self::TAGNAME_RESETDATA);

			if ((bool) $nodeList->length) {
				$data = $nodeList->item(0)->nodeValue;
				if (!empty($data)) {
					$passwordReset = $ezUser->passwordReset();
					$passwordReset->setData($data);
				}
			}
		}

		return $ezUser;
	}

// ---------------------------------------------------------------------------
// Functions for sending stuff to the browser
// ---------------------------------------------------------------------------
	protected static /*.void.*/ function sendContent(/*.string.*/ $content, $container = '', $contentType = 'text/html', $attributes = '') {
		// Send headers first
		if (!headers_sent()) {
			if ($container === '') $container = 'ezuser';
//header("Container-length: " . strlen($container)); // debug
			header('Package: ezUser');
			header("ezUser-id: $container");
			header("Content-type: $contentType");

			if ($attributes !== '') {
				$document = new DOMDocument();
				$document->loadHTML("<p $attributes />");
				$attributeList = $document->getElementsByTagName('p')->item(0)->attributes;

				foreach ($attributeList as $item) {
					$attribute = cast('DOMAttr', $item);
					header($attribute->nodeName . ': ' , $attribute->nodeValue);
				}
			}
		}

		// Send content
		echo $content;

/* Comment out profiling statements if not needed
		// Send profiling data as a comment
		global $ezuser_profile;

		if (count($ezuser_profile) > 0) {
			$ezuser_profile['response'] = ezuser_time();

			if ($contentType === 'text/javascript' || $contentType === 'text/css') {
				$commentStart	= '/' . '*';
				$commentEnd	= '*' . '/';
			} else {
				$commentStart	= '<!--';
				$commentEnd	= '-->';
			}

			echo "\n$commentStart\n";
			$previous = reset($ezuser_profile);

			while (list($key, $value) = each($ezuser_profile)) {
				$elapsed	= round($value - $previous, 4);
				$previous	= $value;
				echo "$key\t$value\t$elapsed\n";
			}
			echo "$commentEnd\n";
		}
*/
	}

	protected static /*.string.*/ function resultText(/*.int.*/ $result, $more = '', $sendToBrowser = false) {
		switch ($result) {
			// Authentication results
			case self::RESULT_UNDEFINED:		$text = "Undefined";					break;
			case self::RESULT_SUCCESS:		$text = "Success";					break;
			case self::RESULT_NOUSER:		$text = "Please enter a username";			break;
			case self::RESULT_UNKNOWNACTION:	$text = "Unrecognised action";				break;
			case self::RESULT_NOACTION:		$text = "No action specified";				break;
			case self::RESULT_FAILEDAUTOSIGNIN:	$text = "Couldn't auto-sign-in";			break;
			case self::RESULT_FAIL:			$text = "Please try again";				break;
			case self::RESULT_UNKNOWNUSER:		$text = "Username not recognised";			break;
			case self::RESULT_BADPASSWORD:		$text = "Password is wrong";				break;

			// Registration and validation results
			case self::RESULT_VALIDATED:		$text = "Validation was successful";			break;
			case self::RESULT_NOID:			$text = "ID cannot be blank";				break;
			case self::RESULT_NOUSERNAME:		$text = "The username cannot be blank";			break;
			case self::RESULT_NOEMAIL:		$text = "Please provide an email address";		break;
			case self::RESULT_EMAILFORMATERR:	$text = "Incorrect email address format";		break;
			case self::RESULT_NOPASSWORD:		$text = "Password hash cannot be blank";		break;
			case self::RESULT_NULLPASSWORD:		$text = "Password cannot be blank";			break;
			case self::RESULT_STATUSNAN:		$text = "Status code must be numeric";			break;
			case self::RESULT_RESULTNAN:		$text = "Result code must be numeric";			break;
			case self::RESULT_CONFIGNOTARRAY:	$text = "Configuration settings must be an array";	break;
			case self::RESULT_USERNAMEEXISTS:	$text = "This username already exists";			break;
			case self::RESULT_EMAILEXISTS:		$text = "Email address is already registered";		break;
			case self::RESULT_NOTSIGNEDIN:		$text = "You must be signed in to update your account";	break;
			case self::RESULT_INCOMPLETE:		$text = "Not enough information to update the account";	break;

			// Session and environment issues
			case self::RESULT_NOSESSION:		$text = "No session data available";			break;
			case self::RESULT_NOSESSIONCOOKIES:	$text = "Session cookies are not enabled";		break;
			case self::RESULT_STORAGEERR:		$text = "Error with stored user details";		break;
			case self::RESULT_HEADERSSENT:		$text = "Headers already sent";				break;

			// Result codes for verification process
			case self::RESULT_ALREADYVERIFIED:	$text = "Account has already been verified";		break;
			case self::RESULT_EMAILFAIL:		$text = "Email not sent: please try again later";	break;

			default:				$text = "Unknown result code";				break;
		}

		if ($more !== '')	$text .= ": $more";
		if ($sendToBrowser)	{self::sendContent($text); return '';} else return $text;
	}

// ---------------------------------------------------------------------------
// Sign-in and session variables
// ---------------------------------------------------------------------------
	protected static /*.string.*/ function getInstanceId($container = 'ezuser') {
		return ($container === self::ACTION_CONTROLPANEL || $container === 'ezuser') ? 'ezuser' : "ezuser-$container";
	}

	protected static /*.void.*/ function setSessionObject(ezUser_base $ezUser, $instance = 'ezuser') {
		self::checkSession();
		$instanceId = self::getInstanceId($instance);
		$_SESSION[$instanceId] = $ezUser;

/*
$debug_isset		= (isset($_SESSION))										? 'true' : 'false'; // debug
$debug_isarray		= ((isset($_SESSION)) && (is_array($_SESSION)))							? 'true' : 'false'; // debug
$debug_keyexists	= ((isset($_SESSION)) && (is_array($_SESSION)) && (array_key_exists($instanceId, $_SESSION)))	? 'true' : 'false'; // debug
$debug_result		= ((isset($_SESSION)) && (is_array($_SESSION)) && (array_key_exists($instanceId, $_SESSION)))	? $_SESSION[$instanceId]->result() : 'n/a'; // debug
self::logMessage("setSessionObject|\$_SESSION exists: $debug_isset|\$_SESSION is array: $debug_isarray|\$instanceId = $instanceId|array key exists: $debug_keyexists|result = $debug_result|session_id = " . session_id()); // debug
*/
	}

	private static /*.boolean.*/ function autoSignInAvailable(){
		return	(
			array_key_exists(self::COOKIE_AUTOSIGN, $_COOKIE)	&&
			array_key_exists(self::COOKIE_USERNAME, $_COOKIE)	&&
			($_COOKIE[self::COOKIE_AUTOSIGN] === self::STRING_TRUE)	&&
			($_COOKIE[self::COOKIE_USERNAME] !== '')
			);
	}

	public static /*.ezUser_base.*/ function signIn($userData = /*.(array[string]mixed).*/ array()) {
		$autoSignInRequest	= (count($userData) === 0);
		$logEntry		= 'Sign in|' . $_SERVER['REMOTE_ADDR'] . '|' . session_id();

		if ($autoSignInRequest) {
			if (self::autoSignInAvailable()) {
				$userData[self::COOKIE_USERNAME]	= (string) $_COOKIE[self::COOKIE_USERNAME];
				$userData[self::COOKIE_PASSWORD]	= hash(self::HASH_FUNCTION, session_id() . (string) $_COOKIE[self::COOKIE_PASSWORD]);
				$logEntry				.= '|auto';
			} else {
				$userData[self::COOKIE_USERNAME]	= '';
				$userData[self::COOKIE_PASSWORD]	= '';
				$logEntry				.= '|auto not available';
			}
		} else {
			$logEntry				.= '|manual';
		}

		$username	= (string) $userData[self::COOKIE_USERNAME];
		$password	= (string) $userData[self::COOKIE_PASSWORD];
		$logEntry	.= "|$username|$password";

		if ($username === '') {
			$ezUser = new ezUser_base();
			$ezUser->setResult(self::RESULT_NOUSER);
		} else {
			$ezUser = self::lookup($username);

			if ($ezUser->status() === self::STATUS_UNKNOWN) {
				$logEntry .= "|(user not found)";
				$ezUser->setResult(($autoSignInRequest) ? self::RESULT_FAILEDAUTOSIGNIN : self::RESULT_UNKNOWNUSER);
			} else {
				$logEntry .= '|' . $ezUser->id();
				$ezUser->authenticate($password); // Sets result itself
			}
		}

		self::setSessionObject($ezUser);
		self::logMessage($logEntry . '|' . $ezUser->result());
		return $ezUser;
	}

	protected static /*.ezUser_base.*/ function getSessionObject($instance = 'ezuser') {
		$file		= '';
		$lineInt	= 0;

		// There may already be a session in progress. We will use the existing
		// session if possible.
		if ((int) ini_get('session.use_cookies') === 0) {
			echo self::resultText(self::RESULT_NOSESSIONCOOKIES);
			die(self::RESULT_NOSESSIONCOOKIES);
		} else if (headers_sent($file, $lineInt)) {
			$line = (string) $lineInt;
			echo self::resultText(self::RESULT_HEADERSSENT, "$file (line $line)");
			die(self::RESULT_HEADERSSENT);
		} else {
			self::checkSession();
		}

		$instanceId = self::getInstanceId($instance);

/*
$debug_isset		= (isset($_SESSION))										? 'true' : 'false'; // debug
$debug_isarray		= ((isset($_SESSION)) && (is_array($_SESSION)))							? 'true' : 'false'; // debug
$debug_keyexists	= ((isset($_SESSION)) && (is_array($_SESSION)) && (array_key_exists($instanceId, $_SESSION)))	? 'true' : 'false'; // debug
$debug_result		= ((isset($_SESSION)) && (is_array($_SESSION)) && (array_key_exists($instanceId, $_SESSION)))	? $_SESSION[$instanceId]->result() : 'n/a'; // debug
self::logMessage("getSessionObject|\$_SESSION exists: $debug_isset|\$_SESSION is array: $debug_isarray|\$instanceId = $instanceId|array key exists: $debug_keyexists|result = $debug_result|session_id = " . session_id()); // debug
*/

		if	(
				!array_key_exists($instanceId, $_SESSION)
			)
			$_SESSION[$instanceId] = self::signIn(); // Returns ezUser object, signed in if possible

		$ezUser = cast('ezUser_base', $_SESSION[$instanceId]);

		if	(
				!$ezUser->authenticated() &&
				!$ezUser->manualSignOut() &&
				self::autoSignInAvailable()
			)
			$_SESSION[$instanceId] = self::signIn(); // Returns ezUser object, signed in if possible

		return cast('ezUser_base', $_SESSION[$instanceId]);
	}

// ---------------------------------------------------------------------------
// Configuration settings
// ---------------------------------------------------------------------------
	protected static /*.string.*/ function thisURL() {
		return self::getURL(self::URL_MODE_PATH, 'ezuser.php');
	}

	private static /*.array[string]string.*/ function loadConfig() {
		$settings	= new ezUser_settings();
		$config		= $settings->get_all();

		if (count($config) === 0) $config[self::SETTINGS_EMPTY] = self::STRING_TRUE;
		$config[self::SETTINGS_PERSISTED] = self::STRING_TRUE;

		$ezUser = self::getSessionObject();
		$ezUser->setConfig($config);
		return $config;
	}

	private static /*.array[string]string.*/ function getSettings() {
		$ezUser	= self::getSessionObject();
		$config = $ezUser->config();

		if (!is_array($config))						{$config = self::loadConfig();}
		if (!array_key_exists(self::SETTINGS_PERSISTED, $config))	{$config = self::loadConfig();}
		if ($config[self::SETTINGS_PERSISTED] !== self::STRING_TRUE)	{$config = self::loadConfig();}

		return $config;
	}

	protected static /*.string.*/ function getSetting(/*.string.*/ $setting) {
		$config		= self::getSettings();
		$thisSetting	= (array_key_exists($setting, $config)) ? $config[$setting] : '';

		return $thisSetting;
	}
// ---------------------------------------------------------------------------
	private static /*.boolean.*/ function sendEmail($to = '', $subject = '', $message = '', $additional_headers = '') {
		if ($to === '')			return false;	// Can't send to an empty address
		if ($subject.$message === '')	return false;	// Can't send empty subject and message - that's just creepy

		$from	= self::getSetting(self::SETTINGS_ADMINEMAIL);
		$from	= ($from === '') ? 'webmaster' : $from;

		// If there's no domain, then assume same as this host
		if (strpos($from, self::DELIMITER_EMAIL) === false) {
			$host	= self::getURL(self::URL_MODE_HOST);
			$domain = (substr_count($host, '.') > 1) ? substr($host, strpos($host, '.') + 1) : $host;
			$from	.= self::DELIMITER_EMAIL . $domain;
		}

		// Extra headers
		$additional_headers .= "From: $from\r\n";

		date_default_timezone_set(@date_default_timezone_get());	// E_STRICT needs this or it complains about the mail function

		// Try three times to send the mail
		$success = false;

		for ($i = 0; $i < 3; $i++) {
			if ($i > 0) self::logMessage('Failed to send email, retrying');
			$success = @mail($to, $subject, $message, $additional_headers);
			if ($success) break;
		}

		if (!$success) self::logMessage('Failed to send email');
		return $success;
	}

// ---------------------------------------------------------------------------
	private static /*.int.*/ function is_duplicate(/*.string.*/ $username_or_email, /*.string.*/ $id) {
		$resultCode	= ((bool) strpos($username_or_email, self::DELIMITER_EMAIL)) ? self::RESULT_EMAILEXISTS : self::RESULT_USERNAMEEXISTS;
		$ezUser		= self::lookup($username_or_email);

		return ($ezUser->status() === self::STATUS_UNKNOWN) || ($ezUser->id() === $id) ? self::RESULT_VALIDATED : $resultCode;
	}

// ---------------------------------------------------------------------------
//	Storage methods
// ---------------------------------------------------------------------------
	private static /*.void.*/ function addElement (DOMDocument $storage, DOMElement $record, /*.string.*/ $tagName, /*.string.*/ $value) {
		$record->appendChild($storage->createTextNode("\n\t\t")); // XML formatting
		$record->appendChild($storage->createElement($tagName, $value));
	}

	private static /*.DOMElement.*/ function createRecord(DOMDocument $storage, ezUser_base $ezUser) {
		$record = $storage->createElement(self::TAGNAME_USER);
		self::addElement($storage, $record, self::TAGNAME_USERNAME,	$ezUser->username());			// Add username
		self::addElement($storage, $record, self::TAGNAME_EMAIL,	$ezUser->email());				// Add email address
		self::addElement($storage, $record, self::TAGNAME_ID,		$ezUser->id());					// Add id
		self::addElement($storage, $record, self::TAGNAME_DATA,		$ezUser->data());				// Add data blob

		// Add verification key if necessary
		$verificationKey = $ezUser->verificationKey();

		if (!empty($verificationKey)) {
			self::addElement($storage, $record, self::TAGNAME_VERIFICATIONKEY, $verificationKey);		// Add verification key
		}

		// Add password reset data if necessary
		if ($ezUser->hasPasswordReset()) {
			$passwordReset = $ezUser->passwordReset();
			self::addElement($storage, $record, self::TAGNAME_RESETKEY,	$passwordReset->resetKey());	// Add password reset key
			self::addElement($storage, $record, self::TAGNAME_RESETDATA,	$passwordReset->data());		// Add password reset data
		}

		self::addElement($storage, $record, 'updated', gmdate("Y-m-d H:i:s (T)"));				// Note when the record was updated
		$record->appendChild($storage->createTextNode("\n\t")); // XML formatting
		return $record;
	}

// ---------------------------------------------------------------------------
	private static /*.int.*/ function add(ezUser_base $ezUser) {
		$storage	= self::openStorage();
		$record		= self::createRecord($storage, $ezUser);

		$users = $storage->getElementsByTagName('users')->item(0);
		$users->appendChild($storage->createTextNode("\t")); // XML formatting
		$users->appendChild($record);
		$users->appendChild($storage->createTextNode("\n")); // XML formatting

		self::closeStorage($storage);
		return self::RESULT_SUCCESS;
	}

// ---------------------------------------------------------------------------
	private static /*.int.*/ function update(ezUser_base $ezUser) {
		$storage	= self::openStorage();
		$oldRecord	= self::findUser($storage, $ezUser->id(), self::TAGNAME_ID);

		if (!$oldRecord->hasChildNodes()) return self::RESULT_STORAGEERR;

		$newRecord	= self::createRecord($storage, $ezUser);

		$oldRecord->parentNode->replaceChild($newRecord, $oldRecord);
		self::closeStorage($storage);
		return self::RESULT_SUCCESS;
	}

// ---------------------------------------------------------------------------
//	Account verification
// ---------------------------------------------------------------------------
	protected static /*.integer.*/ function verify_notify($username_or_email = '') {
		$ezUser = self::lookup($username_or_email);

		if ($ezUser->status() !== self::STATUS_PENDING) return self::RESULT_ALREADYVERIFIED;	// Only send confirmation email to users who are pending verification

		// Message - SMTP needs CRLF not a bare LF (http://cr.yp.to/docs/smtplf.html)
		$URL		= self::getURL(self::URL_MODE_ALL, 'ezuser.php');
		$host		= self::getURL(self::URL_MODE_HOST);
		$message	= "Somebody calling themselves " . $ezUser->fullName() . " created an account at $host using this email address.\r\n";
		$message	.= "If it was you please click on the following link to verify the account.\r\n\r\n";
		$message	.= "$URL?" . self::ACTION_VERIFY . "=" . $ezUser->verificationKey() . "\r\n\r\n";
		$message	.= "After you click the link your account will be fully functional.\r\n";

		// Send it
		return (self::sendEmail($ezUser->email(), 'New account confirmation', $message)) ? self::RESULT_SUCCESS : self::RESULT_EMAILFAIL;
	}

	protected static /*.void.*/ function verify_update(ezUser_base $ezUser, /*.string.*/ $verificationKey) {
		if ($ezUser->status() === self::STATUS_PENDING && $ezUser->verificationKey() === $verificationKey) {
			$ezUser->setStatus(self::STATUS_CONFIRMED);
			self::update($ezUser);
		}
	}

// ---------------------------------------------------------------------------
	public static /*.ezUser_base.*/ function save(/*.array[string]mixed.*/ $userData) {
		$result			= self::RESULT_VALIDATED;
		$newUser		= (array_key_exists(self::TAGNAME_NEWUSER, $userData) && ($userData[self::TAGNAME_NEWUSER] === self::STRING_TRUE)) ? true : false;
		$emailChanged		= false;
		$usernameChanged	= false;
		$ezUser			= self::getSessionObject(self::ACTION_ACCOUNT);

		if (!$newUser && $ezUser->authenticated()) $ezUser->clearErrors(); else $newUser = true;

		if ($newUser) {
			$ezUser = new ezUser_base();
			self::setSessionObject($ezUser, self::ACTION_ACCOUNT);
		}

		// Update email address
		if (array_key_exists(self::TAGNAME_EMAIL, $userData)) {
			$email			= (string) $userData[self::TAGNAME_EMAIL];
			$emailChanged		= ($email !== $ezUser->email());
			$thisResult		= $ezUser->setEmail($email);
			$result			= ($result === self::RESULT_VALIDATED) ? $thisResult : $result;
		} else	$email			= '';

		// Update username
		if (array_key_exists(self::COOKIE_USERNAME, $userData)) {
			$username		= (string) $userData[self::COOKIE_USERNAME];
			$usernameChanged	= ($username !== $ezUser->username());
			$thisResult		= $ezUser->setUsername($username);
			$result			= ($result === self::RESULT_VALIDATED) ? $thisResult : $result;
		} else	$username		= '';

		// Update password
		if (array_key_exists(self::COOKIE_PASSWORD, $userData)) {
			$passwordHash		= (string) $userData[self::COOKIE_PASSWORD];
			$thisResult		= $ezUser->setPasswordHash($passwordHash);
			$result			= ($result === self::RESULT_VALIDATED) ? $thisResult : $result;
		}

		// Update first name and last name
		if (array_key_exists(self::TAGNAME_FIRSTNAME,	$userData)) $ezUser->setFirstName((string) $userData[self::TAGNAME_FIRSTNAME]);
		if (array_key_exists(self::TAGNAME_LASTNAME,	$userData)) $ezUser->setLastName ((string) $userData[self::TAGNAME_LASTNAME]);

		// Check for duplicates
		$id = $ezUser->id();
		if (($result === self::RESULT_VALIDATED) && $emailChanged)	$result = self::is_duplicate($email,	$id);
		if (($result === self::RESULT_VALIDATED) && $usernameChanged)	$result = self::is_duplicate($username,	$id);

		// Final checks and update
		if ($result === self::RESULT_VALIDATED) {
			if ($ezUser->isChanged()) {
				if ($newUser || $emailChanged) $ezUser->setStatus(self::STATUS_PENDING);

				if ($ezUser->incomplete()) {
					$result = self::RESULT_INCOMPLETE;
				} else {
					$result = ($newUser) ? self::add($ezUser) : self::update($ezUser);
					if ($result === self::RESULT_SUCCESS) $ezUser->clearChanges();
					if ($newUser || $emailChanged) self::verify_notify($email);
				}

			} else {
				$result = self::RESULT_SUCCESS;
			}
		}

		$ezUser->setResult($result);
		return $ezUser;
	}

// ---------------------------------------------------------------------------
//	Secure content handling
// ---------------------------------------------------------------------------
	private static /*.array[int]string.*/  function findBestMatch(/*.array[int]string.*/ $refererElements, /*.string.*/ $folder) {
		$refererCount	= count($refererElements);
		$name		= $refererElements[$refererCount - 1];
		$filename	= realpath("$folder/$name");
		$score		= 0;

		// Is there a match in this folder?
		if (is_file($filename)) {
			// compute its score by counting matching elements back from the last one
			$file		= (DIRECTORY_SEPARATOR !== self::URL_SEPARATOR) ? (string) str_replace(DIRECTORY_SEPARATOR, self::URL_SEPARATOR , $filename) : $filename;
			$fileElements	= explode(self::URL_SEPARATOR, $file);
			$fileElement	= end($fileElements);
			$refererElement	= end($refererElements);

			while ((bool) $fileElement && (bool) $refererElement && ($fileElement === $refererElement)) {
				$score++;
				$fileElement	= prev($fileElements);
				$refererElement	= prev($refererElements);
			}

			// If it's a perfect match then it's the same page as the referer
			// Don't use this one or we'll get in a loop
			if ($score === $refererCount) $score = 0;
		}

		// Check subfolders
		$folders = glob("$folder/*", GLOB_ONLYDIR);

		foreach ($folders as $subfolder) {
			// Exclude known red herrings
			$basename = basename($subfolder);

			switch ($basename) {
			case '_vti_cnf':	// Fall-through ->
			case '.git':		// Fall-through ->
			case '.hg':		$redHerring = true;	break;
			default:		$redHerring = false;	break;
			}

			if (!$redHerring) {
				$match	= self::findBestMatch($refererElements, $subfolder);

				if ((int) $match[1] > $score) {
					$filename	= $match[0];
					$score		= (int) $match[1];
					break;
				}
			}
		}

		return array($filename, (string) $score);
	}

// ---------------------------------------------------------------------------
	protected static /*.string.*/ function getSecureContent(/*.string.*/ $referer) {
		$refererElements	= cast('array[int]string', array_slice(explode(self::URL_SEPARATOR, $referer), 3));
		$folder			= self::getSetting(self::SETTINGS_SECUREFOLDER);

		if ($folder === '') $folder = dirname(realpath(__FILE__));

		$match			= self::findBestMatch($refererElements, $folder);
		$filename		= $match[0];
		$html			= (is_file($filename)) ? self::getFileContents($filename) : '';
		$start			= strpos($html, '<body>') + 6;
		$length			= strpos($html, '</body>') - $start;
		$html			= substr($html, $start, $length);

		return $html;
	}

// ---------------------------------------------------------------------------
//	Password reset handling
// ---------------------------------------------------------------------------
	private static /*.boolean.*/ function passwordReset_notify(ezUser_base $ezUser) {
		$passwordReset = $ezUser->passwordReset();

		// Message
		$URL		= self::getURL(self::URL_MODE_ALL, 'ezuser.php');
		$host		= self::getURL(self::URL_MODE_HOST);
		$message	= "A password reset was requested for an account at $host using this email address.\r\n";
		$message	.= "If you want to reset your password please click on the following link.\r\n\r\n";
		$message	.= "$URL?" . self::ACTION_RESET . "=" . $passwordReset->resetKey() . "\r\n\r\n";
		$message	.= "If nothing happens when you click on the link then please copy it into your browser's address bar.\r\n";

		// Send it
		return self::sendEmail($ezUser->email(), 'Account maintenance', $message);
	}

	protected static /*.boolean.*/ function passwordReset_initialize(/*.string.*/ $username_or_email) {
		$ezUser = self::lookup($username_or_email);
		if ($ezUser->status() === self::STATUS_UNKNOWN) return false;
		$passwordReset = $ezUser->passwordReset();
		$passwordReset->initialize();
		return ((bool) self::update($ezUser)) ? self::passwordReset_notify($ezUser) : false;
	}

	protected static /*.void.*/ function passwordReset_update(ezUser_base $ezUser, /*.string.*/$passwordHash) {
		if ($ezUser->hasPasswordReset()) {
			$ezUser->setPasswordHash($passwordHash);
			$ezUser->passwordReset(true); // Clear password reset data
			self::update($ezUser);
		}
	}
}
// End of class ezUser_environment


/**
 * This class manages the HTML, CSS and Javascript that you can include in
 * your web pages to support user registration and authentication.
 *
 * @package ezUser
 */
interface I_ezUser extends I_ezUser_environment {
		// Modes for account form
	const	ACCOUNT_MODE_NEW	= 'new',
		ACCOUNT_MODE_EDIT	= 'edit',
		ACCOUNT_MODE_DISPLAY	= 'display',
		ACCOUNT_MODE_RESULT	= 'result',
		ACCOUNT_MODE_CANCEL	= 'cancel',

		// Button types
		BUTTON_TYPE_ACTION	= 'action',
		BUTTON_TYPE_DEFAULT	= 'default',
		BUTTON_TYPE_PREFERENCE	= 'preference',
		BUTTON_TYPE_FIXEDWIDTH	= 'fixedwidth',
		BUTTON_TYPE_HIDDEN	= 'hidden',

		// Message types
		MESSAGE_TYPE_DEFAULT	= 'message',
		MESSAGE_TYPE_DIALOG	= 'dialog',

		// Message styles
		MESSAGE_STYLE_DEFAULT	= 'info',
		MESSAGE_STYLE_FAIL	= 'fail',
		MESSAGE_STYLE_TEXT	= 'text',
		MESSAGE_STYLE_PLAIN	= 'plain',

		// Container styles
		CONTAINER_STYLE_INLINE	= 'inline',
		CONTAINER_STYLE_DIALOG	= 'dialog',

		// Miscellaneous constants
		DELIMITER_PLUS		= '+',
		PASSWORD_MASK		= '************',
		STRING_LEFT		= 'left',
		STRING_RIGHT		= 'right',

		// Debugging & QA
		VERBOSE			= false; // set to true for diagnostic (but insecure) messages


// Methods may be commented out to reduce the attack surface when they are
// not required. Uncomment them if you need them.
//	public static /*.void.*/	function getStatusText		(/*.int.*/ $status, $more = '');
//	public static /*.void.*/	function getResultText		(/*.int.*/ $result, $more = '');
//	public static /*.void.*/	function getStatusDescription	(/*.int.*/ $status, $more = '');
//	public static /*.void.*/	function getResultDescription	(/*.int.*/ $result, $more = '');
	public static /*.void.*/	function getResultForm		(/*.int.*/ $result, $more = '');
	public static /*.void.*/	function getAccountForm		($mode = '', $newUser = false, $wizard = false);
//	public static /*.void.*/	function getSignInForm		();
	public static /*.void.*/	function getSignInResults	();
//	public static /*.void.*/	function getControlPanel	();
//	public static /*.void.*/	function getStyleSheet		();
//	public static /*.void.*/	function getJavascript		($containerList = '');
	public static /*.void.*/	function getContainer		($action = self::ACTION_CONTROLPANEL);
	public static /*.void.*/	function getAbout		();
	public static /*.void.*/	function getAboutText		();
//	public static /*.void.*/	function getSourceCode		();
}

/**
 * This class manages the HTML, CSS and Javascript that you can include in
 * your web pages to support user registration and authentication.
 *
 * @package ezUser
 */
class ezUser extends ezUser_environment implements I_ezUser {
	private static /*.string.*/ function getXML($html = '', $container = '', $attributes = '') {
		if (is_numeric($container) || $container === '') $container = 'ezuser'; // If passed to sendXML as an array
		$attributeDelim = ($attributes === '') ? '' : ' ';
		return "<container id=\"$container\"$attributeDelim$attributes><![CDATA[$html]]></container>";
	}

	private static /*.void.*/ function sendXML(/*.mixed.*/ $content = '', $container = '', $attributes = '') {
		if (is_array($content)) {
			$contentArray	= cast('array[int]', $content);

			if (is_array($contentArray[0])) {
				$xml = '';

				foreach ($contentArray as $item) {
					$thisContent	= cast('array[int]string', $item);
					$xml		.= self::getXML($thisContent[0], $thisContent[1], $thisContent[2]);
				}

				$xml = "<containers>$xml</containers>";
			} else {
				$xml = self::getXML($contentArray[0], $contentArray[1], $contentArray[2]);
			}
		} else {
			$xml = self::getXML((string) $content, $container, $attributes);
		}

		self::sendContent($xml, $container, 'text/xml', $attributes);
	}

// ---------------------------------------------------------------------------
// Functions that build common HTML fragments
// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlPage($body = '', $title = '', $sendToBrowser = false) {
		$URL		= self::thisURL();
		$actionJs	= self::ACTION_JAVASCRIPT;
		$actionCSS	= self::ACTION_STYLESHEET;

		$html = <<<HTML
<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8"/>
	<title>$title</title>
	<link type="text/css" rel="stylesheet" href="$URL?$actionCSS" title="ezUser">
	<script src="$URL?$actionJs"></script>
</head>

<body class="ezuser ezuser-body">
$body
</body>

</html>
HTML;

		if ($sendToBrowser) {self::sendContent($html); return '';} else return $html;
	}

	private static /*.string.*/ function htmlContainer($action = self::ACTION_CONTROLPANEL, $sendToBrowser = false) {
		$baseAction	= explode('=', $action);
		$container	= self::getInstanceId($baseAction[0]);
		$actionCommand	= self::ACTION;
		$actionJs	= self::ACTION_JAVASCRIPT;
		$URL		= self::thisURL();

		$html = <<<HTML
	<div id="$container"></div>
	<script type="text/javascript">document.write(unescape('%3Cscript src="$URL?$actionCommand=$actionJs"%3E%3C/script%3E'));</script>
	<script type="text/javascript">ezUser.action('$action');</script>
HTML;

		if ($sendToBrowser) {self::sendContent($html); return '';} else return $html;
	}

	private static /*.string.*/ function htmlInputText($styleDialog = self::STRING_RIGHT) {
		return <<<HTML
					class		=	"ezuser-text ezuser-$styleDialog"
					onkeyup		=	"ezUser.keyUp(event)"
					size		=	"40"
HTML;
	}

	private static /*.string.*/ function htmlDialogClose(/*.string.*/ $container) {
		$URL		= self::thisURL();
		$actionBitmap	= self::ACTION_BITMAP;

		return <<<HTML
<img id="$container-close" class="ezuser-dialog-control ezuser-dialog-close" src="$URL?$actionBitmap=close" onclick="ezUser.click(this)" />
HTML;
	}

	private static /*.string.*/ function htmlButton(/*.string.*/ $type) {
		$classVerbose	= (self::VERBOSE) ? ' ezuser-' . self::BUTTON_TYPE_PREFERENCE . '-' . self::TAGNAME_VERBOSE : '';
		$styleString	= ($type === self::BUTTON_TYPE_HIDDEN)	? 'ezuser-' . self::BUTTON_TYPE_ACTION . " ezuser-$type" : "ezuser-$type";
		$state		= ($type === self::BUTTON_TYPE_DEFAULT)	? '16' : '0';
		$styleRight	= self::STRING_RIGHT;

		return <<<HTML
					type		=	"button"
					class		=	"ezuser-button ezuser-$styleRight $styleString$classVerbose ezuser-state-$state"
					onclick		=	"ezUser.click(this)"
					onmouseover	=	"ezUser.control(this).setState(1, true)"
					onmouseout	=	"ezUser.control(this).setState(1, false)"
					onfocus		=	"ezUser.control(this).setState(2, true)"
					onblur		=	"ezUser.control(this).setState(2, false)"
HTML;
	}

	private static /*.string.*/ function htmlMessage($message = '', $style = self::MESSAGE_STYLE_DEFAULT, $container = '', $type = self::MESSAGE_TYPE_DEFAULT, $extraHTML = '') {
		$styleRight	= self::STRING_RIGHT;
		$hidden		= ($message === '') ? ' style="visibility: hidden;"' : '';
		$message	= "<p class=\"ezuser\">$message</p>";
		$id		= ($container === '') ? "ezuser-$type" : "$container-$type";
		$indent		= ($type === self::MESSAGE_TYPE_DIALOG) ? '' : "\t\t";

		return <<<HTML
$indent		<div id="$id" class="ezuser-$type ezuser-$styleRight ezuser-message-$style" onclick="ezUser.click(this)"$hidden>$message$extraHTML</div>
HTML;
	}

	private static /*.string.*/ function htmlContainerStyle($style = self::CONTAINER_STYLE_DIALOG) {
		return 'class="ezuser-' . $style . '"';
	}

// ---------------------------------------------------------------------------
// Text versions of status and result codes
// ---------------------------------------------------------------------------
	private static /*.string.*/ function statusText(/*.int.*/ $status, $more = '', $sendToBrowser = false) {
		switch ($status) {
			case self::STATUS_UNKNOWN:		$text = "Unknown status";				break;
			case self::STATUS_PENDING:		$text = "Awaiting confirmation";			break;
			case self::STATUS_CONFIRMED:		$text = "Confirmed and active";				break;
			case self::STATUS_INACTIVE:		$text = "Inactive";					break;
			default:				$text = "Unknown status code";				break;
		}

		if ($more !== '')	$text .= ": $more";
		if ($sendToBrowser)	{self::sendContent($text); return '';} else return $text;
	}

	private static /*.string.*/ function statusDescription(/*.int.*/ $status, $more = '', $sendToBrowser = false) {
		switch ($status) {
			case self::STATUS_PENDING:		$text = "Your account has been created and a confirmation email has been sent. Please click on the link in the confirmation email to verify your account.";
															break;
			default:				$text = self::statusText($status);			break;
		}

		if ($more !== '')	$text .= ": $more";
		if ($sendToBrowser)	{self::sendContent($text); return '';} else return $text;
	}

	private static /*.string.*/ function resultDescription(/*.int.*/ $result, $more = '', $sendToBrowser = false) {
		switch ($result) {
			case self::RESULT_EMAILFORMATERR:	$text = "The format of the email address you entered was incorrect. Email addresses are usually in the form <em>joe.smith@example.com</em>";
								break;
			default:				$text = '';
								break;
		}

		if ($more !== '')	$text .= ": $more";
		if ($sendToBrowser)	{self::sendContent($text); return '';} else return $text;
	}

// ---------------------------------------------------------------------------
// HTML for UI Forms
// ---------------------------------------------------------------------------
/**
 * Render the HTML for the account maintenance form
 *
 * $newUser indicates whether this is an existing user from the
 * database, or a new registration that we are processing. If the user
 * enters invalid data we might render this form a number of times
 * until validation is successful. $newUser should persist until
 * registration is successful.
 *
 * The form can also operate as a "wizard" (with Next and Back buttons). This
 * allows it to work in the confined space of the ezUser control panel
 *
 * This function is also driven by the mode parameter as follows:
 *
 * Mode		Behaviour
 * -------	--------------------------------------------------------------
 * - (none)	Infer mode from current ezUser object - if it's authenticated
 *		then display the account page for that user. If not then
 *		display a registration form for a new user. Inferred mode will
 *		be 'display' or 'new'
 *
 * - new	Register a new user. Input controls are blank but available.
 *		Button says Register.
 *
 * - display	View account details. Input controls are populated but
 *		unavailable. Button says Edit.
 *
 * - edit	Edit an existing account or correct a failed registration.
 *		Input controls are populated with existing data. Buttons say
 *		OK and Cancel
 *
 * - result	Infer actual mode from result of validation. If validated then
 *		display account details, otherwise allow them to be corrected.
 * 		Inferred mode will be either 'display' or 'edit'.
 *
 * - cancel	Infer actual mode from $newUser. If we're cancelling a new
 *		registration then clear the form. If we're cancelling editing
 *		an existing user then redisplay details from the database.
 * 		Inferred mode will be either 'new' or 'display'.
 *
 * So, the difference between $mode = 'new' and $newUser = true is as
 * follows:
 *
 * - $mode = 'new'	means this is a blank form for a new registration
 *
 * - $newUser = true	means we are processing a new registration but we
 *			might be asking the user to re-enter certain values:
 *			the form might therefore need to be populated with the
 *			attempted registration details.
 *
 * @param string	$mode		See above
 * @param boolean	$newUser	Is this a new or existing user?
 * @param boolean	$wizard		Display as a wizard within control panel
 * @param boolean	$sendToBrowser	Send HTML to browser?
 */
	private static /*.array[int]string.*/ function htmlAccountForm($mode = '', $newUser = false, $wizard = false, $sendToBrowser = false) {
		/* Comment out profiling statements if not needed
		global $ezuser_profile;
		$ezuser_profile[self::ACTION_ACCOUNT . '-start'] = ezuser_time();
		*/

		$action			= self::ACTION_ACCOUNT;
		$actionResend		= self::ACTION_RESEND;
		$actionValidate		= self::ACTION_VALIDATE;
		$accountForm		= self::getInstanceId($action);
//		$container		= ($wizard) ? 'ezuser' : $accountForm;
		$container		= $accountForm;

		$tagFirstName		= self::TAGNAME_FIRSTNAME;
		$tagLastName		= self::TAGNAME_LASTNAME;
		$tagEmail		= self::TAGNAME_EMAIL;
		$tagUsername		= self::TAGNAME_USERNAME;
		$tagPassword		= self::TAGNAME_PASSWORD;
		$tagConfirm		= self::TAGNAME_CONFIRM;
		$tagNewUser		= self::TAGNAME_NEWUSER;
		$tagUseSavedPassword	= self::TAGNAME_SAVEDPASSWORD;
		$tagWizard		= self::TAGNAME_WIZARD;

		$modeNew		= self::ACCOUNT_MODE_NEW;
		$modeEdit		= self::ACCOUNT_MODE_EDIT;
		$modeDisplay		= self::ACCOUNT_MODE_DISPLAY;
		$modeResult		= self::ACCOUNT_MODE_RESULT;
		$modeCancel		= self::ACCOUNT_MODE_CANCEL;

		$stringRight		= self::STRING_RIGHT;
		$htmlButtonAction	= self::htmlButton(self::BUTTON_TYPE_ACTION);
		$htmlButtonHidden	= self::htmlButton(self::BUTTON_TYPE_HIDDEN);
		$htmlInputText		= self::htmlInputText();
		$htmlDialogClose	= self::htmlDialogClose($container);
		$messageShort		= self::htmlMessage('', self::MESSAGE_STYLE_PLAIN, $accountForm);
		$messageLong		= self::htmlMessage('', self::MESSAGE_STYLE_TEXT, $accountForm, self::MESSAGE_TYPE_DIALOG);

		if (!isset($mode) || empty($mode)) $mode = '';

		$modeInfo		= ($newUser) ? self::STRING_TRUE : self::STRING_FALSE;
		$modeInfo		= "(originally mode was '$mode', new flag was $modeInfo) -->";

		if ($mode === '') {
			$ezUser	= self::getSessionObject();
			$result	= self::RESULT_SUCCESS;

			if ($ezUser->authenticated()) {
				$mode	= $modeDisplay;
				$ezUser->addSignOutAction($action);
				self::setSessionObject($ezUser, $action);
			} else {
				$mode	= $modeNew;
				$ezUser->clearSignOutActions();
			}
		} else {
			$ezUser	= self::getSessionObject($action);
			$result	= $ezUser->result();
		}

		if ($mode === $modeCancel) $ezUser->clearErrors();

		// Some raw logic - think carefully about these lines before amending
		if (!isset($newUser))		$newUser	= false;
		if ($mode === $modeNew)		$newUser	= true;
		if ($mode === $modeCancel)	$mode		= ($newUser)				? $modeNew	: $modeDisplay;
		if ($mode === $modeResult)	$mode		= ($result === self::RESULT_SUCCESS)	? $modeDisplay	: $modeEdit;


		switch ($mode) {
		case self::ACCOUNT_MODE_NEW:
			$email			= '';
			$firstName		= '';
			$lastName		= '';
			$username		= '';
			$password		= '';

			$buttonId		= $actionValidate;
			$buttonText		= 'Register';
			$buttonAction		= $actionValidate;
			$disabled		= '';
			$htmlOtherButton	= "\t\t\t\t<input id=\"$accountForm-$modeCancel\" data-ezuser-action=\"$action=$modeCancel\" value=\"Cancel\"\n\t\t\t\t\ttabindex\t=\t\"3219\"\n$htmlButtonAction\n\t\t\t\t/>\n";
			$useSavedPassword	= false;
			break;
		case self::ACCOUNT_MODE_DISPLAY:
			$errors			= $ezUser->errors();

			$email			= (array_key_exists(self::TAGNAME_EMAIL, $errors))	? $errors[self::TAGNAME_EMAIL]		: $ezUser->email();
			$firstName		= (array_key_exists(self::TAGNAME_FIRSTNAME, $errors))	? $errors[self::TAGNAME_FIRSTNAME]	: $ezUser->firstName();
			$lastName		= (array_key_exists(self::TAGNAME_LASTNAME, $errors))	? $errors[self::TAGNAME_LASTNAME]	: $ezUser->lastName();
			$username		= (array_key_exists(self::TAGNAME_USERNAME, $errors))	? $errors[self::TAGNAME_USERNAME]	: $ezUser->username();
			$password		= ($ezUser->passwordHash() === '') ? '' : self::PASSWORD_MASK;

			$buttonId		= $modeEdit;
			$buttonText		= 'Edit';
			$buttonAction		= "$action=$modeEdit";
			$disabled		= "\t\t\t\t\tdisabled\t=\t\"disabled\"\r\n";
			$htmlOtherButton	= "\t\t\t\t<input id=\"$accountForm-$modeNew\" data-ezuser-action=\"$action=$modeNew\" value=\"New\"\n\t\t\t\t\ttabindex\t=\t\"3219\"\n$htmlButtonAction\n\t\t\t\t/>\n";
			$useSavedPassword	= false;
			$newUser		= false;

			if ($result === self::RESULT_SUCCESS || $result === self::RESULT_UNDEFINED) {
				// Show status information
				$status		= $ezUser->status();

				$resendButton = ($status === self::STATUS_PENDING) ? "\n\t\t\t\t<input id=\"$accountForm-$actionResend\" data-ezuser-action=\"$actionResend\" value=\"Resend\"\n\t\t\t\t\ttabindex\t=\t\"3221\"\n$htmlButtonAction\n\t\t\t\t/>" : '';

				$messageLong	= ($status === self::STATUS_CONFIRMED) ? '' : self::statusDescription($status);
				$messageLong	= self::htmlMessage($messageLong, self::MESSAGE_STYLE_TEXT, $accountForm, self::MESSAGE_TYPE_DIALOG, $resendButton);
			} else {
				// Show result information
				$messageShort	= self::resultText($result);
				$messageShort	= self::htmlMessage($messageShort, self::MESSAGE_STYLE_FAIL, $accountForm);

				$messageLong	= self::resultDescription($result);
				$messageLong	= self::htmlMessage($messageLong, self::MESSAGE_STYLE_TEXT, $accountForm, self::MESSAGE_TYPE_DIALOG);
			}

			break;
		case self::ACCOUNT_MODE_EDIT:
			$errors			= $ezUser->errors();

			$email			= (array_key_exists(self::TAGNAME_EMAIL,	$errors)) ? $errors[self::TAGNAME_EMAIL]	: $ezUser->email();
			$firstName		= (array_key_exists(self::TAGNAME_FIRSTNAME,	$errors)) ? $errors[self::TAGNAME_FIRSTNAME]	: $ezUser->firstName();
			$lastName		= (array_key_exists(self::TAGNAME_LASTNAME,	$errors)) ? $errors[self::TAGNAME_LASTNAME]	: $ezUser->lastName();
			$username		= (array_key_exists(self::TAGNAME_USERNAME,	$errors)) ? $errors[self::TAGNAME_USERNAME]	: $ezUser->username();
			$password		= ($ezUser->passwordHash() === '') ? '' : self::PASSWORD_MASK;

			$buttonId		= $actionValidate;
			$buttonText		= 'OK';
			$buttonAction		= $actionValidate;
			$disabled		= '';
			$htmlOtherButton	= "\t\t\t\t<input id=\"$accountForm-$modeCancel\" data-ezuser-action=\"$action=$modeCancel\" value=\"Cancel\"\n\t\t\t\t\ttabindex\t=\t\"3219\"\n$htmlButtonAction\n\t\t\t\t/>\n";
			$useSavedPassword	= $newUser;

			if ($result === self::RESULT_SUCCESS || $result === self::RESULT_UNDEFINED) {
				$messageShort	= self::htmlMessage('', self::MESSAGE_STYLE_TEXT, $accountForm);
			} else {
				// Show result information
				$messageShort	= self::resultText($result);
				$messageShort	= self::htmlMessage($messageShort, self::MESSAGE_STYLE_FAIL, $accountForm);

				$messageLong	= self::resultDescription($result);
				$messageLong	= self::htmlMessage($messageLong, self::MESSAGE_STYLE_TEXT, $accountForm, self::MESSAGE_TYPE_DIALOG);
			}

			break;
		default:
			$useSavedPassword	= false;
			$email			= '';
			$disabled		= '';
			$firstName		= '';
			$lastName		= '';
			$username		= '';
			$password		= '';
			$buttonId		= '';
			$buttonAction		= '';
			$buttonText		= '';
			$htmlOtherButton	= '';
			$messageLong		= '';
			break;
		}

		// Some hidden form elements
		$newString		= ($newUser)		? self::STRING_TRUE : self::STRING_FALSE;
		$useSavedPasswordString	= ($useSavedPassword)	? self::STRING_TRUE : self::STRING_FALSE;
		$modeInfo		= "<!-- Mode is '$mode', new flag is $newString $modeInfo";

		// Form varies slightly if it's working in wizard mode
		if ($wizard) {
			$wizardString	= self::STRING_TRUE;
			$styleHidden	= ' style="visibility: hidden;"';
			$htmlNavigation = <<<HTML
				<input id="$accountForm-next" data-ezuser-action="next" value="Next &gt;"
					tabindex	=	"3218"
$htmlButtonAction
				/>
				<input id="$accountForm-back" data-ezuser-action="back" value="&lt; Back"
					tabindex	=	"3217"
$htmlButtonHidden
				/>
HTML;
		} else {
			$wizardString	= self::STRING_FALSE;
			$styleHidden	= '';
			$htmlNavigation = '';
		}

		// The lower two fieldsets are transposed if we're in wizard mode
		$messageFieldset = <<<HTML
			<fieldset id="$accountForm-fieldset-3" class="ezuser-fieldset"$styleHidden>
				<input id="$accountForm-$tagNewUser"		type="hidden" value="$newString" />
				<input id="$accountForm-$tagWizard"		type="hidden" value="$wizardString" />
				<input id="$accountForm-$tagUseSavedPassword"	type="hidden" value="$useSavedPasswordString" />
			</fieldset>

HTML;

		$buttonsFieldset = <<<HTML
			<fieldset id="$accountForm-fieldset-buttons" class="ezuser-fieldset">
$messageShort
				<input id="$accountForm-$buttonId" data-ezuser-action="$buttonAction" value="$buttonText"
					tabindex	=	"3220"
$htmlButtonAction
				/>
$htmlOtherButton$htmlNavigation			</fieldset>

HTML;

		$bottomFieldsets = ($wizard) ? "$messageFieldset$buttonsFieldset" : "$buttonsFieldset$messageFieldset";

		// At this point we have finished with the result of any prior validation
		// so we can clear the result field
		$ezUser->setResult(self::RESULT_UNDEFINED);

		$html = <<<HTML
		$modeInfo
		$htmlDialogClose
		<form id="$accountForm-form" class="ezuser-form" onsubmit="return false">
			<fieldset id="$accountForm-fieldset-1" class="ezuser-fieldset">
				<input id= "$accountForm-$tagEmail"
					tabindex	=	"3211"
					value		=	"$email"
					type		=	"text"
$disabled$htmlInputText
				/>
				<label class="ezuser-label ezuser-$stringRight ezuser-mandatory" for="$accountForm-$tagEmail">Email address:</label>
				<input id= "$accountForm-$tagFirstName"
					tabindex	=	"3212"
					value		=	"$firstName"
					type		=	"text"
$disabled$htmlInputText
				/>
				<label class="ezuser-label ezuser-$stringRight ezuser-optional" for="$accountForm-$tagFirstName">First name (optional):</label>
				<input id= "$accountForm-$tagLastName"
					tabindex	=	"3213"
					value		=	"$lastName"
					type		=	"text"
$disabled$htmlInputText
				/>
				<label class="ezuser-label ezuser-$stringRight ezuser-optional" for="$accountForm-$tagLastName">Last name (optional):</label>
			</fieldset>
			<fieldset id="$accountForm-fieldset-2" class="ezuser-fieldset$styleHidden">
				<input id= "$accountForm-$tagUsername"
					tabindex	=	"3214"
					value		=	"$username"
					type		=	"text"
					onkeypress	=	"return ezUser.keyPress(event)"
$disabled$htmlInputText
				/>
				<label class="ezuser-label ezuser-$stringRight ezuser-mandatory" for="$accountForm-$tagUsername">Username:</label>
				<input id= "$accountForm-$tagPassword"
					tabindex	=	"3215"
					value		=	"$password"
					type		=	"password"
					onfocus		=	"ezUser.passwordFocus(this)"
					onblur		=	"ezUser.passwordBlur(this)"
$disabled$htmlInputText
				/>
				<label class="ezuser-label ezuser-$stringRight ezuser-mandatory" for="$accountForm-$tagPassword">Password:</label>
				<input id= "$accountForm-confirm"
					tabindex	=	"3216"
					value		=	"$password"
					type		=	"password"
					onfocus		=	"ezUser.passwordFocus(this)"
					onblur		=	"ezUser.passwordBlur(this)"
$disabled$htmlInputText
				/>
				<label class="ezuser-label ezuser-$stringRight ezuser-mandatory" for="$accountForm-$tagConfirm">Confirm password:</label>
			</fieldset>
$bottomFieldsets		</form>
$messageLong
HTML;

		/* Comment out profiling statements if not needed
		$ezuser_profile[self::ACTION_ACCOUNT . '-end'] = ezuser_time();
		*/

		$data = array($html, $container, self::htmlContainerStyle());
		if ($sendToBrowser) {self::sendXML($data); return array('', '', '');} else return $data;
	}

// ---------------------------------------------------------------------------
	private static /*.array[int]string.*/ function htmlSignInForm($sendToBrowser = false) {
		$action			= self::ACTION_SIGNIN;
		$container		= self::getInstanceId($action);
		$actionCancel		= self::ACTION_CANCEL;
		$actionResetRequest	= self::ACTION_RESETREQUEST;
		$actionResetRequestForm	= self::ACTION_RESETREQUESTFORM;
		$tagUsername		= self::TAGNAME_USERNAME;
		$tagPassword		= self::TAGNAME_PASSWORD;
		$tagRememberMe		= self::TAGNAME_REMEMBERME;
		$tagStaySignedIn	= self::TAGNAME_STAYSIGNEDIN;
		$tagVerbose		= self::TAGNAME_VERBOSE;

		$stringRight		= self::STRING_RIGHT;
		$htmlButtonAction	= self::htmlButton(self::BUTTON_TYPE_ACTION);
		$htmlButtonDefault	= self::htmlButton(self::BUTTON_TYPE_DEFAULT);	// Call to action button
		$htmlButtonPreference	= self::htmlButton(self::BUTTON_TYPE_PREFERENCE);
		$htmlInputText		= self::htmlInputText();
		$htmlDialogClose	= self::htmlDialogClose($container);
		$ezUser			= self::getSessionObject();
		$result			= (($ezUser->result() < self::RESULT_FAIL) || self::VERBOSE) ? $ezUser->result() : self::RESULT_FAIL; // Only send detailed error information if it's not too revealing
		$password		= '';

		if ($result <= self::RESULT_SUCCESS) {
			$username	= '';
			$message	= self::htmlMessage('', self::MESSAGE_STYLE_DEFAULT, $container);
			$verboseHTML	= '';
		} else {
			$ezUser->setResult(self::RESULT_UNDEFINED);

			$username	= $ezUser->username();
			$message	= self::htmlMessage(self::resultText($result), self::MESSAGE_STYLE_FAIL, $container);

			if (self::VERBOSE) {
				$verboseHTML = self::htmlButton(self::BUTTON_TYPE_PREFERENCE);
				$verboseHTML = <<<HTML
				<input id="ezuser-$tagVerbose" value="$result"
$verboseHTML
				/>
HTML;
			} else {
				$verboseHTML = '';
			}
		}

		$html = <<<HTML
		$htmlDialogClose
		<form id="$container-form" class="ezuser-form" onsubmit="return false">
			<fieldset class="ezuser-fieldset">
				<input id= "ezuser-$tagUsername"
					tabindex	=	"3201"
					value		=	"$username"
					type		=	"text"
$htmlInputText
				/>
				<label class="ezuser-label ezuser-$stringRight" for="ezuser-$tagUsername">Username:</label>
				<input id= "ezuser-$tagPassword"
					tabindex	=	"3202"
					value		=	"$password"
					type		=	"password"
					onfocus		=	"ezUser.passwordFocus(this)"
					onblur		=	"ezUser.passwordBlur(this)"
$htmlInputText
				/>
				<label class="ezuser-label ezuser-$stringRight" for="ezuser-$tagPassword">Password:</label>
$verboseHTML			</fieldset>
			<fieldset class="ezuser-fieldset">
$message
				<input id="ezuser-$actionCancel" data-ezuser-action="$actionCancel" value="Cancel"
					tabindex	=	"3204"
$htmlButtonAction
				/>
				<input id="ezuser-$action-button" data-ezuser-action="$action" value="Sign in"
					tabindex	=	"3203"
$htmlButtonDefault
				/>
			</fieldset>
			<fieldset class="ezuser-fieldset">
				<input id="ezuser-$tagStaySignedIn" value="Stay signed in"
					tabindex	=	"3207"
$htmlButtonPreference
				/>
				<input id="ezuser-$tagRememberMe" value="Remember me"
					tabindex	=	"3206"
$htmlButtonPreference
				/>
				<input id="ezuser-$actionResetRequestForm" data-ezuser-action="$actionResetRequest" value="Reset password"
					tabindex	=	"3205"
$htmlButtonPreference
				/>
			</fieldset>
		</form>
HTML;

		$data = array($html, $container, self::htmlContainerStyle());
		if ($sendToBrowser) {self::sendXML($data); return array('', '', '');} else return $data;
	}

/**
 * HTML for control panel when user is authenticated
 *
 * @param boolean $sendToBrowser
 */
	private static /*.array[int]string.*/ function htmlControlPanelAuthenticated($sendToBrowser = false) {
		$action			= self::ACTION_CONTROLPANEL;
		$actionSignOut		= self::ACTION_SIGNOUT;
		$actionAccountForm	= self::ACTION_ACCOUNTFORM;
		$container		= self::getInstanceId($action);
		$tagFullName		= self::TAGNAME_FULLNAME;
		$htmlButtonFixedWidth	= self::htmlButton(self::BUTTON_TYPE_FIXEDWIDTH);
//-		$message		= self::htmlMessage();
		$ezUser			= self::getSessionObject();
		$fullName		= $ezUser->fullName();

		$html = <<<HTML
		<form id="$container-form" class="ezuser-form" onsubmit="return false">
			<fieldset class="ezuser-fieldset">
				<input id="ezuser-$actionSignOut" data-ezuser-action="$actionSignOut" value="Sign out"
					tabindex	=	"3222"
					accesskey	=	"S"
$htmlButtonFixedWidth
				/>
				<div id="ezuser-$actionAccountForm" data-ezuser-action="$actionAccountForm" class="ezuser-$tagFullName" onclick = "ezUser.click(this)">$fullName</div>
			</fieldset>
		</form>
HTML;
//-			<fieldset class="ezuser-fieldset">
//-$message
//-			</fieldset>
//-		</form>
//-HTML;

		$data = array($html, $container, self::htmlContainerStyle(self::CONTAINER_STYLE_INLINE));
		if ($sendToBrowser) {self::sendXML($data); return array('', '', '');} else return $data;
	}

/**
 * HTML for control panel when user is not authenticated
 *
 * @param boolean $sendToBrowser
 */
	private static /*.array[int]string.*/ function htmlControlPanelNotAuthenticated($sendToBrowser = false) {
		$action			= self::ACTION_CONTROLPANEL;
		$actionSignInForm	= self::ACTION_SIGNINFORM;
		$actionAccountForm	= self::ACTION_ACCOUNTFORM;
		$container		= self::getInstanceId($action);
		$htmlButtonFixedWidth	= self::htmlButton(self::BUTTON_TYPE_FIXEDWIDTH);
//-		$message		= self::htmlMessage();

		$html = <<<HTML
		<form id="$container-form" class="ezuser-form" onsubmit="return false">
			<fieldset class="ezuser-fieldset">
				<input id="ezuser-$actionAccountForm" data-ezuser-action="$actionAccountForm" value="Register"
					tabindex	=	"3222"
					accesskey	=	"R"
$htmlButtonFixedWidth
				/>
				<input id="ezuser-$actionSignInForm" data-ezuser-action="$actionSignInForm" value="Sign in"
					tabindex	=	"3221"
					accesskey	=	"S"
$htmlButtonFixedWidth
				/>
			</fieldset>
		</form>
HTML;
//-			<fieldset class="ezuser-fieldset">
//-$message
//-			</fieldset>
//-		</form>
//-HTML;

		$data = array($html, $container, self::htmlContainerStyle(self::CONTAINER_STYLE_INLINE));
		if ($sendToBrowser) {self::sendXML($data); return array('', '', '');} else return $data;
	}

/**
 * HTML for control panel
 *
 * @param boolean $sendToBrowser
 */
	private static /*.array[int]string.*/ function htmlControlPanel($sendToBrowser = false) {
		$ezUser = self::getSessionObject();
		$data = ($ezUser->authenticated()) ? self::htmlControlPanelAuthenticated() : self::htmlControlPanelNotAuthenticated();
		if ($sendToBrowser) {self::sendXML($data); return array('', '', '');} else return $data;
	}

/**
 * Process results of sign-in attempt
 *
 * @param boolean $sendToBrowser
 */
	private static /*.mixed.*/ function htmlSignInResults($sendToBrowser = false) {
		$ezUser = self::getSessionObject();

		if ($ezUser->authenticated()) {
			$data = self::htmlControlPanelAuthenticated();
			if ($sendToBrowser) {self::sendXML($data); return array('', '', '');} else return $data;
		} else {
			$result		= (($ezUser->result() < self::RESULT_FAIL) || self::VERBOSE) ? $ezUser->result() : self::RESULT_FAIL; // Only send detailed error information if it's not too revealing
			$message	= self::resultText($result);
			$container	= self::getInstanceId(self::ACTION_SIGNIN) . '-' . self::MESSAGE_TYPE_DEFAULT;

			if ($sendToBrowser) {self::sendContent($message, $container); return '';} else return $message;
		}
	}

// ---------------------------------------------------------------------------
	private static /*.array[int]string.*/ function htmlResetRequest ($username = '', $sendToBrowser = false) {
		$action			= self::ACTION_RESETREQUEST;
		$actionReset		= self::ACTION_RESET;
		$actionCancel		= self::ACTION_CANCEL;
		$actionResetPassword	= self::ACTION_RESETPASSWORD;
		$actionControlPanel	= self::ACTION_CONTROLPANEL;
		$container		= self::getInstanceId($actionReset);
		$htmlDialogClose	= self::htmlDialogClose($container);
		$tagUsername		= self::TAGNAME_USERNAME;
		$htmlButtonPreference	= self::htmlButton(self::BUTTON_TYPE_PREFERENCE);
		$stringLeft		= self::STRING_LEFT;
		$stringRight		= self::STRING_RIGHT;
		$htmlInputText		= self::htmlInputText($stringRight);

		$html = <<<HTML
		$htmlDialogClose
		<form id="$container-form" class="ezuser-form" onsubmit="return false">
			<fieldset class="ezuser-fieldset">
				<input id="ezuser-$tagUsername"
					tabindex	=	"3241"
					value		=	"$username"
					type		=	"text"
$htmlInputText
				/>
				<label class="ezuser-label ezuser-$stringRight" for="ezuser-$tagUsername">Username or email:</label>
			</fieldset>
			<fieldset class="ezuser-fieldset">
				<input id="$container-$actionCancel" data-ezuser-action="$actionControlPanel" value="Cancel"
					tabindex	=	"3243"
$htmlButtonPreference
				/>
				<input id="ezuser-$actionResetPassword" data-ezuser-action="$actionResetPassword" value="Reset password"
					tabindex	=	"3242"
$htmlButtonPreference
				/>
			</fieldset>
		</form>
HTML;

		$data = array($html, $container, self::htmlContainerStyle());
		if ($sendToBrowser) {self::sendXML($data); return array('', '', '');} else return $data;
	}

/**
 * Password reset form (& confirmation form below)
 *
 * This function is slightly different as it send the HTML for an entire
 * page, rather than the contents of a DIV. This is because this form
 * is displayed in response to the user clicking on a link in an email.
 * We have no context in which to display it and no knowledge of the
 * site that ezUser is living in, so we are forced to display a bare page.
 *
 * @param ezUser_base $ezUser
 * @param boolean $sendToBrowser
 */
	private static /*.string.*/ function htmlResetPassword (ezUser_base $ezUser, $sendToBrowser = false) {
		$action			= self::ACTION_RESET;
		$tagPassword		= self::TAGNAME_PASSWORD;
		$tagConfirm		= self::TAGNAME_CONFIRM;
		$container		= self::getInstanceId($action);
		$htmlInputText		= self::htmlInputText();
		$htmlButtonPreference	= self::htmlButton(self::BUTTON_TYPE_ACTION);
		$stringRight		= self::STRING_RIGHT;
		$fullName		= $ezUser->fullName();
		$message		= self::htmlMessage('', self::MESSAGE_STYLE_PLAIN, '', self::MESSAGE_TYPE_DIALOG);

		$html = <<<HTML
	<div id="ezuser">
		<h4 class="ezuser-heading">Welcome $fullName</h4>
		<p class="ezuser-message-plain">You should always check the address bar before entering your password on any web site. The fact that we know your name is $fullName should also reassure you this is the site for which you requested a password reset.</p>
		<p class="ezuser-message-plain">If you didn't ask for a password reset for this web site then you should close this browser window now.</p>
		<hr />
		<p class="ezuser-message-plain">Please enter a new password for your account:</p>
		<form id="$container-form" class="ezuser-form ezuser-inline" onsubmit="return false">
			<fieldset class="ezuser-fieldset">
				<input id= "$container-$tagPassword"
					tabindex	=	"3241"
					value		=	""
					type		=	"password"
					onfocus		=	"ezUser.passwordFocus(this)"
					onblur		=	"ezUser.passwordBlur(this)"
$htmlInputText
				/>
				<label class="ezuser-label ezuser-$stringRight" for="$container-$tagPassword">Password:</label>
				<input id= "$container-confirm"
					tabindex	=	"3242"
					value		=	""
					type		=	"password"
					onfocus		=	"ezUser.passwordFocus(this)"
					onblur		=	"ezUser.passwordBlur(this)"
$htmlInputText
				/>
				<label class="ezuser-label ezuser-$stringRight" for="$container-$tagConfirm">Confirm password:</label>
			</fieldset>
			<fieldset class="ezuser-fieldset">
				<input id="$container-OK" data-ezuser-action="$action" value="OK"
					tabindex	=	"3243"
$htmlButtonPreference
				/>
			</fieldset>
			<fieldset class="ezuser-fieldset">
$message
			</fieldset>
		</form>
	</div>
HTML;

		$html = self::htmlPage($html, 'Reset your password');

		if ($sendToBrowser) {self::sendContent($html); return '';} else return $html;
	}

	private static /*.string.*/ function htmlMessagePage (/*.string.*/ $title, /*.string.*/ $message, $sendToBrowser = false) {
		$message = self::htmlMessage($message, self::MESSAGE_STYLE_TEXT, '');

		$html = <<<HTML
	<div id="ezuser">
		<h4 class="ezuser-heading">$title</h4>
$message
	</div>
HTML;

		$html = self::htmlPage($html, $title);

		if ($sendToBrowser) {self::sendContent($html); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
	private static /*.array[int]string.*/ function htmlMessageForm (/*.string.*/ $message, /*.string.*/ $action, $sendToBrowser = false) {
		$container		= self::getInstanceId($action);
		$htmlDialogClose	= self::htmlDialogClose($container);
		$actionControlPanel	= self::ACTION_CONTROLPANEL;
		$htmlButtonFixedWidth	= self::htmlButton(self::BUTTON_TYPE_FIXEDWIDTH);
		$message		= self::htmlMessage($message, self::MESSAGE_STYLE_TEXT, '');

		$html = <<<HTML
		$htmlDialogClose
		<form id="$container-form" class="ezuser-form" onsubmit="return false">
			<fieldset class="ezuser-fieldset">
$message
				<input id="ezuser-OK" data-ezuser-action="$actionControlPanel" value="OK"
					tabindex	=	"3241"
$htmlButtonFixedWidth
				/>
			</fieldset>
		</form>
HTML;

		$data = array($html, $container, self::htmlContainerStyle());
		if ($sendToBrowser) {self::sendXML($data); return array('', '', '');} else return $data;
	}

	private static /*.array[int]string.*/ function htmlResultForm (/*.int.*/ $result, $more = '', $sendToBrowser = false) {
		$data = self::htmlMessageForm(self::resultText($result, $more), self::ACTION_RESULTFORM);
		if ($sendToBrowser) {self::sendXML($data); return array('', '', '');} else return $data;
	}

// ---------------------------------------------------------------------------
	private	static /*.string.*/ function htmlAboutText($sendToBrowser = false) {
		$php	= self::getFileContents('ezuser.php', 0, NULL, -1, 4096);
		$html	= self::docBlock_to_HTML($php);

		if ($sendToBrowser) {self::sendContent($html); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
	private	static /*.string.*/ function htmlAbout($sendToBrowser = false) {
		$html	= self::htmlPage(self::htmlAboutText(), 'ezUser - About');

		if ($sendToBrowser) {self::sendContent($html); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
	private	static /*.string.*/ function htmlSourceCode($sendToBrowser = false) {
		$html = (string) highlight_file(__FILE__, 1);
		if ($sendToBrowser) {self::sendContent($html); return '';} else return $html;
	}

/**
 * Other MIME types: CSS, Javascript, bitmaps
 *
 * @param boolean $sendToBrowser
 */
	private static /*.string.*/ function htmlStyleSheet($sendToBrowser = false) {
		$accountForm		= self::getInstanceId(self::ACTION_ACCOUNT);
		$signInForm		= self::getInstanceId(self::ACTION_SIGNIN);
		$resetForm		= self::getInstanceId(self::ACTION_RESET);
		$tagFullName		= self::TAGNAME_FULLNAME;
		$tagVerbose		= self::TAGNAME_VERBOSE;
		$buttonTypeAction	= self::BUTTON_TYPE_ACTION;
		$buttonTypeDefault	= self::BUTTON_TYPE_DEFAULT;
		$buttonTypePreference	= self::BUTTON_TYPE_PREFERENCE;
		$buttonTypeFixedWidth	= self::BUTTON_TYPE_FIXEDWIDTH;

		$css = <<<GENERATED
@charset "UTF-8";
/**
 * Enables user registration and authentication for a website
 * 
 * This code has three principle design goals:
 * 
 *     1. To make it easy for people to register and sign in to your site.
 *     2. To make it easy for you to add this functionality to your site.
 *     3. To make it easy for you to administer the user database on your site.
 * 
 * Other design goals, such as run-time efficiency, are important but secondary to
 * these.
 * 
 * Copyright (c) 2008-2010, Dominic Sayers							<br>
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 * 
 *     - Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *     - Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *     - Neither the name of Dominic Sayers nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 * 
 * @package	ezUser
 * @author	Dominic Sayers <dominic@sayers.cc>
 * @copyright	2008-2010 Dominic Sayers
 * @license	http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link	http://code.google.com/p/ezuser/
 * @version	0.27.5 - PHPLint is even tighter, so some code style changes were necessary
 */

.dummy {} /* Webkit is ignoring the first item so we'll put a dummy one in */

.ezuser {
	margin:0;
	padding:0;
	font-family:"Segoe UI",Geneva,Tahoma,Arial,Helvetica,sans-serif;
	font-size:11px;
	line-height:150%;
}

pre.ezuser		{font-family:Consolas, Courier New, Courier, fixedsys;}
.ezuser-body		{margin:4em;width:300px;}
.ezuser-body .ezuser-heading
			{padding:0;font-size:16px;}
.ezuser-body .ezuser-fieldset
			{float:left;width:280px;}
.ezuser-left		{float:left;}
.ezuser-right		{float:right;}
.ezuser-mandatory	{color:#000000;}
.ezuser-optional	{color:#444444;}
.ezuser-heading	{padding:6px;margin:0 0 1em;}
.ezuser-dialog-control	{cursor:pointer;}

img.ezuser-dialog-close {
	width:40px;
	height:40px;
	position:absolute;
	left:319px;
	top:-25px;
}

div.ezuser-dialog {
	text-align:left;
	font-family:"Segoe UI",Geneva,Tahoma,Arial,Helvetica,sans-serif;
	font-size:11px;
	padding:1.5em;
	border:1em solid #CCCCCC;
	border-radius:2em;
	-icab-border-radius:2em;	/* iCab */
	-khtml-border-radius:2em;	/* Konqueror */
	-moz-border-radius:2em;		/* Firefox */
	-o-border-radius:2em;		/* Opera */
	-webkit-border-radius:2em;	/* Chrome, Safari */
	box-shadow:0.4em 0.4em 1.8em #555555;
	-icab-box-shadow:0.4em 0.4em 1.8em #555555;	/* iCab */
	-khtml-box-shadow:0.4em 0.4em 1.8em #555555;	/* Konqueror */
	-moz-box-shadow:0.4em 0.4em 1.8em #555555;	/* Firefox */
	-o-box-shadow:0.4em 0.4em 1.8em #555555;	/* Opera */
	-webkit-box-shadow:0.4em 0.4em 1.8em #555555;	/* Chrome, Safari */
	background-color:#EEEEEE;
}

div#$signInForm		{width:300px;height:110px;}
div#$accountForm	{width:300px;height:170px;font-size:12px;}
div#$accountForm-dialog	{width:304px;height:64px;position:absolute;top:222px;left:-1em;}
div#$resetForm		{width:300px;height:60px;}

div.ezuser-message {
	float:left;
	margin-top:4px;
	text-align:center;
	font-weight:normal;
}

div.ezuser-message-plain	{border-color:#CCCCCC;}
div.ezuser-message-info		{border-color:#00FFFF;}
div.ezuser-message-text		{text-align:left;}
div.ezuser-message-fail		{border-color:#FF0000;font-weight:bold;}

div.ezuser-$tagFullName {
	float:right;
	margin:2px 0 0;
	padding:6px;
	color:#555555;
	font-weight:bold;
	cursor:pointer;
}

a.ezuser-keydragdrop:link {
	position:absolute;
	height:16px;
	width:16px;
	top:114px;
	left:7px;
	cursor:move;
	font-size:16px;
	text-decoration:none;
	color:#AAAAAA;
	display:none; /* Remove this line to enable keyboard drag-and-drop for accessibility */
}

form.ezuser-form			{margin:0;}
fieldset.ezuser-fieldset		{margin:0 0 0.5em;padding:0;border:0;clear:both;float:right;width:300px;}
fieldset.ezuser-fieldset-dialog	{margin:0;padding:0;border:0;clear:both;float:right;}
label.ezuser-label			{padding:4px;}

input.ezuser-text {
	font-size:11px;
	width:160px;
	margin-bottom:4px;
}

input.ezuser-button {
	font-family:"Segoe UI",Geneva,Tahoma,Arial,Helvetica,sans-serif;
	border-radius:5px;
	-icab-border-radius:5px;	/* iCab */
	-khtml-border-radius:5px;	/* Konqueror */
	-moz-border-radius:5px;	/* Firefox */
	-o-border-radius:5px;		/* Opera */
	-webkit-border-radius:5px;	/* Chrome, Safari */
}

input.ezuser-fixedwidth {width:40px;}

input.ezuser-$buttonTypeAction,
input.ezuser-$buttonTypeDefault {
	padding:2px 2px 3px;
	font-size:12px;
	width:52px;
	margin:0 0 0 6px;
}

input.ezuser-$buttonTypeDefault {
	font-weight:bold;
}

input.ezuser-$buttonTypePreference {
	padding:3px 3px 4px;
	font-size:10px;
	margin:4px 1px 1px 6px;
}

input.ezuser-$buttonTypeFixedWidth {
	width:50px;
	padding:3px 3px 4px;
	font-size:10px;
	margin:4px 1px 1px 2px;
}

input.ezuser-preference-$tagVerbose {float:left;margin:0;}

form.ezuser-form input[type="submit"]::-moz-focus-inner, input[type="button"]::-moz-focus-inner	{border:none;}
form.ezuser-form input[type="submit"]:focus, input[type="button"]:focus	{outline:none;}

/*
State settings are derived from four binary states
Each state is mapped to a bit in the state number
i.e. the state number is the sum of whichever of the
following states is ON:

Mouse is over button		1
Button has focus		2
Toggle button has been selected	4
Button is disabled		8
Default				16

If none of these is true then clearly the state is 0
If, for example, the button is toggled ON and the mouse
is over it then the state is 6

We'll assume the default button isn't going to be disabled
or toggleable so we don't need to style for those cases
*/
input.ezuser-state-0	{background:#888888;color:#FFFFFF;border:1px solid #FFFFFF;cursor:pointer;}
input.ezuser-state-1	{background:#888888;color:#FFFFFF;border:1px solid #FF8000;cursor:pointer;}
input.ezuser-state-2	{background:#888888;color:#FFFFFF;border:1px solid #000000;cursor:pointer;}
input.ezuser-state-3	{background:#888888;color:#FFFFFF;border:1px solid #FF8000;cursor:pointer;}

input.ezuser-state-4	{background:#E8E8E8;color:#686868;border:1px solid #A8A8A8;cursor:pointer;}
input.ezuser-state-5	{background:#E8E8E8;color:#686868;border:1px solid #FF8000;cursor:pointer;}
input.ezuser-state-6	{background:#E8E8E8;color:#686868;border:1px solid #000000;cursor:pointer;}
input.ezuser-state-7	{background:#E8E8E8;color:#686868;border:1px solid #FF8000;cursor:pointer;}

input.ezuser-state-8	{background:#C8C8C8;color:#FFFFFF;border:1px solid #FFFFFF;cursor:default;}
input.ezuser-state-9	{background:#C8C8C8;color:#FFFFFF;border:1px solid #FFFFFF;cursor:default;}
input.ezuser-state-10	{background:#C8C8C8;color:#FFFFFF;border:1px solid #A8A8A8;cursor:default;}
input.ezuser-state-11	{background:#C8C8C8;color:#FFFFFF;border:1px solid #FFFFFF;cursor:default;}

input.ezuser-state-12	{background:#E8E8E8;color:#C8C8C8;border:1px solid #FFFFFF;cursor:default;}
input.ezuser-state-13	{background:#E8E8E8;color:#C8C8C8;border:1px solid #FFFFFF;cursor:default;}
input.ezuser-state-14	{background:#E8E8E8;color:#C8C8C8;border:1px solid #C8C8C8;cursor:default;}
input.ezuser-state-15	{background:#E8E8E8;color:#C8C8C8;border:1px solid #FFFFFF;cursor:default;}

input.ezuser-state-16	{background:#686868;color:#FFFF00;border:1px solid #FFFFFF;cursor:pointer;}
input.ezuser-state-17	{background:#686868;color:#FFFF00;border:1px solid #FF8000;cursor:pointer;}
input.ezuser-state-18	{background:#686868;color:#FFFF00;border:1px solid #000000;cursor:pointer;}
input.ezuser-state-19	{background:#686868;color:#FFFF00;border:1px solid #FF8000;cursor:pointer;}

GENERATED;
// Generated code - do not modify in built package

		if ($sendToBrowser) {self::sendContent($css, '', 'text/css'); return '';} else return $css;
	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlJavascript($containerList = '', $sendToBrowser = false) {
		$sessionName		= ini_get('session.name');
		$remoteAddress		= $_SERVER['REMOTE_ADDR'];
		$URL			= self::thisURL();
		$folder			= dirname($URL);

		$cookieUsername		= self::COOKIE_USERNAME;
		$cookiePassword		= self::COOKIE_PASSWORD;
		$cookieStaySignedIn	= self::COOKIE_AUTOSIGN;

		$tagFirstName		= self::TAGNAME_FIRSTNAME;
		$tagLastName		= self::TAGNAME_LASTNAME;
		$tagEmail		= self::TAGNAME_EMAIL;
		$tagUsername		= self::TAGNAME_USERNAME;
		$tagPassword		= self::TAGNAME_PASSWORD;
		$tagConfirm		= self::TAGNAME_CONFIRM;
		$tagNewUser		= self::TAGNAME_NEWUSER;
		$tagRememberMe		= self::TAGNAME_REMEMBERME;
		$tagStaySignedIn	= self::TAGNAME_STAYSIGNEDIN;
		$tagUseSavedPassword	= self::TAGNAME_SAVEDPASSWORD;
		$tagVerbose		= self::TAGNAME_VERBOSE;
		$tagWizard		= self::TAGNAME_WIZARD;

		$action			= self::ACTION;
		$actionAccount		= self::ACTION_ACCOUNT;
		$actionAccountForm	= self::ACTION_ACCOUNTFORM;
//		$actionAccountWizard	= self::ACTION_ACCOUNTWIZARD;
		$actionControlPanel	= self::ACTION_CONTROLPANEL;
		$actionValidate		= self::ACTION_VALIDATE;
		$actionSignIn		= self::ACTION_SIGNIN;
		$actionCancel		= self::ACTION_CANCEL;
		$actionCSS		= self::ACTION_STYLESHEET;
		$actionResultForm	= self::ACTION_RESULTFORM;
		$actionResend		= self::ACTION_RESEND;
		$actionReset		= self::ACTION_RESET;
		$actionResetPassword	= self::ACTION_RESETPASSWORD;
		$actionResetRequest	= self::ACTION_RESETREQUEST;

		$accountForm		= self::getInstanceId($actionAccount);
		$signInForm		= self::getInstanceId($actionSignIn);
		$controlPanelForm	= self::getInstanceId($actionControlPanel);
		$resetForm		= self::getInstanceId($actionReset);

		$modeEdit		= self::ACCOUNT_MODE_EDIT;

		$messageTypeDialog	= self::MESSAGE_TYPE_DIALOG;
		$messageTypeDefault	= self::MESSAGE_TYPE_DEFAULT;
		$containerStyleDialog	= self::CONTAINER_STYLE_DIALOG;
		$delimPlus		= self::DELIMITER_PLUS;
		$stringRight		= self::STRING_RIGHT;
		$stringTrue		= self::STRING_TRUE;
		$stringFalse		= self::STRING_FALSE;
		$passwordMask		= self::PASSWORD_MASK;

		$accountPage		= self::getSetting(self::SETTINGS_ACCOUNTPAGE);
		$accountClick		= ($accountPage === '') ? "ezUser.action('$actionAccount')" : "window.location = '$folder/$accountPage'";

		// Append code to request container content
		if ($containerList === '') {
			$immediateJavascript = '';
		} else {
			// Space-separated list of containers to fill
			$immediateJavascript = "ezUser.action('" . (string) str_replace(self::DELIMITER_SPACE, self::DELIMITER_PLUS, $containerList) . "');";
		}

		$js = <<<GENERATED
/**
 * Enables user registration and authentication for a website
 * 
 * This code has three principle design goals:
 * 
 *     1. To make it easy for people to register and sign in to your site.
 *     2. To make it easy for you to add this functionality to your site.
 *     3. To make it easy for you to administer the user database on your site.
 * 
 * Other design goals, such as run-time efficiency, are important but secondary to
 * these.
 * 
 * Copyright (c) 2008-2010, Dominic Sayers							<br>
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 * 
 *     - Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *     - Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *     - Neither the name of Dominic Sayers nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 * 
 * @package	ezUser
 * @author	Dominic Sayers <dominic@sayers.cc>
 * @copyright	2008-2010 Dominic Sayers
 * @license	http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link	http://code.google.com/p/ezuser/
 * @version	0.27.5 - PHPLint is even tighter, so some code style changes were necessary
 */

/*jslint eqeqeq: true, immed: true, nomen: true, onevar: true, regexp: true, undef: true */
/*global window, document, event, ActiveXObject */ // For JSLint
//"use strict";

/**
 * All ezUser functionality is held in this class to avoid namespace clashes
 */
function C_ezUser() {
	if (!(this instanceof arguments.callee)) {throw Error('Constructor called as a function');}

// Generated code - do not modify in built package
/**
*
*  Secure Hash Algorithm (SHA256)
*  http://www.webtoolkit.info/
*
*  Original code by Angel Marin, Paul Johnston.
*
**/

function SHA256(s){

	var chrsz   = 8, hexcase = 0;

	function safe_add (x, y) {
		var lsw = (x & 0xFFFF) + (y & 0xFFFF),
		msw = (x >> 16) + (y >> 16) + (lsw >> 16);
		return (msw << 16) | (lsw & 0xFFFF);
	}

	function S (X, n) { return ( X >>> n ) | (X << (32 - n)); }
	function R (X, n) { return ( X >>> n ); }
	function Ch(x, y, z) { return ((x & y) ^ ((~x) & z)); }
	function Maj(x, y, z) { return ((x & y) ^ (x & z) ^ (y & z)); }
	function Sigma0256(x) { return (S(x, 2) ^ S(x, 13) ^ S(x, 22)); }
	function Sigma1256(x) { return (S(x, 6) ^ S(x, 11) ^ S(x, 25)); }
	function Gamma0256(x) { return (S(x, 7) ^ S(x, 18) ^ R(x, 3)); }
	function Gamma1256(x) { return (S(x, 17) ^ S(x, 19) ^ R(x, 10)); }

	function core_sha256 (m, l) {
		var K = [0x428A2F98, 0x71374491, 0xB5C0FBCF, 0xE9B5DBA5, 0x3956C25B, 0x59F111F1, 0x923F82A4, 0xAB1C5ED5, 0xD807AA98, 0x12835B01, 0x243185BE, 0x550C7DC3, 0x72BE5D74, 0x80DEB1FE, 0x9BDC06A7, 0xC19BF174, 0xE49B69C1, 0xEFBE4786, 0xFC19DC6, 0x240CA1CC, 0x2DE92C6F, 0x4A7484AA, 0x5CB0A9DC, 0x76F988DA, 0x983E5152, 0xA831C66D, 0xB00327C8, 0xBF597FC7, 0xC6E00BF3, 0xD5A79147, 0x6CA6351, 0x14292967, 0x27B70A85, 0x2E1B2138, 0x4D2C6DFC, 0x53380D13, 0x650A7354, 0x766A0ABB, 0x81C2C92E, 0x92722C85, 0xA2BFE8A1, 0xA81A664B, 0xC24B8B70, 0xC76C51A3, 0xD192E819, 0xD6990624, 0xF40E3585, 0x106AA070, 0x19A4C116, 0x1E376C08, 0x2748774C, 0x34B0BCB5, 0x391C0CB3, 0x4ED8AA4A, 0x5B9CCA4F, 0x682E6FF3, 0x748F82EE, 0x78A5636F, 0x84C87814, 0x8CC70208, 0x90BEFFFA, 0xA4506CEB, 0xBEF9A3F7, 0xC67178F2],
		HASH = [0x6A09E667, 0xBB67AE85, 0x3C6EF372, 0xA54FF53A, 0x510E527F, 0x9B05688C, 0x1F83D9AB, 0x5BE0CD19],
		W = new Array(64),
		a, b, c, d, e, f, g, h, i, j,
		T1, T2;

		m[l >> 5] |= 0x80 << (24 - l % 32);
		m[((l + 64 >> 9) << 4) + 15] = l;

		for ( var i = 0; i<m.length; i+=16 ) {
			a = HASH[0];
			b = HASH[1];
			c = HASH[2];
			d = HASH[3];
			e = HASH[4];
			f = HASH[5];
			g = HASH[6];
			h = HASH[7];

			for ( var j = 0; j<64; j++) {
				if (j < 16) W[j] = m[j + i];
				else W[j] = safe_add(safe_add(safe_add(Gamma1256(W[j - 2]), W[j - 7]), Gamma0256(W[j - 15])), W[j - 16]);

				T1 = safe_add(safe_add(safe_add(safe_add(h, Sigma1256(e)), Ch(e, f, g)), K[j]), W[j]);
				T2 = safe_add(Sigma0256(a), Maj(a, b, c));

				h = g;
				g = f;
				f = e;
				e = safe_add(d, T1);
				d = c;
				c = b;
				b = a;
				a = safe_add(T1, T2);
			}

			HASH[0] = safe_add(a, HASH[0]);
			HASH[1] = safe_add(b, HASH[1]);
			HASH[2] = safe_add(c, HASH[2]);
			HASH[3] = safe_add(d, HASH[3]);
			HASH[4] = safe_add(e, HASH[4]);
			HASH[5] = safe_add(f, HASH[5]);
			HASH[6] = safe_add(g, HASH[6]);
			HASH[7] = safe_add(h, HASH[7]);
		}
		return HASH;
	}

	function str2binb (str) {
		var bin = Array();
		var mask = (1 << chrsz) - 1;
		for(var i = 0; i < str.length * chrsz; i += chrsz) {
			bin[i>>5] |= (str.charCodeAt(i / chrsz) & mask) << (24 - i%32);
		}
		return bin;
	}

	function Utf8Encode(string) {
		string = string.replace(/\\r\\n/g,"\\n");
		var utftext = "";

		for (var n = 0; n < string.length; n++) {

			var c = string.charCodeAt(n);

			if (c < 128) {
				utftext += String.fromCharCode(c);
			}
			else if((c > 127) && (c < 2048)) {
				utftext += String.fromCharCode((c >> 6) | 192);
				utftext += String.fromCharCode((c & 63) | 128);
			}
			else {
				utftext += String.fromCharCode((c >> 12) | 224);
				utftext += String.fromCharCode(((c >> 6) & 63) | 128);
				utftext += String.fromCharCode((c & 63) | 128);
			}

		}

		return utftext;
	}

	function binb2hex (binarray) {
		var hex_tab = hexcase ? "0123456789ABCDEF" : "0123456789abcdef";
		var str = "";
		for(var i = 0; i < binarray.length * 4; i++) {
			str += hex_tab.charAt((binarray[i>>2] >> ((3 - i%4)*8+4)) & 0xF) +
			hex_tab.charAt((binarray[i>>2] >> ((3 - i%4)*8  )) & 0xF);
		}
		return str;
	}

	s = Utf8Encode(s);
	return binb2hex(core_sha256(str2binb(s), s.length * chrsz));

}
// End of generated code

	var that = this;

// ---------------------------------------------------------------------------
	this.control = function(id_or_control) {
		// control private members
		var id, control, nodeList;

		// Identify the control
		if (typeof id_or_control === 'string') {
			// Look for element with this id
			id	= id_or_control;
			control	= document.getElementById(id);

			if (control === null || typeof control === 'undefined') {
				// ...otherwise look for element with this name
				nodeList = document.getElementsByName(id);

				if (nodeList.length === 0) {
					// ...final resort is to look for elements with this tag (e.g. 'body')
					nodeList = document.getElementsByTagName(id);
					if (nodeList.length === 0) {
						control = {};
					} else {
						control = nodeList[0];
					}
				} else {
					control = nodeList[0]
				}
			}
		} else {
			id	= id_or_control.id;
			control	= id_or_control;
		}

		function windowRect() {
			var	width	= 0,
				height	= 0;

			if (typeof window.innerWidth === 'number') {
				width	= window.innerWidth;
				height	= window.innerHeight;
			} else if (document.documentElement && document.documentElement.clientWidth) {
				width	= document.documentElement.clientWidth;
				height	= document.documentElement.clientHeight;
			} else if (document.body && document.body.clientWidth) {
				width	= document.body.clientWidth;
				height	= document.body.clientHeight;
			}

			return {width: width, height: height};
		}

		function controlRect() {return {width: control.offsetWidth, height: control.offsetHeight};}

		// control "public" interface (only public within ezUser class)
		// Extends underlying control's public members
		control.hide	= function()		{if (control.style)				{control.style.visibility = 'hidden';}	return control;}
		control.show	= function()		{if (control.style)				{control.style.visibility = '';}	return control;}
		control.fill	= function(html)	{if (typeof control.innerHTML === 'string')	{control.innerHTML = html;}		return control;}
		control.append	= function(html)	{if (typeof control.innerHTML === 'string')	{control.innerHTML += html;}		return control;}

		if (!control.forms) control.forms = function() {return control.getElementsByTagName('form');}

		control.classNames = {
			add: function(name) {
				if (typeof name !== 'string') {return control;}

				var	classString	= control.className.toLowerCase(),
					classArray	= classString.split(' '),
					i		= classArray.indexOf(name.toLowerCase());

				if (i === -1) {control.className = (control.className) ? control.className + ' ' + name : name;}
				return control;	// NB could get repeated class names if name is passed as 'a b' and control already has class a or b
			},

			remove: function(name) {
				if (typeof name !== 'string') {return control;}

				var	classString	= control.className.toLowerCase(),
					classArray	= classString.split(' '),
					i		= classArray.indexOf(name.toLowerCase());

				if (i !== -1) {
					classArray = control.className.split(' ');
					classArray.splice(i, 1);
					control.className = classArray.join(' ');
				}

				return control;
			}
		}

		control.event = {
			add: function (eventName, functionHandle) {
				if	(control.addEventListener)	{control.addEventListener(eventName, functionHandle, false);}
				else if	(control.attachEvent)		{control.attachEvent('on' + eventName, functionHandle);}
			},

			remove: function (eventName, functionHandle) {
				if	(control.removeEventListener)	{control.removeEventListener(eventName, functionHandle, false);}
				else if	(control.detachEvent)		{control.detachEvent('on' + eventName, functionHandle);}
			},

			fire: function(eventType, detail) {
				var e, result; // Returned result from dispatchEvent

				switch (eventType.toLowerCase()) {
				case 'keyup':
				case 'keydown':
					if (document.createEventObject) {
						// IE
						e		= document.createEventObject();
						e.keyCode	= detail;
						result		= control.fireEvent('on' + eventType);
					} else if (window.KeyEvent) {
						// Firefox
						e		= document.createEvent('KeyEvents');
						e.initKeyEvent(eventType, true, true, window, false, false, false, false, detail, 0);
						result		= control.dispatchEvent(e);
					} else {
						e		= document.createEvent('UIEvents');
						e.initUIEvent(eventType, true, true, window, 1);
						e.keyCode	= detail;
						result		= control.dispatchEvent(e);
					}

					break;
				case 'focus':
				case 'blur':
				case 'change':
					if (document.createEventObject) {
						// IE
						e		= document.createEventObject();
						result		= control.fireEvent('on' + eventType);
					} else {
						e		= document.createEvent('UIEvents');
						e.initUIEvent(eventType, true, true, window, 1);
						result		= control.dispatchEvent(e);
					}

					break;
				case 'click':
					if (document.createEventObject) {
						// IE
						e		= document.createEventObject();
						result		= control.fireEvent('on' + eventType);
					} else {
						e		= document.createEvent('MouseEvents');
						e.initMouseEvent(eventType, true, true, window, 1);
						result		= control.dispatchEvent(e);
					}

					break;
				}

				return result;
			}
		}

		control.setFocus = function() {
			var doEvent;

			if (control.disabled || !control.focus) {return control;}

			if (typeof document.activeElement.onBlur === 'function') {that.control(document.activeElement).event.fire('blur');}
			if (typeof document.activeElement.onblur === 'function') {that.control(document.activeElement).event.fire('blur');}
			if (typeof control.onFocus === 'function') {doEvent = that.control(document.activeElement).event.fire('focus');}
			if (typeof control.onfocus === 'function') {doEvent = that.control(document.activeElement).event.fire('focus');}
			if (doEvent !== false) {control.focus();}
			control.select();
			return control;
			}

		control.setState = function (eventID, setOn) {
			// eventID	1 = mouseover/mouseout
			//		2 = focus/blur
			//		4 = selected/unselected
			//		8 = disabled/enabled
			//		16 = default/not default

			if (control === null) {return false;}

			var	baseClass	= control.className,
				stateClass	= 'ezuser-state-',
				pos		= baseClass.indexOf(stateClass),
				currentState	= /(?:ezuser-state-)([0-9]{1,2})/.exec(baseClass)[1];

			currentState		= (setOn) ? currentState | eventID : currentState & ~eventID;
			baseClass		= (pos === -1) ? baseClass + ' ' : baseClass.substring(0, pos);
			control.className	= baseClass + stateClass + String(currentState);
			return true;
		}

		control.position = function(left, top) {
			if (typeof top === 'undefined' || typeof left === 'undefined') {
				var	dialogSize			= controlRect(),
					windowSize			= windowRect(),
					goldenSectionCenter	= 2 * windowSize.height / (3 + Math.sqrt(5)),
					dialogCenter		= dialogSize.height / 2;

				if (typeof top	=== 'undefined')	{top	= (goldenSectionCenter	> dialogCenter)		? (goldenSectionCenter		- dialogCenter)			+ 'px' : 0;}
				if (typeof left	=== 'undefined')	{left	= (windowSize.width	> dialogSize.width)	? ((windowSize.width / 2)	- (dialogSize.width / 2))	+ 'px' : 0;}
			}

			control.style.position	= 'absolute';
			control.style.left	= left;
			control.style.top	= top;
			return control;
		}

		control.create = function(tag) {
			if (control === null || typeof control === 'undefined' || !control.id) {
				control		= document.createElement(tag);
				control.id	= id;

				document.getElementsByTagName('body')[0].appendChild(control);
				control = that.control(control);
				control.hide();
			}

			return control;
		}

		return control;
	};

// ---------------------------------------------------------------------------
	this.dragDrop = {
		keyHTML:	'<a href="#" class="ezuser-keydragdrop" tabindex="3300">&#9000;</a>',
		keySpeed:	10, // pixels per keypress event
		initialMouseX:	undefined,
		initialMouseY:	undefined,
		startX:		undefined,
		startY:		undefined,
		dXKeys:		undefined,
		dYKeys:		undefined,
		draggedObject:	undefined,

		initElement: function (div) {
			if (typeof div === 'string') {div = that.control(div);}

			div.onmousedown	= that.dragDrop.startDragMouse;
			div.innerHTML	+= that.dragDrop.keyHTML;

			var	links		= div.getElementsByTagName('a'),
				lastLink	= links[links.length-1];

			lastLink.relatedElement	= div;
			lastLink.onclick	= that.dragDrop.startDragKeys;
		},

		startDragMouse: function (e) {
			var	thisEvent		= e || window.event,
				clickTarget;

			if (e.target)			{clickTarget = e.target;}
			else				{clickTarget = e.srcElement;}
			if (clickTarget.nodeType === 3)	{clickTarget = clickTarget.parentNode;}

			if (clickTarget.nodeName.toLowerCase() === 'input') {return true;} // Don't drag if input control was clicked

			that.dragDrop.startDrag(this);

			that.dragDrop.initialMouseX	= thisEvent.clientX;
			that.dragDrop.initialMouseY	= thisEvent.clientY;

			that.control(document).event.add('mousemove', that.dragDrop.dragMouse);
			that.control(document).event.add('mouseup', that.dragDrop.releaseElement);
			return true;
		},

		startDragKeys: function () {
			that.dragDrop.startDrag(this.relatedElement);

			that.dragDrop.dXKeys		= that.dragDrop.dYKeys = 0;

			that.control(document).event.add('keydown', that.dragDrop.dragKeys);
			that.control(document).event.add('keypress', that.dragDrop.switchKeyEvents);

			this.blur();
			return false;
		},

		startDrag: function (div) {
			if (that.dragDrop.draggedObject) {that.dragDrop.releaseElement();}

			that.dragDrop.startX		= div.offsetLeft;
			that.dragDrop.startY		= div.offsetTop;
			that.dragDrop.draggedObject	= div;
			that.control(div).classNames.add('ezuser-dragged');
			div.style.cursor		= 'move';
		},

		dragMouse: function (e) {
			var	thisEvent	= e || window.event,
				dX		= thisEvent.clientX - that.dragDrop.initialMouseX,
				dY		= thisEvent.clientY - that.dragDrop.initialMouseY;

			that.dragDrop.setPosition(dX, dY);
			return false;
		},

		dragKeys: function(e) {
			var	thisEvent	= e || window.event,
				key		= thisEvent.keyCode;

			switch (key) {
			case 37:	// left
			case 63234:
				that.dragDrop.dXKeys -= that.dragDrop.keySpeed;
				break;
			case 38:	// up
			case 63232:
				that.dragDrop.dYKeys -= that.dragDrop.keySpeed;
				break;
			case 39:	// right
			case 63235:
				that.dragDrop.dXKeys += that.dragDrop.keySpeed;
				break;
			case 40:	// down
			case 63233:
				that.dragDrop.dYKeys += that.dragDrop.keySpeed;
				break;
			case 13:	// enter
			case 27:	// escape
				that.dragDrop.releaseElement();
				return false;
			default:
				return true;
			}

			that.dragDrop.setPosition(that.dragDrop.dXKeys, that.dragDrop.dYKeys);

			if (thisEvent.preventDefault) {thisEvent.preventDefault();}
			return false;
		},

		setPosition: function (dx, dy) {
			that.dragDrop.draggedObject.style.left		= that.dragDrop.startX + dx + 'px';
			that.dragDrop.draggedObject.style.top		= that.dragDrop.startY + dy + 'px';
		},

		switchKeyEvents: function () {
			// for Opera and Safari 1.3
			that.control(document).event.remove('keydown',	that.dragDrop.dragKeys);
			that.control(document).event.remove('keypress',	that.dragDrop.switchKeyEvents);
			that.control(document).event.add('keypress',		that.dragDrop.dragKeys);
		},
		releaseElement: function() {
			that.control(document).event.remove('mousemove',	that.dragDrop.dragMouse);
			that.control(document).event.remove('mouseup',	that.dragDrop.releaseElement);
			that.control(document).event.remove('keypress',	that.dragDrop.dragKeys);
			that.control(document).event.remove('keypress',	that.dragDrop.switchKeyEvents);
			that.control(document).event.remove('keydown',	that.dragDrop.dragKeys);

			that.control(that.dragDrop.draggedObject).classNames.remove('ezuser-dragged');
			that.dragDrop.draggedObject.style.cursor	= 'auto';
			that.dragDrop.draggedObject			= null;
		}
	};


// ---------------------------------------------------------------------------
// Public properties
	this.passwordSaved		= '';
	this.passwordDefault_SignIn	= false;
	this.passwordDefault_Account	= false;
	this.usernameDefault_Account	= false;

// ---------------------------------------------------------------------------
	addStyleSheet = function () {
		var	htmlHead	= document.getElementsByTagName('head')[0],
			nodeList	= htmlHead.getElementsByTagName('link'),
			elementCount	= nodeList.length,
			found		= false,
			i, node;

		for (i = 0; i < elementCount; i++) {
			if (nodeList[i].title === 'ezUser') {
				found = true;
				break;
			}
		}

		if (!found) {
			// Add style sheet
			node		= document.createElement('link');
			node.type	= 'text/css';
			node.rel	= 'stylesheet';
			node.href	= '$URL?$actionCSS';
			node.title	= 'ezUser';
			htmlHead.appendChild(node);
		}
	};

// ---------------------------------------------------------------------------
	setInitialFocus = function (id) {
		// Set focus to the first text control
		var textId = '', textBox = null;

		switch (id) {
		case 'ezuser':
			textId = 'ezuser-$tagUsername';
			break;
		case '$accountForm':
			textId = '$accountForm-$tagEmail';
			break;
		}

		if (textId !== '') {textBox = that.control(textId);}
		if (textBox === null || typeof textBox === 'undefined' || textBox.disabled === 'disabled') {return;}
		textBox.setFocus();
	}

// ---------------------------------------------------------------------------
	updateMessage = function (message, fail, messageType, instance) {
		if (arguments.length < 1) {message	= '';}
		if (arguments.length < 2) {fail		= false;}
		if (arguments.length < 3) {messageType	= 'message';}
		if (arguments.length < 4) {instance	= 'ezuser';}

		var	id		= instance + '-' + messageType,
			div		= that.control(id),
			messageStyle	= (fail) ? 'fail' : 'info',
			classString	= 'ezuser-' + messageType + ' ezuser-$stringRight ezuser-message-' + messageStyle,
			p;

		if (!div.id)			{return;} // No such control under our management
		if (div.hasChildNodes())	{div.removeChild(div.firstChild);}

		if (message === '') {
			div.hide();
		} else {
			p		= document.createElement('p');
			p.className	= 'ezuser';
			p.innerHTML	= message;
			div.className	= classString;

			div.appendChild(p);
			div.show();
		}

		div = that.control('ezuser-$tagVerbose');
		if (div.parentNode) {div.parentNode.removeChild(div);}
	};

// ---------------------------------------------------------------------------
	fillContainerText = function (id, html, className) {
		var container, containerList, formList, formId;

		container = that.control(id).create('div');
		container.classNames.add(id);
		container.classNames.add(className);
		container.fill(html);

		if (typeof className === 'string' && className.indexOf('ezuser-$containerStyleDialog') !== -1) {
			// It's a dialog
			container.position();
			that.dragDrop.initElement(container);
//?			that.control(window).event.add('resize', that.control(id).position);
		}

		formList	= container.forms();
		formId		= ((typeof formList === 'undefined') || (formList.length === 0)) ? '' : formList[0].getAttribute('id');

		switch (formId) {
		case '$controlPanelForm-form':
			// If it's the control panel, hide all the floating dialogs
			that.control('$signInForm').hide();
			that.control('$resetForm').hide();
			that.control('$accountForm').hide();

			break;
		case '$signInForm-form':
			that.control('$accountForm').hide();
			that.control('$resetForm').hide();
			cookies.showPreferences();

			if (cookies.rememberMe) {
				this.passwordDefault_SignIn = true;
				that.control('ezuser-$tagUsername').value	= cookies.username;
				that.control('ezuser-$tagPassword').value	= '$passwordMask';
			}

			break;
		case '$accountForm-form':
			that.control('$resetForm').hide();
			that.control('$signInForm').hide();
			wizard.initialize(); // Set wizard to page 1
			that.usernameDefault_Account = (that.control('$accountForm-$tagUsername').value === '');
			that.passwordDefault_Account = (that.control('$accountForm-$tagNewUser').value !== '$stringTrue');

			if (that.control('$accountForm-$tagUseSavedPassword').value === '$stringTrue') {
				that.control('$accountForm-$tagPassword').value	= that.passwordSaved;
				that.control('$accountForm-$tagConfirm').value	= that.passwordSaved;
			} else {
				that.passwordSaved = '';
			}

			break;
		case '$resetForm-form':
			that.control('$signInForm').hide();
			that.control('$accountForm').hide();
		}

		that.control(container).show();
		setInitialFocus(id);
	};

// ---------------------------------------------------------------------------
	fillContainersXML = function (xml) {
		var nodeList, nodeCount, i, node, parent, id, className, html;

		nodeList	= xml.childNodes;
		nodeCount	= nodeList.length;

		for (i = 0; i < nodeCount; i++) {
			node = nodeList[i];

			switch (node.nodeType) {
			case 1: // Node.ELEMENT_NODE: // recurse
				fillContainersXML(node);
				break;
			case 4: // Node.CDATA_SECTION_NODE: // fill the container
				parent		= node.parentNode;
				id		= parent.getAttribute('id');
				className	= parent.getAttribute('class');
				html		= node.nodeValue;

				fillContainerText(id, html, className);
				break;
			case 3: // Node.TEXT_NODE:
			case 7: // Node.PROCESSING_INSTRUCTION_NODE: // Usually caused by PHP passing an error message along with the XHR content
			case 8: // Node.COMMENT_NODE
				break; // Ignore
			default:
				window.alert('I wasn\\'t expecting a node type of ' + node.nodeType);
				break;
			}
		}
	};

// ---------------------------------------------------------------------------
	removeIllegalCharacters = function (restrictedString) {
		var	regexString	= '[^0-9A-Za-z_-]',
			regex		= new RegExp(regexString, 'g');

		return restrictedString.replace(regex, '');
	};

// ---------------------------------------------------------------------------
	normalizeUsername = function (username) {
		username		= removeIllegalCharacters(username);

		var textBox		= that.control('$accountForm-$tagUsername');
		textBox.defaultValue	= username;
		textBox.value		= username;
	};

// ---------------------------------------------------------------------------
	localValidation = function (formId) {
		var	textBox,
			textEmail,
			textUsername,
			textPassword,
			textConfirm,
			textNew,
			instance,
			message		= '';

		switch (formId) {
		case '$accountForm-form':
			textEmail	= that.control('$accountForm-$tagEmail');
			textUsername	= that.control('$accountForm-$tagUsername');
			textPassword	= that.control('$accountForm-$tagPassword');
			textConfirm	= that.control('$accountForm-$tagConfirm');
			textNew		= that.control('$accountForm-$tagNewUser');
			instance	= '$accountForm';

			// Hide the loquacious container
			updateMessage('', false, '$messageTypeDialog', instance);

			// Valid email address
			if (textEmail.value === '') {
				message = 'You must provide an email address';
				textBox	= textEmail;
			} else {
				// Valid username
				normalizeUsername(textUsername.value);

				if (textUsername.value === '') {
					message = 'The username cannot be blank';
					textBox	= textUsername;
				} else {
					// Password OK?
					if (textPassword.value !== textConfirm.value) {
						message = 'Passwords are not the same';
					} else if (that.passwordDefault_Account) {
						if (textNew.value === '$stringTrue') {message = 'Password cannot be blank';}
					} else if (textPassword.value === '') {
						message = 'Password cannot be blank';
					}

					textBox	= textPassword;
				}
			}

			break;
		case 'ezuser-$actionReset-form':
			textPassword	= that.control('ezuser-$actionReset-$tagPassword');
			textConfirm	= that.control('ezuser-$actionReset-$tagConfirm');
			instance	= 'ezuser';
			textBox		= textPassword;

			// Password OK?
			if (textPassword.value !== textConfirm.value) {
				message = 'Passwords are not the same';
			} else if (textPassword.value === '') {
				message = 'Password cannot be blank';
			}

			break;
		case 'ezuser-$actionResetRequest-form':
			textUsername	= that.control('ezuser-$tagUsername');
			instance	= 'ezuser';
			textBox		= textUsername;

			// Username entered?
			if (textUsername.value === '') {message = 'Username cannot be blank';}
			break;
		}

		if (message === '') {
			return true;
		} else {
			updateMessage(message, true, '$messageTypeDefault', instance);
			textBox.setFocus();
			return false;
		}
	};

/**
 * Cookies! Mmmm.
 */
	cookies	= {
		sessionId:	'',
		username:	'',
		passwordHash:	'',
		staySignedIn:	false,
		rememberMe:	false,

		persist: function (name, value, days) {
			var date, expires;

			if (typeof days !== 'undefined') {
				date = new Date();
				date.setTime(date.getTime() + (days * 1000 * 3600 * 24));
				expires = '; expires=' + date.toGMTString();
			} else {
				expires = '';
			}

			document.cookie = name + '=' + value + expires + '; path=/';
		},

		acquire: function (name) {
			var i, c, carray = document.cookie.split(';');
			name = name + '=';

			for (i = 0; i < carray.length; i += 1) {
				c = carray[i];
				while (c.charAt(0) === ' ') {c = c.substring(1, c.length);}
				if (c.indexOf(name) === 0) {return c.substring(name.length, c.length);}
			}

			return '';
		},

		remove: function (name) {this.persist(name, '', -1);},

		read: function () {
			this.sessionId		= this.acquire('$sessionName');
			this.username		= this.acquire('$cookieUsername');
			this.passwordHash	= this.acquire('$cookiePassword');
			this.staySignedIn	= this.acquire('$cookieStaySignedIn');
			this.staySignedIn	= (this.staySignedIn === '') ? false : true;

			if (this.username === '') {
				this.staySignedIn		= false;
			} else {
				this.rememberMe			= true;
			}

			if (this.passwordHash === '') {
				that.passwordDefault_SignIn	= false;
				this.staySignedIn		= false;
			} else {
				that.passwordDefault_SignIn	= true;
				this.rememberMe			= true;
			}
		},

		update: function () {
			this.username = that.control('ezuser-$tagUsername').value;

//			if (typeof ajaxUnit === 'function') {ajaxUnit('passwordDefault_SignIn = ' + that.passwordDefault_SignIn,	true);}	// Debug
//			if (typeof ajaxUnit === 'function') {ajaxUnit('this.passwordHash = ' + this.passwordHash,			true);}	// Debug

			if (!that.passwordDefault_SignIn || (this.passwordHash === '')) {
				var password		= that.control('ezuser-$tagPassword').value;
				this.passwordHash	= SHA256('$remoteAddress' + SHA256(password));
			}

//			if (typeof ajaxUnit === 'function') {ajaxUnit('\\$remoteAddress = $remoteAddress',				true);}	// Debug
//			if (typeof ajaxUnit === 'function') {ajaxUnit('this.passwordHash = ' + this.passwordHash,			true);}	// Debug

			if (this.rememberMe) {
				// Remember username & password for 30 days
				this.persist('$cookieUsername', this.username, 30);
				this.persist('$cookiePassword', this.passwordHash, 30);
			} else {
				this.remove('$cookieUsername');
				this.remove('$cookiePassword');
			}

			if (this.staySignedIn) {
				// Stay signed in for 2 weeks
				this.persist('$cookieStaySignedIn', true, 24);
			} else {
				this.remove('$cookieStaySignedIn');
			}
		},

		showPreferences: function () {
			that.control('ezuser-$tagRememberMe').setState(4, this.rememberMe);
			that.control('ezuser-$tagStaySignedIn').setState(4, this.staySignedIn);
		},

		toggleRememberMe: function() {
			this.rememberMe		= !this.rememberMe;
			this.staySignedIn	= (this.rememberMe) ? this.staySignedIn : false;

			this.showPreferences();
			this.update();
		},

		toggleStaySignedIn: function() {
			this.staySignedIn	= !this.staySignedIn;
			this.rememberMe		= (this.staySignedIn) ? true : this.rememberMe;

			this.showPreferences();
			this.update();
		}
	};

/**
 * AJAX handling
 */
	ajax = {
		xhr: new window.XMLHttpRequest(),

		handleServerResponse: function () {
			var id, className, cancelButton;

			if ((this.readyState === 4) && (this.status === 200)) {
				id		= this.getResponseHeader('ezUser-id');
				className	= this.getResponseHeader('ezUser-class');

				if (this.responseXML !== null) {
					fillContainersXML(this.responseXML);
				} else if (id === null) {
					that.control('body').append(this.responseText);
				} else {
					fillContainerText(id, this.responseText, className);
				}

				if (typeof ajaxUnit === 'function') {ajaxUnit(this);}	// Automated unit testing
			}
		},

		serverTalk: function (URL, requestType, requestData) {
			this.xhr.open(requestType, URL);
			this.xhr.onreadystatechange = this.handleServerResponse;
			this.xhr.setRequestHeader('Accept', 'text/html,application/ezuser');
			if (requestType === 'POST') {this.xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');}
			this.xhr.send(requestData);
		},

		execute: function (thisAction) {
			var	action,
				passwordHash,
				equals		= '=',
				requestType	= 'GET',
				requestData	= '',
				URL		= '$URL',
				delimPos	= thisAction.indexOf('$delimPlus'),
				textNew,
				readyState;

			thisAction	= (delimPos === -1) ? thisAction : '$action=' + thisAction;
			delimPos	= thisAction.indexOf(equals);
			action		= (delimPos === -1) ? thisAction : thisAction.slice(0, delimPos);

			switch (action) {
			case '$actionSignIn':
				updateMessage('Signing in - please wait', false, '$messageTypeDefault', '$signInForm');
				cookies.update();	// Updates ezuser.passwordHash;
				passwordHash	= SHA256(cookies.sessionId + cookies.passwordHash);

//				if (typeof ajaxUnit === 'function') {ajaxUnit('sessionId = ' + cookies.sessionId,		true);}	// Debug
//				if (typeof ajaxUnit === 'function') {ajaxUnit('passwordHash = ' + passwordHash,			true);}	// Debug

				requestData	= '$action='			+ action;
				requestData	+= '&$cookieUsername='		+ that.control('ezuser-$tagUsername').value;
				requestData	+= '&$cookiePassword='		+ passwordHash;
				requestType	= 'POST';

				break;
			case '$actionValidate':
				updateMessage('Registering - please wait', false, '$messageTypeDefault', '$accountForm');

				textNew		= that.control('$accountForm-$tagNewUser').value;
				requestData	= '$action='			+ action;
				requestData	+= '&$tagNewUser='		+ textNew;
				requestData	+= '&$tagWizard='		+ encodeURIComponent(that.control('$accountForm-$tagWizard').value);
				requestData	+= '&$tagEmail='		+ encodeURIComponent(that.control('$accountForm-$tagEmail').value);
				requestData	+= '&$tagFirstName='		+ encodeURIComponent(that.control('$accountForm-$tagFirstName').value);
				requestData	+= '&$tagLastName='		+ encodeURIComponent(that.control('$accountForm-$tagLastName').value);
				requestData	+= '&$cookieUsername='		+ that.control('$accountForm-$tagUsername').value;

				if (!that.passwordDefault_Account || (textNew === '$stringTrue')) {
					passwordHash	= SHA256(that.control('$accountForm-$tagPassword').value);
					requestData	+= '&$cookiePassword='	+ passwordHash;
				}

				requestType	= 'POST';

				break;
			case '$actionCancel':
				readyState = this.xhr.readyState;

				if ((readyState > 0) && (readyState < 4)) {
					// Cancel ongoing sign-in
					this.xhr.abort();
					this.xhr = new window.XMLHttpRequest();
				}

				return;
			case '$actionResend':
				URL += '?' + thisAction + equals + encodeURIComponent(that.control('$accountForm-$tagEmail').value);
				break;
			case '$actionResetPassword':		// Fall-through ->
			case '$actionResetRequest':
				URL += '?' + thisAction + equals + that.control('ezuser-$tagUsername').value;
				break;
			case '$actionReset':
				passwordHash = SHA256(that.control('ezuser-$actionReset-$tagPassword').value);
				URL = window.location.href + '&$cookiePassword=' + passwordHash;
				break;
			default:
				URL += '?' + thisAction;
				break;
			}

			this.serverTalk(URL, requestType, requestData);
		}
	};

/**
 * Account wizard page handling
 */
	wizard = {
		page: 1,

		changePage: function (delta) {
			var nextPageId, nextPage;

			if (that.control('$accountForm-$tagWizard').value === '$stringFalse') {return;}	// Not in wizard mode

			this.page = (arguments.length === 0) ? 1 : this.page + delta;
			if (this.page < 1) {this.page = 1;}

			// Previous page
			if (this.page === 1) {
				that.control('$accountForm-back').hide();				// Hide 'Back' button
			} else {
				that.control('$accountForm-back').show();				// Show 'Back' button
				that.control('$accountForm-fieldset-' + (this.page - 1)).hide();	// Hide previous page
			}

			// Current page
			that.control('$accountForm-fieldset-' + this.page).show();			// Show this page

			// Next page
			nextPageId	= '$accountForm-fieldset-' + (this.page + 1);
			nextPage	= that.control(nextPageId);

			if (nextPage === null) {
				that.control('$accountForm-next').hide();				// Hide 'Next' button
			} else {
				that.control('$accountForm-next').show();				// Show 'Next' button
				that.control(nextPageId).hide();					// Hide next page
			}
		},

		pageNext:	function () {this.changePage(1);},
		pageBack:	function () {this.changePage(-1);},
		initialize:	function () {this.page = 1;}
	};


// More public methods
// ---------------------------------------------------------------------------
	this.action = function (thisAction) {
		ajax.execute(thisAction);
	}

// ---------------------------------------------------------------------------
	this.click = function (button) {
		var	id	= button.id,
			action	= button.getAttribute('data-ezuser-action');

		switch (id) {
		case 'ezuser-$actionAccountForm':
			$accountClick;
			break;
		case 'ezuser-$tagRememberMe':
			cookies.toggleRememberMe();
			break;
		case 'ezuser-$tagStaySignedIn':
			cookies.toggleStaySignedIn();
			break;
		case '$accountForm-next':
			wizard.pageNext();	// Next wizard page
			break;
		case '$accountForm-back':
			wizard.pageBack();	// Previous wizard page
			break;
		case 'ezuser-$actionResetPassword':	// Fall-through ->
		case 'ezuser-$actionReset-OK':		// Fall-through ->
		case '$accountForm-$actionValidate':
			if (localValidation(button.form.id)) {ajax.execute(action);}
			break;
		case '$signInForm-close':		// Fall-through ->
		case '$accountForm-close':
			that.control(button.parentNode.id).hide();
			break;
		default:
			if (action === null) {break;}
			ajax.execute(action);
			break;
		}

		return false;
	};

// ---------------------------------------------------------------------------
	this.keyPress = function (e) {
		if (!e) {e = window.event;}

		var formId, id, target, status = true;

		// Process Carriage Return and tidy up form
		target	= (e.target) ? e.target : e.srcElement;
		formId	= target.form.id;
		id	= target.id;

		if (formId === '$accountForm-form' && id === '$accountForm-$tagUsername' && (e.keyCode >= 32)) {
			// If we are messing with the username then forget creating a default
			this.usernameDefault_Account = false;

			if ('' === removeIllegalCharacters(String.fromCharCode(e.charCode))) {
				status = false; // cancel the event (i.e. don't allow the character)
			}
		}

		return status;
	};

// ---------------------------------------------------------------------------
	this.keyUp = function (e) {
		if (!e) {e = window.event;}
		var formId, id, button, target;

		// Process Carriage Return and tidy up form
		target	= (e.target) ? e.target : e.srcElement;
		formId	= target.form.id;
		id	= target.id;

		switch (formId) {
		case 'ezuser-$actionSignIn-form':
			if (id === 'ezuser-$tagPassword' && this.passwordDefault_SignIn) {
				// Forget password from cookie
				cookies.passwordHash	= '';
				this.passwordDefault_SignIn	= false;
			}

			if (e.keyCode === 13) {
				this.click(that.control('ezuser-$actionSignIn-button'));
			} else {
				updateMessage('', false, '$messageTypeDefault', '$signInForm'); // Hide message
			}

			break;
		case '$accountForm-form':
			switch (id) {
			case '$accountForm-$tagFirstName':
			case '$accountForm-$tagLastName':
				if (that.control('$accountForm-$tagUsername').value === '') {this.usernameDefault_Account = true;}
				if (this.usernameDefault_Account) {normalizeUsername(that.control('$accountForm-$tagFirstName').value + that.control('$accountForm-$tagLastName').value);}
				break;
			case '$accountForm-$tagPassword':
				that.passwordSaved = target.value;
				that.passwordDefault_Account = false;
				break;
			case '$accountForm-$tagConfirm':
				that.passwordDefault_Account = false;
				break;
			}

			if (e.keyCode === 13) {
				button = that.control('$accountForm-$actionValidate');
				if (button === null) {button = that.control('$accountForm-$modeEdit');}
				that.click(button);
			} else {
				updateMessage('', false, '$messageTypeDefault', '$accountForm'); // Hide message
			}

			break;
		case 'ezuser-$actionReset-form':
			if (e.keyCode === 13) {
				that.click(that.control('ezuser-$actionReset-OK'));
			} else {
				updateMessage('', false, '$messageTypeDefault', '$signInForm'); // Hide message
			}

			break;
		}

		return true;
	};


// ---------------------------------------------------------------------------
	this.passwordFocus = function (textBox) {
		switch (textBox.form.id) {
		case 'ezuser-$actionSignIn-form':
			if (this.passwordDefault_SignIn) {textBox.value = '';}
			break;
		case '$accountForm-form':
			if (that.passwordDefault_Account) {
				that.control('$accountForm-$tagPassword').value	= '';
				that.control('$accountForm-$tagConfirm').value	= '';
			}
			break;
		}

		return true;
	};

// ---------------------------------------------------------------------------
	this.passwordBlur = function (textBox) {
		switch (textBox.form.id) {
		case 'ezuser-$actionSignIn-form':
			if (this.passwordDefault_SignIn) {textBox.value = '$passwordMask';}
			break;
		case '$accountForm-form':
			if (that.passwordDefault_Account) {
				that.control('$accountForm-$tagPassword').value	= '$passwordMask';
				that.control('$accountForm-$tagConfirm').value	= '$passwordMask';
			}
			break;
		}

		return true;
	};

/**
 * Constructor
 */
	cookies.read();
	addStyleSheet();
}

/**
 * Do stuff
 */
var ezUser = new C_ezUser();
$immediateJavascript
GENERATED;
// Generated code - do not modify in built package

		if ($sendToBrowser) {self::sendContent($js, '', 'text/javascript'); return '';} else return $js;
	}

	public static /*.string.*/ function inlineBitmap(/*.string.*/ $id, $sendToBrowser = true) {
		switch ($id) {
		case 'close':
			$mimeType	= 'image/gif';
			$bitmap		= base64_decode('R0lGODlhKAAoAIQaAMzMzM3Nzc7Ozs/Pz9DQ0NLS0tXV1djY2Nra2tvb293d3d7e3uDg4OHh4ePj4+np6erq6uzs7O7u7vPz8/X19fb29vn5+fr6+vz8/P7+/v///////////////////////yH+IUNvcHlyaWdodCAoYykgMjAxMCBEb21pbmljIFNheWVycwAh+QQBCgAfACwAAAAAKAAoAAAF/uAnjmT5EcQQBANqvvArtI00WVlmTVKTCrHgaOagaI7I5JHiGACFMsYlSZE8HI6HxIi8MJ5QkaBQQU4aBYB6rS40JshKARwcHDDHCoLN5yPKGhgHA0IDCxkaGQ59jHwOiBkLhDACB4gYe42aagh4GQd0JAV4GAabpwAGpAUyZRmZqJsIiBWhAgxHi2sKEQmoCxEMbA5HXyQDUxVsuEcQmxBICmxlF5MfAsQasAARSc6M0EgRbAi5TwNGE3wLSt9s4Ui+bHAUkwRHDX3wze/tfQ1HCIgAqCGNPn8A9mlwx6YAvhMSNFDQpBBCRU1GJKCAI+GZEoSNIk5IYUHDg1MK5pEwZPRAgwUViHR5BKmJWIYVMVGlXMnIJouSJ2d+5Mmn5UsCHIV+XLhJJIqIExtVvNgoo0CCBvlQpcrHoYYGIu59PeitX1k+BAV+QKdBHZsENNUoXMCH3iRsR7Z1Uyk1ybg15TQ4AINMg7JdfCkiEbaGmrVrzGQCYBCB7qkEEaStyWaMhABX22IxmmU4lIhRgUyJZqQqECsZlgKFXt0pEagYhiBJFv0okaRCd/LMbvTniKDHMcYAaoumkRs4eeaEGSKFihUsWrgc8WI6DBHtS5c06T79Gg0bOHTw8OGkfBgUKli4KB8CADs=');	// Generated code - do not modify in built package
			break;
		default:
			$mimeType	= '';
			$bitmap		= 'Unknown bitmap';
		}

		if ($sendToBrowser) {self::sendContent($bitmap, 'bitmap', $mimeType); return '';} else return $bitmap;
	}


// ---------------------------------------------------------------------------
// Account verification
// ---------------------------------------------------------------------------
	private static /*.string.*/ function verify_renotify($username_or_email = '', $sendToBrowser = false) {
		$result		= self::verify_notify($username_or_email);
		$message	= ($result === self::RESULT_SUCCESS) ? 'Verification email has been resent.' : self::resultText($result);
		$container	= self::getInstanceId(self::ACTION_ACCOUNT . '-' . self::MESSAGE_TYPE_DEFAULT);

		if ($sendToBrowser) {self::sendContent($message, $container); return '';} else return $message;
	}

	private static /*.void.*/ function verify(/*.string.*/ $verificationKey) {
		$title	= 'Email address verification';
		$ezUser	= self::lookup($verificationKey, self::TAGNAME_VERIFICATIONKEY);

		switch ($ezUser->status()) {
		case self::STATUS_PENDING:
			self::verify_update($ezUser, $verificationKey);
			$message = 'Your email address has been confirmed. You can now close this browser tab.';
			break;
		case self::STATUS_CONFIRMED:
			$message = 'This email address has already been verified.';
			break;
		default:
			$message = 'Sorry, this is not a valid account verification key.';
			break;
		}

		self::htmlMessagePage($title, $message, true);
	}

// ---------------------------------------------------------------------------
// Password reset
// ---------------------------------------------------------------------------
	private static /*.void.*/ function passwordReset_validate(/*.string.*/ $username_or_email) {
		if ($username_or_email === '')
			self::htmlResetRequest($username_or_email, true);
		else {
			$message =	(self::passwordReset_initialize($username_or_email))
					? 'An email has been sent to your registered address with instructions for resetting your password.'
					: 'Couldn\'t complete password reset for this user.';

			self::htmlMessageForm($message, self::ACTION_RESET, true);
		}
	}

	private static /*.void.*/ function passwordReset_reset(/*.string.*/ $resetKey) {
		$title	= 'Password reset';
		$ezUser	= self::lookup($resetKey, self::TAGNAME_RESETKEY);

		if ($ezUser->status() === self::STATUS_UNKNOWN) {
			self::htmlMessagePage($title, 'Sorry, this is not a valid password reset key.', true);
			return;
		}

		// Is there a password hash in $_GET?
		if (array_key_exists(self::COOKIE_PASSWORD, $_GET)) {
			// Attempt to update user's account with the new password
			$passwordHash = (string) $_GET[self::COOKIE_PASSWORD];
			self::passwordReset_update($ezUser, $passwordHash);
			self::htmlMessagePage($title, 'Your password has been updated. You can now close this browser tab.', true);
		} else {
			// Get the new password from the user
			self::htmlResetPassword($ezUser, true);
		}
	}

// ---------------------------------------------------------------------------
// Secure content handling
// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlSecureContent($sendToBrowser = false) {
		$ezUser		= self::getSessionObject();
		$refererKey	= 'HTTP_REFERER';

		if (array_key_exists($refererKey, $_SERVER)) {
			$referer = $_SERVER[$refererKey];

			if ($ezUser->authenticated()) {
				$html = self::getSecureContent($referer);
			} else {
				header('HTTP/1.1 403 Forbidden', false, 403);
				$referer = (string) str_replace('http://' . $_SERVER['HTTP_HOST'], '' , $referer);
				$html = <<<HTML
<h1>Forbidden</h1>
<p>You don't have permission to access $referer on this server.</p>
HTML;
			}
		} else {
			$html = 'No referer';
		}

		if ($sendToBrowser) {self::sendContent($html, self::ACTION_BODY); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
// Sign in and sign out
// ---------------------------------------------------------------------------

	private static /*.void.*/ function fatalError(/*.int.*/ $result, $more = '') {
		self::htmlResultForm($result, $more, true);
		exit;
	}

// ---------------------------------------------------------------------------
	/*. forward public static void function doActions(array[string]string $actions); .*/

	private static /*.void.*/ function signOut() {
		$ezUser	= self::getSessionObject();
		if (!$ezUser->authenticated()) return;	// Not signed in so nothing to do

		// Sign out then check if a post-signout function has been registered
		$ezUser->authenticate();		// Sign out
		$ezUser->addSignOutAction(self::ACTION_CONTROLPANEL);
		$signOutActions = $ezUser->signOutActions();
		self::setSessionObject(new ezUser_base(), self::ACTION_ACCOUNT);
		self::doActions(array(self::ACTION => $signOutActions));
	}

// ---------------------------------------------------------------------------
// General action handling
// ---------------------------------------------------------------------------
	private static /*.array[int]string.*/ function doAction($action = '', $id = '', $sendToBrowser = true) {
		$html = array('', '', '');

		switch ($action) {
		case self::ACTION_CONTROLPANEL:		$html		= self::htmlControlPanel	($sendToBrowser);				break;
		case self::ACTION_STYLESHEET:		$html[0]	= self::htmlStyleSheet		($sendToBrowser);				break;
		case self::ACTION_BODY:			$html[0]	= self::htmlSecureContent	($sendToBrowser);				break;
		case self::ACTION_ABOUT:		$html[0]	= self::htmlAbout		($sendToBrowser);				break;
		case self::ACTION_ABOUTTEXT:		$html[0]	= self::htmlAboutText		($sendToBrowser);				break;
		case self::ACTION_SIGNINFORM:		$html		= self::htmlSignInForm		($sendToBrowser);				break;
		case self::ACTION_SOURCECODE:		$html[0]	= self::htmlSourceCode		($sendToBrowser);				break;
		case self::ACTION_CONTAINER:		$html[0]	= self::htmlContainer		($id,			$sendToBrowser);	break;
		case self::ACTION_BITMAP:		$html[0]	= self::inlineBitmap		($id,			$sendToBrowser);	break;
		case self::ACTION_RESETREQUEST:		$html		= self::htmlResetRequest	($id,			$sendToBrowser);	break;
		case self::ACTION_ACCOUNT:		$html		= self::htmlAccountForm		($id, false, false,	$sendToBrowser);	break;
		case self::ACTION_ACCOUNTWIZARD:	$html		= self::htmlAccountForm		($id, false, true,	$sendToBrowser);	break;
		case self::ACTION_STATUSTEXT:		$html[0]	= self::statusText		((int) $id, '',		$sendToBrowser);	break;
		case self::ACTION_RESULTTEXT:		$html[0]	= self::resultText		((int) $id, '',		$sendToBrowser);	break;
		case self::ACTION_RESULTFORM:		$html		= self::htmlResultForm		((int) $id, '',		$sendToBrowser);	break;
		case self::ACTION_RESEND:		$html[0]	= self::verify_renotify		($id,			$sendToBrowser);	break;
		case self::ACTION_JAVASCRIPT:		$html[0]	= self::htmlJavascript		($id,			$sendToBrowser);	break;
		case self::ACTION_VERIFY:		self::verify					($id);						break;
		case self::ACTION_RESETPASSWORD:	self::passwordReset_validate			($id);						break;
		case self::ACTION_RESET:		self::passwordReset_reset			($id);						break;
		case self::ACTION_SIGNOUT:		self::signOut					();						break;
		default:				self::fatalError				(self::RESULT_UNKNOWNACTION, $action);		break;
		}

		return $html;
	}

// ---------------------------------------------------------------------------
/**
 * Performs one or more actions
 *
 * To perform more than one action, specify them in the condensed format <action>[=<id>]
 *
 * <pre>
 *     ezuser.php?foo1+foo2+foo3=bar1+bar2+bar3
 * </pre>
 *
 * or the extended format action=<action>[&id-<id>]
 *
 * <pre>
 *     ezuser.php?action=foo1+foo2+foo3&id=bar1+bar2+bar3
 * </pre>
 *
 * By the time it reaches this function they will be in an array with the '+'
 * delimiters replaced with spaces by the magic of PHP. So if you're calling
 * this function with your own parameters you would specify them like this:
 *
 * <pre>
 *     [$actions] => Array
 *        (
 *            [action] => foo1 foo2 foo3
 *            [id] => bar1 bar2 bar3
 *        )
 * </pre>
 *
 * Each space-delimited element in the <kbd>action</kbd> member of the {@link $actions}
 * array will be performed.
 *
 * @param array[string]string $actions Same format as {@link http://www.php.net/$_GET $_GET} (which is where it usually comes from)
 */
	public static /*.void.*/ function doActions(/*.array[string]string.*/ $actions) {
		// Translate from short form (ezuser.php?foo=bar) to extended form (ezuser.php?action=foo&id=bar)
		if (!array_key_exists(self::ACTION, $actions)) {
			$actions[self::TAGNAME_ID]	= (string) reset($actions);
			$actions[self::ACTION]		= (string) key($actions);
		}

		$actionList	= (array_key_exists(self::ACTION, $actions))		? $actions[self::ACTION]	: '';
		$id		= (array_key_exists(self::TAGNAME_ID, $actions))	? $actions[self::TAGNAME_ID]	: '';

		if (strpos($actionList, self::DELIMITER_SPACE) !== false) {
			$actionItems	= explode(self::DELIMITER_SPACE, $actionList);
			$content	= /*.(array[int][int]string).*/ array();
			foreach ($actionItems as $action) $content[] = self::doAction($action, $id, false);
			self::sendXML($content);
		} else {
			self::doAction($actionList, $id);
		}
	}

// ---------------------------------------------------------------------------
// 'Get' actions
// ---------------------------------------------------------------------------
// Methods may be commented out to reduce the attack surface if they are not
// required. Uncomment them if you need them.
//	public static /*.void.*/ function getStatusText		(/*.int.*/ $status, $more = '')			{self::statusText($status, $more,			true);}
//	public static /*.void.*/ function getResultText		(/*.int.*/ $result, $more = '')			{self::resultText($result, $more,			true);}
//	public static /*.void.*/ function getStatusDescription	(/*.int.*/ $status, $more = '')			{self::statusDescription($status, $more,		true);}
//	public static /*.void.*/ function getResultDescription	(/*.int.*/ $result, $more = '')			{self::resultDescription($result, $more,		true);}
	public static /*.void.*/ function getResultForm		(/*.int.*/ $result, $more = '')			{self::htmlResultForm($result, $more,			true);}
	public static /*.void.*/ function getAccountForm	($mode = '', $newUser = false, $wizard = false)	{self::htmlAccountForm($mode, $newUser, $wizard,	true);}
//	public static /*.void.*/ function getSignInForm		()						{self::htmlSignInForm(					true);}
	public static /*.void.*/ function getSignInResults	()						{self::htmlSignInResults(				true);}
//	public static /*.void.*/ function getControlPanel	()						{self::htmlControlPanel(				true);}
//	public static /*.void.*/ function getStyleSheet		()						{self::htmlStyleSheet(					true);}
//	public static /*.void.*/ function getJavascript		($containerList = '')				{self::htmlJavascript($containerList,			true);}
	public static /*.void.*/ function getContainer		($action = self::ACTION_CONTROLPANEL)		{self::htmlContainer($action,				true);}
	public static /*.void.*/ function getAbout		()						{self::htmlAbout(					true);}
	public static /*.void.*/ function getAboutText		()						{self::htmlAboutText(					true);}
//	public static /*.void.*/ function getSourceCode		()						{self::htmlSourceCode(					true);}
}
// End of class ezUser



// Is this script included in another page or is it the HTTP target itself?
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
	// This script has been called directly by the browser, so check what it has sent
	if (is_array($_POST) && array_key_exists(ezUser::ACTION, $_POST)) {
		switch ((string) $_POST[ezUser::ACTION]) {
		case ezUser::ACTION_SIGNIN:
			ezUser::signIn(cast('array[string]string', $_POST));
			ezUser::getSignInResults();
			break;
		case ezUser::ACTION_VALIDATE:
			ezUser::save($_POST);
			ezUser::getAccountForm(ezUser::ACCOUNT_MODE_RESULT, ($_POST[ezUser::TAGNAME_NEWUSER] === ezUser::STRING_TRUE), ($_POST[ezUser::TAGNAME_WIZARD] === ezUser::STRING_TRUE));
			break;
		default:
			ezUser::getResultForm(ezUser::RESULT_UNKNOWNACTION);
			break;
		}
	} else if (is_array($_GET) && (count($_GET) > 0)) {
		ezUser::doActions(cast('array[string]string', $_GET));
	} else {
		ezUser::getAbout(); // Nothing useful in $_GET or $_POST, so give a friendly greeting
	}
}
?>