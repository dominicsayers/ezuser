<?php
/**
 * ezUser - adds user registration and authentication to a website
 *
 * This code has three principle design goals:
 *
 * 	1. To make it easy for people to register and sign in to your site.
 * 	2. To make it easy for you to add this functionality to your site.
 * 	3. To make it easy for you to administer the user database on your site
 *
 * Other design goals, such as run-time efficiency, are important but
 * secondary to these.
 *
 * @package	ezUser
 * @author	Dominic Sayers <dominic_sayers@hotmail.com>
 * @copyright	2009 Dominic Sayers
 * @license	http://www.opensource.org/licenses/cpal_1.0 Common Public Attribution License Version 1.0 (CPAL) license
 * @link	http://code.google.com/p/ezuser/
 * @version	(development code)
 */

/*.
	require_module 'standard';
	require_module 'spl';
	require_module 'pcre';
	require_module 'hash';
	require_module 'dom';
	require_module 'session';
.*/

$ezUser_verbose = true;	// Set to true to see detailed status codes

// Code for release package is inserted here

// ---------------------------------------------------------------------------
// 		ezUserAPI
// ---------------------------------------------------------------------------
// ezUser REST interface & other constants
// ---------------------------------------------------------------------------
/**
 * @package	ezUser
 * @author	Dominic Sayers <dominic_sayers@hotmail.com>
 * @copyright	2009 Dominic Sayers
 * @license	http://www.opensource.org/licenses/cpal_1.0 Common Public Attribution License Version 1.0 (CPAL) license
 * @link	http://www.dominicsayers.com
 * @version	0.12 - Local validation improved
 */
interface ezUserAPI {
	const	PACKAGE			= 'ezUser',

		// REST interface actions
		ACTION_ABOUT		= 'about',
		ACTION_ACCOUNT		= 'account',
		ACTION_PANELACCOUNT	= 'accountinpanel',
		ACTION			= 'action',
		ACTION_CANCEL		= 'cancel',
		ACTION_SOURCECODE	= 'code',
		ACTION_CONTAINER	= 'container',
		ACTION_MAIN		= 'controlpanel',
		ACTION_CSS		= 'css',
		ACTION_JAVASCRIPT	= 'js',
		ACTION_RESEND		= 'resend',
		ACTION_RESULTFORM	= 'resultform',
		ACTION_RESULTTEXT	= 'resulttext',
		ACTION_SIGNIN		= 'signin',
		ACTION_SIGNOUT		= 'signout',
		ACTION_STATUSTEXT	= 'statustext',
		ACTION_VALIDATE		= 'validate',		// Validate registration form details
		ACTION_VERIFY		= 'verify',		// Verify verification email

		// Keys for the user data array members
		TAGNAME_ID		= 'id',
		TAGNAME_PASSWORD	= 'password',
		TAGNAME_CONFIRM		= 'confirm',
		TAGNAME_FIRSTNAME	= 'firstName',
		TAGNAME_LASTNAME	= 'lastName',
		TAGNAME_STATUS		= 'status',
		TAGNAME_USERNAME	= 'username',
		TAGNAME_EMAIL		= 'email',
		TAGNAME_VERIFICATIONKEY	= 'verificationKey',
		TAGNAME_NEW		= 'new',
		TAGNAME_FULLNAME	= 'fullName',
		TAGNAME_AUTHENTICATED	= 'authenticated',

		// Keys for the configuration settings
		SETTINGS_ACCOUNTPAGE	= 'accountPage',
		SETTINGS_ADMINEMAIL	= 'adminEmail',

		// Cookie names
		EZUSER_COOKIE_USERNAME	= 'ezUser1',
		EZUSER_COOKIE_PASSWORD	= 'ezUser2',
		EZUSER_COOKIE_AUTOSIGN	= 'ezUser3',

		// Miscellaneous constants
		EMAIL_DELIMITER		= '@',
		HASH_FUNCTION		= 'SHA256',

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
		RESULT_EMAILEXISTS	= 43;
}
// End of interface ezUserAPI
//+C_ezUserAPI+

// ---------------------------------------------------------------------------
// 		ezUserValidate
// ---------------------------------------------------------------------------
// Field validation functions for ezUser
// ---------------------------------------------------------------------------
/**
 * @package	ezUser
 * @author	Dominic Sayers <dominic_sayers@hotmail.com>
 * @copyright	2009 Dominic Sayers
 * @license	http://www.opensource.org/licenses/cpal_1.0 Common Public Attribution License Version 1.0 (CPAL) license
 * @link	http://www.dominicsayers.com
 * @version	0.12 - Local validation improved
 */
class ezUserValidate {
	public static /*.boolean.*/ function is_email(/*.string.*/ $email, $checkDNS = false) {
		// Check that $email is a valid address. Read the following RFCs to understand the constraints:
		// 	(http://tools.ietf.org/html/rfc5322)
		// 	(http://tools.ietf.org/html/rfc3696)
		// 	(http://tools.ietf.org/html/rfc5321)
		// 	(http://tools.ietf.org/html/rfc4291#section-2.2)
		// 	(http://tools.ietf.org/html/rfc1123#section-2.1)

		// the upper limit on address lengths should normally be considered to be 256
		// 	(http://www.rfc-editor.org/errata_search.php?rfc=3696)
		// 	NB I think John Klensin is misreading RFC 5321 and the the limit should actually be 254
		// 	However, I will stick to the published number until it is changed.
		//
		// The maximum total length of a reverse-path or forward-path is 256
		// characters (including the punctuation and element separators)
		// 	(http://tools.ietf.org/html/rfc5321#section-4.5.3.1.3)
		$emailLength = strlen($email);
		if ($emailLength > 256)	return false;	// Too long

		// Contemporary email addresses consist of a "local part" separated from
		// a "domain part" (a fully-qualified domain name) by an at-sign ("@").
		// 	(http://tools.ietf.org/html/rfc3696#section-3)
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
				if ($replaceChar) $email[$i] = 'x';	// Replace the offending character with something harmless
			}
		}

		$localPart	= substr($email, 0, $atIndex);
		$domain		= substr($email, $atIndex + 1);
		$FWS		= "(?:(?:(?:[ \\t]*(?:\\r\\n))?[ \\t]+)|(?:[ \\t]+(?:(?:\\r\\n)[ \\t]+)*))";	// Folding white space
		// Let's check the local part for RFC compliance...
		//
		// local-part      =       dot-atom / quoted-string / obs-local-part
		// obs-local-part  =       word *("." word)
		// 	(http://tools.ietf.org/html/rfc5322#section-3.4.1)
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
				// 	(http://tools.ietf.org/html/rfc3696#section-3)
				//
				// A zero-length element implies a period at the beginning or end of the
				// local part, or two periods together. Either way it's not allowed.
				if ($element === '')								return false;	// Dots in wrong place

				// Any ASCII graphic (printing) character other than the
				// at-sign ("@"), backslash, double quote, comma, or square brackets may
				// appear without quoting.  If any of that list of excluded characters
				// are to appear, they must be quoted
				// 	(http://tools.ietf.org/html/rfc3696#section-3)
				//
				// Any excluded characters? i.e. 0x00-0x20, (, ), <, >, [, ], :, ;, @, \, comma, period, "
				if (preg_match('/[\\x00-\\x20\\(\\)<>\\[\\]:;@\\\\,\\."]/', $element) > 0)	return false;	// These characters must be in a quoted string
			}
		}

		if ($partLength > 64) return false;	// Local part must be 64 characters or less

		// Now let's check the domain part...

		// The domain name can also be replaced by an IP address in square brackets
		// 	(http://tools.ietf.org/html/rfc3696#section-3)
		// 	(http://tools.ietf.org/html/rfc5321#section-4.1.3)
		// 	(http://tools.ietf.org/html/rfc4291#section-2.2)
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
				// 	(http://tools.ietf.org/html/rfc1123#section-6.1.3.5)
				if ($elementLength > 63)				return false;	// Label must be 63 characters or less

				// Each dot-delimited component must be atext
				// A zero-length element implies a period at the beginning or end of the
				// local part, or two periods together. Either way it's not allowed.
				if ($elementLength === 0)				return false;	// Dots in wrong place

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
				if (preg_match('/[\\x00-\\x20\\(\\)<>\\[\\]:;@\\\\,\\."]|^-|-$/', $element) > 0) {
											return false;
				}
			}

			if ($partLength > 255) 						return false;	// Local part must be 64 characters or less

			if (preg_match('/^[0-9]+$/', $element) > 0)			return false;	// TLD can't be all-numeric

			// Check DNS?
			if ($checkDNS && function_exists('checkdnsrr')) {
				if (!(checkdnsrr($domain, 'A') || checkdnsrr($domain, 'MX'))) {
											return false;	// Domain doesn't actually exist
				}
			}
		}

		// Eliminate all other factors, and the one which remains must be the truth.
		// 	(Sherlock Holmes, The Sign of Four)
		return true;
	}
}
// End of class ezUserValidate
//+C_ezUserValidate+

// ---------------------------------------------------------------------------
// 		ezUser
// ---------------------------------------------------------------------------
// This class encapsulates all the functions needed for an app to interact
// with a user. It has no knowledge of how user information is persisted - see
// the ezUsers class for that.
// ---------------------------------------------------------------------------
/**
 * @package	ezUser
 * @author	Dominic Sayers <dominic_sayers@hotmail.com>
 * @copyright	2009 Dominic Sayers
 * @license	http://www.opensource.org/licenses/cpal_1.0 Common Public Attribution License Version 1.0 (CPAL) license
 * @link	http://www.dominicsayers.com
 * @version	0.12 - Local validation improved
 */
class ezUser extends ezUserValidate implements ezUserAPI, Iterator {
	// User data
	private		$values			= /*.(array[string]string).*/ array();

	// State and derived data
	private		$authenticated		= false;
	private		$usernameIsDefault	= true;
	private		$fullName		= '';
	private		$result			= self::RESULT_UNDEFINED;
	private		$config			= /*.(array[string]string).*/ array();
	private		$errors			= /*.(array[string]string).*/ array();
	protected	$isChanged		= false;

// ---------------------------------------------------------------------------
// Iterator
// ---------------------------------------------------------------------------
	public /*.void.*/	function rewind()	{reset($this->values);}
	public /*.string.*/	function current()	{return (string) current($this->values);}
	public /*.string.*/	function key()		{return (string) key($this->values);}
	public /*.void.*/	function next()		{next($this->values);}
	public /*.boolean.*/	function valid()	{return (!is_null(key($this->values)));}

// ---------------------------------------------------------------------------
// Substantive methods
// ---------------------------------------------------------------------------
	public /*.boolean.*/ function authenticate(/*.string.*/ $passwordHash) {
		$sessionHash = hash(self::HASH_FUNCTION, (string) $_COOKIE[ini_get('session.name')] . hash(self::HASH_FUNCTION, $_SERVER['REMOTE_ADDR'] . $this->values[self::TAGNAME_PASSWORD]));
		$this->authenticated = ($passwordHash === $sessionHash);
		return $this->authenticated;
	}

	public /*.boolean.*/ function signOut() {
		$this->authenticated = false;
		return $this->authenticated;
	}

// ---------------------------------------------------------------------------
// "Get" methods
// ---------------------------------------------------------------------------
	private /*.string.*/ function getValue(/*.string.*/ $key) {
		switch ($key) {
		case self::TAGNAME_VERIFICATIONKEY:
			if ($this->status() === self::STATUS_PENDING) {
				return (isset($this->values[$key])) ? $this->values[$key] : 'badkey';
			} else {
				return 'badstatus';	// Don't return
			}
		case 'fullName':	return $this->fullName;
		case 'authenticated':	return (string) $this->authenticated;
		default:		return (isset($this->values[$key])) ? $this->values[$key] : '';
		}
	}

	protected	/*.string.*/			function id()			{return $this->getValue(self::TAGNAME_ID);}
	public		/*.string.*/			function username()		{return $this->getValue(self::TAGNAME_USERNAME);}
	protected	/*.string.*/			function passwordHash()		{return $this->getValue(self::TAGNAME_PASSWORD);}
	public		/*.string.*/			function firstName()		{return $this->getValue(self::TAGNAME_FIRSTNAME);}
	public		/*.string.*/			function lastName()		{return $this->getValue(self::TAGNAME_LASTNAME);}
	public		/*.string.*/			function email()		{return $this->getValue(self::TAGNAME_EMAIL);}
	protected	/*.string.*/			function verificationKey()	{return $this->getValue(self::TAGNAME_VERIFICATIONKEY);}
	public		/*.int.*/			function status()		{return (int) $this->getValue(self::TAGNAME_STATUS);}
	public		/*.string.*/			function fullName()		{return $this->getValue(self::TAGNAME_FULLNAME);}
	public		/*.boolean.*/			function authenticated()	{return $this->getValue(self::TAGNAME_AUTHENTICATED);}
	protected	/*.int.*/			function result()		{return $this->result;}
	protected	/*.array[string]string.*/	function &config()		{return $this->config;}
	protected	/*.array[string]string.*/	function errors()		{return $this->errors;}

// ---------------------------------------------------------------------------
// "Set" methods
// ---------------------------------------------------------------------------
	protected /*.void.*/ function clearErrors() {
		$this->errors = /*.(array[string]string).*/ array();
	}

	protected /*.void.*/ function setValue(/*.string.*/ $key, /*.string.*/ $value) {
		$this->values[$key] = $value;
	}

	private /*.boolean.*/ function changeValue(/*.string.*/ $key, /*.string.*/ $value) {
		if ((!isset($this->values[$key])) || ($value !== $this->values[$key])) {
			$this->values[$key]	= $value;
			$this->isChanged	= true;
			return true;
		} else {
			return false;
		}
	}

//	protected /*.int.*/ function setID(/*.string.*/ $id) {
//		if ($id === '') return self::RESULT_NOID;
//		$this->changeValue(self::TAGNAME_ID, $id);
//		return self::RESULT_VALIDATED;
//	}

	protected /*.int.*/ function setPasswordHash(/*.string.*/ $passwordHash) {
		if ($passwordHash === '')				return self::RESULT_NOPASSWORD;
		if ($passwordHash === hash(self::HASH_FUNCTION, ''))	return self::RESULT_NULLPASSWORD;
		$this->changeValue(self::TAGNAME_PASSWORD, $passwordHash);
		return self::RESULT_VALIDATED;
	}

	protected /*.int.*/ function setStatus(/*.int.*/ $status) {
		if (is_nan($status)) return self::RESULT_STATUSNAN;

		// If we're setting this user to Pending then generate a verification key
		if ($status === self::STATUS_PENDING && $this->status() !== self::STATUS_PENDING) {
			// Make sure we have an ID
			if ($this->id() === '') {
				list($usec, $sec) = explode(" ", microtime());
				$id = base_convert($sec, 10, 36) . base_convert(mt_rand(0, 35), 10, 36) . str_pad(base_convert(($usec * 1000000), 10, 36), 4, '_', STR_PAD_LEFT);
				$this->changeValue(self::TAGNAME_ID, $id);
			}

			// Use the ID to generate a verification key
			$this->changeValue(self::TAGNAME_VERIFICATIONKEY, hash(self::HASH_FUNCTION, $_SERVER['REQUEST_TIME'] . $this->id()));
		}

		$this->changeValue(self::TAGNAME_STATUS, (string) $status);
		return self::RESULT_VALIDATED;
	}

	protected /*.int.*/ function setResult(/*.int.*/ $result) {
		if (is_nan($result)) return self::RESULT_RESULTNAN;
		$this->result = $result;
		return self::RESULT_VALIDATED;
	}

	protected /*.int.*/ function setConfig(/*.array[string]string.*/ $config) {
		if (!is_array($config)) return self::RESULT_CONFIGNOTARRAY;
		$this->config = $config;
		return self::RESULT_VALIDATED;
	}

	private /*.string.*/ function usernameDefault() {
		$lastName = (isset($this->values[self::TAGNAME_LASTNAME])) ? $this->values[self::TAGNAME_LASTNAME] : '';
		return strtolower($this->values[self::TAGNAME_FIRSTNAME] . $lastName);
	}

	private /*.void.*/ function update_name() {
		$lastName	= (isset($this->values[self::TAGNAME_LASTNAME])) ? $this->values[self::TAGNAME_LASTNAME] : '';
		$separator	= ($this->values[self::TAGNAME_FIRSTNAME] === '') ? '' : ' ';
		$fullName	= $this->values[self::TAGNAME_FIRSTNAME] . $separator . $lastName;

		if ($fullName !== $this->fullName) {
			$this->fullName		= $fullName;
			$this->isChanged	= true;
		}

		if ($this->usernameIsDefault) {$this->changeValue(self::TAGNAME_USERNAME, $this->usernameDefault());}
	}

	private /*.void.*/ function setNamePart(/*.string.*/ $key, /*.string.*/ $name) {
		if ($this->changeValue($key, $name)) $this->update_name();
	}

	public /*.void.*/ function setFirstName(/*.string.*/ $name)	{$this->setNamePart(self::TAGNAME_FIRSTNAME, $name);}
	public /*.void.*/ function setLastName(/*.string.*/ $name)	{$this->setNamePart(self::TAGNAME_LASTNAME, $name);}

	protected /*.int.*/ function setUsername($name = '') {
		if ($name === '') {
			$this->usernameIsDefault = true;
			$name = $this->usernameDefault();
		} else {
			$this->usernameIsDefault = ($name === $this->usernameDefault());
		}

		if ($name === '') return self::RESULT_NOUSERNAME;
		$this->changeValue(self::TAGNAME_USERNAME, $name);
		return self::RESULT_VALIDATED;
	}

	protected /*.int.*/ function setEmail(/*.string.*/ $email) {
		if ($email === '') return self::RESULT_NOEMAIL;

		if (!self::is_email($email)) {
			$this->errors[self::TAGNAME_EMAIL] = $email;
			return self::RESULT_EMAILFORMATERR;
		}

		$this->changeValue(self::TAGNAME_EMAIL, $email);
		return self::RESULT_VALIDATED;
	}
}
// End of class ezUser
//+C_ezUser+

// ---------------------------------------------------------------------------
// 		ezUsers
// ---------------------------------------------------------------------------
// This class encapsulates all the functions needed to manage the collection
// of stored users. It interacts with the storage mechanism (e.g. database or
// XML file).
// ---------------------------------------------------------------------------
/**
 * @package	ezUser
 * @author	Dominic Sayers <dominic_sayers@hotmail.com>
 * @copyright	2009 Dominic Sayers
 * @license	http://www.opensource.org/licenses/cpal_1.0 Common Public Attribution License Version 1.0 (CPAL) license
 * @link	http://www.dominicsayers.com
 * @version	0.12 - Local validation improved
 */
class ezUsers extends ezUser {
	/*.private.*/ const STORAGE = '.ezuser-data.php';

// ---------------------------------------------------------------------------
// Helper methods
// ---------------------------------------------------------------------------
	private static /*.DOMDocument.*/ function connectStorage() {
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
		$document = new DOMDocument();
		$document->load($storage_file);

		return $document;
	}

// ---------------------------------------------------------------------------
// Substantive methods
// ---------------------------------------------------------------------------
	protected static /*.ezUser.*/ function lookup($username_or_email = '') {
		$ezUser = new ezUser();
		$ezUser->setStatus(self::STATUS_UNKNOWN);
		if ($username_or_email === '') return $ezUser;

		$document	= self::connectStorage();
		$tagName	= ((bool) strpos($username_or_email,self::EMAIL_DELIMITER)) ? self::TAGNAME_EMAIL : self::TAGNAME_USERNAME;
		$nodeList	= $document->getElementsByTagName($tagName);
		$found		= false;

		for ($i = 0; $i < $nodeList->length; $i++) {
			$node = $nodeList->item($i);

			if ($node->nodeValue === $username_or_email) {
				$found = true;
				break;
			}
		}

		if ($found) {
			// Populate $ezUser from stored data
			$nodeList = $node->parentNode->childNodes;

			// We could make this quicker and possibly more secure by storing the username and email
			// in their own tags to allow for duplicate searching as above, but storing the other fields
			// in some internal format (serialized?) to allow us to pass the whole lot to C_ezUser
			// en masse. This would remove the need to expose the ability to this class to set the id property.
			for ($i = 0; $i < $nodeList->length; $i++) {
				$node = $nodeList->item($i);
				if ($node->nodeType === XML_ELEMENT_NODE) {$ezUser->setValue($node->nodeName, $node->nodeValue);}
			}
		}

		return $ezUser;
	}

// ---------------------------------------------------------------------------
	private static /*.int.*/ function is_duplicate(/*.string.*/ $username, /*.string.*/ $email, /*.string.*/ $id) {
//if ($email === 'dominic_sayers2@hotmail.com') echo "<pre>username: $username | email: $email | id: $id</pre>\n"; // debug
		// Username must be unique
		$ezUser = self::lookup($username);

		if ($ezUser->status() !== self::STATUS_UNKNOWN) {
//if ($email === 'dominic_sayers2@hotmail.com') echo "<pre>id: {$ezUser->id()} | status: {$ezUser->status()}</pre>\n"; // debug
			if ($ezUser->id() !== $id) return self::RESULT_USERNAMEEXISTS;
		}

		// Email must be unique
		$ezUser = self::lookup($email);

		if ($ezUser->status() !== self::STATUS_UNKNOWN) {
//if ($email === 'dominic_sayers2@hotmail.com') echo "<pre>id: {$ezUser->id()} | status: {$ezUser->status()}</pre>\n"; // debug
			if ($ezUser->id() !== $id) return self::RESULT_EMAILEXISTS;
		}

		// No choice but to...
		return self::RESULT_VALIDATED;
	}

// ---------------------------------------------------------------------------
	private static /*.void.*/ function persist(/*.ezUser.*/ $ezUser) {
		$document	= self::connectStorage();
		$user		= $document->createElement('user');
		$users		= $document->getElementsByTagName('users')->item(0);

		$users->appendChild($document->createTextNode("\t")); // XML formatting
		$users->appendChild($user);
		$users->appendChild($document->createTextNode("\n")); // XML formatting

		foreach ($ezUser as $key => $value) {
			$user->appendChild($document->createTextNode("\n\t\t")); // XML formatting
			$user->appendChild($document->createElement((string) $key, $value));
		}

		// Note when the record was updated
		$user->appendChild($document->createTextNode("\n\t\t")); // XML formatting
		$user->appendChild($document->createElement('updated', gmdate("Y-m-d H:i:s (T)")));

		$user->appendChild($document->createTextNode("\n\t")); // XML formatting
		$storage_file = dirname(__FILE__) . '/' . self::STORAGE;
		$document->save($storage_file);
	}

// ---------------------------------------------------------------------------
	public static /*.ezUser.*/ function doSignIn(/*.array[string]mixed.*/ $userData) {
		$username	= (string) $userData[self::EZUSER_COOKIE_USERNAME];
		$password	= (string) $userData[self::EZUSER_COOKIE_PASSWORD];
		$ezUser		= self::lookup($username);

		if ($ezUser->status() === self::STATUS_UNKNOWN) {
			$ezUser->setResult(self::RESULT_UNKNOWNUSER);		// User does not exist
		} else {
			if ($ezUser->authenticate($password)) {
				$ezUser->setResult(self::RESULT_SUCCESS);	// Correct password
			} else {
				$ezUser->setResult(self::RESULT_BADPASSWORD);	// User exists but password is wrong
			}
		}

		return $ezUser;
	}

// ---------------------------------------------------------------------------
	public static /*.int.*/ function validate(/*.array[string]mixed.*/ $userData, /*.ezUser.*/ &$ezUser) {
		$result		= self::RESULT_VALIDATED;

//if ($userData[self::TAGNAME_EMAIL] === 'dominic_sayers2@hotmail.com') echo "<pre>new: {$userData[self::TAGNAME_NEW]}</pre>\n"; // debug
		if ((bool) $userData[self::TAGNAME_NEW]) {
//if ($userData[self::TAGNAME_EMAIL] === 'dominic_sayers2@hotmail.com') echo "<pre>It's true</pre>\n"; // debug
			$id	= '';
			$ezUser	= new ezUser();
		} else {
//if ($userData[self::TAGNAME_EMAIL] === 'dominic_sayers2@hotmail.com') echo "<pre>It's false</pre>\n"; // debug
			$id	= $ezUser->id();
			$ezUser->clearErrors();
		}

		$ezUser->setFirstName(	(string) $userData[self::TAGNAME_FIRSTNAME]);
		$ezUser->setLastName(	(string) $userData[self::TAGNAME_LASTNAME]);
		$email		=	(string) $userData[self::TAGNAME_EMAIL];
		$username	=	(string) $userData[self::EZUSER_COOKIE_USERNAME];
		$passwordHash	=	(string) $userData[self::EZUSER_COOKIE_PASSWORD];

		$thisResult 	= $ezUser->setEmail		($email);		if ($thisResult !== self::RESULT_VALIDATED) $result = $thisResult;
		$thisResult 	= $ezUser->setUsername		($username);		if ($thisResult !== self::RESULT_VALIDATED) $result = $thisResult;
		$thisResult 	= $ezUser->setPasswordHash	($passwordHash);	if ($thisResult !== self::RESULT_VALIDATED) $result = $thisResult;

		if ($result === self::RESULT_VALIDATED) $result = self::is_duplicate($username, $email, $id);

		$ezUser->setResult($result);

		if ($result === self::RESULT_VALIDATED) {
			$ezUser->setStatus(self::STATUS_PENDING);

			if ($ezUser->isChanged) {
				self::persist($ezUser);
				$ezUser->isChanged = false;
			}
		}

		return $result;
	}
}
// End of class ezUsers
//+C_ezUsers+

// ---------------------------------------------------------------------------
// 		ezUserUI
// ---------------------------------------------------------------------------
// This class manages the HTML, CSS and Javascript that you can include in
// your web pages to support user registration and authentication.
//
// Assumes $_SESSION[self::PACKAGE] exists
// ---------------------------------------------------------------------------
/**
 * @package	ezUser
 * @author	Dominic Sayers <dominic_sayers@hotmail.com>
 * @copyright	2009 Dominic Sayers
 * @license	http://www.opensource.org/licenses/cpal_1.0 Common Public Attribution License Version 1.0 (CPAL) license
 * @link	http://www.dominicsayers.com
 * @version	0.12 - Local validation improved
 */
class ezUserUI extends ezUsers implements ezUserAPI {
	/*.private.*/ const	PARAM_PERSISTED		= 'getPersisted',
				PARAM_EMPTY		= 'empty',

				MESSAGE_TYPE_DEFAULT	= 'message',
				MESSAGE_TYPE_TEXT	= 'text',

				MESSAGE_STYLE_DEFAULT	= 'info',
				MESSAGE_STYLE_FAIL	= 'fail',
				MESSAGE_STYLE_TEXT	= 'text',
				MESSAGE_STYLE_PLAIN	= 'plain',

				STRING_TRUE		= 'true',
				STRING_FALSE		= 'false';

// ---------------------------------------------------------------------------
// Additional forms may need their own session object for state
// ---------------------------------------------------------------------------
	private static /*.string.*/ function getInstanceId($action = self::ACTION_MAIN) {
		return ($action === self::ACTION_MAIN) ? self::PACKAGE : self::PACKAGE . "-$action";
	}

	public static /*.ezUser.*/ function &getSessionObject($action = self::ACTION_MAIN) {
		$instance = ezUserUI::getInstanceId($action);
		if (!isset($_SESSION[$instance])) $_SESSION[$instance] = new ezUser();
		return $_SESSION[$instance];
	}

// ---------------------------------------------------------------------------
// Configuration settings
// ---------------------------------------------------------------------------
	private static /*.string.*/ function thisURL() {
		$package = self::PACKAGE;

		// Find out the URL of this script so we can call it later
		$file = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? (string) str_replace("\\", '/' , __FILE__) : __FILE__;
		return dirname(substr($file, strpos($_SERVER['SCRIPT_FILENAME'], $_SERVER['SCRIPT_NAME']))) . "/$package.php";
	}

	private static /*.array[string]string.*/ function loadConfig() {
		$package	= self::PACKAGE;
		$ezUser		=& /*.(ezUser).*/ $_SESSION[$package];
		$config		= $ezUser->config();
		$settingsFile	= realpath(dirname(__FILE__) . "/.$package-settings.php");

		// If configuration settings file doesn't exist then use default settings
		if (($settingsFile === false) || !is_file($settingsFile)) {
			$config[self::PARAM_EMPTY] = self::STRING_TRUE;
		} else {
			// Open the container for use
			$document = new DOMDocument();
			$document->load($settingsFile);
			$nodeList = $document->getElementsByTagName('settings')->item(0)->childNodes;

			for ($i = 0; $i < $nodeList->length; $i++) {
				$node = $nodeList->item($i);

				if ($node->nodeType == XML_ELEMENT_NODE) {
					$config[$node->nodeName] = $node->nodeValue;
				}
			}
		}

		$config[self::PARAM_PERSISTED] = self::STRING_TRUE;
		$ezUser->setConfig($config);
		return $config;
	}

	private static /*.array[string]string.*/ function getSettings() {
		$ezUser =& /*.(ezUser).*/ $_SESSION[self::PACKAGE];
		$config =& $ezUser->config();

		if (!is_array($config))						{$config = self::loadConfig();}
		if (!isset($config[self::PARAM_PERSISTED]))			{$config = self::loadConfig();}
		if ($config[self::PARAM_PERSISTED] !== self::STRING_TRUE)	{$config = self::loadConfig();}

		return $config;
	}

	private static /*.string.*/ function getSetting(/*.string.*/ $setting) {
		$config = self::getSettings();
		$thisSetting = (isset($config[$setting])) ? $config[$setting] : '';
		return $thisSetting;
	}

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------
	private static /*.string.*/ function componentHeader() {return self::PACKAGE . "-component";}

	private static /*.void.*/ function sendContent(/*.string.*/ $content, /*.string.*/ $component, $contentType = '') {
		// Send headers first
		if (!headers_sent()) {
			$package 	= self::PACKAGE;

			$defaultType	= ($component	=== 'container')	? "text/html"	: "application/$package"; // Webkit oddity
			$contentType	= ($contentType	=== '')			? $defaultType	: $contentType;
			$component	= ($component	=== $package)		? $package	: "$package-$component";

			header("Package: $package");
			header(self::componentHeader() . ": $component");
			header("Content-type: $contentType");
		}

		// Send content
		echo $content;
	}

	private static /*.string.*/ function htmlInputText() {
		$package 	= self::PACKAGE;
		$onKeyUp	= $package . "_keyUp";

		return <<<HTML
					class		=	"$package-text"
					onkeyup		=	"$onKeyUp(event)"
					size		=	"40"
HTML;
	}

	private static /*.string.*/ function htmlButtonEvents() {
		$package 	= self::PACKAGE;
		$setButtonState	= $package . "_setButtonState";
		$onClick	= $package . "_click";

		return <<<HTML
					onclick		=	"$onClick(this)"
					onmouseover	=	"$setButtonState(this, 1, true)"
					onmouseout	=	"$setButtonState(this, 1, false)"
					onfocus		=	"$setButtonState(this, 2, true)"
					onblur		=	"$setButtonState(this, 2, false)"
HTML;
	}

	private static /*.string.*/ function htmlButton(/*.string.*/ $type, $verbose = false) {
		$package 	= self::PACKAGE;
		$classVerbose	= ($verbose) ? " $package-preference-verbose" : "";

		return <<<HTML
					type		=	"button"
					class		=	"$package-button $package-$type$classVerbose $package-buttonstate-0"
HTML;
	}

	private static /*.string.*/ function htmlMessage($message = '', $style = self::MESSAGE_STYLE_DEFAULT, $instance = '', $type = self::MESSAGE_TYPE_DEFAULT) {
		$package	= self::PACKAGE;
		$message	= ($message === '') ? '' : "<p class=\"$package-message-$style\">$message</p>";
		$id		= ($instance === '') ? "$package-$type" : "$instance-$type";
		$onClick	= $package . "_click";

		return <<<HTML
				<div id="$id" class="$package-$type" onclick="$onClick(this)">$message</div>
HTML;
	}

// ---------------------------------------------------------------------------
// UI features
// ---------------------------------------------------------------------------
	private static /*.string.*/ function statusText(/*.int.*/ $status) {
		switch ($status) {
			case self::STATUS_UNKNOWN:		return "Unknown status";
			case self::STATUS_PENDING:		return "Awaiting confirmation";
			case self::STATUS_CONFIRMED:		return "Confirmed and active";
			case self::STATUS_INACTIVE:		return "Inactive";
			default:				return "Unknown status code";
		}
	}

	private static /*.string.*/ function statusDescription(/*.int.*/ $status) {
		switch ($status) {
			case self::STATUS_PENDING:		return "Your account has been created and a confirmation email has been sent. Please click on the link in the confirmation email to verify your account.";
			default:				return self::statusText($status);
		}
	}

	private static /*.string.*/ function resultText(/*.int.*/ $result) {
		switch ($result) {
			// Authentication results
			case self::RESULT_UNDEFINED:		return "Undefined";
			case self::RESULT_SUCCESS:		return "Success";
			case self::RESULT_UNKNOWNUSER:		return "Username not recognised";
			case self::RESULT_BADPASSWORD:		return "Password is wrong";
			case self::RESULT_UNKNOWNACTION:	return "Unknown action";
			case self::RESULT_NOACTION:		return "No action specified";
			case self::RESULT_NOSESSION:		return "No session data available";
			case self::RESULT_NOSESSIONCOOKIES:	return "Session cookies are not enabled";
			case self::RESULT_STORAGEERR:		return "Error with stored user details";

			// Registration and validation results
			case self::RESULT_VALIDATED:		return "Success";
			case self::RESULT_NOID:			return "ID cannot be blank";
			case self::RESULT_NOUSERNAME:		return "The username cannot be blank";
			case self::RESULT_NOEMAIL:		return "Please provide an email address";
			case self::RESULT_EMAILFORMATERR:	return "Incorrect email address format";
			case self::RESULT_NOPASSWORD:		return "Password hash cannot be blank";
			case self::RESULT_NULLPASSWORD:		return "Password cannot be blank";
			case self::RESULT_STATUSNAN:		return "Status code must be numeric";
			case self::RESULT_RESULTNAN:		return "Result code must be numeric";
			case self::RESULT_CONFIGNOTARRAY:	return "Configuration settings must be an array";
			case self::RESULT_USERNAMEEXISTS:	return "This username already exists";
			case self::RESULT_EMAILEXISTS:		return "Email address is already registered";
			default:				return "Unknown result code";
		}
	}

	private static /*.string.*/ function resultDescription(/*.int.*/ $result) {
		switch ($result) {
			case self::RESULT_EMAILFORMATERR:	return "The format of the email address you entered was incorrect. Email addresses should be in the form <em>joe.smith@example.com</em>";
			default:				return self::resultText($result);
;
		}
	}

	public static /*.void.*/ function getStatusText(/*.int.*/ $status) {
		$text = self::statusText($status);
		self::sendContent($text, 'statusText');

	}

	public static /*.void.*/ function getResultText(/*.int.*/ $result) {
		$text = self::resultText($result);
		self::sendContent($text, 'resultText');
	}

	private static /*.string.*/ function htmlResultForm (/*.int.*/ $result) {
		$package		= self::PACKAGE;
		$htmlButtonPreference	= self::htmlButton("preference");
		$htmlButtonEvents	= self::htmlButtonEvents();
		$message		= self::htmlMessage(self::resultText($result));
		$onClick		= $package . "_click";

		return <<<HTML
		<form onsubmit="return false">
			<fieldset class="$package-fieldset">
$message
				<input id="$package-OK" value="OK"
					tabindex	=	"3231"
$htmlButtonPreference
$htmlButtonEvents
				/>
			</fieldset>
		</form>
HTML;
	}

	public static /*.void.*/ function getResultForm(/*.int.*/ $result) {
		$html = self::htmlResultForm($result);
		self::sendContent($html, 'resultForm');
	}

	public static /*.void.*/ function fatalError(/*.int.*/ $result) {
		self::getResultForm($result);
		exit;
	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlAccountForm(/*.string.*/ $mode, $newFlag = false) {
		// This function is driven by the mode parameter as follows:
		//
		// Mode		Behaviour
		// -------	------------------------------------------------------------
		// new		Register a new user. Input controls are blank but available.
		// 		Button says Register.
		//
		// edit		Edit an existing account or correct a failed registration.
		// 		Input controls are populated with existing data. Buttons
		// 		say OK and Cancel
		//
		// display	View account details. Input controls are populated but
		// 		unavailable. Button says Edit.
		//
		// result	Infer actual mode from result of validation. If validated
		// 		then display account details, otherwise allow them to be
		// 		corrected. Inferred mode will be either 'display' or 'edit'.

		// $newFlag indicates whether this is an existing user from the database, or a new
		// registration that we are processing. If the user enters invalid data
		// we might render this form a number of times until validation is successful.

		// So, the difference between $mode = 'new' and $newFlag = true is as follows:
		//
		// 	$mode = 'new' means this is a blank form for a new registration
		//
		// 	$newFlag = true means we are processing a new registration but we might
		// 		be asking the user to re-enter certain values: the form might
		// 		therefore need to be populated with the attempted registration
		// 		details.

		$package		= self::PACKAGE;
		$action			= self::ACTION_ACCOUNT;
		$instance		= self::getInstanceID($action);

		$tagFirstName		= self::TAGNAME_FIRSTNAME;
		$tagLastName		= self::TAGNAME_LASTNAME;
		$tagEmail		= self::TAGNAME_EMAIL;
		$tagUsername		= self::TAGNAME_USERNAME;
		$tagPassword		= self::TAGNAME_PASSWORD;
		$tagConfirm		= self::TAGNAME_CONFIRM;
		$tagNew			= self::TAGNAME_NEW;

		$htmlButtonAction	= self::htmlButton('action');
		$htmlButtonEvents	= self::htmlButtonEvents();
		$htmlInputText		= self::htmlInputText();
		$message		= self::htmlMessage('* = mandatory field', self::MESSAGE_STYLE_PLAIN, $instance);
		$resendButton		= '';
		$ezUser			=& self::getSessionObject($action);
		$result			= $ezUser->result();

		if ($mode === 'result') $mode = ($result === self::RESULT_VALIDATED) ? 'display' : 'edit';

		// Specific stuff for new user mode
		if ($mode === 'new') {
			$newFlag	= true;
			$text		= self::htmlMessage('', self::MESSAGE_STYLE_TEXT, $instance, self::MESSAGE_TYPE_TEXT);

			$buttonID	= 'validate';
			$buttonText	= 'Register';

			$email		= '';
			$firstName	= '';
			$lastName	= '';
			$username	= '';
			$password	= '';
		} else {
			if (!isset($newFlag)) $newFlag = false;

			$errors		= $ezUser->errors();
			$email		= (isset($errors[self::TAGNAME_EMAIL]))		? $errors[self::TAGNAME_EMAIL]		: $ezUser->email();
			$firstName	= (isset($errors[self::TAGNAME_FIRSTNAME]))	? $errors[self::TAGNAME_FIRSTNAME]	: $ezUser->firstName();
			$lastName	= (isset($errors[self::TAGNAME_LASTNAME]))	? $errors[self::TAGNAME_LASTNAME]	: $ezUser->lastName();
			$username	= (isset($errors[self::TAGNAME_USERNAME]))	? $errors[self::TAGNAME_USERNAME]	: $ezUser->username();
			$password	= ($ezUser->passwordHash() === '') ? '' : '************';

			if ($result === self::RESULT_VALIDATED) {
				// Show status information
				$text	= ($ezUser->status() === self::STATUS_CONFIRMED) ? '' : self::statusDescription($ezUser->status());
				$text	= self::htmlMessage($text, self::MESSAGE_STYLE_TEXT, $instance, self::MESSAGE_TYPE_TEXT);

				$resendButton = <<<HTML

				<input id="$instance-resend" value="Resend"
					tabindex	=	"3219"
$htmlButtonAction
$htmlButtonEvents
				/>
HTML;
			} else {
				// Show result information
				$text	= self::resultDescription($result);
				$text	= self::htmlMessage($text, self::MESSAGE_STYLE_FAIL, $instance, self::MESSAGE_TYPE_TEXT);
			}
		}

		// Specific stuff for display mode
		if ($mode === 'display') {
			$buttonID	= 'edit';
			$buttonText	= 'Edit';
			$disabled	= "\t\t\t\t\tdisabled\t=\t\"disabled\"\r\n";
			$newValue	= self::STRING_FALSE;
		} else {
			$disabled	= '';
			$newValue	= ($newFlag) ? self::STRING_TRUE : self::STRING_FALSE;
		}

		// Specific stuff for edit mode
		if ($mode === 'edit') {
			$buttonID	= 'validate';
			$buttonText	= 'OK';

			$htmlCancelButton = <<<HTML
				<input id="$instance-cancel" value="Cancel"
					tabindex	=	"3218"
$htmlButtonAction
$htmlButtonEvents
				/>
HTML;
		} else {
			$htmlCancelButton = '';
		}

		// At this point we have finished with the result of any prior validation
		// so we can clear the result field
		$ezUser->setResult(self::RESULT_UNDEFINED);

		return <<<HTML
		<form class="$package-form" onsubmit="return false">
			<fieldset class="$package-fieldset">
				<input id= "$instance-$tagEmail"
					tabindex	=	"3211"
					value		=	"$email"
					type		=	"text"
$disabled$htmlInputText
				/>
				<label class="$package-label" for="$instance-$tagEmail">* Email address:</label>
				<input id= "$instance-$tagFirstName"
					tabindex	=	"3212"
					value		=	"$firstName"
					type		=	"text"
$disabled$htmlInputText
				/>
				<label class="$package-label" for="$instance-$tagFirstName">First name:</label>
				<input id= "$instance-$tagLastName"
					tabindex	=	"3213"
					value		=	"$lastName"
					type		=	"text"
$disabled$htmlInputText
				/>
				<label class="$package-label" for="$instance-$tagLastName">Last name:</label>
				<input id= "$instance-$tagUsername"
					tabindex	=	"3214"
					value		=	"$username"
					type		=	"text"
$disabled$htmlInputText
				/>
				<label class="$package-label" for="$instance-$tagUsername">* Username:</label>
				<input id= "$instance-$tagPassword"
					tabindex	=	"3215"
					value		=	"$password"
					type		=	"password"
$disabled$htmlInputText
				/>
				<label class="$package-label" for="$instance-$tagPassword">* Password:</label>
				<input id= "$instance-confirm"
					tabindex	=	"3216"
					value		=	"$password"
					type		=	"password"
$disabled$htmlInputText
				/>
				<label class="$package-label" for="$instance-$tagConfirm">* Confirm password:</label>
			</fieldset>
			<fieldset class="$package-fieldset">
$message
				<input id="$instance-$buttonID" value="$buttonText"
					tabindex	=	"3217"
$htmlButtonAction
$htmlButtonEvents
				/>
$htmlCancelButton			</fieldset>
			<fieldset class="$package-fieldset">
$text$resendButton
				<input id="$instance-$tagNew" type="hidden" value="$newValue" />
			</fieldset>
		</form>
HTML;
	}

	public static /*.void.*/ function getAccountForm(/*.string.*/ $mode, $newFlag = false) {
		$html = self::htmlAccountForm($mode, $newFlag);
		self::sendContent($html, self::ACTION_ACCOUNT);
	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlDashboard() {
		$package		= self::PACKAGE;
		$htmlButtonPreference	= self::htmlButton("preference");
		$htmlButtonEvents	= self::htmlButtonEvents();
		$message		= self::htmlMessage();
		$ezUser			=& /*.(ezUser).*/ $_SESSION[$package];
		$fullName		= $ezUser->fullName();

		return <<<HTML
		<form class="$package-form" onsubmit="return false">
			<fieldset class="$package-fieldset">
				<input id="$package-signOut" value="Sign out"
					tabindex	=	"3222"
$htmlButtonPreference
$htmlButtonEvents
				/>
				<input id="$package-goaccount" value="My account"
					tabindex	=	"3221"
$htmlButtonPreference
$htmlButtonEvents
				/>
				<div id="$package-fullName"
					class="$package-fullName">$fullName</div>
			</fieldset>
			<fieldset class="$package-fieldset">
$message
			</fieldset>
		</form>
HTML;
	}

//	public static /*.void.*/ function getDashboard() {
//		$html = self::htmlDashboard();
//		self::sendContent($html, self::PACKAGE);
//	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlSignInForm($username = '') {
		global $ezUser_verbose;

		$package		= self::PACKAGE;
		$tagUsername		= self::TAGNAME_USERNAME;
		$tagPassword		= self::TAGNAME_PASSWORD;
		$htmlButtonAction	= self::htmlButton('action');
		$htmlButtonPreference	= self::htmlButton('preference');
		$htmlButtonEvents	= self::htmlButtonEvents();
		$htmlInputText		= self::htmlInputText();
		$ezUser 		=& /*.(ezUser).*/ $_SESSION[$package];
		$result			= $ezUser->result();

		if ($result <= self::RESULT_SUCCESS) {
			$username = ($username === '') ? $ezUser->username() : $username;
			$message = self::htmlMessage();
			$verbose = "";
		} else {
			$username = $ezUser->username();
			$message = self::htmlMessage("Check username & password", self::MESSAGE_STYLE_FAIL);

			if ($ezUser_verbose) {
				$verbose = self::htmlButton("preference", true);
				$verbose = <<<HTML
				<input id="$package-verbose" value="$result"
$verbose
$htmlButtonEvents
				/>
HTML;
			} else {
				$verbose = "";
			}
		}

		$password = ($username === '') ? '' : '************';

		return <<<HTML
		<form class="$package-form" onsubmit="return false">
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
					onfocus		=	"{$package}_passwordFocus(this)"
$htmlInputText
				/>
				<label class="$package-label" for="$package-$tagPassword">Password:</label>
$verbose			</fieldset>
			<fieldset class="$package-fieldset">
$message
				<input id="$package-signIn" value="Sign in"
					tabindex	=	"3204"
$htmlButtonAction
$htmlButtonEvents
				/>
				<input id="$package-goaccount" value="Register"
					tabindex	=	"3203"
$htmlButtonAction
$htmlButtonEvents
				/>
			</fieldset>
			<fieldset class="$package-fieldset">
				<input id="$package-staySignedIn"	value="Stay signed in"
					tabindex	=	"3207"
$htmlButtonPreference
$htmlButtonEvents
				/>
				<input id="$package-rememberMe" value="Remember me"
					tabindex	=	"3206"
$htmlButtonPreference
$htmlButtonEvents
				/>
				<input id="$package-reminder" value="Reset password"
					tabindex	=	"3205"
$htmlButtonPreference
$htmlButtonEvents
				/>
			</fieldset>
		</form>
HTML;
	}

//	public static /*.void.*/ function getSignInForm () {
//		$html = self::htmlSignInForm();
//		self::sendContent($html, self::PACKAGE);
//	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlControlPanel($username = '') {
		$ezUser =& /*.(ezUser).*/ $_SESSION[self::PACKAGE];

		if ($ezUser->authenticated()) {
			return self::htmlDashboard();
		} else {
			return self::htmlSignInForm($username);
		}
	}

	public static /*.void.*/ function getControlPanel($username = '') {
		$html = self::htmlControlPanel($username);
		self::sendContent($html, self::PACKAGE);
	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlCSS() {
		$package	= self::PACKAGE;
		$instance	= self::getInstanceId(self::ACTION_ACCOUNT);

		$css = <<<CSS
/**
 * @package	ezUser
 * @author	Dominic Sayers <dominic_sayers@hotmail.com>
 * @copyright	2009 Dominic Sayers
 * @license	http://www.opensource.org/licenses/cpal_1.0 Common Public Attribution License Version 1.0 (CPAL) license
 * @link	http://www.dominicsayers.com
 * @version	0.12 - Local validation improved
 */
@charset "UTF-8";

.dummy {} /* Webkit is ignoring the first item so we'll put a dummy one in */

div#$package {
	font-family:"Segoe UI", Calibri, Arial, Helvetica, "sans serif";
	font-size:11px;
	line-height:100%;
	width:286px;
	float:left;
}

div#$instance {
	font-family:"Segoe UI", Calibri, Arial, Helvetica, "sans serif";
	font-size:12px;
	line-height:100%;
	width:286px;
	float:left;
}

div.$package-message {
/*	width:154px; */
	float:left;
	padding:6px;
	text-align:center;
	font-weight:normal;
/*	visibility:hidden; */
}

div.$package-text {
	width:286px;
	height:48px;
	float:left;
	padding:0;
	text-align:justify;
/*	visibility:hidden; */
	margin-top:7px;
	line-height:16px;
}

p.$package-message-plain	{margin:0;padding:6px;}
p.$package-message-info		{margin:0;padding:6px;background-color:#FFCC00;color:#000000;}
p.$package-message-text		{margin:0;padding:6px;background-color:#EEEEEE;color:#000000;}
p.$package-message-fail		{margin:0;padding:6px;background-color:#FF0000;color:#FFFFFF;font-weight:bold;}

div.$package-fullName {
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
	font-family:"Segoe UI", Calibri, Arial, Helvetica, "sans serif";
	border-style:solid;
	border-width:1px;
	cursor:pointer;
}

input.$package-action {
	font-size:12px;
	width:52px;
	margin:0 0 0 6px;
}

input.$package-preference {
	font-size:10px;
	margin:4px 0 0 6px;
}

input.$package-preference-verbose {float:left;margin:0;}

input.$package-buttonstate-0 {background-color:#FFFFFF;color:#444444;border-color:#666666 #333333 #333333 #666666;}
input.$package-buttonstate-1 {background-color:#FFFFFF;color:#444444;border-color:#FF9900 #CC6600 #CC6600 #FF9900;}
input.$package-buttonstate-2 {background-color:#FFFFFF;color:#444444;border-color:#666666 #333333 #333333 #666666;}
input.$package-buttonstate-3 {background-color:#FFFFFF;color:#444444;border-color:#FF9900 #CC6600 #CC6600 #FF9900;}
input.$package-buttonstate-4 {background-color:#CCCCCC;color:#222222;border-color:#333333 #666666 #666666 #333333;}
input.$package-buttonstate-5 {background-color:#CCCCCC;color:#222222;border-color:#CC6600 #FF9900 #FF9900 #CC6600;}
input.$package-buttonstate-6 {background-color:#CCCCCC;color:#222222;border-color:#333333 #666666 #666666 #333333;}
input.$package-buttonstate-7 {background-color:#CCCCCC;color:#222222;border-color:#CC6600 #FF9900 #FF9900 #CC6600;}

CSS;
		return $css;
	}

	public static /*.void.*/ function getCSS() {
		$html = self::htmlCSS();
		self::sendContent($html, 'CSS', 'text/css');
	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlJavascript() {
		$package		= self::PACKAGE;
		$instance		= self::getInstanceId(self::ACTION_ACCOUNT);

		$sessionName		= ini_get('session.name');
		$remoteAddress		= $_SERVER['REMOTE_ADDR'];
		$URL			= self::thisURL();
		$folder			= dirname($URL);

		$cookieUsername		= self::EZUSER_COOKIE_USERNAME;
		$cookiePassword		= self::EZUSER_COOKIE_PASSWORD;
		$cookieStaySignedIn	= self::EZUSER_COOKIE_AUTOSIGN;

		$tagFirstName		= self::TAGNAME_FIRSTNAME;
		$tagLastName		= self::TAGNAME_LASTNAME;
		$tagEmail		= self::TAGNAME_EMAIL;
		$tagUsername		= self::TAGNAME_USERNAME;
		$tagPassword		= self::TAGNAME_PASSWORD;
		$tagConfirm		= self::TAGNAME_CONFIRM;
		$tagNew			= self::TAGNAME_NEW;

		$action			= self::ACTION;
		$actionMain		= self::ACTION_MAIN;
		$actionAccount		= self::ACTION_ACCOUNT;
		$actionAccountInPanel	= self::ACTION_PANELACCOUNT;
		$actionValidate		= self::ACTION_VALIDATE;
		$actionSignIn		= self::ACTION_SIGNIN;
		$actionSignOut		= self::ACTION_SIGNOUT;
		$actionCancel		= self::ACTION_CANCEL;
		$actionCSS		= self::ACTION_CSS;
		$actionResultForm	= self::ACTION_RESULTFORM;
		$actionResend		= self::ACTION_RESEND;
		$actionEdit		= self::ACTION_ACCOUNT . '=edit';

		$accountPage		= self::getSetting(self::SETTINGS_ACCOUNTPAGE);
		$accountClick		= ($accountPage === '') ? $package . "_ajax[0].execute('$actionAccountInPanel')" : "window.location = '$folder/$accountPage'";
		$componentHeader	= self::componentHeader();

		$js = <<<JAVASCRIPT
/**
 * @package	ezUser
 * @author	Dominic Sayers <dominic_sayers@hotmail.com>
 * @copyright	2009 Dominic Sayers
 * @license	http://www.opensource.org/licenses/cpal_1.0 Common Public Attribution License Version 1.0 (CPAL) license
 * @link	http://www.dominicsayers.com
 * @version	0.12 - Local validation improved
 */
/*global window, document, event, ActiveXObject */ // For JSLint
'use strict';
var ezUser, ezUser_node, ezUser_ajax = [];

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
// 		ezUser_setButtonState
// ---------------------------------------------------------------------------
// Responds to various UI events and controls the appearance of the form's
// buttons
// ---------------------------------------------------------------------------
function ezUser_setButtonState(control, eventID, setOn) {
	// eventID	1 = mouseover/mouseout
	// 		2 = focus/blur
	// 		4 = selected/unselected

	if (control === null) {return;}

	var	baseClass	= control.className,
		stateClass	= '$package-buttonstate-',
		pos		= baseClass.indexOf(stateClass),
		currentState	= Number(control.state);

	currentState		= (setOn) ? currentState | eventID : currentState & ~eventID;
	control.state		= String(currentState);
	baseClass		= (pos === -1) ? baseClass + ' ' : baseClass.substring(0, pos);
	control.className	= baseClass + stateClass + String(currentState);
}

// ---------------------------------------------------------------------------
// 		C_ezUser_cookies
// ---------------------------------------------------------------------------
// General cookie management
// ---------------------------------------------------------------------------
function C_ezUser_cookies() {
	// Public methods
	this.persist = function(name, value, days) {
		var date, expires;

		if (typeof(days) !== 'undefined') {
			date = new Date();
			date.setTime(date.getTime() + (days * 1000 * 3600 * 24));
			expires = '; expires=' + date.toGMTString();
		} else {
			expires = '';
		}

		document.cookie = name + '=' + value + expires + '; path=/';
	};

	this.acquire = function(name) {
		name = name + '=';
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
// 		C_ezUser
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
			that.staySignedIn = false;
		} else {
			that.username = username;
			that.rememberMe = true;
		}

		if (passwordHash === null) {
			that.staySignedIn = false;
		} else {
			that.passwordHash = passwordHash;
			that.rememberMe = true;
		}
	}

// ---------------------------------------------------------------------------
	// Public properties
	this.rememberMe		= false;
	this.staySignedIn	= false;
	this.usernameDefault	= true;
	this.username		= '';
	this.passwordHash	= '';
	this.sessionID		= '';

	// Public methods
// ---------------------------------------------------------------------------
	this.updateCookies = function() {
		this.username = document.getElementById('$package-$tagUsername').value;

		var passwordHash = (this.passwordHash === '') ? ezUser_SHA256plusIP(document.getElementById('$package-$tagPassword').value) : this.passwordHash;
		this.passwordHash = passwordHash;

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
		case 0:
			message		= '';
			fail		= false;
			messageType	= 'message';
			instance	= '$package';
			break;
		case 1:
			fail		= false;
			messageType	= 'message';
			instance	= '$package';
			break;
		case 2:
			messageType	= 'message';
			instance	= '$package';
			break;
		case 3:
			instance	= '$package';
			break;
		}

		var	id		= instance + '-' + messageType,
			div		= document.getElementById(id),
			classString	= '$package-' + messageType,
			subClass	= (fail) ? 'fail' : 'info',
			p;

		if (div === null) {return;} // No such control
		if (div.hasChildNodes()) {div.removeChild(div.firstChild);}

		if (message !== '') {
			p		= document.createElement('p');
			p.className	= '$package-message-' + subClass;
			p.innerHTML	= message;

			div.className	= classString;
			div.appendChild(p);
		}

		div = document.getElementById('$package-verbose');
		if (div !== null) {div.parentNode.removeChild(div);}
	};

// ---------------------------------------------------------------------------
	this.showPreferences = function() {
		ezUser_setButtonState(document.getElementById('$package-rememberMe'), 4, this.rememberMe);
		ezUser_setButtonState(document.getElementById('$package-staySignedIn'), 4, this.staySignedIn);
	};

// ---------------------------------------------------------------------------
	this.fillContainer = function(id, html) {
		var container = document.getElementById(id);

		if (container === null || typeof(container) === 'undefined') {return;}
		if (container.className.length === 0) {container.className = id;} // IE6 uses container.class, but we aren't supporting IE6 yet
		container.innerHTML = html;

		// Set focus to the first text control
		var textId = '', textBox = null;

		switch (id) {
		case '$package':
			textId = '$package-$tagUsername';
			break;
		case '$instance':
			textId = '$instance-$tagEmail';
			break;
		}

		if (!textId === '') {textBox = document.getElementById(textId);}
		if (textBox === null || typeof(textBox) === 'undefined' || textBox.disabled === 'disabled') {return;}

		textBox.focus();
		textBox.select();
	};

// ---------------------------------------------------------------------------
	this.localValidation = function() {
		var	textUsername	= document.getElementById('$instance-$tagUsername'),
			textPassword	= document.getElementById('$instance-$tagPassword'),
			textConfirm	= document.getElementById('$instance-$tagConfirm'),
			message;

		// Valid username
		this.normaliseUsername(textUsername.value);
		
		if (textUsername.value === '') {
			message = 'The username cannot be blank';
			this.showMessage(message, true, 'text', '$instance');
			textUsername.focus();
			textUsername.select();
	
			return false;
		}

		// Passwords match
		if (textPassword.value !== '' && textPassword.value === textConfirm.value) {return true;}

		message = (textPassword.value === '') ? 'Password cannot be blank' : 'Passwords are not the same';
		this.showMessage(message, true, 'text', '$instance');
		textPassword.focus();
		textPassword.select();

		return false;
	};

// ---------------------------------------------------------------------------
	this.normaliseUsername = function(username) {
		var	regexString	= '[^0-9a-z_-]',
			regex		= new RegExp(regexString, 'g'),

		username		= username.toLowerCase();
		username		= username.replace(regex, '');
		control			= document.getElementById('$instance-$tagUsername');
		control.defaultValue	= username;
	};

// ---------------------------------------------------------------------------
// Constructor
// ---------------------------------------------------------------------------
	getCookies();
}

// ---------------------------------------------------------------------------
// 		C_ezUser_AJAX
// ---------------------------------------------------------------------------
// Talk to the man
// ---------------------------------------------------------------------------
function C_ezUser_AJAX() {

	// Private methods
// ---------------------------------------------------------------------------
	function getXMLHttpRequest() {
		if (typeof(window.XMLHttpRequest) === 'undefined') {
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
				var id = ajax.getResponseHeader('$componentHeader');

				if (id === null) {id = '$package';}
				ezUser.fillContainer(id, ajax.responseText);
				if (id === '$package') {ezUser.showPreferences();}
				if (id === '$instance') {ezUser.usernameDefault = true;}
			} else {
				var	fail		= true,
					message		= 'Server error, please try later',
					cancelButton	= document.getElementById('$package-cancel');

				ezUser.showMessage(message, fail);

				if (cancelButton !== null) {
					cancelButton.id		= '$package-signIn';
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
		ajax.setRequestHeader('Accept', 'text/html,application/ezUser');
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
			equalPos = thisAction.indexOf('=');

		action = (equalPos === -1) ? thisAction : thisAction.slice(0, equalPos);

		switch (action) {
		case '$actionSignIn':
			ezUser.showMessage('Signing in - please wait');
			document.getElementById('$package-signIn').id = '$package-cancel';
			document.getElementById('$package-cancel').value = 'Cancel';
			ezUser.updateCookies();

			passwordHash	= (ezUser.passwordHash === '') ? ezUser_SHA256plusIP(document.getElementById('$package-$tagPassword').value) : ezUser.passwordHash;
			passwordHash	= SHA256(ezUser.sessionID + passwordHash);
			requestData	= '$action='		+ action
					+ '&$cookieUsername='	+ document.getElementById('$package-$tagUsername').value
					+ '&$cookiePassword='	+ passwordHash;
			requestType	= 'POST';

			break;
		case '$actionValidate':
			passwordHash	= SHA256(document.getElementById('$instance-$tagPassword').value);
			requestData	= '$action='		+ action
					+ '&$tagNew='		+ document.getElementById('$instance-$tagNew').value
					+ '&$tagEmail='		+ document.getElementById('$instance-$tagEmail').value
					+ '&$tagFirstName='	+ document.getElementById('$instance-$tagFirstName').value
					+ '&$tagLastName='	+ document.getElementById('$instance-$tagLastName').value
					+ '&$cookieUsername='	+ document.getElementById('$instance-$tagUsername').value
					+ '&$cookiePassword='	+ passwordHash;
			requestType	= 'POST';

			break;
		case '$actionMain':
			URL += '?' + action;
			if (ezUser.rememberMe === true) {URL += '=' + ezUser.username;}
			requestType = 'GET';
			break;
		case '$actionCancel':
			var readyState = ajax.readyState;

			if ((readyState > 0) && (readyState < 4)) {
				// Cancel ongoing sign-in
				ajax.abort();
				ajax = getXMLHttpRequest();
			}

			return;
			break;
		case '$actionResend':
			URL += '?' + thisAction;
			URL += '=' + document.getElementById('$instance-$tagEmail').value;
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
// Put the CSS in the CSS place
// ---------------------------------------------------------------------------
var	htmlHead	= document.getElementsByTagName('head')[0],
	nodeList	= htmlHead.getElementsByTagName('LINK'),
	elementCount	= nodeList.length,
	found		= false,
	i;

for (i = 0; i < elementCount; i++) {
	if (nodeList[i].title === '$package') {
		found = true;
		break;
	}
}

if (found === false) {
	ezUser_node		= document.createElement('link');
	ezUser_node.type	= 'text/css';
	ezUser_node.rel		= 'stylesheet';
	ezUser_node.href	= '$URL?$actionCSS';
	ezUser_node.title	= '$package';
	htmlHead.appendChild(ezUser_node);
}

// ---------------------------------------------------------------------------
// Do stuff
// ---------------------------------------------------------------------------
ezUser = new C_ezUser();

// ---------------------------------------------------------------------------
// 		ezUser_click
// ---------------------------------------------------------------------------
// Responds to clicks on the ezUser form
// ---------------------------------------------------------------------------
function ezUser_click(control) {
	switch (control.id) {
	case '$package-signIn':
		ezUser_ajax[0].execute('$actionSignIn');
		break;
	case '$package-signOut':
		ezUser_ajax[0].execute('$actionSignOut');
		break;
	case '$package-goaccount':
		$accountClick;
		break;
	case '$package-cancel':
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
	case '$package-verbose':
		ezUser_ajax[0].execute('$actionResultForm=' + control.value);
		break;
	case '$package-OK':
		ezUser_ajax[0].execute('$actionMain');
		break;
	case '$instance-validate':
		if (ezUser.localValidation()) {
			ezUser_ajax[0].execute('$actionValidate');
		}

		break;
	case '$instance-edit':
		ezUser_ajax[0].execute('$actionEdit');
		break;
	case '$instance-resend':
		ezUser_ajax[0].execute('$actionResend');
		break;
	}
}

// ---------------------------------------------------------------------------
// 		ezUser_keyUp
// ---------------------------------------------------------------------------
// Responds to key presses on the ezUser form
// ---------------------------------------------------------------------------
function ezUser_keyUp(e) {
	var characterCode, id, control;

	if (e && e.which) {
		e = e;
		characterCode = e.which;
	} else {
		if (typeof(event) !== 'undefined') {e = event;}
		characterCode = e.keyCode;
	}

	// If we are messing with the username then forget creating a default
	id = e.target.id;
	
	if (id === '$instance-$tagUsername') {
		ezUser.usernameDefault = false;
		ezUser.normaliseUsername(document.getElementById(id).value);
	} else if (ezUser.usernameDefault === true && (id === '$instance-$tagFirstName' || id === '$instance-$tagLastName')) {
		ezUser.normaliseUsername(document.getElementById('$instance-$tagFirstName').value + document.getElementById('$instance-$tagLastName').value);
	}

	// Process Carriage Return and tidy up form
	id = e.target.parentNode.parentNode.parentNode.id;

	switch (id) {
	case '$package':
		if (characterCode === 13) {
			ezUser_click(document.getElementById('$package-signIn'));
		} else {
			ezUser.showMessage(); // Hide message
		}

		break;
	case '$instance':
		if (characterCode === 13) {
			control = document.getElementById('$instance-validate');
			if (control === null) {control = document.getElementById('$instance-edit');}
			ezUser_click(control);
		} else {
			ezUser.showMessage('', false, 'text', '$instance'); // Hide message
		}

		break;
	}
}

// ---------------------------------------------------------------------------
// 		ezUser_passwordFocus
// ---------------------------------------------------------------------------
// Responds to focus arriving on the password input control
// ---------------------------------------------------------------------------
function ezUser_passwordFocus(control) {
	if (ezUser.passwordHash !== '') {
		// Forget password from cookie
		ezUser.passwordHash = '';
		control.value = '';
	}
}

// ---------------------------------------------------------------------------
// 		ezUser_getHTML
// ---------------------------------------------------------------------------
// Creates a new ajax object and uses it to get some HTML from the server
// ---------------------------------------------------------------------------
function ezUser_getHTML(action) {
	var	ajax		= new C_ezUser_AJAX(),
		newIndex	= ezUser_ajax.push(ajax) - 1;

	ezUser_ajax[newIndex].execute(action);
}

JAVASCRIPT;
		return $js;
	}

	public static /*.void.*/ function getJavascript() {
		$html = self::htmlJavascript();
		self::sendContent($html, 'Javascript', 'text/javascript');
	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlContainer($action = self::ACTION_MAIN) {
		$package		= self::PACKAGE;
		$instance		= self::getInstanceId($action);
		$actionJavascript	= self::ACTION_JAVASCRIPT;
		$URL			= self::thisURL();
		$jsVariable		= $package . '_ajax';
		$js			= $jsVariable . '[' . $jsVariable . '.push(new C_' . $package . "_AJAX()) - 1].execute('$action')";

		return <<<HTML
	<div id="$instance"></div>
	<script type="text/javascript">document.write(unescape('%3Cscript src="$URL?$actionJavascript" type="text/javascript"%3E%3C/script%3E'));</script>
	<script type="text/javascript">
		$js;
	</script>
HTML;
	}

	public static /*.void.*/ function getContainer($action = self::ACTION_MAIN) {
		$html = self::htmlContainer($action);
		self::sendContent($html, 'container');
	}

// ---------------------------------------------------------------------------
	private	static /*.string.*/ function htmlAbout() {
		$matches = /*.(array[int][int]string).*/ array();
		preg_match_all("!(?<=^ \\* @)(?:.)+(?=$)!m", file_get_contents(__FILE__, 0, NULL, -1, 1024), $matches);
		$html = "<pre>\n";
		foreach ($matches[0] as $match) {$html .= "    " . htmlspecialchars($match) . "\n";}
		$html .= "<hr />\n";
		$html .= "</pre>\n";
		return $html;
	}

	public	static /*.void.*/ function getAbout() {
		$html = self::htmlAbout();
		self::sendContent($html, 'about');
	}

	private	static /*.string.*/ function htmlSourceCode() {return (string) highlight_file(__FILE__, 1);}

	public	static /*.void.*/ function getSourceCode() {
		$html = self::htmlSourceCode();
		self::sendContent($html, 'sourceCode');
	}

// ---------------------------------------------------------------------------
	public static /*.boolean.*/ function sendConfirmationEmail($email = '') {
		if ($email === '') return false;
		$ezUser = ezUsers::lookup($email);
		if ($ezUser->status() !== self::STATUS_PENDING) return false;

		$URL	= self::thisURL();
		$host	= $_SERVER['HTTP_HOST'];
		$s	= ($_SERVER['SERVER_PROTOCOL'] === 'HTTPS') ? 's' : '';

		$from	= self::getSetting(self::SETTINGS_ADMINEMAIL);
		$from	= ($from === '') ? 'webmaster' : $from;

		// If there's no domain, then assume same as this host
		if (strpos($from, self::EMAIL_DELIMITER) === false) {
			$domain = (substr_count($host, '.') > 1) ? substr($host, strpos($host, '.') + 1) : $host;
			$from .= self::EMAIL_DELIMITER . $domain;
		}

		// Extra headers
		$additional_headers = "From: $from\r\n";

		// Message
		$message	= "Somebody calling themselves " . $ezUser->fullName() . " created an account at http$s://$host using this email address.\n";
		$message	.= "If it was you please click on the following link to verify the account.\n\n";
		$message	.= "http$s://$host$URL?" . self::ACTION_VERIFY . "=" . $ezUser->verificationKey() . "\n\n";
		$message	.= "After you click the link your account will be fully functional.\n";

		// Send it
		$to		= $ezUser->email();
		$subject	= "New account confirmation";
		date_default_timezone_set(@date_default_timezone_get());	// E_STRICT needs this or it complains about the mail function
		$success	= @mail($to, $subject, $message, $additional_headers);
		$message	= ($success) ? "Verification email has been resent." : "Verification email was not sent: please try again later";
		self::sendContent($message, self::ACTION_ACCOUNT . '-' . self::MESSAGE_TYPE_TEXT);

		return $success;
	}
}
// End of class ezUserUI
//+C_ezUserUI+

set_include_path(get_include_path() . PATH_SEPARATOR . realpath(dirname(__FILE__)));
if (!function_exists('__autoload')) {/*.void.*/ function __autoload(/*.string.*/ $className) {require "C_$className.php";}}

// ---------------------------------------------------------------------------
// 		ezUser.php
// ---------------------------------------------------------------------------
// Some code to make this all automagic and a bit RESTful
// If you want more control over how ezUser works then you might need to amend
// or even remove the code below here

// There may already be a session in progress. We will use the existing
// session if possible.
if ((int) ini_get('session.use_cookies') === 0) {
	ezUserUI::fatalError(ezUser::RESULT_NOSESSIONCOOKIES);
} else {
	if (!isset($_SESSION) || !is_array($_SESSION) || !is_object($_SESSION[ezUser::PACKAGE])) session_start();
}

$ezUser =& ezUserUI::getSessionObject();

// Is this script included in another page or is it the HTTP target itself?
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
	// This script has been called directly by the client

	// Attempt auto-signin?
	if (!$ezUser->authenticated()) {
		if (isset($_COOKIE[ezUser::EZUSER_COOKIE_AUTOSIGN]) && ($_COOKIE[ezUser::EZUSER_COOKIE_AUTOSIGN] === 'true')) {
			$user[ezUser::EZUSER_COOKIE_USERNAME] = (string) $_COOKIE[ezUser::EZUSER_COOKIE_USERNAME];
			$user[ezUser::EZUSER_COOKIE_PASSWORD] = hash(ezUser::HASH_FUNCTION, (string) $_COOKIE[ini_get('session.name')] . (string) $_COOKIE[ezUser::EZUSER_COOKIE_PASSWORD]);
			$ezUser = ezUsers::doSignIn($user);
		}
	}

	// First, deal with anything in $_GET
	if (is_array($_GET) && (count($_GET) > 0)) {
		if (isset($_GET[ezUser::ACTION_CONTAINER]))	ezUserUI::getContainer		((string)	$_GET[ezUser::ACTION_CONTAINER]);
		if (isset($_GET[ezUser::ACTION_MAIN]))		ezUserUI::getControlPanel	((string)	$_GET[ezUser::ACTION_MAIN]);
		if (isset($_GET[ezUser::ACTION_ACCOUNT]))	ezUserUI::getAccountForm	((string)	$_GET[ezUser::ACTION_ACCOUNT]);
		if (isset($_GET[ezUser::ACTION_PANELACCOUNT]))	ezUserUI::getAccountForm	((string)	$_GET[ezUser::ACTION_PANELACCOUNT]); // To do
		if (isset($_GET[ezUser::ACTION_STATUSTEXT]))	ezUserUI::getStatusText		((int)		$_GET[ezUser::ACTION_STATUSTEXT]);
		if (isset($_GET[ezUser::ACTION_RESULTTEXT]))	ezUserUI::getResultText		((int)		$_GET[ezUser::ACTION_RESULTTEXT]);
		if (isset($_GET[ezUser::ACTION_RESULTFORM]))	ezUserUI::getResultForm		((int)		$_GET[ezUser::ACTION_RESULTFORM]);
		if (isset($_GET[ezUser::ACTION_RESEND]))	ezUserUI::sendConfirmationEmail	((string)	$_GET[ezUser::ACTION_RESEND]);
		if (isset($_GET[ezUser::ACTION_JAVASCRIPT]))	ezUserUI::getJavascript();
		if (isset($_GET[ezUser::ACTION_CSS]))		ezUserUI::getCSS();
		if (isset($_GET[ezUser::ACTION_ABOUT]))		ezUserUI::getAbout();
		if (isset($_GET[ezUser::ACTION_SOURCECODE]))	ezUserUI::getSourceCode();
		if (isset($_GET[ezUser::ACTION_SIGNOUT])) {
			$ezUser->signOut();
			ezUserUI::getControlPanel();
		}
	} else {
		// Now let's check $_POST
		if (is_array($_POST) && (count($_POST) > 0)) {
			if (isset($_POST[ezUser::ACTION])) {
				switch ((string) $_POST[ezUser::ACTION]) {
					case ezUser::ACTION_SIGNIN:
						$ezUser = ezUsers::doSignIn($_POST);
						ezUserUI::getControlPanel();
						break;
					case ezUser::ACTION_VALIDATE:
						ezUsers::validate($_POST, ezUserUI::getSessionObject(ezUser::ACTION_ACCOUNT));
						ezUserUI::getAccountForm('result',(bool) $_POST[ezUser::TAGNAME_NEW]);
						break;
					default:
						ezUserUI::getResultForm(ezUser::RESULT_UNKNOWNACTION);
				}
			}
		} else {
			// Nothing in $_GET or $_POST, so give a friendly greeting
			ezUserUI::getAbout();
		}
	}
}
?>