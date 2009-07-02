<?php
/*
--------------------------------------------------------------------------------
ezUser - adds user registration and authentication to a website
--------------------------------------------------------------------------------

This code has three principle design goals:

	1. To make it easy for people to register and sign in to your site.
	2. To make it easy for you to add this functionality to your site.
	3. To make it easy for you to administer the user database on your site

Other design goals, such as run-time efficiency, are important but secondary to
these.

--------------------------------------------------------------------------------

Copyright (c) 2008-2009, Dominic Sayers
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

 * Redistributions of source code must retain the above copyright notice, this
   list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice,
   this list of conditions and the following disclaimer in the documentation
   and/or other materials provided with the distribution.
 * Neither the name of Dominic Sayers nor the names of its contributors may be
   used to endorse or promote products derived from this software without
   specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
--------------------------------------------------------------------------------
*/
/**
 * @package	ezUser
 * @author	Dominic Sayers <dominic_sayers@hotmail.com>
 * @copyright	2009 Dominic Sayers
 * @license	http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link	http://code.google.com/p/ezuser/
 * @version	0.18 - Less cruft and more win
 */

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

// ---------------------------------------------------------------------------
//		ezUserAPI
// ---------------------------------------------------------------------------
// ezUser REST interface & other constants
// ---------------------------------------------------------------------------
interface ezUserAPI {
	const	PACKAGE			= 'ezUser',

		// REST interface actions
		ACTION			= 'action',
		ACTION_ABOUT		= 'about',
		ACTION_ACCOUNT		= 'account',
		ACTION_BODY		= 'body',
		ACTION_CANCEL		= 'cancel',
		ACTION_CONTAINER	= 'container',
		ACTION_DASHBOARD	= 'dashboard',
		ACTION_JAVASCRIPT	= 'js',
		ACTION_MAIN		= 'controlpanel',
		ACTION_PANELACCOUNT	= 'accountinpanel',
		ACTION_RESEND		= 'resend',
		ACTION_RESULTFORM	= 'resultform',
		ACTION_RESULTTEXT	= 'resulttext',
		ACTION_SIGNIN		= 'signin',
		ACTION_SIGNOUT		= 'signout',
		ACTION_SOURCECODE	= 'code',
		ACTION_STATUSTEXT	= 'statustext',
		ACTION_STYLESHEET	= 'css',
		ACTION_VALIDATE		= 'validate',	// Validate registration form details
		ACTION_VERIFY		= 'verify',	// Verify verification email

		// Keys for the user data array members
		TAGNAME_ID		= 'id',
		TAGNAME_PASSWORD	= 'password',
		TAGNAME_CONFIRM		= 'confirm',
		TAGNAME_DATA		= 'data',
		TAGNAME_FIRSTNAME	= 'firstName',
		TAGNAME_LASTNAME	= 'lastName',
		TAGNAME_STATUS		= 'status',
		TAGNAME_USERNAME	= 'username',
		TAGNAME_EMAIL		= 'email',
		TAGNAME_VERIFICATIONKEY	= 'verificationKey',
		TAGNAME_NEW		= 'new',
		TAGNAME_FULLNAME	= 'fullName',
		TAGNAME_USER		= 'user',
		TAGNAME_SAVEDPASSWORD	= 'useSavedPassword',
		TAGNAME_VERBOSE		= 'verbose',

		// Modes for account page
		ACCOUNT_MODE_NEW	= 'new',
		ACCOUNT_MODE_EDIT	= 'edit',
		ACCOUNT_MODE_DISPLAY	= 'display',
		ACCOUNT_MODE_RESULT	= 'result',
		ACCOUNT_MODE_CANCEL	= 'cancel',

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

		// Cookie names
		COOKIE_USERNAME		= 'ezUser1',
		COOKIE_PASSWORD		= 'ezUser2',
		COOKIE_AUTOSIGN		= 'ezUser3',

		// Miscellaneous constants
		STRING_TRUE		= 'true',
		STRING_FALSE		= 'false',
		DELIMITER_EMAIL		= '@',
		DELIMITER_SPACE		= ' ',
		DELIMITER_PLUS		= '+',
		EQUALS			= '=',
		PASSWORD_MASK		= '************',
		HASH_FUNCTION		= 'SHA256';
}
// End of interface ezUserAPI

// ---------------------------------------------------------------------------
//		ezUserValidate
// ---------------------------------------------------------------------------
// Field validation functions for ezUser
// ---------------------------------------------------------------------------
class ezUserValidate {
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
			$partLength = 0;

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
// End of class ezUserValidate

/**
 * This class encapsulates all the functions needed for an app to interact
 * with a user. It has no knowledge of how user information is persisted.
 */
class ezUser extends ezUserValidate implements ezUserAPI {
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
						self::TAGNAME_VERIFICATIONKEY
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
	private		$authenticated		= false;
	private		$usernameIsDefault	= true;
	private		$result			= self::RESULT_UNDEFINED;
	private		$config			= /*.(array[string]string).*/ array();
	private		$errors			= /*.(array[string]string).*/ array();
	private		$incomplete		= true;
	protected	$isChanged		= false;
	protected	$signOutActions		= '';

// ---------------------------------------------------------------------------
// Helper methods
// ---------------------------------------------------------------------------
	private /*.boolean.*/ function checkComplete() {
		$this->incomplete =	(	($this->values[self::TAGNAME_USERNAME]	=== '') ||
						($this->values[self::TAGNAME_EMAIL]	=== '') ||
						($this->values[self::TAGNAME_ID]	=== '')
					);

		return $this->incomplete;
	}

	private /*.string.*/ function getValue(/*.string.*/ $key) {
		$value = '';
		if (!in_array($key, $this->keys)) return $value;

		if ($key === self::TAGNAME_VERIFICATIONKEY) {
			if ($this->values[self::TAGNAME_STATUS] === self::STATUS_PENDING) $value = $this->values[$key];
		} else {
			$value	= $this->values[$key];
		}

		return $value;
	}

	private /*.boolean.*/ function setValue(/*.string.*/ $key, /*.string.*/ $value) {
		if ($value !== $this->values[$key]) {
			$this->values[$key]	= $value;
			$this->isChanged	= true;
			$this->checkComplete();

			return true;
		} else {
			return false;
		}
	}

// ---------------------------------------------------------------------------
// Substantive methods
// ---------------------------------------------------------------------------
	public /*.boolean.*/ function authenticate(/*.string.*/ $passwordHash) {
		$sessionHash = hash(self::HASH_FUNCTION, session_id() . hash(self::HASH_FUNCTION, $_SERVER['REMOTE_ADDR'] . $this->values[self::TAGNAME_PASSWORD]));
		$this->authenticated = ($passwordHash === $sessionHash);
		return $this->authenticated;
	}

	public /*.string.*/ function signOut() {
		$this->authenticated = false;
		return $this->signOutActions;
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
	protected	/*.boolean.*/			function incomplete()		{return $this->incomplete;}
	protected	/*.array[string]string.*/	function config()		{return $this->config;}
	protected	/*.array[string]string.*/	function errors()		{return $this->errors;}

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
		$separator	= (($firstName === '') || ($lastName === '')) ? '' : ' ';

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
		$this->checkComplete();
	}

	protected /*.void.*/ function clearErrors() {
		$this->errors = /*.(array[string]string).*/ array();
	}

	protected /*.int.*/ function setStatus(/*.int.*/ $status) {
		if (!is_numeric($status)) return self::RESULT_STATUSNAN;

		// If we're setting this user to Pending then generate a verification key
		if ($status === self::STATUS_PENDING && $this->status() !== self::STATUS_PENDING) {
			// Make sure we have an ID
			if ($this->id() === '') {
				list($usec, $sec) = explode(" ", (string) microtime());
				$id = base_convert($sec, 10, 36) . base_convert((string) mt_rand(0, 35), 10, 36) . str_pad(base_convert(($usec * 1000000), 10, 36), 4, '_', STR_PAD_LEFT);
				$this->setValue(self::TAGNAME_ID, $id);
			}

			// Use the ID to generate a verification key
			$this->setValue(self::TAGNAME_VERIFICATIONKEY, hash(self::HASH_FUNCTION, $_SERVER['REQUEST_TIME'] . $this->id()));
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
		$this->usernameIsDefault = ($name === '');
		if ($this->usernameIsDefault) $name = $this->getDefaultUsername();
		if ($name === '') return self::RESULT_NOUSERNAME;
		$this->setValue(self::TAGNAME_USERNAME, $name);
		return self::RESULT_VALIDATED;
	}

	protected /*.int.*/ function setEmail(/*.string.*/ $email) {
		if ($email === '') return self::RESULT_NOEMAIL;

		if (!self::is_email($email)) {
			$this->errors[self::TAGNAME_EMAIL] = $email;
			return self::RESULT_EMAILFORMATERR;
		}

		$this->setValue(self::TAGNAME_EMAIL, $email);
		return self::RESULT_VALIDATED;
	}

	protected /*.int.*/ function setPasswordHash(/*.string.*/ $passwordHash) {
		if ($passwordHash === '')				return self::RESULT_NOPASSWORD;
		if ($passwordHash === hash(self::HASH_FUNCTION, ''))	return self::RESULT_NULLPASSWORD;
		$this->setValue(self::TAGNAME_PASSWORD, $passwordHash);
		return self::RESULT_VALIDATED;
	}

// ---------------------------------------------------------------------------
// Constructor
// ---------------------------------------------------------------------------
	/*.void.*/ function __construct() {
	}
}
// End of class ezUser

// ---------------------------------------------------------------------------
//		ezUsers
// ---------------------------------------------------------------------------
// This class encapsulates all the functions needed to manage the collection
// of stored users. It interacts with the storage mechanism (e.g. database or
// XML file).
// ---------------------------------------------------------------------------
class ezUsers extends ezUser {
	/*.private.*/ const	STORAGE			= '.ezuser-data.php',
				SETTINGS		= '.ezuser-settings.php';

				// Keys for the configuration settings
	/*.private.*/ const	SETTINGS_ADMINEMAIL	= 'adminEmail',
				SETTINGS_PERSISTED	= 'persisted',
				SETTINGS_EMPTY		= 'empty';
	/*.protected.*/ const	SETTINGS_ACCOUNTPAGE	= 'accountPage',
				SETTINGS_SECUREFOLDER	= 'secureFolder';

// ---------------------------------------------------------------------------
// Configuration settings
// ---------------------------------------------------------------------------
	protected static /*.string.*/ function getInstanceId($container = self::PACKAGE) {
		return ($container === self::ACTION_MAIN || $container === self::PACKAGE) ? self::PACKAGE : self::PACKAGE . "-$container";
	}

	public static /*.ezUser.*/ function &getSessionObject($instance = self::PACKAGE) {
		$instanceId = self::getInstanceId($instance);
		if (!array_key_exists($instanceId, $_SESSION)) $_SESSION[$instanceId] = new ezUser();
		return /*.(ezUser).*/ $_SESSION[$instanceId];
	}

	protected static /*.void.*/ function setSessionObject(/*.ezUser.*/ $ezUser, $instance = self::PACKAGE) {
		$instanceId = self::getInstanceId($instance);
		$_SESSION[$instanceId] =& $ezUser;
	}

	protected static /*.string.*/ function thisURL() {
		$package = self::PACKAGE;

		// Find out the URL of this script so we can call it later
		$file = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? (string) str_replace("\\", '/' , __FILE__) : __FILE__;
		return dirname(substr($file, strpos($_SERVER['SCRIPT_FILENAME'], $_SERVER['SCRIPT_NAME']))) . "/$package.php";
	}

	private static /*.array[string]string.*/ function loadConfig() {
		$ezUser		=& self::getSessionObject();
		$config		= $ezUser->config();
		$settingsFile	= realpath(dirname(__FILE__) . "/" . self::SETTINGS);

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
		$ezUser	=& self::getSessionObject();
		$config =& $ezUser->config();

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
		$storage_file = dirname(__FILE__) . '/' . self::STORAGE;

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
		$storage_file = dirname(__FILE__) . '/' . self::STORAGE;
		$storage->save($storage_file);
}

// ---------------------------------------------------------------------------
	private static /*.DOMNode.*/ function findUser(/*.DOMDocument.*/ $storage, $needle = '', $useId = false) {
		if ($needle === '') return $storage->createElement(self::TAGNAME_USER);

		if ($useId)
			$tagName = self::TAGNAME_ID;
		else
			$tagName = ((bool) strpos($needle,self::DELIMITER_EMAIL)) ? self::TAGNAME_EMAIL : self::TAGNAME_USERNAME;

		$nodeList	= $storage->getElementsByTagName($tagName);
		$found		= false;

		for ($i = 0; $i < $nodeList->length; $i++) {
			$node	= $nodeList->item($i);
			$found	= ($node->nodeValue === $needle);
			if ($found) break;
		}

		if ($found) return $node->parentNode; else return $storage->createElement(self::TAGNAME_USER);
	}

// ---------------------------------------------------------------------------
// Substantive methods
// ---------------------------------------------------------------------------
	private static /*.ezUser.*/ function lookup($username_or_email = '') {
		$ezUser = new ezUser();
		if ($username_or_email === '') return $ezUser;
		$ezUser->setUsername($username_or_email); // Will get overwritten if we successfully find the user in the database

		$storage	= self::openStorage();
		$record		= /*.(DOMElement).*/ self::findUser($storage, $username_or_email);

		if ($record->hasChildNodes()) {
			$data	= $record->getElementsByTagName(self::TAGNAME_DATA)->item(0)->nodeValue;
			if (!empty($data)) $ezUser->setData($data);
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
			$host	= $_SERVER['HTTP_HOST'];
			$domain = (substr_count($host, '.') > 1) ? substr($host, strpos($host, '.') + 1) : $host;
			$from	.= self::DELIMITER_EMAIL . $domain;
		}

		// Extra headers
		$additional_headers .= "From: $from\r\n";
		
		date_default_timezone_set(@date_default_timezone_get());	// E_STRICT needs this or it complains about the mail function
		return @mail($to, $subject, $message, $additional_headers);
	}

// ---------------------------------------------------------------------------
	protected static /*.boolean.*/ function sendVerificationEmail($username_or_email = '') {
		$ezUser = self::lookup($username_or_email);

		if ($ezUser->status() !== self::STATUS_PENDING) return false;	// Only send confirmation email to users who are pending verification

		// Bits of plumbing
		$URL		= self::thisURL();
		$host		= $_SERVER['HTTP_HOST'];
		$s		= ($_SERVER['SERVER_PROTOCOL'] === 'HTTPS') ? 's' : '';

		// Message
		$message	= "Somebody calling themselves " . $ezUser->fullName() . " created an account at http$s://$host using this email address.\n";
		$message	.= "If it was you please click on the following link to verify the account.\n\n";
		$message	.= "http$s://$host$URL?" . self::ACTION_VERIFY . "=" . $ezUser->verificationKey() . "\n\n";
		$message	.= "After you click the link your account will be fully functional.\n";

		// Send it
		return self::sendEmail($ezUser->email(), 'New account confirmation', $message);
	}

// ---------------------------------------------------------------------------
	private static /*.int.*/ function is_duplicate(/*.string.*/ $username_or_email, /*.string.*/ $id) {
		$resultCode	= ((bool) strpos($username_or_email,self::DELIMITER_EMAIL)) ? self::RESULT_EMAILEXISTS : self::RESULT_USERNAMEEXISTS;
		$ezUser		= self::lookup($username_or_email);

		return ($ezUser->status() === self::STATUS_UNKNOWN) || ($ezUser->id() === $id) ? self::RESULT_VALIDATED : $resultCode;
	}

// ---------------------------------------------------------------------------
	private static /*.DOMElement.*/ function createRecord(/*.DOMDocument.*/ $storage, /*.ezUser.*/ $ezUser) {
		$record = $storage->createElement(self::TAGNAME_USER);

		// Add username
		$record->appendChild($storage->createTextNode("\n\t\t")); // XML formatting
		$record->appendChild($storage->createElement(self::TAGNAME_USERNAME, $ezUser->username()));

		// Add email address
		$record->appendChild($storage->createTextNode("\n\t\t")); // XML formatting
		$record->appendChild($storage->createElement(self::TAGNAME_EMAIL, $ezUser->email()));

		// Add id
		$record->appendChild($storage->createTextNode("\n\t\t")); // XML formatting
		$record->appendChild($storage->createElement(self::TAGNAME_ID, $ezUser->id()));

		// Add data blob
		$record->appendChild($storage->createTextNode("\n\t\t")); // XML formatting
		$record->appendChild($storage->createElement(self::TAGNAME_DATA, $ezUser->data()));

		// Note when the record was updated
		$record->appendChild($storage->createTextNode("\n\t\t")); // XML formatting
		$record->appendChild($storage->createElement('updated', gmdate("Y-m-d H:i:s (T)")));

		$record->appendChild($storage->createTextNode("\n\t")); // XML formatting

		return $record;
	}

// ---------------------------------------------------------------------------
	private static /*.int.*/ function add(/*.ezUser.*/ &$ezUser) {
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
	private static /*.int.*/ function update(/*.ezUser.*/ &$ezUser) {
		$storage	= self::openStorage();
		$oldRecord	= self::findUser($storage, $ezUser->id(), true);

		if (!$oldRecord->hasChildNodes()) return self::RESULT_STORAGEERR;

		$newRecord	= self::createRecord($storage, $ezUser);

		$oldRecord->parentNode->replaceChild($newRecord, $oldRecord);
		self::closeStorage($storage);
		return self::RESULT_SUCCESS;
	}

// ---------------------------------------------------------------------------
	public static /*.ezUser.*/ function doSignIn(/*.array[string]mixed.*/ $userData) {
		$username	= (string) $userData[self::COOKIE_USERNAME];
		$password	= (string) $userData[self::COOKIE_PASSWORD];
		$ezUser		= self::lookup($username);

		if ($ezUser->status() === self::STATUS_UNKNOWN) {
			$result = self::RESULT_UNKNOWNUSER;
		} else {
			$result = ($ezUser->authenticate($password)) ? self::RESULT_SUCCESS : self::RESULT_BADPASSWORD;
		}

		$ezUser->setResult($result);
		return $ezUser;
	}

// ---------------------------------------------------------------------------
	public static /*.ezUser.*/ function save(/*.array[string]mixed.*/ $userData) {
		$result			= self::RESULT_VALIDATED;
		$newUser		= (array_key_exists(self::TAGNAME_NEW, $userData) && ($userData[ezUser::TAGNAME_NEW] === self::STRING_TRUE)) ? true : false;
		$emailChanged		= false;
		$usernameChanged	= false;

		if (!$newUser) {
			$ezUser =& self::getSessionObject(self::ACTION_ACCOUNT);
			if ($ezUser->authenticated()) $ezUser->clearErrors(); else $newUser = true;
		}

		if ($newUser) {
			$ezUser = new ezUser();
			self::setSessionObject($ezUser, self::ACTION_ACCOUNT);
		}

		// Update email address
		if (array_key_exists(self::TAGNAME_EMAIL, $userData)) {
			$email			= (string) $userData[self::TAGNAME_EMAIL];
			$emailChanged		= ($email !== $ezUser->email());
			$thisResult		= $ezUser->setEmail($email);
			$result			= ($result === self::RESULT_VALIDATED) ? $thisResult : $result;
		}

		// Update username
		if (array_key_exists(self::COOKIE_USERNAME, $userData)) {
			$username		= (string) $userData[self::COOKIE_USERNAME];
			$usernameChanged	= ($username !== $ezUser->username());
			$thisResult		= $ezUser->setUsername($username);
			$result			= ($result === self::RESULT_VALIDATED) ? $thisResult : $result;
		}

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
			if ($ezUser->isChanged) {
				if ($newUser || $emailChanged) {
					$ezUser->setStatus(self::STATUS_PENDING);
					self::sendVerificationEmail($email);
				}

				if ($ezUser->incomplete()) {
					$result = self::RESULT_INCOMPLETE;
				} else {
					$result = ($newUser) ? self::add($ezUser) : self::update($ezUser);
					$ezUser->isChanged = ($result !== self::RESULT_SUCCESS);
				}
			} else {
				$result = self::RESULT_SUCCESS;
			}
		}

		$ezUser->setResult($result);
		return $ezUser;
	}
}
// End of class ezUsers

/**
 * ezUserUI
 * 
 * This class manages the HTML, CSS and Javascript that you can include in
 * your web pages to support user registration and authentication.
 */
class ezUserUI extends ezUsers {
	/*.private.*/ const	MESSAGE_TYPE_DEFAULT	= 'message',
				MESSAGE_TYPE_TEXT	= 'text',

				MESSAGE_STYLE_DEFAULT	= 'info',
				MESSAGE_STYLE_FAIL	= 'fail',
				MESSAGE_STYLE_TEXT	= 'text',
				MESSAGE_STYLE_PLAIN	= 'plain';

// ---------------------------------------------------------------------------
// Functions for sending stuff to the browser
// ---------------------------------------------------------------------------
	private static /*.string.*/ function containerHeader() {return self::PACKAGE . '-container';}

	private static /*.void.*/ function sendContent(/*.string.*/ $content, $container = self::PACKAGE, $contentType = 'text/html') {
		// Send headers first
		if (!headers_sent()) {
			$package = self::PACKAGE;
			if ($container === '') $container = $package;

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

	private static /*.string.*/ function getXML($html = '', $container = self::PACKAGE) {
		$package = self::PACKAGE;
		if (is_numeric($container)) $container = $package; // If passed to sendXML as an array
		return "<$package container=\"$container\"><![CDATA[$html]]></$package>";
	}

	private static /*.void.*/ function sendXML(/*.mixed.*/ $content = '', $container = self::PACKAGE) {
		if (is_array($content)) {
			// Expected array format is $content['container'] = '<html>'
			$package	= self::PACKAGE;
			$contentArray	= /*.(array[]string).*/ $content;
			$xmlArray	= /*.(array[]string).*/ array_map('ezUserUI::getXML', $contentArray, array_keys($contentArray)); // wrap each element
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
	private static /*.string.*/ function htmlInputText() {
		$package	= self::PACKAGE;
		$onKeyUp	= $package . "_keyUp";

		return <<<HTML
					class		=	"$package-text"
					onkeyup		=	"$onKeyUp(event)"
					size		=	"40"
HTML;
	}

	private static /*.string.*/ function htmlButton(/*.string.*/ $type, $verbose = false) {
		$package	= self::PACKAGE;
		$tagVerbose	= self::TAGNAME_VERBOSE;
		$classVerbose	= ($verbose) ? " $package-preference-$tagVerbose" : "";
		$setButtonState	= $package . "_setButtonState";
		$onClick	= $package . "_click";

		return <<<HTML
					type		=	"button"
					class		=	"$package-button $package-$type$classVerbose $package-buttonstate-0"
					onclick		=	"$onClick(this)"
					onmouseover	=	"$setButtonState(this, 1, true)"
					onmouseout	=	"$setButtonState(this, 1, false)"
					onfocus		=	"$setButtonState(this, 2, true)"
					onblur		=	"$setButtonState(this, 2, false)"
HTML;
	}

	private static /*.string.*/ function htmlMessage($message = '', $style = self::MESSAGE_STYLE_DEFAULT, $container = '', $type = self::MESSAGE_TYPE_DEFAULT) {
		$package	= self::PACKAGE;
		$style		= ($message === '') ? 'hidden' : $style;
		$message	= "<p class=\"$package-message-$style\">$message</p>";
		$id		= ($container === '') ? "$package-$type" : "$container-$type";
		$onClick	= $package . "_click";

		return <<<HTML
				<div id="$id" class="$package-$type" onclick="$onClick(this)">$message</div>
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

	private static /*.string.*/ function statusDescription(/*.int.*/ $status, $more = '', $sendToBrowser = false) {
		switch ($status) {
			case self::STATUS_PENDING:		$text = "Your account has been created and a confirmation email has been sent. Please click on the link in the confirmation email to verify your account.";	break;
			default:				$text = self::statusText($status, $more);		break;
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

	private static /*.string.*/ function resultDescription(/*.int.*/ $result, $more = '', $sendToBrowser = false) {
		switch ($result) {
			case self::RESULT_EMAILFORMATERR:	$text = "The format of the email address you entered was incorrect. Email addresses should be in the form <em>joe.smith@example.com</em>";	break;
			default:				$text = self::resultText($result, $more);		break;
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
 * This function is also driven by the mode parameter as follows:
 *
 * Mode		Behaviour
 * -------	----------------------------------------------
 * - (none)	Infer mode from current ezUser object - if
 * 		it's authenticated then display the account
 * 		page for that user. If not then display a
 * 		registration form for a new user. Inferred
 * 		mode will be 'display' or 'new'
 * 
 * - new	Register a new user. Input controls are blank
 * 		but available. Button says Register.
 * 
 * - display	View account details. Input controls are
 * 		populated but unavailable. Button says Edit.
 * 
 * - edit	Edit an existing account or correct a failed
 * 		registration. Input controls are populated
 * 		with existing data. Buttons say OK and Cancel
 * 
 * - result	Infer actual mode from result of validation.
 * 		If validated then display account details,
 * 		otherwise allow them to be corrected.
 * 		Inferred mode will be either 'display' or
 * 		'edit'.
 * 
 * - cancel	Infer actual mode from $newUser. If we're
 * 		cancelling a new registration then clear the
 * 		form. If we're cancelling editing an existing
 * 		user then redisplay details from the database.
 * 		Inferred mode will be either 'new' or
 * 		'display'.
 * 
 * So, the difference between $mode = 'new' and $newUser = true is as
 * follows:
 * 
 * - $mode = 'new'	means this is a blank form for a new
 * 			registration
 * 
 * - $newUser = true	means we are processing a new registration but
 * 			we might be asking the user to re-enter
 * 			certain values: the form might therefore need
 * 			to be populated with the attempted
 * 			registration details.
 * 
 * @param string	$mode		See above
 * @param boolean	$newUser	Is this a new or existing user?
 * @param boolean	$sendToBrowser	Send HTML to browser?
 */
	private static /*.string.*/ function htmlAccountForm($mode = '', $newUser = false, $sendToBrowser = false) {
		/* Comment out profiling statements if not needed
		global $ezUser_profile;
		$ezUser_profile[self::ACTION_ACCOUNT . '-start'] = ezUser_time();
		*/

		$package		= self::PACKAGE;
		$action			= self::ACTION_ACCOUNT;
		$actionResend		= self::ACTION_RESEND;
		$actionValidate		= self::ACTION_VALIDATE;
		$container		= self::getInstanceId($action);

		$tagFirstName		= self::TAGNAME_FIRSTNAME;
		$tagLastName		= self::TAGNAME_LASTNAME;
		$tagEmail		= self::TAGNAME_EMAIL;
		$tagUsername		= self::TAGNAME_USERNAME;
		$tagPassword		= self::TAGNAME_PASSWORD;
		$tagConfirm		= self::TAGNAME_CONFIRM;
		$tagNew			= self::TAGNAME_NEW;
		$tagUseSavedPassword	= self::TAGNAME_SAVEDPASSWORD;

		$modeNew		= self::ACCOUNT_MODE_NEW;
		$modeEdit		= self::ACCOUNT_MODE_EDIT;
		$modeDisplay		= self::ACCOUNT_MODE_DISPLAY;
		$modeResult		= self::ACCOUNT_MODE_RESULT;
		$modeCancel		= self::ACCOUNT_MODE_CANCEL;

		$htmlButtonAction	= self::htmlButton(self::ACTION);
		$passwordOnFocus	= $package . '_passwordFocus';
		$passwordOnBlur		= $package . '_passwordBlur';
		$htmlInputText		= self::htmlInputText();
		$messageShort		= self::htmlMessage('* = mandatory field', self::MESSAGE_STYLE_PLAIN, $container);
		$resendButton		= '';

		if (!isset($mode) || empty($mode)) $mode = '';

		$modeInfo		= ($newUser) ? self::STRING_TRUE : self::STRING_FALSE;
		$modeInfo		= "(originally mode was '$mode', new flag was $modeInfo) -->";

		if ($mode === '') {
			$ezUser	=& self::getSessionObject();
			$result	= self::RESULT_SUCCESS;

			if ($ezUser->authenticated()) {
				$mode	= $modeDisplay;
				$temp	= $ezUser->signOutActions;
				$temp	.= ($temp === '') ? '' : self::DELIMITER_SPACE;
				$ezUser->signOutActions	= $temp . $action; // Space-delimited string of actions

				self::setSessionObject($ezUser, $action);
			} else {
				$mode	= $modeNew;
				$ezUser->signOutActions	= '';
			}
		} else {
			$ezUser	=& self::getSessionObject($action);
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

			$buttonID		= $actionValidate;
			$buttonText		= 'Register';
			$disabled		= '';
			$htmlOtherButton	= "\t\t\t\t<input id=\"$container-$modeCancel\" value=\"Cancel\"\n\t\t\t\t\ttabindex\t=\t\"3218\"\n$htmlButtonAction\n\t\t\t\t/>\n";
			$useSavedPassword	= false;
			$messageLong		= self::htmlMessage('', self::MESSAGE_STYLE_TEXT, $container, self::MESSAGE_TYPE_TEXT);
			break;
		case self::ACCOUNT_MODE_DISPLAY:
			$errors			= $ezUser->errors();

			$email			= (array_key_exists(self::TAGNAME_EMAIL, $errors))	? $errors[self::TAGNAME_EMAIL]		: $ezUser->email();
			$firstName		= (array_key_exists(self::TAGNAME_FIRSTNAME, $errors))	? $errors[self::TAGNAME_FIRSTNAME]	: $ezUser->firstName();
			$lastName		= (array_key_exists(self::TAGNAME_LASTNAME, $errors))	? $errors[self::TAGNAME_LASTNAME]	: $ezUser->lastName();
			$username		= (array_key_exists(self::TAGNAME_USERNAME, $errors))	? $errors[self::TAGNAME_USERNAME]	: $ezUser->username();
			$password		= ($ezUser->passwordHash() === '') ? '' : self::PASSWORD_MASK;

			$buttonID		= $modeEdit;
			$buttonText		= 'Edit';
			$disabled		= "\t\t\t\t\tdisabled\t=\t\"disabled\"\r\n";
			$htmlOtherButton	= "\t\t\t\t<input id=\"$container-$modeNew\" value=\"New\"\n\t\t\t\t\ttabindex\t=\t\"3218\"\n$htmlButtonAction\n\t\t\t\t/>\n";
			$useSavedPassword	= false;
			$newUser		= false;

			if ($result === self::RESULT_SUCCESS || $result === self::RESULT_UNDEFINED) {
				// Show status information
				$status		= $ezUser->status();
				$messageLong	= ($status === self::STATUS_CONFIRMED) ? '' : self::statusDescription($status);
				$messageLong	= self::htmlMessage($messageLong, self::MESSAGE_STYLE_TEXT, $container, self::MESSAGE_TYPE_TEXT);

				if ($status === self::STATUS_PENDING) $resendButton = "\n\t\t\t\t<input id=\"$container-$actionResend\" value=\"Resend\"\n\t\t\t\t\ttabindex\t=\t\"3219\"\n$htmlButtonAction\n\t\t\t\t/>";
			} else {
				// Show result information
				$messageLong	= self::resultDescription($result);
				$messageLong	= self::htmlMessage($messageLong, self::MESSAGE_STYLE_FAIL, $container, self::MESSAGE_TYPE_TEXT);
			}

			break;
		case self::ACCOUNT_MODE_EDIT:
			$errors			= $ezUser->errors();

			$email			= (array_key_exists(self::TAGNAME_EMAIL,	$errors)) ? $errors[self::TAGNAME_EMAIL]	: $ezUser->email();
			$firstName		= (array_key_exists(self::TAGNAME_FIRSTNAME,	$errors)) ? $errors[self::TAGNAME_FIRSTNAME]	: $ezUser->firstName();
			$lastName		= (array_key_exists(self::TAGNAME_LASTNAME,	$errors)) ? $errors[self::TAGNAME_LASTNAME]	: $ezUser->lastName();
			$username		= (array_key_exists(self::TAGNAME_USERNAME,	$errors)) ? $errors[self::TAGNAME_USERNAME]	: $ezUser->username();
			$password		= ($ezUser->passwordHash() === '') ? '' : self::PASSWORD_MASK;

			$buttonID		= $actionValidate;
			$buttonText		= 'OK';
			$disabled		= '';
			$htmlOtherButton	= "\t\t\t\t<input id=\"$container-$modeCancel\" value=\"Cancel\"\n\t\t\t\t\ttabindex\t=\t\"3218\"\n$htmlButtonAction\n\t\t\t\t/>\n";
			$useSavedPassword	= $newUser;

			if ($result === self::RESULT_SUCCESS || $result === self::RESULT_UNDEFINED) {
				$messageLong	= self::htmlMessage('', self::MESSAGE_STYLE_TEXT, $container, self::MESSAGE_TYPE_TEXT);
			} else {
				// Show result information
				$messageLong	= self::resultDescription($result);
				$messageLong	= self::htmlMessage($messageLong, self::MESSAGE_STYLE_FAIL, $container, self::MESSAGE_TYPE_TEXT);
			}

			break;
		default:
		}

		// At this point we have finished with the result of any prior validation
		// so we can clear the result field
		$ezUser->setResult(self::RESULT_UNDEFINED);

		$newString		= ($newUser)		? self::STRING_TRUE : self::STRING_FALSE;
		$useSavedPasswordString	= ($useSavedPassword)	? self::STRING_TRUE : self::STRING_FALSE;
		$modeInfo		= "<!-- Mode is '$mode', new flag is $newString $modeInfo";

		$html = <<<HTML
		$modeInfo
		<form id="$package-$action-form" class="$package-form" onsubmit="return false">
			<fieldset class="$package-fieldset">
				<input id= "$container-$tagEmail"
					tabindex	=	"3211"
					value		=	"$email"
					type		=	"text"
$disabled$htmlInputText
				/>
				<label class="$package-label" for="$container-$tagEmail">* Email address:</label>
				<input id= "$container-$tagFirstName"
					tabindex	=	"3212"
					value		=	"$firstName"
					type		=	"text"
$disabled$htmlInputText
				/>
				<label class="$package-label" for="$container-$tagFirstName">First name:</label>
				<input id= "$container-$tagLastName"
					tabindex	=	"3213"
					value		=	"$lastName"
					type		=	"text"
$disabled$htmlInputText
				/>
				<label class="$package-label" for="$container-$tagLastName">Last name:</label>
				<input id= "$container-$tagUsername"
					tabindex	=	"3214"
					value		=	"$username"
					type		=	"text"
$disabled$htmlInputText
				/>
				<label class="$package-label" for="$container-$tagUsername">* Username:</label>
				<input id= "$container-$tagPassword"
					tabindex	=	"3215"
					value		=	"$password"
					type		=	"password"
					onfocus		=	"$passwordOnFocus(this)"
					onblur		=	"$passwordOnBlur(this)"
$disabled$htmlInputText
				/>
				<label class="$package-label" for="$container-$tagPassword">* Password:</label>
				<input id= "$container-confirm"
					tabindex	=	"3216"
					value		=	"$password"
					type		=	"password"
					onfocus		=	"$passwordOnFocus(this)"
					onblur		=	"$passwordOnBlur(this)"
$disabled$htmlInputText
				/>
				<label class="$package-label" for="$container-$tagConfirm">* Confirm password:</label>
			</fieldset>
			<fieldset class="$package-fieldset">
$messageShort
				<input id="$container-$buttonID" value="$buttonText"
					tabindex	=	"3217"
$htmlButtonAction
				/>
$htmlOtherButton			</fieldset>
			<fieldset class="$package-fieldset">
$messageLong$resendButton
				<input id="$container-$tagNew"			type="hidden" value="$newString" />
				<input id="$container-$tagUseSavedPassword"	type="hidden" value="$useSavedPasswordString" />
			</fieldset>
		</form>
HTML;

		/* Comment out profiling statements if not needed
		$ezUser_profile[self::ACTION_ACCOUNT . '-end'] = ezUser_time();
		*/

		if ($sendToBrowser) {self::sendXML($html, $container); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlDashboard($sendToBrowser = false) {
		$package		= self::PACKAGE;
		$action			= self::ACTION_DASHBOARD;
		$actionSignOut		= self::ACTION_SIGNOUT;
		$tagFullName		= self::TAGNAME_FULLNAME;
		$htmlButtonPreference	= self::htmlButton("preference");
		$message		= self::htmlMessage();
		$ezUser			=& self::getSessionObject();
		$fullName		= $ezUser->fullName();

		$html = <<<HTML
		<form id="$package-$action-form" class="$package-form" onsubmit="return false">
			<fieldset class="$package-fieldset">
				<input id="$package-$actionSignOut" value="Sign out"
					tabindex	=	"3222"
$htmlButtonPreference
				/>
				<input id="$package-goaccount" value="My account"
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

		$package		= self::PACKAGE;
		$action			= self::ACTION_SIGNIN;
		$tagUsername		= self::TAGNAME_USERNAME;
		$tagPassword		= self::TAGNAME_PASSWORD;
		$tagVerbose		= self::TAGNAME_VERBOSE;

		$htmlButtonAction	= self::htmlButton(self::ACTION);
		$htmlButtonPreference	= self::htmlButton('preference');
		$passwordOnFocus	= $package . '_passwordFocus';
		$passwordOnBlur		= $package . '_passwordBlur';
		$htmlInputText		= self::htmlInputText();
		$ezUser			=& self::getSessionObject();
		$result			= $ezUser->result();

		if ($result <= self::RESULT_SUCCESS) {
			$message = self::htmlMessage();
			$verboseHTML = "";
		} else {
			$username = $ezUser->username();
			$message = self::htmlMessage("Check username &amp; password", self::MESSAGE_STYLE_FAIL);

			if ($verbose) {
				$verboseHTML = self::htmlButton("preference", true);
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
				<label class="$package-label" for="$package-$tagUsername">Username:</label>
				<input id= "$package-$tagPassword"
					tabindex	=	"3202"
					value		=	"$password"
					type		=	"password"
					onfocus		=	"$passwordOnFocus(this)"
					onblur		=	"$passwordOnBlur(this)"
$htmlInputText
				/>
				<label class="$package-label" for="$package-$tagPassword">Password:</label>
$verboseHTML			</fieldset>
			<fieldset class="$package-fieldset">
$message
				<input id="$package-$action" value="Sign in"
					tabindex	=	"3204"
$htmlButtonAction
				/>
				<input id="$package-goaccount" value="Register"
					tabindex	=	"3203"
$htmlButtonAction
				/>
			</fieldset>
			<fieldset class="$package-fieldset">
				<input id="$package-staySignedIn" value="Stay signed in"
					tabindex	=	"3207"
$htmlButtonPreference
				/>
				<input id="$package-rememberMe" value="Remember me"
					tabindex	=	"3206"
$htmlButtonPreference
				/>
				<input id="$package-reminder" value="Reset password"
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
		$ezUser =& self::getSessionObject();
		$html = ($ezUser->authenticated()) ? self::htmlDashboard() : self::htmlSignInForm($username);
		if ($sendToBrowser) {self::sendXML($html); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlResultForm (/*.int.*/ $result, $more = '', $sendToBrowser = false) {
		$package		= self::PACKAGE;
		$action			= self::ACTION_RESULTFORM;
		$htmlButtonPreference	= self::htmlButton("preference");
		$message		= self::htmlMessage(self::resultText($result, $more));

		$html = <<<HTML
		<form id="$package-$action-form" onsubmit="return false">
			<fieldset class="$package-fieldset">
$message
				<input id="$package-OK" value="OK"
					tabindex	=	"3231"
$htmlButtonPreference
				/>
			</fieldset>
		</form>
HTML;

		if ($sendToBrowser) {self::sendXML($html); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
	private	static /*.string.*/ function htmlAbout($sendToBrowser = false) {
		$package = self::PACKAGE;
		$matches = /*.(array[int]mixed).*/ array();
		preg_match_all("!(?<=^ \\* @)(?:.)+(?=$)!m", file_get_contents("$package.php", 0, NULL, -1, 4096), $matches);
		$html = "<pre>\n";
		foreach (/*.(array[]string).*/ $matches[0] as $match) {$html .= "    " . htmlspecialchars($match) . "\n";}
		$html .= "</pre>\n";

		if ($sendToBrowser) {self::sendContent($html); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
	private	static /*.string.*/ function htmlSourceCode($sendToBrowser = false) {
		$html = (string) highlight_file(__FILE__, 1);
		if ($sendToBrowser) {self::sendContent($html); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlContainer($action = self::ACTION_MAIN, $sendToBrowser = false) {
		$package	= self::PACKAGE;
		$baseAction	= explode(self::EQUALS, $action);
		$container	= self::getInstanceId($baseAction[0]);
		$actionCommand	= self::ACTION;
		$actionJs	= self::ACTION_JAVASCRIPT;
		$URL		= self::thisURL();
		$js		= $package . '_ajax';
		$js		.= "[$js.push(new C_$js()) - 1].execute('$action')";

		$html = <<<HTML
	<div id="$container"></div>
	<script type="text/javascript">document.write(unescape('%3Cscript src="$URL?$actionCommand=$actionJs"%3E%3C/script%3E'));</script>
	<script type="text/javascript">$js;</script>
HTML;

		if ($sendToBrowser) {self::sendContent($html); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
// CSS & Javascript
// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlStyleSheet($sendToBrowser = false) {
		$package	= self::PACKAGE;
		$container	= self::getInstanceId(self::ACTION_ACCOUNT);
		$tagFullName	= self::TAGNAME_FULLNAME;
		$tagVerbose	= self::TAGNAME_VERBOSE;
		$action		= self::ACTION;

		$css = <<<CSS
/**
 * @package	ezUser
 * @author	Dominic Sayers <dominic_sayers@hotmail.com>
 * @copyright	2009 Dominic Sayers
 * @license	http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link	http://code.google.com/p/ezuser/
 * @version	0.18 - Less cruft and more win
 */

.dummy {} /* Webkit is ignoring the first item so we'll put a dummy one in */

div#$package {
	font-family:Segoe UI, Calibri, Arial, Helvetica, sans-serif;
	font-size:11px;
	line-height:100%;
	width:286px;
	float:left;
}

div#$container {
	font-family:Segoe UI, Calibri, Arial, Helvetica, sans-serif;
	font-size:12px;
	line-height:100%;
	width:286px;
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

form.$package-form		{margin:0;}
fieldset.$package-fieldset	{margin:0;padding:0;border:0;clear:right;}
label.$package-label		{float:right;padding:4px;}

input.$package-text {
	float:right;
	font-size:11px;
	width:160px;
	margin-bottom:4px;
}

input.$package-button {
	float:right;
	padding:2px;
	font-family:Segoe UI, Calibri, Arial, Helvetica, sans-serif;
	border-style:solid;
	border-width:1px;
	cursor:pointer;
}

input.$package-$action {
	font-size:12px;
	width:52px;
	margin:0 0 0 6px;
}

input.$package-preference {
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
		$package		= self::PACKAGE;
		$container		= self::getInstanceId(self::ACTION_ACCOUNT);

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
		$tagNew			= self::TAGNAME_NEW;
		$tagUseSavedPassword	= self::TAGNAME_SAVEDPASSWORD;
		$tagVerbose		= self::TAGNAME_VERBOSE;

		$action			= self::ACTION;
		$actionMain		= self::ACTION_MAIN;
		$actionAccount		= self::ACTION_ACCOUNT;
		$actionAccountInPanel	= self::ACTION_PANELACCOUNT;
		$actionValidate		= self::ACTION_VALIDATE;
		$actionSignIn		= self::ACTION_SIGNIN;
		$actionSignOut		= self::ACTION_SIGNOUT;
		$actionCancel		= self::ACTION_CANCEL;
		$actionCSS		= self::ACTION_STYLESHEET;
		$actionResultForm	= self::ACTION_RESULTFORM;
		$actionResend		= self::ACTION_RESEND;

		$modeNew		= self::ACCOUNT_MODE_NEW;
		$modeEdit		= self::ACCOUNT_MODE_EDIT;
		$modeCancel		= self::ACCOUNT_MODE_CANCEL;

		$delimPlus		= self::DELIMITER_PLUS;
		$equals			= self::EQUALS;
		$stringTrue		= self::STRING_TRUE;
		$passwordMask		= self::PASSWORD_MASK;

		$accountPage		= self::getSetting(self::SETTINGS_ACCOUNTPAGE);
		$accountClick		= ($accountPage === '') ? $package . "_ajax[0].execute('$actionAccountInPanel')" : "window.location = '$folder/$accountPage'";
		$containerHeader	= self::containerHeader();

		// Append code to request container content
		if ($containerList === '') {
			$immediateJavascript = '';
		} else {
			// Space-separated list of containers to fill
			$immediateJavascript = "ezUser_getHTML('" . (string) str_replace(self::DELIMITER_SPACE, self::DELIMITER_PLUS, $containerList) . "');";
		}

		$js = <<<JAVASCRIPT
/**
 * @package	ezUser
 * @author	Dominic Sayers <dominic_sayers@hotmail.com>
 * @copyright	2009 Dominic Sayers
 * @license	http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link	http://code.google.com/p/ezuser/
 * @version	0.18 - Less cruft and more win
 */

/*global window, document, event, ActiveXObject, SHA256, ajaxUnit */ // For JSLint
'use strict';
var ezUser, ezUser_ajax = [];

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

function ezUser_SHA256plusIP(s) {
	return SHA256('$remoteAddress' + SHA256(s));
}

// ---------------------------------------------------------------------------
//		ezUser_setButtonState
// ---------------------------------------------------------------------------
// Responds to various UI events and controls the appearance of the form's
// buttons
// ---------------------------------------------------------------------------
function ezUser_setButtonState(control, eventID, setOn) {
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
}

// ---------------------------------------------------------------------------
//		C_ezUser_cookies
// ---------------------------------------------------------------------------
// General cookie management
// ---------------------------------------------------------------------------
function C_ezUser_cookies() {
	// Public methods
	this.persist = function(name, value, days) {
		var date, expires;

		if (typeof days !== 'undefined') {
			date = new Date();
			date.setTime(date.getTime() + (days * 1000 * 3600 * 24));
			expires = '; expires=' + date.toGMTString();
		} else {
			expires = '';
		}

		document.cookie = name + '$equals' + value + expires + '; path=/';
	};

	this.acquire = function(name) {
		name = name + '$equals';
		var i, c, carray = document.cookie.split(';');

		for (i = 0; i < carray.length; i += 1) {
			c = carray[i];
			while (c.charAt(0) === ' ') {c = c.substring(1, c.length);}
			if (c.indexOf(name) === 0) {return c.substring(name.length, c.length);}
		}

		return null;
	};

	this.remove = function(name) {this.persist(name, '', -1);};
}

// ---------------------------------------------------------------------------
//		C_ezUser
// ---------------------------------------------------------------------------
// The main ezUser client-side class
// ---------------------------------------------------------------------------
function C_ezUser() {
	// Private properties
	var that = this, cookies = new C_ezUser_cookies();

	// Private methods
// ---------------------------------------------------------------------------
	function getCookies() {
		var	username	= cookies.acquire('$cookieUsername'),
			passwordHash	= cookies.acquire('$cookiePassword');

		that.staySignedIn	= cookies.acquire('$cookieStaySignedIn');
		that.staySignedIn	= (that.staySignedIn === null) ? false : true;
		that.sessionID		= cookies.acquire('$sessionName');

		if (username === null) {
			that.staySignedIn		= false;
		} else {
			that.username			= username;
			that.rememberMe			= true;
		}

		if (passwordHash === null) {
			that.staySignedIn		= false;
			that.passwordDefault_SignIn	= false;
		} else {
			that.passwordHash		= passwordHash;
			that.passwordDefault_SignIn	= true;
			that.rememberMe			= true;
		}
	}

// ---------------------------------------------------------------------------
	function setFocus(textBox) {
		var doEvent;

		if (typeof document.activeElement.onBlur === 'function') {document.activeElement.onBlur()}
		if (typeof document.activeElement.onblur === 'function') {document.activeElement.onblur()}
		if (typeof textBox.onFocus === 'function') {doEvent = textBox.onFocus(textBox);}
		if (typeof textBox.onfocus === 'function') {doEvent = textBox.onfocus(textBox);}
		if (doEvent !== false) {textBox.focus();}
		textBox.select();
	}

// ---------------------------------------------------------------------------
	function setInitialFocus(id) {
		// Set focus to the first text control
		var textId = '', textBox = null;

		switch (id) {
		case '$package':
			textId = '$package-$tagUsername';
			break;
		case '$container':
			textId = '$container-$tagEmail';
			break;
		}

		if (textId !== '') {textBox = document.getElementById(textId);}
		if (textBox === null || typeof textBox === 'undefined' || textBox.disabled === 'disabled') {return;}
		setFocus(textBox);
	}

// ---------------------------------------------------------------------------
	// Public properties
	this.sessionID			= '';
	this.rememberMe			= false;
	this.staySignedIn		= false;
	this.username			= '';
	this.passwordHash		= '';
	this.passwordSaved		= '';
	this.passwordDefault_SignIn	= false;
	this.passwordDefault_Account	= false;
	this.usernameDefault_Account	= false;

	// Public methods
// ---------------------------------------------------------------------------
	this.getValue = function(id)		{return document.getElementById(id).value;};
	this.setValue = function(id, value)	{document.getElementById(id).value = value;};

	this.updateCookies = function() {
		this.username = this.getValue('$package-$tagUsername');
		
		if (!this.passwordDefault_SignIn || (this.passwordHash === '')) {
			var password		= this.getValue('$package-$tagPassword');
			this.passwordHash	= ezUser_SHA256plusIP(password);
		}

		if (this.rememberMe) {
			// Remember username & password for 30 days
			cookies.persist('$cookieUsername', this.username, 30);
			cookies.persist('$cookiePassword', this.passwordHash, 30);
		} else {
			cookies.remove('$cookieUsername');
			cookies.remove('$cookiePassword');
		}

		if (this.staySignedIn) {
			// Stay signed in for 2 weeks
			cookies.persist('$cookieStaySignedIn', true, 24);
		} else {
			cookies.remove('$cookieStaySignedIn');
		}
	};

// ---------------------------------------------------------------------------
	this.showMessage = function(message, fail, messageType, instance) {
		switch (arguments.length) {
		case 0:	message		= '';		// Fall-through ->
		case 1:	fail		= false;	// Fall-through ->
		case 2:	messageType	= 'message';	// Fall-through ->
		case 3:	instance	= '$package';
			break;
		}

		var	id		= instance + '-' + messageType,
			div		= document.getElementById(id),
			classString	= '$package-' + messageType,
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

		div = document.getElementById('$package-$tagVerbose');
		if (div !== null) {div.parentNode.removeChild(div);}
	};

// ---------------------------------------------------------------------------
	this.showPreferences = function() {
		ezUser_setButtonState(document.getElementById('$package-rememberMe'), 4, this.rememberMe);
		ezUser_setButtonState(document.getElementById('$package-staySignedIn'), 4, this.staySignedIn);
	};

// ---------------------------------------------------------------------------
	this.bodyAppend = function(html) {
		document.getElementsByTagName('body')[0].innerHTML += html;
	}

// ---------------------------------------------------------------------------
	this.fillContainerText = function(id, html) {
		var container = document.getElementById(id);

		if (container === null || typeof container === 'undefined') {
			var containerList = document.getElementsByTagName(id);
			
			if (containerList === null || typeof containerList === 'undefined' || containerList.length === 0) {
				window.alert('Can\\'t find a container \\'' + id + '\\' for this content: ' + html);
				return;
			} else {
				container = containerList[0];
			}
		}

		if (container.className.length === 0) {container.className = id;} // IE6 uses container.class
		container.innerHTML = html;

		var	formList	= container.getElementsByTagName('form'),
			formId		= ((typeof formList === 'undefined') || (formList.length === 0)) ? '' : formList[0].getAttribute('id');

		switch (formId) {
		case '$package-$actionSignIn-form':
			ezUser.showPreferences();

			if (this.rememberMe) {
				this.passwordDefault_SignIn = true;
				this.setValue('$package-$tagUsername', this.username);
				this.setValue('$package-$tagPassword', '$passwordMask');
			}

			break;
		case '$container-form':
			ezUser.usernameDefault_Account = (this.getValue('$container-$tagUsername') === '');
			ezUser.passwordDefault_Account = (this.getValue('$container-$tagNew') !== '$stringTrue');

			if (this.getValue('$container-$tagUseSavedPassword') === '$stringTrue') {
				this.setValue('$container-$tagPassword', ezUser.passwordSaved);
				this.setValue('$container-$tagConfirm', ezUser.passwordSaved);
			} else {
				ezUser.savedPassword = '';
			}

			break;
		}

		setInitialFocus(id);
	};

// ---------------------------------------------------------------------------
	this.fillContainersXML = function(xml) {
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
			case 8: // Node.COMMENT_NODE
				break; // Ignore
			default:
				window.alert('I wasn\\'t expecting a node type of ' + formNode.nodeType);
				break;
			}
		}
	};

// ---------------------------------------------------------------------------
	this.localValidation = function() {
		var	textEmail	= document.getElementById('$container-$tagEmail'),
			textUsername	= document.getElementById('$container-$tagUsername'),
			textPassword	= document.getElementById('$container-$tagPassword'),
			textConfirm	= document.getElementById('$container-$tagConfirm'),
			textNew		= document.getElementById('$container-$tagNew'),
			message		= '';

		// Valid email address
		if (textEmail.value === '') {
			message = 'You must provide an email address';
			this.showMessage(message, true, 'text', '$container');
			setFocus(textEmail);
			return false;
		}

		// Valid username
		this.normaliseUsername(textUsername.value);

		if (textUsername.value === '') {
			message = 'The username cannot be blank';
			this.showMessage(message, true, 'text', '$container');
			setFocus(textUsername);
			return false;
		}

		// Password OK?
		if (textPassword.value !== textConfirm.value) {
			message = 'Passwords are not the same';
		} else if (ezUser.passwordDefault_Account) {
			if (textNew.value === '$stringTrue') {message = 'Password cannot be blank';}
		} else if (textPassword.value === '') {
			message = 'Password cannot be blank';
		}

		if (message === '') {
			return true;
		} else {
			this.showMessage(message, true, 'text', '$container');
			setFocus(textPassword);
			return false;
		}
	};

// ---------------------------------------------------------------------------
	this.normaliseUsername = function(username) {
		var	regexString	= '[^0-9a-z_-]',
			regex		= new RegExp(regexString, 'g'),
			control		= document.getElementById('$container-$tagUsername');

		username		= username.toLowerCase();
		username		= username.replace(regex, '');
		control.defaultValue	= username;
		control.value		= username;
	};

// ---------------------------------------------------------------------------
	this.addStyleSheet = function() {
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
			node		= document.createElement('link');
			node.type	= 'text/css';
			node.rel	= 'stylesheet';
			node.href	= '$URL?$actionCSS';
			node.title	= '$package';
			htmlHead.appendChild(node);
		}
	};

// ---------------------------------------------------------------------------
// Constructor
// ---------------------------------------------------------------------------
	getCookies();
}

// ---------------------------------------------------------------------------
//		C_ezUser_ajax
// ---------------------------------------------------------------------------
// Talk to the man
// ---------------------------------------------------------------------------
function C_ezUser_ajax() {

	// Private methods
// ---------------------------------------------------------------------------
	function getXMLHttpRequest() {
		if (typeof window.XMLHttpRequest === 'undefined') {
			try {return new ActiveXObject('MSXML3.XMLHTTP');}	catch (errMSXML3) {}
			try {return new ActiveXObject('MSXML2.XMLHTTP.3.0');}	catch (errMSXML2) {}
			try {return new ActiveXObject('Msxml2.XMLHTTP');}	catch (errMsxml2) {}
			try {return new ActiveXObject('Microsoft.XMLHTTP');}	catch (errMicrosoft) {}
			return null;
		} else {
			return new window.XMLHttpRequest();
		}
	}

	// Private properties
	var	action,
		ajax = getXMLHttpRequest();

// ---------------------------------------------------------------------------
	function handleServerResponse() {
		if ((ajax.readyState === 4) && (ajax.status === 200)) {
			if (isNaN(ajax.responseText)) {
				var id = ajax.getResponseHeader('$containerHeader');

				if (ajax.responseXML !== null) {
					ezUser.fillContainersXML(ajax.responseXML);
				} else if (id === null) {
					ezUser.bodyAppend(ajax.responseText);
				} else {
					ezUser.fillContainerText(id, ajax.responseText);
				}

			} else {
				var	fail		= true,
					message		= 'Server error, please try later',
					cancelButton	= document.getElementById('$package-$actionCancel');

				ezUser.showMessage(message, fail);

				if (cancelButton !== null) {
					cancelButton.id		= '$package-$actionSignIn';
					cancelButton.value	= 'Sign in';
				}
			}

			// Unit testing callback function
			if (typeof ajaxUnit === 'function') {ajaxUnit(ajax);}
		}
	}

// ---------------------------------------------------------------------------
	function serverTalk(URL, requestType, requestData) {
		ajax.open(requestType, URL);
		ajax.onreadystatechange = handleServerResponse;
		ajax.setRequestHeader('Accept', 'text/html,application/$package');
		if (requestType === 'POST') {ajax.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');}
		ajax.send(requestData);
	}

// ---------------------------------------------------------------------------
	// Public methods
	this.execute = function(thisAction) {
		var	passwordHash,
			requestType,
			requestData	= '',
			URL		= '$URL',
			delimPos = thisAction.indexOf('$delimPlus');

		thisAction	= (delimPos === -1) ? thisAction : '$action=' + thisAction;
		delimPos	= thisAction.indexOf('$equals');
		action		= (delimPos === -1) ? thisAction : thisAction.slice(0, delimPos);

		switch (action) {
		case '$actionSignIn':
			document.getElementById('$package-$actionSignIn').id	= '$package-$actionCancel';
			ezUser.setValue('$package-$actionCancel', 'Cancel');

			ezUser.showMessage('Signing in - please wait');
			ezUser.updateCookies();	// Updates ezUser.passwordHash;

			passwordHash	= SHA256(ezUser.sessionID + ezUser.passwordHash);
			requestData	= '$action='		+ action;
			requestData	+= '&$cookieUsername='		+ ezUser.getValue('$package-$tagUsername');
			requestData	+= '&$cookiePassword='		+ passwordHash;
			requestType	= 'POST';

			break;
		case '$actionValidate':
			var textNew	= ezUser.getValue('$container-$tagNew');

			requestData	= '$action='		+ action;
			requestData	+= '&$tagNew='		+ textNew;
			requestData	+= '&$tagEmail='		+ ezUser.getValue('$container-$tagEmail');
			requestData	+= '&$tagFirstName='	+ ezUser.getValue('$container-$tagFirstName');
			requestData	+= '&$tagLastName='		+ ezUser.getValue('$container-$tagLastName');
			requestData	+= '&$cookieUsername='		+ ezUser.getValue('$container-$tagUsername');

			if (!ezUser.passwordDefault_Account || (textNew === '$stringTrue')) {
				passwordHash	= SHA256(ezUser.getValue('$container-$tagPassword'));
				requestData	+= '&$cookiePassword='	+ passwordHash;
			}

			requestType	= 'POST';

			break;
		case '$actionCancel':
			var readyState = ajax.readyState;

			if ((readyState > 0) && (readyState < 4)) {
				// Cancel ongoing sign-in
				ajax.abort();
				ajax = getXMLHttpRequest();
			}

			return;
		case '$actionResend':
			URL += '?' + thisAction;
			URL += '$equals' + ezUser.getValue('$container-$tagEmail');
			requestType = 'GET';
			break;
		default:
			URL += '?' + thisAction;
			requestType = 'GET';
			break;
		}

		serverTalk(URL, requestType, requestData);
	};
}

// ---------------------------------------------------------------------------
//		ezUser_click
// ---------------------------------------------------------------------------
// Responds to clicks on the ezUser form
// ---------------------------------------------------------------------------
function ezUser_click(control) {
	switch (control.id) {
	case '$package-$actionSignIn':
		ezUser_ajax[0].execute('$actionSignIn');
		break;
	case '$package-$actionSignOut':
		ezUser_ajax[0].execute('$actionSignOut');
		break;
	case '$package-goaccount':
		$accountClick;
		break;
	case '$package-$actionCancel':
		ezUser_ajax[0].execute('$actionCancel');
		break;
	case '$package-rememberMe':
		ezUser.rememberMe	= (ezUser.rememberMe) ? false : true;
		ezUser.staySignedIn	= (ezUser.rememberMe) ? ezUser.staySignedIn : false;

		ezUser.showPreferences();
		ezUser.updateCookies();
		break;
	case '$package-staySignedIn':
		ezUser.staySignedIn	= (ezUser.staySignedIn) ? false : true;
		ezUser.rememberMe	= (ezUser.staySignedIn) ? true : ezUser.rememberMe;

		ezUser.showPreferences();
		ezUser.updateCookies();
		break;
	case '$package-$tagVerbose':
		ezUser_ajax[0].execute('$actionResultForm=' + control.value);
		break;
	case '$package-OK':
		ezUser_ajax[0].execute('$actionMain');
		break;
	case '$container-$actionValidate':
		if (ezUser.localValidation()) {ezUser_ajax[0].execute('$actionValidate');}
		break;
	case '$container-$modeEdit':
		ezUser_ajax[0].execute('$actionAccount=$modeEdit');
		break;
	case '$container-$modeNew':
		ezUser_ajax[0].execute('$actionAccount=$modeNew');
		break;
	case '$container-$modeCancel':
		ezUser_ajax[0].execute('$actionAccount=$modeCancel');
		break;
	case '$container-$actionResend':
		ezUser_ajax[0].execute('$actionResend');
		break;
	}

	return true;
}

// ---------------------------------------------------------------------------
//		ezUser_keyUp
// ---------------------------------------------------------------------------
// Responds to key presses on the ezUser form
// ---------------------------------------------------------------------------
function ezUser_keyUp(e) {
	var keyChar;

	if (e && e.which) {
		e	= e;
		keyChar	= e.which;
	} else {
		if (typeof event !== 'undefined') {e = event;}
		keyChar = e.keyCode;
	}

	// Process Carriage Return and tidy up form
	var	formId	= e.target.form.id,
		id	= e.target.id;

	switch (formId) {
	case '$package-$actionSignIn-form':
		switch (id) {
		case '$package-$tagPassword':
			if (ezUser.passwordDefault_SignIn) {
				// Forget password from cookie
				ezUser.passwordHash		= '';
				ezUser.passwordDefault_SignIn	= false;
			}
		}

		if (keyChar === 13) {
			ezUser_click(document.getElementById('$package-$actionSignIn'));
		} else {
			ezUser.showMessage(); // Hide message
		}

		break;
	case '$container-form':
		switch (id) {
		case '$container-$tagUsername':
			// If we are messing with the username then forget creating a default
			ezUser.usernameDefault_Account = false;
			ezUser.normaliseUsername(ezUser.getValue(id));
			break;
		case '$container-$tagFirstName':
		case '$container-$tagLastName':
			if (ezUser.getValue('$container-$tagUsername') === '') {ezUser.usernameDefault_Account = true;}
			if (ezUser.usernameDefault_Account) {ezUser.normaliseUsername(ezUser.getValue('$container-$tagFirstName') + ezUser.getValue('$container-$tagLastName'));}
			break;
		case '$container-$tagPassword':
			ezUser.passwordSaved = e.target.value;
			ezUser.passwordDefault_Account = false;
			break;
		case '$container-$tagConfirm':
			ezUser.passwordDefault_Account = false;
			break;
		}

		if (keyChar === 13) {
			var control = document.getElementById('$container-$actionValidate');
			if (control === null) {control = document.getElementById('$container-$modeEdit');}
			ezUser_click(control);
		} else {
			ezUser.showMessage('', false, 'text', '$container'); // Hide message
		}

		break;
	}

	return true;
}

// ---------------------------------------------------------------------------
//		ezUser_passwordFocus and ezUser_passwordBlur
// ---------------------------------------------------------------------------
// Responds to focus arriving on or leaving a password input control
// ---------------------------------------------------------------------------
function ezUser_passwordFocus(control) {
	switch (control.form.id) {
	case '$package-$actionSignIn-form':
		if (ezUser.passwordDefault_SignIn) {control.value = '';}
		break;
	case '$container-form':
		if (ezUser.passwordDefault_Account) {
			ezUser.setValue('$container-$tagPassword', '');
			ezUser.setValue('$container-$tagConfirm', '');
		}
		break;
	}

	return true;
}

function ezUser_passwordBlur(control) {
	switch (control.form.id) {
	case '$package-$actionSignIn-form':
		if (ezUser.passwordDefault_SignIn) {control.value = '$passwordMask';}
		break;
	case '$container-form':
		if (ezUser.passwordDefault_Account) {
			ezUser.setValue('$container-$tagPassword', '$passwordMask');
			ezUser.setValue('$container-$tagConfirm', '$passwordMask');
		}
		break;
	}

	return true;
}

// ---------------------------------------------------------------------------
//		ezUser_getHTML
// ---------------------------------------------------------------------------
// Creates a new ajax object and uses it to get some HTML from the server
// ---------------------------------------------------------------------------
function ezUser_getHTML(action) {
	var	ajax		= new C_ezUser_ajax(),
		newIndex	= ezUser_ajax.push(ajax) - 1;

	ezUser_ajax[newIndex].execute(action);
}

// ---------------------------------------------------------------------------
// Do stuff
// ---------------------------------------------------------------------------
ezUser = new C_ezUser();
ezUser.addStyleSheet();
$immediateJavascript
JAVASCRIPT;
		if ($sendToBrowser) {self::sendContent($js, '', 'text/javascript'); return '';} else return $js;
	}

// ---------------------------------------------------------------------------
// 'Get' actions
// ---------------------------------------------------------------------------
// Methods may be commented out to reduce the attack surface if they are not
// required. Uncomment them if you need them.
//	public static /*.void.*/ function getStatusText		(/*.int.*/ $status, $more = '')	{self::statusText($status, $more,		true);}
//	public static /*.void.*/ function getResultText		(/*.int.*/ $result, $more = '')	{self::resultText($result, $more,		true);}
//	public static /*.void.*/ function getStatusDescription	(/*.int.*/ $status, $more = '')	{self::statusDescription($status, $more,	true);}
//	public static /*.void.*/ function getResultDescription	(/*.int.*/ $result, $more = '')	{self::resultDescription($result, $more,	true);}
	public static /*.void.*/ function getResultForm		(/*.int.*/ $result, $more = '')	{self::htmlResultForm($result, $more,		true);}
	public static /*.void.*/ function fatalError		(/*.int.*/ $result, $more = '')	{self::htmlResultForm($result, $more,		true); exit;}
	public static /*.void.*/ function getAccountForm	($mode = '', $newUser = false)	{self::htmlAccountForm($mode, $newUser,		true);}
//	public static /*.void.*/ function getDashboard		()				{self::htmlDashboard(				true);}
//	public static /*.void.*/ function getSignInForm		()				{self::htmlSignInForm(				true);}
	public static /*.void.*/ function getControlPanel	($username = '')		{self::htmlControlPanel($username,		true);}
//	public static /*.void.*/ function getStyleSheet		()				{self::htmlStyleSheet(				true);}
//	public static /*.void.*/ function getJavascript		($containerList = '')		{self::htmlJavascript($containerList,		true);}
//	public static /*.void.*/ function getContainer		($action = self::ACTION_MAIN)	{self::htmlContainer($action,			true);}
	public static /*.void.*/ function getAbout		()				{self::htmlAbout(				true);}
//	public static /*.void.*/ function getSourceCode		()				{self::htmlSourceCode(				true);}

// ---------------------------------------------------------------------------
// 'Do' actions
// ---------------------------------------------------------------------------
	private static /*.string.*/ function resendVerificationEmail($username_or_email = '', $sendToBrowser = false) {
		$success	= self::sendVerificationEmail($username_or_email);
		$message	= ($success) ? 'Verification email has been resent.' : 'Verification email was not sent: please try again later';
		$container	= self::getInstanceId(self::ACTION_ACCOUNT . '-' . self::MESSAGE_TYPE_TEXT);

		if ($sendToBrowser) {self::sendContent($message, $container); return '';} else return $message;
	}

// ---------------------------------------------------------------------------
	private static /*.array[int]string.*/  function findBestMatch(/*.array[int]string.*/ $refererElements, /*.string.*/ $folder) {
		$refererCount	= count($refererElements);
		$name		= $refererElements[$refererCount - 1];
		$filename	= realpath("$folder/$name");
		$score		= 0;

		// Is there a match in this folder?
		if (is_file($filename)) {
			// compute its score by counting matching elements back from the last one
			$file		= (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? (string) str_replace("\\", '/' , $filename) : $filename;
			$fileElements	= explode('/', $file);
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
			case '_vti_cnf':
			case '.git':
			case '.hg':	$redHerring = true;	break;
			default:	$redHerring = false;	break;
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
	private static /*.string.*/ function htmlSecureContent($sendToBrowser = false) {
		$ezUser	=& self::getSessionObject();

		if (!$ezUser->authenticated()) {
			header('HTTP/1.1 403 Forbidden', false, 403);
			return '';
		}

		$referer		= $_SERVER['HTTP_REFERER'];
		$refererElements	= /*.(array[int]string).*/ array_slice(explode('/', $referer), 3);
		$folder			= self::getSetting(self::SETTINGS_SECUREFOLDER);

		if ($folder === '') $folder = dirname(realpath(__FILE__));

		$match			= self::findBestMatch($refererElements, $folder);
		$filename		= $match[0];
		$html			= (is_file($filename)) ? file_get_contents($filename) : '';
		$start			= strpos($html, '<body>') + 6;
		$length			= strpos($html, '</body>') - $start;
		$html			= substr($html, $start, $length);

		if ($sendToBrowser) {self::sendXML($html, self::ACTION_BODY); return '';} else return $html;
	}

// ---------------------------------------------------------------------------
	/*. forward public static void function doActions(string $actionList =, string $id =); .*/

	private static /*.string.*/ function doSignOut() {
		$ezUser	=& self::getSessionObject();
		if (!$ezUser->authenticated()) return ''; // Not signed in so nothing to do

		// Sign out then check if a post-signout function has been registered
		$signOutActions = $ezUser->signOut();
		self::setSessionObject(new ezUser(), self::ACTION_ACCOUNT);

		if (empty($signOutActions)) {
			ezUserUI::getControlPanel();
		} else {
			self::doActions(self::ACTION_MAIN  . self::DELIMITER_SPACE . $signOutActions);
		}

		return '';
	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function doAction($action = '', $id = '', $sendToBrowser = true) {
		$html = '';

		switch ($action) {
		case self::ACTION_CONTAINER:	$html = self::htmlContainer		($id,		$sendToBrowser);	break;
		case self::ACTION_MAIN:		$html = self::htmlControlPanel		($id,		$sendToBrowser);	break;
		case self::ACTION_ACCOUNT:	$html = self::htmlAccountForm		($id, false,	$sendToBrowser);	break;
		case self::ACTION_PANELACCOUNT:	$html = self::htmlAccountForm		($id, false,	$sendToBrowser);	break;
		case self::ACTION_STATUSTEXT:	$html = self::statusText		((int) $id, '',	$sendToBrowser);	break;
		case self::ACTION_RESULTTEXT:	$html = self::resultText		((int) $id, '',	$sendToBrowser);	break;
		case self::ACTION_RESULTFORM:	$html = self::htmlResultForm		((int) $id, '',	$sendToBrowser);	break;
		case self::ACTION_RESEND:	$html = self::resendVerificationEmail	($id,		$sendToBrowser);	break;
		case self::ACTION_JAVASCRIPT:	$html = self::htmlJavascript		($id,		$sendToBrowser);	break;
		case self::ACTION_STYLESHEET:	$html = self::htmlStyleSheet		($sendToBrowser);			break;
		case self::ACTION_BODY:		$html = self::htmlSecureContent		($sendToBrowser);			break;
		case self::ACTION_ABOUT:	$html = self::htmlAbout			($sendToBrowser);			break;
		case self::ACTION_SOURCECODE:	$html = self::htmlSourceCode		($sendToBrowser);			break;
		case self::ACTION_SIGNOUT:	self::doSignOut				();					break;
		default:			self::fatalError			(self::RESULT_UNKNOWNACTION, $action);	break;
		}

		return $html;
	}

// ---------------------------------------------------------------------------
	public static /*.void.*/ function doActions($actionList = '', $id = '') {
		if (strpos($actionList, self::DELIMITER_SPACE) !== false) {
			$actions = explode(self::DELIMITER_SPACE, $actionList);
			foreach ($actions as $action) $content[self::getInstanceId($action)] = self::doAction($action, $id, false);
			self::sendXML($content);
		} else {
			self::doAction($actionList, $id);
		}
	}
}
// End of class ezUserUI



// ---------------------------------------------------------------------------
//		ezUser.php
// ---------------------------------------------------------------------------
// Some code to make this all automagic
// If you want more control over how ezUser works then you might need to amend
// or even remove the code below here

// There may already be a session in progress. We will use the existing
// session if possible.
if ((int) ini_get('session.use_cookies') === 0) {
	ezUserUI::fatalError(ezUser::RESULT_NOSESSIONCOOKIES);
} else {
	if (!isset($_SESSION) || !is_array($_SESSION) || !is_object($_SESSION[ezUser::PACKAGE])) session_start();
}

$ezUser =& ezUsers::getSessionObject();

// Is this script included in another page or is it the HTTP target itself?
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
	// This script has been called directly by the client

	// Attempt auto-signin?
	if (!$ezUser->authenticated()) {
		if (array_key_exists(ezUser::COOKIE_AUTOSIGN, $_COOKIE) && ($_COOKIE[ezUser::COOKIE_AUTOSIGN] === ezUser::STRING_TRUE) && ($_COOKIE[ezUser::COOKIE_USERNAME] !== '')) {
			$ezUser_data = /*.(array[string]mixed).*/ array();
			$ezUser_data[ezUser::COOKIE_USERNAME] = (string) $_COOKIE[ezUser::COOKIE_USERNAME];
			$ezUser_data[ezUser::COOKIE_PASSWORD] = hash(ezUser::HASH_FUNCTION, session_id() . (string) $_COOKIE[ezUser::COOKIE_PASSWORD]);
			$ezUser = ezUsers::doSignIn($ezUser_data);
			unset($ezUser_data);
		}
	}

	// First, deal with anything in $_GET
	if (is_array($_GET) && count($_GET) > 0) {
		// Translate from short form (ezUser.php?foo=bar) to extended form (ezUser.php?action=foo&id=bar)
		if (!array_key_exists(ezUser::ACTION, $_GET)) {
			$_GET[ezUser::TAGNAME_ID]	= reset($_GET);
			$_GET[ezUser::ACTION]		= key($_GET);
		}

		$ezUser_id = (array_key_exists(ezUser::TAGNAME_ID, $_GET)) ? (string) $_GET[ezUser::TAGNAME_ID] : '';
		ezUserUI::doActions((string) $_GET[ezUser::ACTION], $ezUser_id);
		unset($ezUser_id);
	} else if (is_array($_POST) && array_key_exists(ezUser::ACTION, $_POST)) {
		switch ((string) $_POST[ezUser::ACTION]) {
		case ezUser::ACTION_SIGNIN:
			$ezUser =& ezUsers::doSignIn($_POST);
			ezUserUI::getControlPanel();
			break;
		case ezUser::ACTION_VALIDATE:
			ezUsers::save($_POST);
			ezUserUI::getAccountForm(ezUser::ACCOUNT_MODE_RESULT,($_POST[ezUser::TAGNAME_NEW] === ezUser::STRING_TRUE));
			break;
		default:
			ezUserUI::getResultForm(ezUser::RESULT_UNKNOWNACTION);
			break;
		}
	} else {
		// Nothing useful in $_GET or $_POST, so give a friendly greeting
		ezUserUI::getAbout();
	}
}
?>