<?php
/**
 * Enables user registration and authentication for a website
 * 
 * This code has three principle design goals:
 * 
 * 	1. To make it easy for people to register and sign in to your site.
 * 	2. To make it easy for you to add this functionality to your site.
 * 	3. To make it easy for you to administer the user database on your site.
 * 
 * Other design goals, such as run-time efficiency, are important but secondary to
 * these.
 * 
 * Copyright (c) 2008-2009, Dominic Sayers							<br>
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 * 
 * 	- Redistributions of source code must retain the above copyright notice,
 * 	  this list of conditions and the following disclaimer.
 * 	- Redistributions in binary form must reproduce the above copyright notice,
 * 	  this list of conditions and the following disclaimer in the documentation
 * 	  and/or other materials provided with the distribution.
 * 	- Neither the name of Dominic Sayers nor the names of its contributors may be
 * 	  used to endorse or promote products derived from this software without
 * 	  specific prior written permission.
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
 * @author	Dominic Sayers <dominic_sayers@hotmail.com>
 * @copyright	2009 Dominic Sayers
 * @license	http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link	http://code.google.com/p/ezuser/
 * @version	0.22 - Newer version of common functions class
 */

// The quality of this code has been improved greatly by using PHPLint
// Copyright (c) 2009 Umberto Salsi
// This is free software; see the license for copying conditions.
// More info: http://www.icosaedro.it/phplint/
/*.
require_module 'dom';
require_module 'pcre';
require_module 'hash';
require_module 'session';
.*/

/* Comment out profiling statements if not needed
function ezUser_time() {list($usec, $sec) = explode(" ",microtime()); return ((float)$usec + (float)$sec);}
$ezUser_profile			= array();
$ezUser_profile['REQUEST_TIME']	= $_SERVER['REQUEST_TIME'];
$ezUser_profile['received']	= ezUser_time();
*/

/**
 * Common utility functions
 *
 * @package ezUser
 * @version 1.2 (revision number of common functions class only)
 */
interface I_ezUser_common {
	const	PACKAGE			= 'ezUser',

		HASH_FUNCTION		= 'SHA256',
		URL_SEPARATOR		= '/',

		// Behaviour settings for strleft()
		STRLEFT_MODE_NONE	= 0,
		STRLEFT_MODE_ALL	= 1,

		// Behaviour settings for getURL()
		URL_MODE_PROTOCOL	= 1,
		URL_MODE_HOST		= 2,
		URL_MODE_PORT		= 4,
		URL_MODE_PATH		= 8,
		URL_MODE_ALL		= 15,

		// Behaviour settings for getPackage()
		PACKAGE_CASE_DEFAULT	= 0,
		PACKAGE_CASE_LOWER	= 0,
		PACKAGE_CASE_CAMEL	= 1,
		PACKAGE_CASE_UPPER	= 2;

	// Basic utility functions
	public static /*.string.*/	function strleft(/*.string.*/ $haystack, /*.string.*/ $needle);
	public static /*.string.*/	function getInnerHTML(/*.string.*/ $html, /*.string.*/ $tag);
	public static /*.string.*/	function array_to_HTML(/*.array[mixed]mixed.*/ $source = NULL);

	// Environment functions
	public static /*.string.*/	function getPackage($mode = self::PACKAGE_CASE_DEFAULT);
	public static /*.string.*/	function getURL($mode = self::URL_MODE_PATH, $filename = '');

	// File system functions
	public static /*.string.*/	function getFileContents(/*.string.*/ $filename, /*.int.*/ $flags = 0, /*.object.*/ $context = NULL, /*.int.*/ $offset = -1, /*.int.*/ $maxLen = -1);
	public static /*.string.*/	function findIndexFile(/*.string.*/ $folder);

	// Data functions
	public static /*.string.*/	function makeId();
	public static /*.string.*/	function makeUniqueKey(/*.string.*/ $id);

	// Validation functions
	public static /*.boolean.*/	function is_email(/*.string.*/ $email, $checkDNS = false);
}

/**
 * Common utility functions
 */
abstract class ezUser_common implements I_ezUser_common {
/**
 * Return the beginning of a string, up to but not including the search term.
 *
 * @param string $haystack The string containing the search term
 * @param string $needle The end point of the returned string. In other words, if <var>needle<var> is found then the begging of <var>haystack</var> is returned up to the character before <needle>.
 * @param int $mode If <var>needle</var> is not found then <pre>FALSE</pre> will be returned. */
	public static /*.string.*/ function strleft(/*.string.*/ $haystack, /*.string.*/ $needle, /*.int.*/ $mode = self::STRLEFT_MODE_NONE) {
		$posNeedle = strpos($haystack, $needle);

		if ($posNeedle === false) {
			switch ($mode) {
			case self::STRLEFT_MODE_NONE:
				return $posNeedle;
				break;
			case self::STRLEFT_MODE_ALL:
				return $haystack;
				break;
			}
		} else {
			return substr($haystack, 0, $posNeedle);
		}
	}

/**
 * Return the contents of an HTML element, the first one matching the <var>tag</var> parameter.
 *
 * @param string $html The string containing the html to be searched
 * @param string $tag The type of element to search for. The contents of first matching element will be returned. If the element doesn't exist then <var>false</var> is returned.
 */
	public static /*.string.*/ function getInnerHTML(/*.string.*/ $html, /*.string.*/ $tag) {
		$pos_tag_open_start	= stripos($html, "<$tag");
		$pos_tag_open_end	= strpos($html, '>',		$pos_tag_open_start);
		$pos_tag_close		= stripos($html, "</$tag>",	$pos_tag_open_end);
		return substr($html, $pos_tag_open_end + 1, $pos_tag_close - $pos_tag_open_end - 1);
	}

/**
 * Return the contents of a captured var_dump() as HTML. This is a recursive function.
 *
 * @param string $var_dump The captured <var>var_dump()</var>.
 * @param int $offset Whereabouts to start in the captured string. Defaults to the beginning of the string.
 */
	public static /*.string.*/ function var_dump_to_HTML(/*.string.*/ $var_dump, $offset = 0) {
		$html	= '';
		$indent	= '';

		while ($posStart = strpos($var_dump, '(', $offset)) {
			$type	= substr($var_dump, $offset, $posStart - $offset);
			$nests	= strrpos($type, ' ');

			if ($nests === false) $nests = 0; else $nests = ($nests + 1) / 2;

			$indent = str_pad('', $nests * 3, "\t");
			$type	= trim($type);
			$offset	= ++$posStart;
			$posEnd	= strpos($var_dump, ')', $offset); if ($posEnd === false) break;
			$offset	= $posEnd + 1;
			$value	= substr($var_dump, $posStart, $posEnd - $posStart);

			switch ($type) {
			case 'string':
				$length	= $value;
				$value	= htmlspecialchars(substr($var_dump, $offset + 2, $length));
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
 * Return the contents of an array as HTML (like <var>var_dump()</var> on steroids)
 *
 * @param mixed $source The array to export. If it's empty then $GLOBALS is exported.
 */
	public static /*.string.*/ function array_to_HTML(/*.array[mixed]mixed.*/ $source = NULL) {
// If no specific array is passed we will export $GLOBALS to HTML
// Unfortunately, this means we have to use var_dump() because var_export() barfs on $GLOBALS
// In fact var_dump is easier to walk than var_export anyway so this is no bad thing.

		ob_start();
		if (empty($source)) var_dump($GLOBALS); else var_dump($source);
		$var_dump = ob_get_clean();

		return self::var_dump_to_HTML($var_dump);
	}

/**
 * Return the name of this package. By default this will be in lower case for use in Javascript tags etc.
 *
 * @param int $mode One of the <var>PACKAGE_CASE_XXX</var> predefined constants defined in this class
 */
	public static /*.string.*/ function getPackage($mode = self::PACKAGE_CASE_DEFAULT) {
		switch ($mode) {
		case self::PACKAGE_CASE_CAMEL:
			$package = self::PACKAGE;
			break;
		case self::PACKAGE_CASE_UPPER:
			$package = strtoupper(self::PACKAGE);
			break;
		default:
			$package = strtolower(self::PACKAGE);
			break;
		}

		return $package;
	}

/**
 * Return all or part of the URL of the current script.
 *
 * @param int $mode One of the <var>URL_MODE_XXX</var> predefined constants defined in this class
 * @param string $filename If this is not empty then the returned script name is forced to be this filename.
 */
	public static /*.string.*/ function getURL($mode = self::URL_MODE_PATH, $filename = self::PACKAGE) {
		$portInteger = array_key_exists('SERVER_PORT', $_SERVER) ? (int) $_SERVER['SERVER_PORT'] : 0;

		if (array_key_exists('HTTPS', $_SERVER) && $_SERVER['HTTPS'] === 'on') {
			$protocolType = 'https';
		} else if (array_key_exists('SERVER_PROTOCOL', $_SERVER)) {
			$protocolType = strtolower(self::strleft($_SERVER['SERVER_PROTOCOL'], self::URL_SEPARATOR, self::STRLEFT_MODE_ALL));
		} else if ($portInteger = 443) {
			$protocolType = 'https';
		} else {
			$protocolType = 'http';
		}

		if ($portInteger === 0) $portInteger = ($protocolType === 'https') ? 443 : 80;

		// Protocol
		if ($mode & self::URL_MODE_PROTOCOL) {
			$protocol = ($mode === self::URL_MODE_PROTOCOL) ? $protocolType : "$protocolType://";
		} else {
			$protocol = '';
		}

		// Host
		if ($mode & self::URL_MODE_HOST) {
			$host = array_key_exists('HTTP_HOST', $_SERVER) ? self::strleft($_SERVER['HTTP_HOST'], ':', self::STRLEFT_MODE_ALL) : '';
		} else {
			$host = '';
		}

		// Port
		if ($mode & self::URL_MODE_PORT) {
			$port = (string) $portInteger;

			if ($mode !== self::URL_MODE_PORT)
				$port = (($protocolType === 'http' && $portInteger === 80) || ($protocolType === 'https' && $portInteger === 443)) ? '' : ":$port";
		} else {
			$port = '';
		}

		// Path
		if ($mode & self::URL_MODE_PATH) {
			$includePath	= __FILE__;
			$scriptPath	= realpath($_SERVER['SCRIPT_FILENAME']);

			if (DIRECTORY_SEPARATOR !== self::URL_SEPARATOR) {
				$includePath	= (string) str_replace(DIRECTORY_SEPARATOR, self::URL_SEPARATOR , $includePath);
				$scriptPath	= (string) str_replace(DIRECTORY_SEPARATOR, self::URL_SEPARATOR , $scriptPath);
			}

			$path = dirname(substr($includePath, strpos(strtolower($scriptPath), strtolower($_SERVER['SCRIPT_NAME'])))) . self::URL_SEPARATOR . $filename;
		} else {
			$path = '';
		}

		return $protocol . $host . $port . $path;
	}

/**
 * Return file contents as a string. Fail silently if the file can't be opened.
 *
 * The parameters are the same as the built-in PHP function {@link http://www.php.net/file_get_contents file_get_contents}
 */
	public static /*.string.*/ function getFileContents(/*.string.*/ $filename, /*.int.*/ $flags = 0, /*.object.*/ $context = NULL, /*.int.*/ $offset = -1, /*.int.*/ $maxlen = -1) {
		$contents = @file_get_contents($filename, $flags, $context, $offset, $maxlen);
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
 * Return the name of the target file from a string that might be a directory. If it's a directory then look for an index file in the directory.
 *
 * @param string $target The file to look for or folder to look in. If no file can be found then an empty string is returned.
 */
	public static /*.string.*/ function findTarget(/*.string.*/ $target) {
		if (is_file($target)) return $target;
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
 */
	public static /*.string.*/ function makeUniqueKey(/*.string.*/ $id) {
		return hash(self::HASH_FUNCTION, $_SERVER['REQUEST_TIME'] . $id);
	}

/**
 * Check that an email address conforms to RFC5322 and other RFCs
 *
 * @param boolean $checkDNS If true then a DNS check for A and MX records will be made
 */
	public static /*.boolean.*/ function is_email(/*.string.*/ $email, $checkDNS = false) {
		// Check that $email is a valid address. Read the following RFCs to understand the constraints:
		//	(http://tools.ietf.org/html/rfc5322)
		//	(http://tools.ietf.org/html/rfc3696)
		//	(http://tools.ietf.org/html/rfc5321)
		//	(http://tools.ietf.org/html/rfc4291#section-2.2)
		//	(http://tools.ietf.org/html/rfc1123#section-2.1)

		// the upper limit on address lengths should normally be considered to be 256
		//	(http://www.rfc-editor.org/errata_search.php?rfc=3696)
		//	NB I think John Klensin is misreading RFC 5321 and the the limit should actually be 254
		//	However, I will stick to the published number until it is changed.
		//
		// The maximum total length of a reverse-path or forward-path is 256
		// characters (including the punctuation and element separators)
		//	(http://tools.ietf.org/html/rfc5321#section-4.5.3.1.3)
		$emailLength = strlen($email);
		if ($emailLength > 256)	return false;	// Too long

		// Contemporary email addresses consist of a "local part" separated from
		// a "domain part" (a fully-qualified domain name) by an at-sign ("@").
		//	(http://tools.ietf.org/html/rfc3696#section-3)
		$atIndex = strrpos($email,'@');

		if ($atIndex === false)		return false;	// No at-sign
		if ($atIndex === 0)		return false;	// No local part
		if ($atIndex === $emailLength)	return false;	// No domain part

		// Sanitize comments
		// - remove nested comments, quotes and dots in comments
		// - remove parentheses and dots from quoted strings
		$braceDepth	= 0;
		$inQuote	= false;
		$escapeThisChar	= false;

		for ($i = 0; $i < $emailLength; ++$i) {
			$char = $email[$i];
			$replaceChar = false;

			if ($char === '\\') {
				$escapeThisChar = !$escapeThisChar;	// Escape the next character?
			} else {
				switch ($char) {
				case '(':
					if ($escapeThisChar) {
						$replaceChar = true;
					} else {
						if ($inQuote) {
							$replaceChar = true;
						} else {
							if ($braceDepth++ > 0) $replaceChar = true;	// Increment brace depth
						}
					}

					break;
				case ')':
					if ($escapeThisChar) {
						$replaceChar = true;
					} else {
						if ($inQuote) {
							$replaceChar = true;
						} else {
							if (--$braceDepth > 0) $replaceChar = true;	// Decrement brace depth
							if ($braceDepth < 0) $braceDepth = 0;
						}
					}

					break;
				case '"':
					if ($escapeThisChar) {
						$replaceChar = true;
					} else {
						if ($braceDepth === 0) {
							$inQuote = !$inQuote;	// Are we inside a quoted string?
						} else {
							$replaceChar = true;
						}
					}

					break;
				case '.':	// Dots don't help us either
					if ($escapeThisChar) {
						$replaceChar = true;
					} else {
						if ($braceDepth > 0) $replaceChar = true;
					}

					break;
				default:
				}

				$escapeThisChar = false;
//				if ($replaceChar) $email[$i] = 'x';	// Replace the offending character with something harmless
// revision 1.12: Line above replaced because PHPLint doesn't like that syntax
				if ($replaceChar) $email = (string) substr_replace($email, 'x', $i, 1);	// Replace the offending character with something harmless
			}
		}

		$localPart	= substr($email, 0, $atIndex);
		$domain		= substr($email, $atIndex + 1);
		$FWS		= "(?:(?:(?:[ \\t]*(?:\\r\\n))?[ \\t]+)|(?:[ \\t]+(?:(?:\\r\\n)[ \\t]+)*))";	// Folding white space
		// Let's check the local part for RFC compliance...
		//
		// local-part      =       dot-atom / quoted-string / obs-local-part
		// obs-local-part  =       word *("." word)
		//	(http://tools.ietf.org/html/rfc5322#section-3.4.1)
		//
		// Problem: need to distinguish between "first.last" and "first"."last"
		// (i.e. one element or two). And I suck at regexes.
		$dotArray	= /*. (array[int]string) .*/ preg_split('/\\.(?=(?:[^\\"]*\\"[^\\"]*\\")*(?![^\\"]*\\"))/m', $localPart);
		$partLength	= 0;

		foreach ($dotArray as $element) {
			// Remove any leading or trailing FWS
			$element = preg_replace("/^$FWS|$FWS\$/", '', $element);

			// Then we need to remove all valid comments (i.e. those at the start or end of the element
			$elementLength = strlen($element);

			if ($element[0] === '(') {
				$indexBrace = strpos($element, ')');
				if ($indexBrace !== false) {
					if (preg_match('/(?<!\\\\)[\\(\\)]/', substr($element, 1, $indexBrace - 1)) > 0) {
														return false;	// Illegal characters in comment
					}
					$element	= substr($element, $indexBrace + 1, $elementLength - $indexBrace - 1);
					$elementLength	= strlen($element);
				}
			}

			if ($element[$elementLength - 1] === ')') {
				$indexBrace = strrpos($element, '(');
				if ($indexBrace !== false) {
					if (preg_match('/(?<!\\\\)(?:[\\(\\)])/', substr($element, $indexBrace + 1, $elementLength - $indexBrace - 2)) > 0) {
														return false;	// Illegal characters in comment
					}
					$element	= substr($element, 0, $indexBrace);
					$elementLength	= strlen($element);
				}
			}

			// Remove any leading or trailing FWS around the element (inside any comments)
			$element = preg_replace("/^$FWS|$FWS\$/", '', $element);

			// What's left counts towards the maximum length for this part
			if ($partLength > 0) $partLength++;	// for the dot
			$partLength += strlen($element);

			// Each dot-delimited component can be an atom or a quoted string
			// (because of the obs-local-part provision)
			if (preg_match('/^"(?:.)*"$/s', $element) > 0) {
				// Quoted-string tests:
				//
				// Remove any FWS
				$element = preg_replace("/(?<!\\\\)$FWS/", '', $element);
				// My regex skillz aren't up to distinguishing between \" \\" \\\" \\\\" etc.
				// So remove all \\ from the string first...
				$element = preg_replace('/\\\\\\\\/', ' ', $element);
				if (preg_match('/(?<!\\\\|^)["\\r\\n\\x00](?!$)|\\\\"$|""/', $element) > 0)	return false;	// ", CR, LF and NUL must be escaped, "" is too short
			} else {
				// Unquoted string tests:
				//
				// Period (".") may...appear, but may not be used to start or end the
				// local part, nor may two or more consecutive periods appear.
				//	(http://tools.ietf.org/html/rfc3696#section-3)
				//
				// A zero-length element implies a period at the beginning or end of the
				// local part, or two periods together. Either way it's not allowed.
				if ($element === '')								return false;	// Dots in wrong place

				// Any ASCII graphic (printing) character other than the
				// at-sign ("@"), backslash, double quote, comma, or square brackets may
				// appear without quoting.  If any of that list of excluded characters
				// are to appear, they must be quoted
				//	(http://tools.ietf.org/html/rfc3696#section-3)
				//
				// Any excluded characters? i.e. 0x00-0x20, (, ), <, >, [, ], :, ;, @, \, comma, period, "
				if (preg_match('/[\\x00-\\x20\\(\\)<>\\[\\]:;@\\\\,\\."]/', $element) > 0)	return false;	// These characters must be in a quoted string
			}
		}

		if ($partLength > 64) return false;	// Local part must be 64 characters or less

		// Now let's check the domain part...

		// The domain name can also be replaced by an IP address in square brackets
		//	(http://tools.ietf.org/html/rfc3696#section-3)
		//	(http://tools.ietf.org/html/rfc5321#section-4.1.3)
		//	(http://tools.ietf.org/html/rfc4291#section-2.2)
		if (preg_match('/^\\[(.)+]$/', $domain) === 1) {
			// It's an address-literal
			$addressLiteral = substr($domain, 1, strlen($domain) - 2);
			$matchesIP	= array();

			// Extract IPv4 part from the end of the address-literal (if there is one)
			if (preg_match('/\\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $addressLiteral, $matchesIP) > 0) {
				$index = strrpos($addressLiteral, $matchesIP[0]);

				if ($index === 0) {
					// Nothing there except a valid IPv4 address, so...
					return true;
				} else {
					// Assume it's an attempt at a mixed address (IPv6 + IPv4)
					if ($addressLiteral[$index - 1] !== ':')	return false;	// Character preceding IPv4 address must be ':'
					if (substr($addressLiteral, 0, 5) !== 'IPv6:')	return false;	// RFC5321 section 4.1.3

					$IPv6		= substr($addressLiteral, 5, ($index ===7) ? 2 : $index - 6);
					$groupMax	= 6;
				}
			} else {
				// It must be an attempt at pure IPv6
				if (substr($addressLiteral, 0, 5) !== 'IPv6:')		return false;	// RFC5321 section 4.1.3
				$IPv6 = substr($addressLiteral, 5);
				$groupMax = 8;
			}

			$groupCount	= preg_match_all('/^[0-9a-fA-F]{0,4}|\\:[0-9a-fA-F]{0,4}|(.)/', $IPv6, $matchesIP);
			$index		= strpos($IPv6,'::');

			if ($index === false) {
				// We need exactly the right number of groups
				if ($groupCount !== $groupMax)				return false;	// RFC5321 section 4.1.3
			} else {
				if ($index !== strrpos($IPv6,'::'))			return false;	// More than one '::'
				$groupMax = ($index === 0 || $index === (strlen($IPv6) - 2)) ? $groupMax : $groupMax - 1;
				if ($groupCount > $groupMax)				return false;	// Too many IPv6 groups in address
			}

			// Check for unmatched characters
			array_multisort($matchesIP[1], SORT_DESC);
			if ($matchesIP[1][0] !== '')					return false;	// Illegal characters in address

			// It's a valid IPv6 address, so...
			return true;
		} else {
			// It's a domain name...

			// The syntax of a legal Internet host name was specified in RFC-952
			// One aspect of host name syntax is hereby changed: the
			// restriction on the first character is relaxed to allow either a
			// letter or a digit.
			//	(http://tools.ietf.org/html/rfc1123#section-2.1)
			//
			// NB RFC 1123 updates RFC 1035, but this is not currently apparent from reading RFC 1035.
			//
			// Most common applications, including email and the Web, will generally not
			// permit...escaped strings
			//	(http://tools.ietf.org/html/rfc3696#section-2)
			//
			// the better strategy has now become to make the "at least one period" test,
			// to verify LDH conformance (including verification that the apparent TLD name
			// is not all-numeric)
			//	(http://tools.ietf.org/html/rfc3696#section-2)
			//
			// Characters outside the set of alphabetic characters, digits, and hyphen MUST NOT appear in domain name
			// labels for SMTP clients or servers
			//	(http://tools.ietf.org/html/rfc5321#section-4.1.2)
			//
			// RFC5321 precludes the use of a trailing dot in a domain name for SMTP purposes
			//	(http://tools.ietf.org/html/rfc5321#section-4.1.2)
			$dotArray	= /*. (array[int]string) .*/ preg_split('/\\.(?=(?:[^\\"]*\\"[^\\"]*\\")*(?![^\\"]*\\"))/m', $domain);
			$partLength	= 0;
			$element	= ''; // Since we use $element after the foreach loop let's make sure it has a value

			if (count($dotArray) === 1)					return false;	// Mail host can't be a TLD

			foreach ($dotArray as $element) {
				// Remove any leading or trailing FWS
				$element = preg_replace("/^$FWS|$FWS\$/", '', $element);

				// Then we need to remove all valid comments (i.e. those at the start or end of the element
				$elementLength = strlen($element);

				if ($element[0] === '(') {
					$indexBrace = strpos($element, ')');
					if ($indexBrace !== false) {
						if (preg_match('/(?<!\\\\)[\\(\\)]/', substr($element, 1, $indexBrace - 1)) > 0) {
											return false;	// Illegal characters in comment
						}
						$element	= substr($element, $indexBrace + 1, $elementLength - $indexBrace - 1);
						$elementLength	= strlen($element);
					}
				}

				if ($element[$elementLength - 1] === ')') {
					$indexBrace = strrpos($element, '(');
					if ($indexBrace !== false) {
						if (preg_match('/(?<!\\\\)(?:[\\(\\)])/', substr($element, $indexBrace + 1, $elementLength - $indexBrace - 2)) > 0) {
											return false;	// Illegal characters in comment
						}
						$element	= substr($element, 0, $indexBrace);
						$elementLength	= strlen($element);
					}
				}

				// Remove any leading or trailing FWS around the element (inside any comments)
				$element = preg_replace("/^$FWS|$FWS\$/", '', $element);

				// What's left counts towards the maximum length for this part
				if ($partLength > 0) $partLength++;	// for the dot
				$partLength += strlen($element);

				// The DNS defines domain name syntax very generally -- a
				// string of labels each containing up to 63 8-bit octets,
				// separated by dots, and with a maximum total of 255
				// octets.
				//	(http://tools.ietf.org/html/rfc1123#section-6.1.3.5)
				if ($elementLength > 63)				return false;	// Label must be 63 characters or less

				// Each dot-delimited component must be atext
				// A zero-length element implies a period at the beginning or end of the
				// local part, or two periods together. Either way it's not allowed.
				if ($elementLength === 0)				return false;	// Dots in wrong place

				// Any ASCII graphic (printing) character other than the
				// at-sign ("@"), backslash, double quote, comma, or square brackets may
				// appear without quoting.  If any of that list of excluded characters
				// are to appear, they must be quoted
				//	(http://tools.ietf.org/html/rfc3696#section-3)
				//
				// If the hyphen is used, it is not permitted to appear at
				// either the beginning or end of a label.
				//	(http://tools.ietf.org/html/rfc3696#section-2)
				//
				// Any excluded characters? i.e. 0x00-0x20, (, ), <, >, [, ], :, ;, @, \, comma, period, "
				if (preg_match('/[\\x00-\\x20\\(\\)<>\\[\\]:;@\\\\,\\."]|^-|-$/', $element) > 0) {
											return false;
				}
			}

			if ($partLength > 255)						return false;	// Local part must be 64 characters or less

			if (preg_match('/^[0-9]+$/', $element) > 0)			return false;	// TLD can't be all-numeric

			// Check DNS?
			if ($checkDNS && function_exists('checkdnsrr')) {
				if (!(checkdnsrr($domain, 'A') || checkdnsrr($domain, 'MX'))) {
											return false;	// Domain doesn't actually exist
				}
			}
		}

		// Eliminate all other factors, and the one which remains must be the truth.
		//	(Sherlock Holmes, The Sign of Four)
		return true;
	}
}
// End of class ezUser_common

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
		list($this->id, $this->resetKey, $expiresString) = /*.(array[int]string).*/ unserialize($data);
		$this->expires = /*.(DateTime).*/ unserialize($expiresString);
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
		ACTION_ACCOUNT		= 'account',
		ACTION_ACCOUNTFORM	= 'accountform',
		ACTION_ACCOUNTWIZARD	= 'accountwizard',
		ACTION_BODY		= 'body',
		ACTION_CANCEL		= 'cancel',
		ACTION_CONTAINER	= 'container',
		ACTION_DASHBOARD	= 'dashboard',
		ACTION_JAVASCRIPT	= 'js',
		ACTION_MAIN		= 'controlpanel',
		ACTION_RESEND		= 'resend',		// Resend verification email
		ACTION_RESET		= 'reset',		// Process password reset link
		ACTION_RESETPASSWORD	= 'resetpassword',	// Initiate password reset processing
		ACTION_RESETREQUEST	= 'resetrequest',	// Request password reset form
		ACTION_RESULTFORM	= 'resultform',
		ACTION_RESULTTEXT	= 'resulttext',
		ACTION_SIGNIN		= 'signin',
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
		RESULT_UNKNOWNUSER	= 2,
		RESULT_BADPASSWORD	= 3,
		RESULT_UNKNOWNACTION	= 4,
		RESULT_NOACTION		= 5,
		RESULT_NOSESSION	= 6,
		RESULT_NOSESSIONCOOKIES	= 7,
		RESULT_STORAGEERR	= 8,
		RESULT_EMAILERR		= 9,

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
	private		$keys	= array (
						self::TAGNAME_USERNAME	,
						self::TAGNAME_EMAIL	,
						self::TAGNAME_ID	,
						self::TAGNAME_PASSWORD	,
						self::TAGNAME_STATUS	,
						self::TAGNAME_FIRSTNAME	,
						self::TAGNAME_LASTNAME	,
						self::TAGNAME_FULLNAME	,
						self::TAGNAME_VERIFICATIONKEY,
					);

	private		$values	= array (
						self::TAGNAME_USERNAME		=> '',
						self::TAGNAME_EMAIL		=> '',
						self::TAGNAME_ID		=> '',
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
// Substantive methods
// ---------------------------------------------------------------------------
	public /*.int.*/ function authenticate($passwordHash = '') {
		if (empty($passwordHash)) {
			// Sign out
			$this->authenticated = false;
			$result = self::RESULT_SUCCESS;
		} else {
			// Sign in
			$sessionHash = hash(self::HASH_FUNCTION, session_id() . hash(self::HASH_FUNCTION, $_SERVER['REMOTE_ADDR'] . $this->values[self::TAGNAME_PASSWORD]));
			$this->authenticated = ($passwordHash === $sessionHash);
			$result = ($this->authenticated) ? self::RESULT_SUCCESS : self::RESULT_BADPASSWORD;
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
		$this->values = /*.(array[string]string).*/ unserialize($data);
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

		if (!self::is_email($email)) {
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
	const	COOKIE_USERNAME		= 'ezuser1',
		COOKIE_PASSWORD		= 'ezuser2',
		COOKIE_AUTOSIGN		= 'ezuser3',

		// Storage locations
		STORAGE			= '.ezuser-data.php',
		SETTINGS		= '.ezuser-settings.php',

		// Keys for the configuration settings
		SETTINGS_ADMINEMAIL	= 'adminEmail',
		SETTINGS_PERSISTED	= 'persisted',
		SETTINGS_EMPTY		= 'empty',
		SETTINGS_ACCOUNTPAGE	= 'accountPage',
		SETTINGS_SECUREFOLDER	= 'secureFolder',

		// Miscellaneous constants
		DELIMITER_EMAIL		= '@';

	public static /*.ezUser_base.*/ function getSessionObject($instance = self::PACKAGE);
	public static /*.ezUser_base.*/ function save(/*.array[string]mixed.*/ $userData);
	public static /*.ezUser_base.*/ function lookup($needle = '', $tagName = '');
}

/**
 * This class encapsulates all the functions needed to manage the collection
 * of stored users. It interacts with the storage mechanism (e.g. database or
 * XML file).
 *
 * @package ezUser
 */
class ezUser_environment extends ezUser_base implements I_ezUser_environment {
// ---------------------------------------------------------------------------
// Configuration settings
// ---------------------------------------------------------------------------
	protected static /*.string.*/ function getInstanceId($container = self::PACKAGE) {
		return ($container === self::ACTION_MAIN || $container === self::PACKAGE) ? self::getPackage() : self::getPackage() . "-$container";
	}

	public static /*.ezUser_base.*/ function getSessionObject($instance = self::PACKAGE) {
		$instanceId = self::getInstanceId($instance);
		if (!array_key_exists($instanceId, $_SESSION)) $_SESSION[$instanceId] = new ezUser_base();
		return /*.(ezUser_base).*/ $_SESSION[$instanceId];
	}

	protected static /*.void.*/ function setSessionObject(/*.ezUser_base.*/ $ezUser, $instance = self::PACKAGE) {
		$instanceId = self::getInstanceId($instance);
		$_SESSION[$instanceId] = $ezUser;
	}

	protected static /*.string.*/ function thisURL() {
		return self::getURL(self::URL_MODE_PATH, self::getPackage() . '.php');
	}

	private static /*.array[string]string.*/ function loadConfig() {
		$ezUser		= self::getSessionObject();
		$config		= $ezUser->config();
		$settingsFile	= realpath(dirname(__FILE__) . self::URL_SEPARATOR . self::SETTINGS);

		// If configuration settings file doesn't exist then use default settings
		if (($settingsFile === false) || !is_file($settingsFile)) {
			$config[self::SETTINGS_EMPTY] = self::STRING_TRUE;
		} else {
			// Open the vessel
			$storage = new DOMDocument();
			$storage->load($settingsFile);
			$nodeList = $storage->getElementsByTagName('settings')->item(0)->childNodes;

			for ($i = 0; $i < $nodeList->length; $i++) {
				$node = $nodeList->item($i);

				if ($node->nodeType == XML_ELEMENT_NODE) {
					$config[$node->nodeName] = $node->nodeValue;
				}
			}
		}

		$config[self::SETTINGS_PERSISTED] = self::STRING_TRUE;
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
// Helper methods
// ---------------------------------------------------------------------------
	private static /*.DOMDocument.*/ function openStorage() {
		// Connect to database or whatever our storage mechanism is in this version

		// Where is the storage container?
		$storage_file = realpath(dirname(__FILE__)) . self::URL_SEPARATOR . self::STORAGE;

		// If storage container doesn't exist then create it
		if (!is_file($storage_file)) {
			$query = '?';
			$html = <<<HTML
<?php header("Location: /"); $query>
<users>
</users>
HTML;

			$handle = @fopen($storage_file, 'wb');
			if ($handle === false) exit(self::RESULT_STORAGEERR);
			fwrite($handle, $html);
			fclose($handle);
			chmod($storage_file, 0600);
		}

		// Open the container for use
		$storage = new DOMDocument();
		$storage->load($storage_file);

		return $storage;
	}

// ---------------------------------------------------------------------------
	private static /*.void.*/ function closeStorage(/*.DOMDocument.*/ $storage) {
		$storage_file = dirname(__FILE__) . self::URL_SEPARATOR . self::STORAGE;

		for ($attempt = 0; $attempt < 3; $attempt++) {
			$count = @$storage->save($storage_file);
			if ((bool) $count) break;
			sleep(1); // File may occasionally be locked by indexing/backups etc.
		}
}

// ---------------------------------------------------------------------------
	private static /*.DOMElement.*/ function findUser(/*.DOMDocument.*/ $storage, $needle = '', $tagName = '') {
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
			/*.object.*/ $userElement_PHPLint = $node->parentNode;	// PHPLint-compliant typecasting (yawn)
			$userElement = /*.(DOMElement).*/ $userElement_PHPLint;
			return $userElement;
		} else {
			return $storage->createElement(self::TAGNAME_USER);
		}
	}

// ---------------------------------------------------------------------------
// Substantive methods
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
//		return @mail($to, $subject, $message, $additional_headers);


ob_start(); // debug
		return mail($to, $subject, $message, $additional_headers); // debug
$ob = ob_get_clean(); // debug
echo "&#65533;&#65533;$ob&#65533;&#65533;"; // debug

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
	private static /*.void.*/ function addElement (/*.DOMDocument.*/ $storage, /*.DOMElement.*/ $record, /*.string.*/ $tagName, /*.string.*/ $value) {
		$record->appendChild($storage->createTextNode("\n\t\t")); // XML formatting
		$record->appendChild($storage->createElement($tagName, $value));
	}

	private static /*.DOMElement.*/ function createRecord(/*.DOMDocument.*/ $storage, /*.ezUser_base.*/ $ezUser) {
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
	private static /*.int.*/ function add(/*.ezUser_base.*/ $ezUser) {
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
	private static /*.int.*/ function update(/*.ezUser_base.*/ $ezUser) {
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
	protected static /*.boolean.*/ function verify_notify($username_or_email = '') {
		$ezUser = self::lookup($username_or_email);

		if ($ezUser->status() !== self::STATUS_PENDING) return false;	// Only send confirmation email to users who are pending verification

		// Message - SMTP needs CRLF not a bare LF (http://cr.yp.to/docs/smtplf.html)
		$URL		= self::getURL(self::URL_MODE_ALL, self::getPackage() . '.php');
		$host		= self::getURL(self::URL_MODE_HOST);
		$message	= "Somebody calling themselves " . $ezUser->fullName() . " created an account at $host using this email address.\r\n";
		$message	.= "If it was you please click on the following link to verify the account.\r\n\r\n";
		$message	.= "$URL?" . self::ACTION_VERIFY . "=" . $ezUser->verificationKey() . "\r\n\r\n";
		$message	.= "After you click the link your account will be fully functional.\r\n";

		// Send it
		return self::sendEmail($ezUser->email(), 'New account confirmation', $message);
	}

	protected static /*.void.*/ function verify_update(/*.ezUser_base.*/ $ezUser, /*.string.*/ $verificationKey) {
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
		$refererElements	= /*.(array[int]string).*/ array_slice(explode(self::URL_SEPARATOR, $referer), 3);
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
	private static /*.boolean.*/ function passwordReset_notify(/*.ezUser_base.*/ $ezUser) {
		$passwordReset = $ezUser->passwordReset();

		// Message
		$URL		= self::getURL(self::URL_MODE_ALL, self::getPackage() . '.php');
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

	protected static /*.void.*/ function passwordReset_update(/*.ezUser_base.*/ $ezUser, /*.string.*/$passwordHash) {
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
		BUTTON_TYPE_PREFERENCE	= 'preference',
		BUTTON_TYPE_HIDDEN	= 'hidden',

		// Message types
		MESSAGE_TYPE_DEFAULT	= 'message',
		MESSAGE_TYPE_TEXT	= 'text',

		// Message styles
		MESSAGE_STYLE_DEFAULT	= 'info',
		MESSAGE_STYLE_FAIL	= 'fail',
		MESSAGE_STYLE_TEXT	= 'text',
		MESSAGE_STYLE_PLAIN	= 'plain',

		// Miscellaneous constants
		DELIMITER_PLUS		= '+',
		PASSWORD_MASK		= '************',
		STRING_LEFT		= 'left',
		STRING_RIGHT		= 'right';


// Methods may be commented out to reduce the attack surface when they are
// not required. Uncomment them if you need them.
//	public static /*.void.*/	function getStatusText		(/*.int.*/ $status, $more = '');
//	public static /*.void.*/	function getResultText		(/*.int.*/ $result, $more = '');
//	public static /*.void.*/	function getStatusDescription	(/*.int.*/ $status, $more = '');
//	public static /*.void.*/	function getResultDescription	(/*.int.*/ $result, $more = '');
	public static /*.void.*/	function getResultForm		(/*.int.*/ $result, $more = '');
	public static /*.void.*/	function fatalError		(/*.int.*/ $result, $more = '');
	public static /*.void.*/	function getAccountForm		($mode = '', $newUser = false);
//	public static /*.void.*/	function getDashboard		();
//	public static /*.void.*/	function getSignInForm		();
//	public static /*.void.*/	function getControlPanel	($username = '');
//	public static /*.void.*/	function getStyleSheet		();
//	public static /*.void.*/	function getJavascript		($containerList = '');
	public static /*.void.*/	function getContainer		($action = self::ACTION_MAIN);
	public static /*.void.*/	function getAbout		();
//	public static /*.void.*/	function getSourceCode		();
	public static /*.void.*/	function signIn			($userData = /*.(array[string]mixed).*/ array());
}

/**
 * This class manages the HTML, CSS and Javascript that you can include in
 * your web pages to support user registration and authentication.
 *
 * @package ezUser
 */
class ezUser extends ezUser_environment implements I_ezUser {
// ---------------------------------------------------------------------------
// Functions for sending stuff to the browser
// ---------------------------------------------------------------------------
	private static /*.string.*/ function containerHeader() {return self::getPackage() . '-container';}

	private static /*.void.*/ function sendContent(/*.string.*/ $content, $container = '', $contentType = 'text/html') {
		// Send headers first
		if (!headers_sent()) {
			$package = self::getPackage();
			if ($container === '') $container = $package;
header("Container-length: " . strlen($container)); // debug
			header("Package: $package");
			header(self::containerHeader() . ": $container");
			header("Content-type: $contentType");
		}

		// Send content
		echo $content;

/* Comment out profiling statements if not needed
		// Send profiling data as a comment
		global $ezUser_profile;

		if (count($ezUser_profile) > 0) {
			$ezUser_profile['response'] = ezUser_time();

			if ($contentType === 'text/javascript' || $contentType === 'text/css') {
				$commentStart	= '/' . '*';
				$commentEnd	= '*' . '/';
			} else {
				$commentStart	= '<!--';
				$commentEnd	= '-->';
			}

			echo "\n$commentStart\n";
			$previous = reset($ezUser_profile);

			while (list($key, $value) = each($ezUser_profile)) {
				$elapsed	= round($value - $previous, 4);
				$previous	= $value;
				echo "$key\t$value\t$elapsed\n";
			}
			echo "$commentEnd\n";
		}
*/
	}

	private static /*.string.*/ function getXML($html = '', $container = '') {
		$package = self::getPackage();
		if (is_numeric($container) || $container === '') $container = $package; // If passed to sendXML as an array
		return "<$package container=\"$container\"><![CDATA[$html]]></$package>";
	}

	private static /*.void.*/ function sendXML(/*.mixed.*/ $content = '', $container = '') {
		if (is_array($content)) {
			// Expected array format is $content['container'] = '<html>'
			$package	= self::getPackage();
			$contentArray	= /*.(array[]string).*/ $content;
			$xmlArray	= /*.(array[]string).*/ array_map('ezUser::getXML', $contentArray, array_keys($contentArray)); // wrap each element
			$xml		= implode('', $xmlArray);
			$xml		= "<$package>$xml</$package>";

		} else {
			$xml = self::getXML((string) $content, $container);
		}

		self::sendContent($xml, $container, 'text/xml');
	}

// ---------------------------------------------------------------------------
// Functions that build common HTML fragments
// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlPage($body = '', $title = '', $sendToBrowser = false) {
		$package	= self::getPackage();
		$URL		= self::thisURL();
		$actionJs	= self::ACTION_JAVASCRIPT;
		$actionCSS	= self::ACTION_STYLESHEET;

		$html = <<<HTML
<!DOCTYPE html>
<html>

<head>
	<meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
	<title>$title</title>
	<script src="$URL?$actionJs"></script>
	<link type="text/css" rel="stylesheet" href="$URL?$actionCSS" title="$package">
</head>

<body class="$package">
$body
</body>

</html>
HTML;

		if ($sendToBrowser) {self::sendContent($html); return '';} else return $html;
	}

	private static /*.string.*/ function htmlContainer($action = self::ACTION_MAIN, $sendToBrowser = false) {
		$package	= self::getPackage();
		$baseAction	= explode('=', $action);
		$container	= self::getInstanceId($baseAction[0]);
		$actionCommand	= self::ACTION;
		$actionJs	= self::ACTION_JAVASCRIPT;
		$URL		= self::thisURL();

		$html = <<<HTML
	<div id="$container"></div>
	<script type="text/javascript">document.write(unescape('%3Cscript src="$URL?$actionCommand=$actionJs"%3E%3C/script%3E'));</script>
	<script type="text/javascript">$package.ajax.execute('$action');</script>
HTML;

		if ($sendToBrowser) {self::sendContent($html); return '';} else return $html;
	}

	private static /*.string.*/ function htmlInputText($styleFloat = self::STRING_RIGHT) {
		$package	= self::getPackage();
		$onKeyUp	= $package . '.keyUp';

		return <<<HTML
					class		=	"$package-text $package-$styleFloat"
					onkeyup		=	"$onKeyUp(event)"
					size		=	"40"
HTML;
	}

	private static /*.string.*/ function htmlButton(/*.string.*/ $type, $styleFloat = self::STRING_RIGHT, $verbose = false) {
		$package	= self::getPackage();
		$classVerbose	= ($verbose) ? " $package-" . self::BUTTON_TYPE_PREFERENCE . '-' . self::TAGNAME_VERBOSE : '';
		$styleString	= ($type === self::BUTTON_TYPE_HIDDEN) ? "$package-" . self::BUTTON_TYPE_ACTION . " $package-$type" : "$package-$type";
		$setButtonState	= $package . '.setButtonState';
		$onClick	= $package . '.click';

		return <<<HTML
					type		=	"button"
					class		=	"$package-button $package-$styleFloat $styleString$classVerbose $package-buttonstate-0"
					onclick		=	"$onClick(this)"
					onmouseover	=	"$setButtonState(this, 1, true)"
					onmouseout	=	"$setButtonState(this, 1, false)"
					onfocus		=	"$setButtonState(this, 2, true)"
					onblur		=	"$setButtonState(this, 2, false)"
HTML;
	}

	private static /*.string.*/ function htmlMessage($message = '', $style = self::MESSAGE_STYLE_DEFAULT, $container = '', $type = self::MESSAGE_TYPE_DEFAULT, $styleFloat = self::STRING_RIGHT) {
		$package	= self::getPackage();
		$style		= ($message === '') ? 'hidden' : $style;
		$message	= "<p class=\"$package-message-$style\">$message</p>";
		$id		= ($container === '') ? "$package-$type" : "$container-$type";
		$onClick	= $package . '.click';

		return <<<HTML
				<div id="$id" class="$package-$type $package-$styleFloat" onclick="$onClick(this)">$message</div>
HTML;
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

	private static /*.string.*/ function resultText(/*.int.*/ $result, $more = '', $sendToBrowser = false) {
		switch ($result) {
			// Authentication results
			case self::RESULT_UNDEFINED:		$text = "Undefined";					break;
			case self::RESULT_SUCCESS:		$text = "Success";					break;
			case self::RESULT_UNKNOWNUSER:		$text = "Username not recognised";			break;
			case self::RESULT_BADPASSWORD:		$text = "Password is wrong";				break;
			case self::RESULT_UNKNOWNACTION:	$text = "Unrecognised action";				break;
			case self::RESULT_NOACTION:		$text = "No action specified";				break;
			case self::RESULT_NOSESSION:		$text = "No session data available";			break;
			case self::RESULT_NOSESSIONCOOKIES:	$text = "Session cookies are not enabled";		break;
			case self::RESULT_STORAGEERR:		$text = "Error with stored user details";		break;
			case self::RESULT_EMAILERR:		$text = "Error sending email";				break;

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
			default:				$text = "Unknown result code";				break;
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
			case self::RESULT_EMAILFORMATERR:	$text = "The format of the email address you entered was incorrect. Email addresses should be in the form <em>joe.smith@example.com</em>";
															break;
			default:				$text = self::resultText($result);			break;
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
	private static /*.string.*/ function htmlAccountForm($mode = '', $newUser = false, $wizard = false, $sendToBrowser = false) {
		/* Comment out profiling statements if not needed
		global $ezUser_profile;
		$ezUser_profile[self::ACTION_ACCOUNT . '-start'] = ezUser_time();
		*/

		$package		= self::getPackage();
		$action			= self::ACTION_ACCOUNT;
		$actionResend		= self::ACTION_RESEND;
		$actionValidate		= self::ACTION_VALIDATE;
		$accountForm		= self::getInstanceId($action);
		$container		= ($wizard) ? $package : $accountForm;

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
		$passwordOnFocus	= $package . '.passwordFocus';
		$passwordOnBlur		= $package . '.passwordBlur';
		$htmlInputText		= self::htmlInputText();
		$messageShort		= self::htmlMessage('* reqd', self::MESSAGE_STYLE_PLAIN, $accountForm);
		$resendButton		= '';

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
			$htmlOtherButton	= "\t\t\t\t<input id=\"$accountForm-$modeCancel\" data-$package-action=\"$action=$modeCancel\" value=\"Cancel\"\n\t\t\t\t\ttabindex\t=\t\"3219\"\n$htmlButtonAction\n\t\t\t\t/>\n";
			$useSavedPassword	= false;
			$messageLong		= self::htmlMessage('', self::MESSAGE_STYLE_TEXT, $accountForm, self::MESSAGE_TYPE_TEXT);
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
			$htmlOtherButton	= "\t\t\t\t<input id=\"$accountForm-$modeNew\" data-$package-action=\"$action=$modeNew\" value=\"New\"\n\t\t\t\t\ttabindex\t=\t\"3219\"\n$htmlButtonAction\n\t\t\t\t/>\n";
			$useSavedPassword	= false;
			$newUser		= false;

			if ($result === self::RESULT_SUCCESS || $result === self::RESULT_UNDEFINED) {
				// Show status information
				$status		= $ezUser->status();
				$messageLong	= ($status === self::STATUS_CONFIRMED) ? '' : self::statusDescription($status);
				$messageLong	= self::htmlMessage($messageLong, self::MESSAGE_STYLE_TEXT, $accountForm, self::MESSAGE_TYPE_TEXT);

				if ($status === self::STATUS_PENDING) $resendButton = "\n\t\t\t\t<input id=\"$accountForm-$actionResend\" data-$package-action=\"$actionResend\" value=\"Resend\"\n\t\t\t\t\ttabindex\t=\t\"3219\"\n$htmlButtonAction\n\t\t\t\t/>";
			} else {
				// Show result information
				$messageLong	= self::resultDescription($result);
				$messageLong	= self::htmlMessage($messageLong, self::MESSAGE_STYLE_FAIL, $accountForm, self::MESSAGE_TYPE_TEXT);
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
			$htmlOtherButton	= "\t\t\t\t<input id=\"$accountForm-$modeCancel\" data-$package-action=\"$action=$modeCancel\" value=\"Cancel\"\n\t\t\t\t\ttabindex\t=\t\"3219\"\n$htmlButtonAction\n\t\t\t\t/>\n";
			$useSavedPassword	= $newUser;

			if ($result === self::RESULT_SUCCESS || $result === self::RESULT_UNDEFINED) {
				$messageLong	= self::htmlMessage('', self::MESSAGE_STYLE_TEXT, $accountForm, self::MESSAGE_TYPE_TEXT);
			} else {
				// Show result information
				$messageLong	= self::resultDescription($result);
				$messageLong	= self::htmlMessage($messageLong, self::MESSAGE_STYLE_FAIL, $accountForm, self::MESSAGE_TYPE_TEXT);
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
			$styleHidden	= " $package-hidden";
			$htmlNavigation = <<<HTML
				<input id="$accountForm-next" data-$package-action="next" value="Next &gt;"
					tabindex	=	"3218"
$htmlButtonAction
				/>
				<input id="$accountForm-back" data-$package-action="back" value="&lt; Back"
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
			<fieldset id="$accountForm-fieldset-3" class="$package-fieldset$styleHidden">
$messageLong$resendButton
				<input id="$accountForm-$tagNewUser"		type="hidden" value="$newString" />
				<input id="$accountForm-$tagWizard"		type="hidden" value="$wizardString" />
				<input id="$accountForm-$tagUseSavedPassword"	type="hidden" value="$useSavedPasswordString" />
			</fieldset>

HTML;

		$buttonsFieldset = <<<HTML
			<fieldset class="$package-fieldset">
$messageShort
				<input id="$accountForm-$buttonId" data-$package-action="$buttonAction" value="$buttonText"
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
		<form id="$accountForm-form" class="$package-form" onsubmit="return false">
			<fieldset id="$accountForm-fieldset-1" class="$package-fieldset">
				<input id= "$accountForm-$tagEmail"
					tabindex	=	"3211"
					value		=	"$email"
					type		=	"text"
$disabled$htmlInputText
				/>
				<label class="$package-label $package-$stringRight" for="$accountForm-$tagEmail">* Email address:</label>
				<input id= "$accountForm-$tagFirstName"
					tabindex	=	"3212"
					value		=	"$firstName"
					type		=	"text"
$disabled$htmlInputText
				/>
				<label class="$package-label $package-$stringRight" for="$accountForm-$tagFirstName">First name:</label>
				<input id= "$accountForm-$tagLastName"
					tabindex	=	"3213"
					value		=	"$lastName"
					type		=	"text"
$disabled$htmlInputText
				/>
				<label class="$package-label $package-$stringRight" for="$accountForm-$tagLastName">Last name:</label>
			</fieldset>
			<fieldset id="$accountForm-fieldset-2" class="$package-fieldset$styleHidden">
				<input id= "$accountForm-$tagUsername"
					tabindex	=	"3214"
					value		=	"$username"
					type		=	"text"
$disabled$htmlInputText
				/>
				<label class="$package-label $package-$stringRight" for="$accountForm-$tagUsername">* Username:</label>
				<input id= "$accountForm-$tagPassword"
					tabindex	=	"3215"
					value		=	"$password"
					type		=	"password"
					onfocus		=	"$passwordOnFocus(this)"
					onblur		=	"$passwordOnBlur(this)"
$disabled$htmlInputText
				/>
				<label class="$package-label $package-$stringRight" for="$accountForm-$tagPassword">* Password:</label>
				<input id= "$accountForm-confirm"
					tabindex	=	"3216"
					value		=	"$password"
					type		=	"password"
					onfocus		=	"$passwordOnFocus(this)"
					onblur		=	"$passwordOnBlur(this)"
$disabled$htmlInputText
				/>
				<label class="$package-label $package-$stringRight" for="$accountForm-$tagConfirm">* Confirm password:</label>
			</fieldset>
$bottomFieldsets		</form>
HTML;

		/* Comment out profiling statements if not needed
		$ezUser_profile[self::ACTION_ACCOUNT . '-end'] = ezUser_time();
		*/

		if ($sendToBrowser) {self::sendXML($html, $container); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlDashboard($sendToBrowser = false) {
		$package		= self::getPackage();
		$action			= self::ACTION_DASHBOARD;
		$actionSignOut		= self::ACTION_SIGNOUT;
		$actionAccountForm	= self::ACTION_ACCOUNTFORM;
		$tagFullName		= self::TAGNAME_FULLNAME;
		$htmlButtonPreference	= self::htmlButton(self::BUTTON_TYPE_PREFERENCE);
		$message		= self::htmlMessage();
		$ezUser			= self::getSessionObject();
		$fullName		= $ezUser->fullName();

		$html = <<<HTML
		<form id="$package-$action-form" class="$package-form" onsubmit="return false">
			<fieldset class="$package-fieldset">
				<input id="$package-$actionSignOut" data-$package-action="$actionSignOut" value="Sign out"
					tabindex	=	"3222"
$htmlButtonPreference
				/>
				<input id="$package-$actionAccountForm" data-$package-action="$actionAccountForm" value="My account"
					tabindex	=	"3221"
$htmlButtonPreference
				/>
				<div id="$package-$tagFullName" class="$package-$tagFullName">$fullName</div>
			</fieldset>
			<fieldset class="$package-fieldset">
$message
			</fieldset>
		</form>
HTML;

		if ($sendToBrowser) {self::sendXML($html); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlSignInForm($username = '', $sendToBrowser = false) {
		$verbose		= false;	// Set to true to let the user see detailed result information (recommended setting is false)

		$package		= self::getPackage();
		$action			= self::ACTION_SIGNIN;
		$actionAccountForm	= self::ACTION_ACCOUNTFORM;
		$actionResetRequest	= self::ACTION_RESETREQUEST;
		$tagUsername		= self::TAGNAME_USERNAME;
		$tagPassword		= self::TAGNAME_PASSWORD;
		$tagRememberMe		= self::TAGNAME_REMEMBERME;
		$tagStaySignedIn	= self::TAGNAME_STAYSIGNEDIN;
		$tagVerbose		= self::TAGNAME_VERBOSE;

		$stringRight		= self::STRING_RIGHT;
		$htmlButtonAction	= self::htmlButton(self::BUTTON_TYPE_ACTION);
		$htmlButtonPreference	= self::htmlButton(self::BUTTON_TYPE_PREFERENCE);
		$passwordOnFocus	= $package . '.passwordFocus';
		$passwordOnBlur		= $package . '.passwordBlur';
		$htmlInputText		= self::htmlInputText();
		$ezUser			= self::getSessionObject();
		$result			= $ezUser->result();

		if ($result <= self::RESULT_SUCCESS) {
			$message = self::htmlMessage();
			$verboseHTML = "";
		} else {
			$ezUser->setResult(self::RESULT_UNDEFINED);
			$username = $ezUser->username();
			$message = self::htmlMessage("Check username &amp; password", self::MESSAGE_STYLE_FAIL);

			if ($verbose) {
				$verboseHTML = self::htmlButton(self::BUTTON_TYPE_PREFERENCE, $stringRight, true);
				$verboseHTML = <<<HTML
				<input id="$package-$tagVerbose" value="$result"
$verboseHTML
				/>
HTML;
			} else {
				$verboseHTML = '';
			}
		}

		$password = '';

		$html = <<<HTML
		<form id="$package-$action-form" class="$package-form" onsubmit="return false">
			<fieldset class="$package-fieldset">
				<input id= "$package-$tagUsername"
					tabindex	=	"3201"
					value		=	"$username"
					type		=	"text"
$htmlInputText
				/>
				<label class="$package-label $package-$stringRight" for="$package-$tagUsername">Username:</label>
				<input id= "$package-$tagPassword"
					tabindex	=	"3202"
					value		=	"$password"
					type		=	"password"
					onfocus		=	"$passwordOnFocus(this)"
					onblur		=	"$passwordOnBlur(this)"
$htmlInputText
				/>
				<label class="$package-label $package-$stringRight" for="$package-$tagPassword">Password:</label>
$verboseHTML			</fieldset>
			<fieldset class="$package-fieldset">
$message
				<input id="$package-$actionAccountForm" data-$package-action="$actionAccountForm" value="Register"
					tabindex	=	"3204"
$htmlButtonAction
				/>
				<input id="$package-$action" data-$package-action="$action" value="Sign in"
					tabindex	=	"3203"
$htmlButtonAction
				/>
			</fieldset>
			<fieldset class="$package-fieldset">
				<input id="$package-$tagStaySignedIn" value="Stay signed in"
					tabindex	=	"3207"
$htmlButtonPreference
				/>
				<input id="$package-$tagRememberMe" value="Remember me"
					tabindex	=	"3206"
$htmlButtonPreference
				/>
				<input id="$package-$actionResetRequest" data-$package-action="$actionResetRequest" value="Reset password"
					tabindex	=	"3205"
$htmlButtonPreference
				/>
			</fieldset>
		</form>
HTML;

		if ($sendToBrowser) {self::sendXML($html); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlControlPanel($username = '', $sendToBrowser = false) {
		$ezUser = self::getSessionObject();
		$html = ($ezUser->authenticated()) ? self::htmlDashboard() : self::htmlSignInForm($username);
		if ($sendToBrowser) {self::sendXML($html); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlResetRequest ($username = '', $sendToBrowser = false) {
		$package		= self::getPackage();
		$action			= self::ACTION_RESETREQUEST;
		$actionCancel		= self::ACTION_CANCEL;
		$actionResetPassword	= self::ACTION_RESETPASSWORD;
		$actionMain		= self::ACTION_MAIN;
		$tagUsername		= self::TAGNAME_USERNAME;
		$htmlButtonPreference	= self::htmlButton(self::BUTTON_TYPE_PREFERENCE);
		$stringLeft		= self::STRING_LEFT;
		$htmlInputText		= self::htmlInputText($stringLeft);

		$html = <<<HTML
		<form id="$package-$action-form" class="$package-form" onsubmit="return false">
			<fieldset class="$package-fieldset-float">
					<label class="$package-label $package-$stringLeft" for="$package-$tagUsername">Username or email address:</label>
					<input style="clear:both;" id="$package-$tagUsername"
					tabindex	=	"3241"
					value		=	"$username"
					type		=	"text"
$htmlInputText
				/>
			</fieldset>
			<fieldset class="$package-fieldset">
				<input id="$package-$actionCancel" data-$package-action="$actionMain" value="Cancel"
					tabindex	=	"3243"
$htmlButtonPreference
				/>
				<input id="$package-$actionResetPassword" data-$package-action="$actionResetPassword" value="Reset password"
					tabindex	=	"3242"
$htmlButtonPreference
				/>
			</fieldset>
		</form>
HTML;

		if ($sendToBrowser) {self::sendXML($html); return '';} else return $html;
	}

/**
 *
 * Password reset form (& confirmation form below)
 *
 * This function is slightly different as it send the HTML for an entire
 * page, rather than the contents of a DIV. This is because this form
 * is displayed in response to the user clicking on a link in an email.
 * We have no context in which to display it and no knowledge of the
 * site that ezUser is living in, so we are forced to display a bare page.
 *
 * @param boolean $sendToBrowser
 */
	private static /*.string.*/ function htmlResetPassword (/*.ezUser_base.*/ $ezUser, $sendToBrowser = false) {
		$package		= self::getPackage();
		$action			= self::ACTION_RESET;
		$tagPassword		= self::TAGNAME_PASSWORD;
		$tagConfirm		= self::TAGNAME_CONFIRM;
		$container		= self::getInstanceId($action);
		$htmlInputText		= self::htmlInputText();
		$htmlButtonPreference	= self::htmlButton(self::BUTTON_TYPE_ACTION);
		$stringRight		= self::STRING_RIGHT;
		$passwordOnFocus	= $package . '.passwordFocus';
		$passwordOnBlur		= $package . '.passwordBlur';
		$fullName		= $ezUser->fullName();
		$message		= self::htmlMessage('', self::MESSAGE_STYLE_PLAIN, $package, self::MESSAGE_TYPE_TEXT);

		$html = <<<HTML
	<div id="$package">
		<h4 class="$package-heading">Welcome $fullName</h4>
		<p class="$package-message-plain">Please enter a new password for your account:</p>
		<form id="$container-form" class="$package-form" onsubmit="return false">
			<fieldset class="$package-fieldset">
				<input id= "$container-$tagPassword"
					tabindex	=	"3241"
					value		=	""
					type		=	"password"
					onfocus		=	"$passwordOnFocus(this)"
					onblur		=	"$passwordOnBlur(this)"
$htmlInputText
				/>
				<label class="$package-label $package-$stringRight" for="$container-$tagPassword">Password:</label>
				<input id= "$container-confirm"
					tabindex	=	"3242"
					value		=	""
					type		=	"password"
					onfocus		=	"$passwordOnFocus(this)"
					onblur		=	"$passwordOnBlur(this)"
$htmlInputText
				/>
				<label class="$package-label $package-$stringRight" for="$container-$tagConfirm">Confirm password:</label>
			</fieldset>
			<fieldset class="$package-fieldset">
				<input id="$container-OK" data-$package-action="$action" value="OK"
					tabindex	=	"3243"
$htmlButtonPreference
				/>
			</fieldset>
			<fieldset class="$package-fieldset">
$message
			</fieldset>
		</form>
	</div>
HTML;

		$html = self::htmlPage($html, 'Reset your password');

		if ($sendToBrowser) {self::sendContent($html); return '';} else return $html;
	}

	private static /*.string.*/ function htmlMessagePage (/*.string.*/ $title, /*.string.*/ $message, $sendToBrowser = false) {
		$package		= self::getPackage();
		$message		= self::htmlMessage($message, self::MESSAGE_STYLE_PLAIN, $package, self::MESSAGE_TYPE_TEXT);

		$html = <<<HTML
	<div id="$package">
		<h4 class="$package-heading">$title</h4>
$message
	</div>
HTML;

		$html = self::htmlPage($html, $title);

		if ($sendToBrowser) {self::sendContent($html); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlMessageForm ($message = '', $action = self::ACTION_MAIN, $sendToBrowser = false) {
		$package		= self::getPackage();
		$actionMain		= self::ACTION_MAIN;
		$htmlButtonPreference	= self::htmlButton(self::BUTTON_TYPE_PREFERENCE);
		$message		= self::htmlMessage($message, self::MESSAGE_STYLE_TEXT, '', self::MESSAGE_TYPE_TEXT);

		$html = <<<HTML
		<form id="$package-$action-form" class="$package-form" onsubmit="return false">
			<fieldset class="$package-fieldset">
$message
				<input id="$package-OK" data-$package-action="$actionMain" value="OK"
					tabindex	=	"3241"
$htmlButtonPreference
				/>
			</fieldset>
		</form>
HTML;

		if ($sendToBrowser) {self::sendXML($html); return '';} else return $html;
	}

	private static /*.string.*/ function htmlResultForm (/*.int.*/ $result, $more = '', $sendToBrowser = false) {
		$html = self::htmlMessageForm(self::resultText($result, $more), self::ACTION_RESULTFORM);
		if ($sendToBrowser) {self::sendXML($html); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
	private	static /*.string.*/ function htmlAbout($sendToBrowser = false) {
		$packageLower	= self::getPackage();
		$package	= self::getPackage(self::PACKAGE_CASE_CAMEL);
		$php		= self::getFileContents("$packageLower.php", 0, NULL, -1, 4096);

		// Get descriptive text from docblock
		$tagStart	= strpos($php, "/**\r\n * ");

		if ($tagStart === false) {
			$html = self::htmlPage('Development version', "$package - About");
			if ($sendToBrowser) {self::sendContent($html); return '';} else return $html;
		}

		$tagStart	+= 8;
		$tagEnd		= strpos($php, "\r\n", $tagStart);
		$short		= substr($php, $tagStart, $tagEnd - $tagStart);

		$tagStart	= $tagEnd + 7;
		$tagPos		= strpos($php, "\r\n * @") + 2;
		$long		= substr($php, $tagStart, $tagPos - $tagStart - 7);
		$long		= str_replace(' * ', '' , $long);

		// Get tags and values from docblock
		do {
			$tagStart	= $tagPos + 4;
			$tagEnd		= strpos($php, "\t", $tagStart);
			$tag		= substr($php, $tagStart, $tagEnd - $tagStart);
			$offset		= $tagEnd + 1;
			$tagPos		= strpos($php, "\r\n", $offset);		
			$value		= substr($php, $tagEnd + 1, $tagPos - $tagEnd - 1);
			$$tag		= htmlspecialchars($value);
			$tagPos		= strpos($php, " * @", $offset);		
		} while ($tagPos);

		$body = <<<HTML
	<h1>$package</h1>
	<h2>$short</h2>
	<pre class="$packageLower">$long</pre>
	<hr />
	<table>
		<tr>
			<td>Version</td>
			<td>$version</td>
		</tr>
		<tr>
			<td>Copyright</td>
			<td>$copyright</td>
		</tr>
		<tr>
			<td>License</td>
			<td>$license</td>
		</tr>
		<tr>
			<td>Author</td>
			<td>$author</td>
		</tr>
		<tr>
			<td>Link</td>
			<td>$link</td>
		</tr>
	</table>	
HTML;

		$html = self::htmlPage($body, "$package - About");

		if ($sendToBrowser) {self::sendContent($html); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
	private	static /*.string.*/ function htmlSourceCode($sendToBrowser = false) {
		$html = (string) highlight_file(__FILE__, 1);
		if ($sendToBrowser) {self::sendContent($html); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
// CSS & Javascript
// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlStyleSheet($sendToBrowser = false) {
		$package		= self::getPackage();
		$container		= self::getInstanceId(self::ACTION_ACCOUNT);
		$tagFullName		= self::TAGNAME_FULLNAME;
		$tagVerbose		= self::TAGNAME_VERBOSE;
		$buttonTypeAction	= self::BUTTON_TYPE_ACTION;
		$buttonTypePreference	= self::BUTTON_TYPE_PREFERENCE;

		$css = <<<CSS
@charset "UTF-8";
/**
 * Enables user registration and authentication for a website
 * 
 * This code has three principle design goals:
 * 
 * 	1. To make it easy for people to register and sign in to your site.
 * 	2. To make it easy for you to add this functionality to your site.
 * 	3. To make it easy for you to administer the user database on your site.
 * 
 * Other design goals, such as run-time efficiency, are important but secondary to
 * these.
 * 
 * Copyright (c) 2008-2009, Dominic Sayers							<br>
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 * 
 * 	- Redistributions of source code must retain the above copyright notice,
 * 	  this list of conditions and the following disclaimer.
 * 	- Redistributions in binary form must reproduce the above copyright notice,
 * 	  this list of conditions and the following disclaimer in the documentation
 * 	  and/or other materials provided with the distribution.
 * 	- Neither the name of Dominic Sayers nor the names of its contributors may be
 * 	  used to endorse or promote products derived from this software without
 * 	  specific prior written permission.
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
 * @author	Dominic Sayers <dominic_sayers@hotmail.com>
 * @copyright	2009 Dominic Sayers
 * @license	http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link	http://code.google.com/p/ezuser/
 * @version	0.22 - Newer version of common functions class
 */

.dummy {} /* Webkit is ignoring the first item so we'll put a dummy one in */

.$package {
	margin:0;
	padding:0;
	font-family:Segoe UI, Calibri, Arial, Helvetica, sans-serif;
	font-size:11px;
}

pre.$package {
	font-family:Consolas, Courier New, Courier, fixedsys;
}

.$package-left		{float:left;}
.$package-right		{float:right;}
.$package-hidden	{display:none;}
.$package-heading	{padding:6px;margin:0 0 1em 0;}

div#$package {
	font-family:Segoe UI, Calibri, Arial, Helvetica, sans-serif;
	font-size:11px;
	line-height:100%;
	float:left;
}

div#$container {
	font-family:Segoe UI, Calibri, Arial, Helvetica, sans-serif;
	font-size:12px;
	line-height:100%;
	float:left;
}

div.$package-message {
/*	width:154px;		*/
	float:left;
/*	padding:6px;		*/
	text-align:center;
	font-weight:normal;
/*	visibility:hidden;	*/
}

div.$package-text {
	width:286px;
/*	height:48px;		*/
	float:left;
	padding:0;
	text-align:justify;
/*	visibility:hidden;	*/
	margin:7px 0 7px 0;
	line-height:16px;
}

p.$package-message-plain	{margin:0;padding:6px;}
p.$package-message-info	{margin:0;padding:6px;background-color:#FFCC00;color:#000000;}
p.$package-message-text	{margin:0;padding:6px;background-color:#EEEEEE;color:#000000;}
p.$package-message-fail	{margin:0;padding:6px;background-color:#FF0000;color:#FFFFFF;font-weight:bold;}
p.$package-message-hidden	{display:none;}

div.$package-$tagFullName {
	float:right;
	margin:4px 0 0 0;
	padding:6px;
	color:#555555;
	font-weight:bold;
}

form.$package-form			{margin:0;}
fieldset.$package-fieldset		{margin:0;padding:0;border:0;clear:both;float:right;width:286px;}
fieldset.$package-fieldset-float	{margin:0;padding:0;border:0;clear:both;float:right;}
label.$package-label			{padding:4px;}

input.$package-text {
	font-size:11px;
	width:160px;
	margin-bottom:4px;
}

input.$package-button {
	padding:2px;
	font-family:Segoe UI, Calibri, Arial, Helvetica, sans-serif;
	border-style:solid;
	border-width:1px;
	cursor:pointer;
}

input.$package-$buttonTypeAction {
	font-size:12px;
	width:52px;
	margin:0 0 0 6px;
}

input.$package-$buttonTypePreference {
	font-size:10px;
	margin:4px 0 0 6px;
}

input.$package-preference-$tagVerbose {float:left;margin:0;}

input.$package-buttonstate-0 {background-color:#FFFFFF;color:#444444;border-color:#666666 #333333 #333333 #666666;}
input.$package-buttonstate-1 {background-color:#FFFFFF;color:#444444;border-color:#FF9900 #CC6600 #CC6600 #FF9900;}
input.$package-buttonstate-2 {background-color:#FFFFFF;color:#444444;border-color:#666666 #333333 #333333 #666666;}
input.$package-buttonstate-3 {background-color:#FFFFFF;color:#444444;border-color:#FF9900 #CC6600 #CC6600 #FF9900;}
input.$package-buttonstate-4 {background-color:#CCCCCC;color:#222222;border-color:#333333 #666666 #666666 #333333;}
input.$package-buttonstate-5 {background-color:#CCCCCC;color:#222222;border-color:#CC6600 #FF9900 #FF9900 #CC6600;}
input.$package-buttonstate-6 {background-color:#CCCCCC;color:#222222;border-color:#333333 #666666 #666666 #333333;}
input.$package-buttonstate-7 {background-color:#CCCCCC;color:#222222;border-color:#CC6600 #FF9900 #FF9900 #CC6600;}

CSS;

		if ($sendToBrowser) {self::sendContent($css, '', 'text/css'); return '';} else return $css;
	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlJavascript($containerList = '', $sendToBrowser = false) {
		$package		= self::getPackage();
		$accountForm		= self::getInstanceId(self::ACTION_ACCOUNT);

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
		$actionAccountForm	= self::ACTION_ACCOUNTFORM;
		$actionAccountWizard	= self::ACTION_ACCOUNTWIZARD;
		$actionValidate		= self::ACTION_VALIDATE;
		$actionSignIn		= self::ACTION_SIGNIN;
		$actionCancel		= self::ACTION_CANCEL;
		$actionCSS		= self::ACTION_STYLESHEET;
		$actionResultForm	= self::ACTION_RESULTFORM;
		$actionResend		= self::ACTION_RESEND;
		$actionReset		= self::ACTION_RESET;
		$actionResetPassword	= self::ACTION_RESETPASSWORD;
		$actionResetRequest	= self::ACTION_RESETREQUEST;

		$modeEdit		= self::ACCOUNT_MODE_EDIT;

		$messageTypeText	= self::MESSAGE_TYPE_TEXT;
		$delimPlus		= self::DELIMITER_PLUS;
		$stringRight		= self::STRING_RIGHT;
		$stringTrue		= self::STRING_TRUE;
		$stringFalse		= self::STRING_FALSE;
		$passwordMask		= self::PASSWORD_MASK;

		$accountPage		= self::getSetting(self::SETTINGS_ACCOUNTPAGE);
		$accountClick		= ($accountPage === '') ? "$package.ajax.execute('$actionAccountWizard')" : "window.location = '$folder/$accountPage'";
		$containerHeader	= self::containerHeader();

		// Append code to request container content
		if ($containerList === '') {
			$immediateJavascript = '';
		} else {
			// Space-separated list of containers to fill
			$immediateJavascript = "$package.ajax.execute('" . (string) str_replace(self::DELIMITER_SPACE, self::DELIMITER_PLUS, $containerList) . "');";
		}

		$js = <<<JAVASCRIPT
/**
 * Enables user registration and authentication for a website
 * 
 * This code has three principle design goals:
 * 
 * 	1. To make it easy for people to register and sign in to your site.
 * 	2. To make it easy for you to add this functionality to your site.
 * 	3. To make it easy for you to administer the user database on your site.
 * 
 * Other design goals, such as run-time efficiency, are important but secondary to
 * these.
 * 
 * Copyright (c) 2008-2009, Dominic Sayers							<br>
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 * 
 * 	- Redistributions of source code must retain the above copyright notice,
 * 	  this list of conditions and the following disclaimer.
 * 	- Redistributions in binary form must reproduce the above copyright notice,
 * 	  this list of conditions and the following disclaimer in the documentation
 * 	  and/or other materials provided with the distribution.
 * 	- Neither the name of Dominic Sayers nor the names of its contributors may be
 * 	  used to endorse or promote products derived from this software without
 * 	  specific prior written permission.
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
 * @author	Dominic Sayers <dominic_sayers@hotmail.com>
 * @copyright	2009 Dominic Sayers
 * @license	http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link	http://code.google.com/p/ezuser/
 * @version	0.22 - Newer version of common functions class
 */

/*jslint eqeqeq: true, immed: true, nomen: true, onevar: true, regexp: true, undef: true */
/*global window, document, event, ActiveXObject */ // For JSLint

// ---------------------------------------------------------------------------
// The main ezuser client-side class
// ---------------------------------------------------------------------------
function C_ezuser() {
	if (!(this instanceof arguments.callee)) {throw Error('Constructor called as a function');}

/**
*
*  Secure Hash Algorithm (SHA256)
*  http://www.webtoolkit.info/
*
*  Original code by Angel Marin, Paul Johnston.
*
**/

function SHA256(s){

	var chrsz   = 8;
	var hexcase = 0;

	function safe_add (x, y) {
		var lsw = (x & 0xFFFF) + (y & 0xFFFF);
		var msw = (x >> 16) + (y >> 16) + (lsw >> 16);
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
		var K = new Array(0x428A2F98, 0x71374491, 0xB5C0FBCF, 0xE9B5DBA5, 0x3956C25B, 0x59F111F1, 0x923F82A4, 0xAB1C5ED5, 0xD807AA98, 0x12835B01, 0x243185BE, 0x550C7DC3, 0x72BE5D74, 0x80DEB1FE, 0x9BDC06A7, 0xC19BF174, 0xE49B69C1, 0xEFBE4786, 0xFC19DC6, 0x240CA1CC, 0x2DE92C6F, 0x4A7484AA, 0x5CB0A9DC, 0x76F988DA, 0x983E5152, 0xA831C66D, 0xB00327C8, 0xBF597FC7, 0xC6E00BF3, 0xD5A79147, 0x6CA6351, 0x14292967, 0x27B70A85, 0x2E1B2138, 0x4D2C6DFC, 0x53380D13, 0x650A7354, 0x766A0ABB, 0x81C2C92E, 0x92722C85, 0xA2BFE8A1, 0xA81A664B, 0xC24B8B70, 0xC76C51A3, 0xD192E819, 0xD6990624, 0xF40E3585, 0x106AA070, 0x19A4C116, 0x1E376C08, 0x2748774C, 0x34B0BCB5, 0x391C0CB3, 0x4ED8AA4A, 0x5B9CCA4F, 0x682E6FF3, 0x748F82EE, 0x78A5636F, 0x84C87814, 0x8CC70208, 0x90BEFFFA, 0xA4506CEB, 0xBEF9A3F7, 0xC67178F2);
		var HASH = new Array(0x6A09E667, 0xBB67AE85, 0x3C6EF372, 0xA54FF53A, 0x510E527F, 0x9B05688C, 0x1F83D9AB, 0x5BE0CD19);
		var W = new Array(64);
		var a, b, c, d, e, f, g, h, i, j;
		var T1, T2;

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

	this.getControl	= function (id)		{return document.getElementById(id);};
	this.getValue	= function (id)		{return this.getControl(id).value;};
	this.setValue	= function (id, value)	{this.getControl(id).value = value;};

	var that = this;

// ---------------------------------------------------------------------------
	function fireEvent(control, eventType, detail) {
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

// ---------------------------------------------------------------------------
	function setFocus(control) {
		var doEvent;

		if (control.disabled) {return;}

		if (typeof document.activeElement.onBlur === 'function') {fireEvent(document.activeElement, 'blur');}
		if (typeof document.activeElement.onblur === 'function') {fireEvent(document.activeElement, 'blur');}
		if (typeof control.onFocus === 'function') {doEvent = fireEvent(control, 'focus');}
		if (typeof control.onfocus === 'function') {doEvent = fireEvent(control, 'focus');}
		if (doEvent !== false) {control.focus();}
		control.select();
	}

// ---------------------------------------------------------------------------
	function setInitialFocus(id) {
		// Set focus to the first text control
		var textId = '', control = null;

		switch (id) {
		case '$package':
			textId = '$package-$tagUsername';
			break;
		case '$accountForm':
			textId = '$accountForm-$tagEmail';
			break;
		}

		if (textId !== '') {control = that.getControl(textId);}
		if (control === null || typeof control === 'undefined' || control.disabled === 'disabled') {return;}
		setFocus(control);
	}

// ---------------------------------------------------------------------------
	function hideControl(id) {
		var	control		= that.getControl(id),
			className	= control.className + ' $package-hidden';

		control.className	= className;
		control.style.display	= 'none';	// belt and braces
	}

// ---------------------------------------------------------------------------
	function showControl(id) {
		var	control		= that.getControl(id),
			classString	= control.className;

		classString		= classString.replace(/$package-hidden/g, '');
		classString		= classString.replace(/ {2}/g, ' ');
		control.className	= classString;
		control.style.display	= '';	// belt and braces
	}

// ---------------------------------------------------------------------------
	// Public properties
	this.passwordSaved		= '';
	this.passwordDefault_SignIn	= false;
	this.passwordDefault_Account	= false;
	this.usernameDefault_Account	= false;

	// Public methods
// ---------------------------------------------------------------------------
	this.showMessage = function (message, fail, messageType, instance) {
		if (arguments.length < 1) {message	= '';}
		if (arguments.length < 2) {fail		= false;}
		if (arguments.length < 3) {messageType	= 'message';}
		if (arguments.length < 4) {instance	= '$package';}

		var	id		= instance + '-' + messageType,
			div		= this.getControl(id),
			classString	= '$package-' + messageType + ' $package-$stringRight',
			subClass	= (fail) ? 'fail' : 'info',
			p;

		if (div === null)		{return;} // No such control
		if (div.hasChildNodes())	{div.removeChild(div.firstChild);}

		if (message !== '') {
			p		= document.createElement('p');
			p.className	= '$package-message-' + subClass;
			p.innerHTML	= message;
			div.className	= classString;

			div.appendChild(p);
		}

		div = this.getControl('$package-$tagVerbose');
		if (div !== null) {div.parentNode.removeChild(div);}
	};

// ---------------------------------------------------------------------------
	this.bodyAppend = function (html) {
		document.getElementsByTagName('body')[0].innerHTML += html;
	};

// ---------------------------------------------------------------------------
// Responds to various UI events and controls the appearance of the form's
// buttons
// ---------------------------------------------------------------------------
	this.setButtonState = function (control, eventID, setOn) {
		// eventID	1 = mouseover/mouseout
		//		2 = focus/blur
		//		4 = selected/unselected

		if (control === null) {return false;}

		var	baseClass	= control.className,
			stateClass	= '$package-buttonstate-',
			pos		= baseClass.indexOf(stateClass),
			currentState	= Number(control.state);

		currentState		= (setOn) ? currentState | eventID : currentState & ~eventID;
		control.state		= String(currentState);
		baseClass		= (pos === -1) ? baseClass + ' ' : baseClass.substring(0, pos);
		control.className	= baseClass + stateClass + String(currentState);
		return true;
	};

// ---------------------------------------------------------------------------
//	Cookies! Mmmm.
// ---------------------------------------------------------------------------
	this.cookies	= {
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
			this.username		= this.acquire('$cookieUsername'),
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
			this.username = that.getValue('$package-$tagUsername');

			if (!that.passwordDefault_SignIn || (this.passwordHash === '')) {
				var password		= that.getValue('$package-$tagPassword');
				this.passwordHash	= SHA256('$remoteAddress' + SHA256(password));
			}

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
			that.setButtonState(that.getControl('$package-$tagRememberMe'),		4, this.rememberMe);
			that.setButtonState(that.getControl('$package-$tagStaySignedIn'),	4, this.staySignedIn);
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

// ---------------------------------------------------------------------------
// AJAX handling
// ---------------------------------------------------------------------------
	this.ajax = {
		xhr: new window.XMLHttpRequest(),

		handleServerResponse: function () {
			var id, fail, message, cancelButton;

			if ((this.readyState === 4) && (this.status === 200)) {
				if (isNaN(this.responseText)) {
					id = this.getResponseHeader('$containerHeader');

					if (this.responseXML !== null) {
						that.fillContainersXML(this.responseXML);
					} else if (id === null) {
						that.bodyAppend(this.responseText);
					} else {
						that.fillContainerText(id, this.responseText);
					}

				} else {
					fail		= true;
					message		= 'Server error, please try later';
					cancelButton	= that.getControl('$package-$actionCancel');

					that.showMessage(message, fail);

					if (cancelButton !== null) {
						cancelButton.id		= '$package-$actionSignIn';
						cancelButton.value	= 'Sign in';
						cancelButton.setAttribute('data-$package-action', '$actionSignIn');
					}
				}

				// Unit testing callback function
				if (typeof ajaxUnit === 'function') {ajaxUnit(this);}
			}
		},

		serverTalk: function (URL, requestType, requestData) {
			this.xhr.open(requestType, URL);
			this.xhr.onreadystatechange = this.handleServerResponse;
			this.xhr.setRequestHeader('Accept', 'text/html,application/$package');
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
				control,
				textNew,
				readyState;

			thisAction	= (delimPos === -1) ? thisAction : '$action=' + thisAction;
			delimPos	= thisAction.indexOf(equals);
			action		= (delimPos === -1) ? thisAction : thisAction.slice(0, delimPos);

			switch (action) {
			case '$actionSignIn':
				control		= that.getControl('$package-$actionSignIn');
				control.id	= '$package-$actionCancel';
				control.value	= 'Cancel';
				control.setAttribute('data-$package-action', '$actionCancel');

				that.showMessage('Signing in - please wait');
				that.cookies.update();	// Updates ezuser.passwordHash;

				passwordHash	= SHA256(that.cookies.sessionId + that.cookies.passwordHash);
				requestData	= '$action='		+ action;
				requestData	+= '&$cookieUsername='		+ that.getValue('$package-$tagUsername');
				requestData	+= '&$cookiePassword='		+ passwordHash;
				requestType	= 'POST';

				break;
			case '$actionValidate':
				textNew		= that.getValue('$accountForm-$tagNewUser');
				requestData	= '$action='		+ action;
				requestData	+= '&$tagNewUser='		+ textNew;
				requestData	+= '&$tagWizard='		+ encodeURIComponent(that.getValue('$accountForm-$tagWizard'));
				requestData	+= '&$tagEmail='		+ encodeURIComponent(that.getValue('$accountForm-$tagEmail'));
				requestData	+= '&$tagFirstName='	+ encodeURIComponent(that.getValue('$accountForm-$tagFirstName'));
				requestData	+= '&$tagLastName='		+ encodeURIComponent(that.getValue('$accountForm-$tagLastName'));
				requestData	+= '&$cookieUsername='		+ that.getValue('$accountForm-$tagUsername');

				if (!that.passwordDefault_Account || (textNew === '$stringTrue')) {
					passwordHash	= SHA256(that.getValue('$accountForm-$tagPassword'));
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
				URL += '?' + thisAction + equals + encodeURIComponent(that.getValue('$accountForm-$tagEmail'));
				break;
			case '$actionResetPassword':		// Fall-through ->
			case '$actionResetRequest':
				URL += '?' + thisAction + equals + that.getValue('$package-$tagUsername');
				break;
			case '$actionReset':
				passwordHash = SHA256(that.getValue('$package-$actionReset-$tagPassword'));
				URL = window.location.href + '&$cookiePassword=' + passwordHash;
				break;
			default:
				URL += '?' + thisAction;
				break;
			}

			this.serverTalk(URL, requestType, requestData);
		}
	};

// ---------------------------------------------------------------------------
//	Account wizard page handling
// ---------------------------------------------------------------------------
	this.wizard = {
		page: 1,

		changePage: function (delta) {
			var nextPageId, nextPage;

			if (this.getValue('$accountForm-$tagWizard') === '$stringFalse') {return;}	// Not in wizard mode

			this.page = (arguments.length === 0) ? 1 : this.page + delta;
			if (this.page < 1) {this.page = 1;}

			// Previous page
			if (this.page === 1) {
				hideControl('$accountForm-back');				// Hide 'Back' button
			} else {
				showControl('$accountForm-back');				// Show 'Back' button
				hideControl('$accountForm-fieldset-' + (this.page - 1));	// Hide previous page
			}

			// Current page
			showControl('$accountForm-fieldset-' + this.page); 			// Show this page

			// Next page
			nextPageId	= '$accountForm-fieldset-' + (this.page + 1),
			nextPage	= this.getControl(nextPageId);

			if (nextPage === null) {
				hideControl('$accountForm-next');				// Hide 'Next' button
			} else {
				showControl('$accountForm-next');				// Show 'Next' button
				hideControl(nextPageId);					// Hide next page
			}
		},

		pageNext:	function () {this.changePage(1);},
		pageBack:	function () {this.changePage(-1);},
		initialize:	function () {this.page = 1;}
	};

// ---------------------------------------------------------------------------
// Responds to clicks on the ezuser form
// ---------------------------------------------------------------------------
	this.click = function (control) {
		var	id	= control.id,
			action	= control.getAttribute('data-$package-action');

		switch (id) {
		case '$package-$actionAccountForm':
			$accountClick;
			break;
		case '$package-$tagRememberMe':
			this.cookies.toggleRememberMe();
			break;
		case '$package-$tagStaySignedIn':
			this.cookies.toggleStaySignedIn();
			break;
		case '$accountForm-next':
			this.wizard.pageNext();	// Next wizard page
			break;
		case '$accountForm-back':
			this.wizard.pageBack();	// Previous wizard page
			break;
		case '$package-$tagVerbose':
			this.ajax.execute('$actionResultForm=' + control.value);
			break;
		case '$package-$actionResetPassword':	// Fall-through ->
		case '$package-$actionReset-OK':		// Fall-through ->
		case '$accountForm-$actionValidate':
			if (this.localValidation(control.form.id)) {this.ajax.execute(action);}
			break;
		default:
			if (action === null) {break;}
			this.ajax.execute(action);
			break;
		}

		return false;
	};

// ---------------------------------------------------------------------------
// Responds to key presses on the ezuser form
// ---------------------------------------------------------------------------
	this.keyUp = function (e) {
		if (!e) var e = window.event;
		var formId, id, control, target;

		// Process Carriage Return and tidy up form
		target	= (e.target) ? e.target : e.srcElement;
		formId	= target.form.id;
		id	= target.id;

		switch (formId) {
		case '$package-$actionSignIn-form':
			if (id === '$package-$tagPassword' && this.passwordDefault_SignIn) {
				// Forget password from cookie
				this.cookies.passwordHash	= '';
				this.passwordDefault_SignIn	= false;
			}

			if (e.keyCode === 13) {
				this.click(this.getControl('$package-$actionSignIn'));
			} else {
				this.showMessage(); // Hide message
			}

			break;
		case '$accountForm-form':
			switch (id) {
			case '$accountForm-$tagUsername':
				// If we are messing with the username then forget creating a default
				this.usernameDefault_Account = false;
				this.normaliseUsername(this.getValue(id));
				break;
			case '$accountForm-$tagFirstName':
			case '$accountForm-$tagLastName':
				if (this.getValue('$accountForm-$tagUsername') === '') {this.usernameDefault_Account = true;}
				if (this.usernameDefault_Account) {this.normaliseUsername(this.getValue('$accountForm-$tagFirstName') + this.getValue('$accountForm-$tagLastName'));}
				break;
			case '$accountForm-$tagPassword':
				this.passwordSaved = target.value;
				this.passwordDefault_Account = false;
				break;
			case '$accountForm-$tagConfirm':
				this.passwordDefault_Account = false;
				break;
			}

			if (e.keyCode === 13) {
				control = this.getControl('$accountForm-$actionValidate');
				if (control === null) {control = this.getControl('$accountForm-$modeEdit');}
				this.click(control);
			} else {
				this.showMessage('', false, '$messageTypeText', '$accountForm'); // Hide message
			}

			break;
		case '$package-$actionReset-form':
			if (e.keyCode === 13) {
				this.click(this.getControl('$package-$actionReset-OK'));
			} else {
				this.showMessage('', false, '$messageTypeText'); // Hide message
			}

			break;
		}

		return true;
	};

// ---------------------------------------------------------------------------
	this.fillContainerText = function (id, html) {
		var	container	= this.getControl(id),
			containerList,
			formList,
			formId;

		if (container === null || typeof container === 'undefined') {
			containerList = document.getElementsByTagName(id);

			if (containerList === null || typeof containerList === 'undefined' || containerList.length === 0) {
				window.alert('Can\\'t find a container \\'' + id + '\\' for this content: ' + html.substring(0, 256));
				return;
			} else {
				container = containerList[0];
			}
		}

		if (container.className.length === 0) {container.className = id;} // IE6 uses container.class

		container.innerHTML	= html;
		formList		= container.getElementsByTagName('form');
		formId			= ((typeof formList === 'undefined') || (formList.length === 0)) ? '' : formList[0].getAttribute('id');

		switch (formId) {
		case '$package-$actionSignIn-form':
			this.cookies.showPreferences();

			if (this.cookies.rememberMe) {
				this.passwordDefault_SignIn = true;
				this.setValue('$package-$tagUsername', this.cookies.username);
				this.setValue('$package-$tagPassword', '$passwordMask');
			}

			break;
		case '$accountForm-form':
			this.wizard.initialize(); // Set wizard to page 1
			this.usernameDefault_Account = (this.getValue('$accountForm-$tagUsername') === '');
			this.passwordDefault_Account = (this.getValue('$accountForm-$tagNewUser') !== '$stringTrue');

			if (this.getValue('$accountForm-$tagUseSavedPassword') === '$stringTrue') {
				this.setValue('$accountForm-$tagPassword', this.passwordSaved);
				this.setValue('$accountForm-$tagConfirm', this.passwordSaved);
			} else {
				this.savedPassword = '';
			}

			break;
		}

		setInitialFocus(id);
	};

// ---------------------------------------------------------------------------
	this.fillContainersXML = function (xml) {
		var i, iHalt, id, html, formNode, formList;

		formList	= xml.childNodes;
		iHalt		= formList.length;

		for (i = 0; i < iHalt; i++) {
			formNode = formList[i];

			switch (formNode.nodeType) {
			case 1: // Node.ELEMENT_NODE: // recurse
				this.fillContainersXML(formNode);
				break;
			case 4: // Node.CDATA_SECTION_NODE: // fill the container
				id	= formNode.parentNode.getAttribute('container');
				html	= formNode.nodeValue;

				this.fillContainerText(id, html);
				break;
			case 3: // Node.TEXT_NODE:
			case 7: // Node.PROCESSING_INSTRUCTION_NODE: // Usually caused by PHP passing an error message along with the XHR content
			case 8: // Node.COMMENT_NODE
				break; // Ignore
			default:
				window.alert('I wasn\\'t expecting a node type of ' + formNode.nodeType);
				break;
			}
		}
	};

// ---------------------------------------------------------------------------
	this.localValidation = function (formId) {
		var	control,
			textEmail,
			textUsername,
			textPassword,
			textConfirm,
			textNew,
			instance,
			message		= '';

		switch (formId) {
		case '$accountForm-form':
			textEmail	= this.getControl('$accountForm-$tagEmail');
			textUsername	= this.getControl('$accountForm-$tagUsername');
			textPassword	= this.getControl('$accountForm-$tagPassword');
			textConfirm	= this.getControl('$accountForm-$tagConfirm');
			textNew		= this.getControl('$accountForm-$tagNewUser');
			instance	= '$accountForm';

			// Valid email address
			if (textEmail.value === '') {
				message = 'You must provide an email address';
				control	= textEmail;
			} else {
				// Valid username
				this.normaliseUsername(textUsername.value);

				if (textUsername.value === '') {
					message = 'The username cannot be blank';
					control	= textUsername;
				} else {
					// Password OK?
					if (textPassword.value !== textConfirm.value) {
						message = 'Passwords are not the same';
					} else if (this.passwordDefault_Account) {
						if (textNew.value === '$stringTrue') {message = 'Password cannot be blank';}
					} else if (textPassword.value === '') {
						message = 'Password cannot be blank';
					}

					control	= textPassword;
				}
			}

			break;
		case '$package-$actionReset-form':
			textPassword	= this.getControl('$package-$actionReset-$tagPassword');
			textConfirm	= this.getControl('$package-$actionReset-$tagConfirm');
			instance	= '$package';
			control		= textPassword;

			// Password OK?
			if (textPassword.value !== textConfirm.value) {
				message = 'Passwords are not the same';
			} else if (textPassword.value === '') {
				message = 'Password cannot be blank';
			}

			break;
		case '$package-$actionResetRequest-form':
			textUsername	= this.getControl('$package-$tagUsername');
			instance	= '$package';
			control		= textUsername;

			// Username entered?
			if (textUsername.value === '') {message = 'Username cannot be blank';}
			break;
		}

		if (message === '') {
			return true;
		} else {
			this.showMessage(message, true, '$messageTypeText', instance);
			setFocus(control);
			return false;
		}
	};

// ---------------------------------------------------------------------------
	this.normaliseUsername = function (username) {
		var	regexString	= '[^0-9a-z_-]',
			regex		= new RegExp(regexString, 'g'),
			control		= this.getControl('$accountForm-$tagUsername');

		username		= username.toLowerCase();
		username		= username.replace(regex, '');
		control.defaultValue	= username;
		control.value		= username;
	};

// ---------------------------------------------------------------------------
	this.addStyleSheet = function () {
		var	htmlHead	= document.getElementsByTagName('head')[0],
			nodeList	= htmlHead.getElementsByTagName('link'),
			elementCount	= nodeList.length,
			found		= false,
			i, node;

		for (i = 0; i < elementCount; i++) {
			if (nodeList[i].title === '$package') {
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
			node.title	= '$package';
			htmlHead.appendChild(node);
		}
	};

	this.passwordFocus = function (control) {
		switch (control.form.id) {
		case '$package-$actionSignIn-form':
			if (this.passwordDefault_SignIn) {control.value = '';}
			break;
		case '$accountForm-form':
			if (this.passwordDefault_Account) {
				this.setValue('$accountForm-$tagPassword', '');
				this.setValue('$accountForm-$tagConfirm', '');
			}
			break;
		}

		return true;
	};

	this.passwordBlur = function (control) {
		switch (control.form.id) {
		case '$package-$actionSignIn-form':
			if (this.passwordDefault_SignIn) {control.value = '$passwordMask';}
			break;
		case '$accountForm-form':
			if (this.passwordDefault_Account) {
				this.setValue('$accountForm-$tagPassword', '$passwordMask');
				this.setValue('$accountForm-$tagConfirm', '$passwordMask');
			}
			break;
		}

		return true;
	};

// ---------------------------------------------------------------------------
// Constructor
// ---------------------------------------------------------------------------
	this.cookies.read();
	this.addStyleSheet();
}

// ---------------------------------------------------------------------------
// Do stuff
// ---------------------------------------------------------------------------
var ezuser = new C_ezuser();
$immediateJavascript
JAVASCRIPT;

		if ($sendToBrowser) {self::sendContent($js, '', 'text/javascript'); return '';} else return $js;
	}

// ---------------------------------------------------------------------------
// Account verification
// ---------------------------------------------------------------------------
	private static /*.string.*/ function verify_renotify($username_or_email = '', $sendToBrowser = false) {
		$success	= self::verify_notify($username_or_email);
		$message	= ($success) ? 'Verification email has been resent.' : 'Verification email was not sent: please try again later';
		$container	= self::getInstanceId(self::ACTION_ACCOUNT . '-' . self::MESSAGE_TYPE_TEXT);

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
				if ($sendToBrowser) {self::sendXML($html, self::ACTION_BODY); return '';} else return $html;
			} else {
				header('HTTP/1.1 403 Forbidden', false, 403);
				$referer = str_replace('http://' . $_SERVER['HTTP_HOST'], '' , $referer);
				echo <<<HTML
<h1>Forbidden</h1>
<p>You don't have permission to access $referer on this server.</p>
HTML;
			}
		} else {
			$html = 'No referer';
			if ($sendToBrowser) {self::sendContent($html, self::ACTION_BODY); return '';} else return $html;
		}
	}

// ---------------------------------------------------------------------------
// Sign in and sign out
// ---------------------------------------------------------------------------
	public static /*.void.*/ function signIn($userData = /*.(array[string]mixed).*/ array()) {
		$autoSignIn = (count($userData) === 0);

		if ($autoSignIn) {
			if (	array_key_exists(self::COOKIE_AUTOSIGN, $_COOKIE)	&&
				array_key_exists(self::COOKIE_USERNAME, $_COOKIE)	&&
				($_COOKIE[self::COOKIE_AUTOSIGN] === self::STRING_TRUE)	&&
				($_COOKIE[self::COOKIE_USERNAME] !== '')
			   ) {
				$userData[self::COOKIE_USERNAME] = (string) $_COOKIE[self::COOKIE_USERNAME];
				$userData[self::COOKIE_PASSWORD] = hash(self::HASH_FUNCTION, session_id() . (string) $_COOKIE[self::COOKIE_PASSWORD]);
			} else {
				return;
			}
		}

		$username	= (string) $userData[self::COOKIE_USERNAME];
		$password	= (string) $userData[self::COOKIE_PASSWORD];
		$ezUser		= (($username === '') || ($password === '')) ? new ezUser_base() : self::lookup($username);

		if ($ezUser->status() === self::STATUS_UNKNOWN) {
			$ezUser->setResult(self::RESULT_UNKNOWNUSER);
		} else {
			$ezUser->authenticate($password); // Sets result itself
		}

		self::setSessionObject($ezUser);
		if (!$autoSignIn) self::htmlControlPanel('', true);
	}

// ---------------------------------------------------------------------------
	/*. forward public static void function doActions(array[string]string $actions); .*/

	private static /*.void.*/ function signOut() {
		$ezUser	= self::getSessionObject();
		if (!$ezUser->authenticated()) return;	// Not signed in so nothing to do

		// Sign out then check if a post-signout function has been registered
		$ezUser->authenticate();		// Sign out
		$ezUser->addSignOutAction(self::ACTION_MAIN);
		$signOutActions = $ezUser->signOutActions();
		self::setSessionObject(new ezUser_base(), self::ACTION_ACCOUNT);
		self::doActions(array(self::ACTION => $signOutActions));
	}

// ---------------------------------------------------------------------------
// General action handling
// ---------------------------------------------------------------------------
	private static /*.string.*/ function doAction($action = '', $id = '', $sendToBrowser = true) {
		$html = '';

		switch ($action) {
		case self::ACTION_CONTAINER:		$html = self::htmlContainer		($id,			$sendToBrowser);	break;
		case self::ACTION_MAIN:			$html = self::htmlControlPanel		($id,			$sendToBrowser);	break;
		case self::ACTION_RESETREQUEST:		$html = self::htmlResetRequest		($id,			$sendToBrowser);	break;
		case self::ACTION_ACCOUNT:		$html = self::htmlAccountForm		($id, false, false,	$sendToBrowser);	break;
		case self::ACTION_ACCOUNTWIZARD:	$html = self::htmlAccountForm		($id, false, true,	$sendToBrowser);	break;
		case self::ACTION_STATUSTEXT:		$html = self::statusText		((int) $id, '',		$sendToBrowser);	break;
		case self::ACTION_RESULTTEXT:		$html = self::resultText		((int) $id, '',		$sendToBrowser);	break;
		case self::ACTION_RESULTFORM:		$html = self::htmlResultForm		((int) $id, '',		$sendToBrowser);	break;
		case self::ACTION_RESEND:		$html = self::verify_renotify		($id,			$sendToBrowser);	break;
		case self::ACTION_JAVASCRIPT:		$html = self::htmlJavascript		($id,			$sendToBrowser);	break;
		case self::ACTION_STYLESHEET:		$html = self::htmlStyleSheet		($sendToBrowser);				break;
		case self::ACTION_BODY:			$html = self::htmlSecureContent		($sendToBrowser);				break;
		case self::ACTION_ABOUT:		$html = self::htmlAbout			($sendToBrowser);				break;
		case self::ACTION_SOURCECODE:		$html = self::htmlSourceCode		($sendToBrowser);				break;
		case self::ACTION_VERIFY:		self::verify				($id);						break;
		case self::ACTION_RESETPASSWORD:	self::passwordReset_validate		($id);						break;
		case self::ACTION_RESET:		self::passwordReset_reset		($id);						break;
		case self::ACTION_SIGNOUT:		self::signOut				();						break;
		default:				self::fatalError			(self::RESULT_UNKNOWNACTION, $action);		break;
		}

		return $html;
	}

// ---------------------------------------------------------------------------
/**
 * Performs one or more actions
 *
 * To perform more than one action, specify them in the condensed format
 *
 * <pre>
 *     ezuser.php?foo1+foo2+foo3=bar1+bar2+bar3
 * </pre>
 *
 * or the extended format
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
 * @param array $actions Same format as {@link http://www.php.net/$_GET $_GET} (which is where it usually comes from)
 */
	public static /*.void.*/ function doActions(/*.array[string]string.*/ $actions) {
		// Translate from short form (ezUser.php?foo=bar) to extended form (ezUser.php?action=foo&id=bar)
		if (!array_key_exists(self::ACTION, $actions)) {
			$actions[self::TAGNAME_ID]	= (string) reset($actions);
			$actions[self::ACTION]		= (string) key($actions);
		}

		$actionList	= (array_key_exists(self::ACTION, $actions))		? $actions[self::ACTION]	: '';
		$id		= (array_key_exists(self::TAGNAME_ID, $actions))	? $actions[self::TAGNAME_ID]	: '';

		if (strpos($actionList, self::DELIMITER_SPACE) !== false) {
			$actionItems	= explode(self::DELIMITER_SPACE, $actionList);
			$content	= /*.(array[string]string).*/ array();
			foreach ($actionItems as $action) $content[self::getInstanceId($action)] = self::doAction($action, $id, false);
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
	public static /*.void.*/ function fatalError		(/*.int.*/ $result, $more = '')			{self::htmlResultForm($result, $more,			true); exit;}
	public static /*.void.*/ function getAccountForm	($mode = '', $newUser = false, $wizard = false)	{self::htmlAccountForm($mode, $newUser, $wizard,	true);}
//	public static /*.void.*/ function getDashboard		()						{self::htmlDashboard(					true);}
//	public static /*.void.*/ function getSignInForm		()						{self::htmlSignInForm(					true);}
//	public static /*.void.*/ function getControlPanel	($username = '')				{self::htmlControlPanel($username,			true);}
//	public static /*.void.*/ function getStyleSheet		()						{self::htmlStyleSheet(					true);}
//	public static /*.void.*/ function getJavascript		($containerList = '')				{self::htmlJavascript($containerList,			true);}
	public static /*.void.*/ function getContainer		($action = self::ACTION_MAIN)			{self::htmlContainer($action,				true);}
	public static /*.void.*/ function getAbout		()						{self::htmlAbout(					true);}
//	public static /*.void.*/ function getSourceCode		()						{self::htmlSourceCode(					true);}
}
// End of class ezUser


// Some code to make this all automagic
//
// If you want more control over how ezUser works then you might need to amend
// or even remove the code below here

// There may already be a session in progress. We will use the existing
// session if possible.
if ((int) ini_get('session.use_cookies') === 0)
	ezUser::fatalError(ezUser::RESULT_NOSESSIONCOOKIES);
else
	if (!isset($_SESSION) || !is_array($_SESSION)) session_start();

$ezUser = ezUser::getSessionObject();

// Is this script included in another page or is it the HTTP target itself?
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
	// This script has been called directly by the client

	// $_POST & $_GET are our friends
	if (is_array($_POST) && array_key_exists(ezUser::ACTION, $_POST)) {
		switch ((string) $_POST[ezUser::ACTION]) {
		case ezUser::ACTION_SIGNIN:
			ezUser::signIn($_POST);
			break;
		case ezUser::ACTION_VALIDATE:
			ezUser::save($_POST);
			ezUser::getAccountForm(ezUser::ACCOUNT_MODE_RESULT, ($_POST[ezUser::TAGNAME_NEWUSER] === ezUser::STRING_TRUE), ($_POST[ezUser::TAGNAME_WIZARD] === ezUser::STRING_TRUE));
			break;
		default:
			ezUser::getResultForm(ezUser::RESULT_UNKNOWNACTION);
			break;
		}
	} else if (is_array($_GET) && count($_GET) > 0) {
		if (!$ezUser->authenticated()) ezUser::signIn(); // Attempt auto-signin?
		ezUser::doActions(/*.(array[string]string).*/ $_GET);
	} else {
		ezUser::getAbout(); // Nothing useful in $_GET or $_POST, so give a friendly greeting
	}
}
?>