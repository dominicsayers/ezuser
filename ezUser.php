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
 * @link	http://www.dominicsayers.com
 * @version	0.9 - Code tidy and unit testing started
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


// ---------------------------------------------------------------------------
// 			ezUserAPI
// ---------------------------------------------------------------------------
// ezUser REST interface & other constants
// ---------------------------------------------------------------------------
/**
 * @package	ezUser
 * @author	Dominic Sayers <dominic_sayers@hotmail.com>
 * @copyright	2009 Dominic Sayers
 * @license	http://www.opensource.org/licenses/cpal_1.0 Common Public Attribution License Version 1.0 (CPAL) license
 * @link	http://www.dominicsayers.com
 * @version	0.9 - Code tidy and unit testing started
 */
interface ezUserAPI {
	const	PACKAGE			= 'ezUser',

		ACTION			= 'action',
		ACTION_MAIN		= 'controlpanel',
		ACTION_SIGNIN		= 'signin',
		ACTION_SIGNOUT		= 'signout',
		ACTION_ACCOUNT		= 'account',
		ACTION_PANELACCOUNT	= 'accountinpanel',
		ACTION_VALIDATE		= 'validate',
		ACTION_CONTAINER	= 'container',
		ACTION_JAVASCRIPT	= 'js',
		ACTION_CSS		= 'css',
		ACTION_ABOUT		= 'about',
		ACTION_SOURCECODE	= 'code',
		ACTION_STATUSTEXT	= 'statustext',
		ACTION_RESULTTEXT	= 'resulttext',
		ACTION_RESULTFORM	= 'resultform',
		ACTION_CANCEL		= 'cancel',

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

// ---------------------------------------------------------------------------
// 				ezUser
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
 * @version	0.9 - Code tidy and unit testing started
 */
class ezUser implements ezUserAPI, Iterator {
	// Keys for the user data array members
	/*.private.*/ const	KEY_ID			= 'id',
				KEY_PASSWORD		= 'password',
				KEY_FIRSTNAME		= 'firstName',
				KEY_LASTNAME		= 'lastName',
				KEY_STATUS		= 'status';
	/*.protected.*/	const	KEY_USERNAME		= 'username',
				KEY_EMAIL		= 'email';

	// User data
	private $values = /*.(array[string]string).*/ array();

	// State and derived data
	private		$authenticated		= false;
	private		$usernameIsDefault	= true;
	private		$fullName		= '';
	private		$result			= self::RESULT_UNDEFINED;
	private		$config			= /*.(array[string]string).*/ array();
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
// Helper methods
// ---------------------------------------------------------------------------
	private static /*.boolean.*/ function is_email(/*.string.*/ $email, $checkDNS = false) {
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

// ---------------------------------------------------------------------------
// Substantive methods
// ---------------------------------------------------------------------------
	public /*.boolean.*/ function authenticate(/*.string.*/ $passwordHash) {
		$sessionHash = hash(self::HASH_FUNCTION, (string) $_COOKIE[ini_get('session.name')] . hash(self::HASH_FUNCTION, $_SERVER['REMOTE_ADDR'] . $this->values[self::KEY_PASSWORD]));
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
		case self::KEY_ID:		return '';
		case self::KEY_PASSWORD:	return '';
		case 'fullName':		return $this->fullName;
		case 'authenticated':		return (string) $this->authenticated;
		default:			return (isset($this->values[$key])) ? $this->values[$key] : '';
		}
	}

	protected	/*.string.*/			function id()			{return $this->getValue(self::KEY_ID);}
	public		/*.string.*/			function username()		{return $this->getValue(self::KEY_USERNAME);}
	protected	/*.string.*/			function passwordHash()		{return $this->getValue(self::KEY_PASSWORD);}
	public		/*.string.*/			function firstName()		{return $this->getValue(self::KEY_FIRSTNAME);}
	public		/*.string.*/			function lastName()		{return $this->getValue(self::KEY_LASTNAME);}
	public		/*.string.*/			function email()		{return $this->getValue(self::KEY_EMAIL);}
	public		/*.int.*/			function status()		{return (int) $this->getValue(self::KEY_STATUS);}
	public		/*.string.*/			function fullName()		{return $this->fullName;}
	public		/*.boolean.*/			function authenticated()	{return $this->authenticated;}
	protected	/*.int.*/			function result()		{return $this->result;}
	protected	/*.array[string]string.*/	function config()		{return $this->config;}

// ---------------------------------------------------------------------------
// "Set" methods
// ---------------------------------------------------------------------------
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

	protected /*.int.*/ function setID(/*.string.*/ $id) {
		if ($id === '') return self::RESULT_NOID;
		$this->changeValue(self::KEY_ID, $id);
		return self::RESULT_VALIDATED;
	}

	protected /*.int.*/ function setPasswordHash(/*.string.*/ $passwordHash) {
		if ($passwordHash === '')				return self::RESULT_NOPASSWORD;
		if ($passwordHash === hash(self::HASH_FUNCTION, ''))	return self::RESULT_NULLPASSWORD;
		$this->changeValue(self::KEY_PASSWORD, $passwordHash);
		return self::RESULT_VALIDATED;
	}

	protected /*.int.*/ function setStatus(/*.int.*/ $status) {
		if (is_nan($status)) return self::RESULT_STATUSNAN;
		$this->changeValue(self::KEY_STATUS, (string) $status);
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
		$lastName = (isset($this->values[self::KEY_LASTNAME])) ? $this->values[self::KEY_LASTNAME] : '';
		return strtolower($this->values[self::KEY_FIRSTNAME] . $lastName);
	}

	private /*.void.*/ function update_name() {
		$lastName	= (isset($this->values[self::KEY_LASTNAME])) ? $this->values[self::KEY_LASTNAME] : '';
		$separator	= ($this->values[self::KEY_FIRSTNAME] === '') ? '' : ' ';
		$fullName	= $this->values[self::KEY_FIRSTNAME] . $separator . $lastName;

		if ($fullName !== $this->fullName) {
			$this->fullName		= $fullName;
			$this->isChanged	= true;
		}

		if ($this->usernameIsDefault) {$this->changeValue(self::KEY_USERNAME, $this->usernameDefault());}
	}

	private /*.void.*/ function setNamePart(/*.string.*/ $key, /*.string.*/ $name) {
		if ($this->changeValue($key, $name)) $this->update_name();
	}

	public /*.void.*/ function setFirstName(/*.string.*/ $name)	{$this->setNamePart(self::KEY_FIRSTNAME, $name);}
	public /*.void.*/ function setLastName(/*.string.*/ $name)	{$this->setNamePart(self::KEY_LASTNAME, $name);}

	protected /*.int.*/ function setUsername($name = '') {
		if ($name === '') {
			$this->usernameIsDefault = true;
			$name = $this->usernameDefault();
		} else {
			$this->usernameIsDefault = ($name === $this->usernameDefault());
		}

		if ($name === '') return self::RESULT_NOUSERNAME;
		$this->changeValue(self::KEY_USERNAME, $name);
		return self::RESULT_VALIDATED;
	}

	protected /*.int.*/ function setEmail(/*.string.*/ $email) {
		if ($email === '')		return self::RESULT_NOEMAIL;
		if (!self::is_email($email))	return self::RESULT_EMAILFORMATERR;
		$this->changeValue(self::KEY_EMAIL, $email);
		return self::RESULT_VALIDATED;
	}
}
// End of class ezUser

// ---------------------------------------------------------------------------
// 								ezUsers
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
 * @version	0.9 - Code tidy and unit testing started
 */
class ezUsers extends ezUser {
				// Cookie names
	/*.public.*/ const	COOKIE_USERNAME		= 'ezUser1',
				COOKIE_PASSWORD		= 'ezUser2',
				COOKIE_STAYSIGNEDIN	= 'ezUser3';

	/*.private.*/ const	STORAGE			= '.ezuser_data.php';

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
			if ($handle === false) die(parent::RESULT_STORAGEERR);
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
	private static /*.ezUser.*/ function lookup($id = '') {
		$document	= self::connectStorage();
		$tagName	= ((bool) strpos($id,parent::EMAIL_DELIMITER)) ? parent::KEY_EMAIL : parent::KEY_USERNAME;
		$nodeList	= $document->getElementsByTagName($tagName);
		$found		= false;

		for ($i = 0; $i < $nodeList->length; $i++) {
			$node = $nodeList->item($i);

			if ($node->nodeValue === $id) {
				$found = true;
				break;
			}
		}

		$ezUser = new ezUser();

		if ($found) {
			// Populate $ezUser from stored data
			$nodeList = $node->parentNode->childNodes;

			for ($i = 0; $i < $nodeList->length; $i++) {
				$node = $nodeList->item($i);
				if ($node->nodeType === XML_ELEMENT_NODE) {$ezUser->setValue($node->nodeName, $node->nodeValue);}
			}
		} else {
			$ezUser->setStatus(parent::STATUS_UNKNOWN);
		}

		return $ezUser;
	}

// ---------------------------------------------------------------------------
	private static /*.int.*/ function is_duplicate(/*.string.*/ $username, /*.string.*/ $email, /*.string.*/ $id) {
		// Username must be unique
		$ezUser = self::lookup($username);

		if ($ezUser->status() !== parent::STATUS_UNKNOWN) {
			if ($ezUser->id() !== $id) return parent::RESULT_USERNAMEEXISTS;
		}

		// Email must be unique
		$ezUser = self::lookup($email);

		if ($ezUser->status() !== parent::STATUS_UNKNOWN) {
			if ($ezUser->id() !== $id) return parent::RESULT_EMAILEXISTS;
		}

		// No choice but to...
		return parent::RESULT_VALIDATED;
	}

// ---------------------------------------------------------------------------
	private static /*.void.*/ function persist(/*.ezUser.*/ &$ezUser) {
		$document	= self::connectStorage();
		$user		= $document->createElement('user');
		$users		= $document->getElementsByTagName('users')->item(0);

		$users->appendChild($user);
		$users->appendChild($document->createTextNode("\n")); // XML formatting

		foreach ($ezUser as $key => $value) {
			$user->appendChild($document->createTextNode("\t")); // XML formatting
			$user->appendChild($document->createElement((string) $key, $value));
			$user->appendChild($document->createTextNode("\n")); // XML formatting
		}

		$storage_file = dirname(__FILE__) . '/' . self::STORAGE;
		$document->save($storage_file);
	}

// ---------------------------------------------------------------------------
	public static /*.ezUser.*/ function doSignIn(/*.array[string]mixed.*/ $user) {
		$username	= (string) $user[self::COOKIE_USERNAME];
		$password	= (string) $user[self::COOKIE_PASSWORD];
		$ezUser		= self::lookup($username);

		if ($ezUser->status() == parent::STATUS_UNKNOWN) {
			$ezUser->setResult(parent::RESULT_UNKNOWNUSER);		// User does not exist
		} else {
			if ($ezUser->authenticate($password)) {
				$ezUser->setResult(parent::RESULT_SUCCESS);	// Correct password
			} else {
				$ezUser->setResult(parent::RESULT_BADPASSWORD);	// User exists but password is wrong
			}
		}

		return $ezUser;
	}

// ---------------------------------------------------------------------------
	public static /*.int.*/ function validate(/*.array[string]mixed.*/ $userData, /*.ezUser.*/ &$ezUser) {
		$id = $ezUser->id();

		$ezUser->setFirstName(	(string) $userData['firstname']);
		$ezUser->setLastName(	(string) $userData['lastname']);
		$email			=		(string) $userData['email'];
		$username		=		(string) $userData[self::COOKIE_USERNAME];
		$passwordHash	=		(string) $userData[self::COOKIE_PASSWORD];

		$result			= parent::RESULT_VALIDATED;

		$thisResult 	= $ezUser->setEmail		($email);		if ($thisResult !== parent::RESULT_VALIDATED) $result = $thisResult;
		$thisResult 	= $ezUser->setUsername		($username);		if ($thisResult !== parent::RESULT_VALIDATED) $result = $thisResult;
		$thisResult 	= $ezUser->setPasswordHash	($passwordHash);	if ($thisResult !== parent::RESULT_VALIDATED) $result = $thisResult;

		if ($result === parent::RESULT_VALIDATED) $result = self::is_duplicate($username, $email, $id);

		$ezUser->setResult($result);

		if ($result === parent::RESULT_VALIDATED) {
			if (!isset($id) || $id === '') $id = $_SERVER['REMOTE_ADDR'] . "." . $_SERVER['REQUEST_TIME'];

			$ezUser->setID($id);
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
 * @version	0.9 - Code tidy and unit testing started
 */
class ezUserUI extends ezUsers implements ezUserAPI {
	/*.private.*/	const	CONFIG_FILE 	= '.ezuser_settings.php',
				DIVID_MAIN	= self::PACKAGE,
				DIVID_ACCOUNT	= 'ezUser-account';

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
		$ezUser		=& /*.(ezUser).*/ $_SESSION[self::PACKAGE];
		$config		= $ezUser->config();
		$settingsFile	= realpath(dirname(__FILE__) . '/' . self::CONFIG_FILE);

		// If configuration settings file doesn't exist then use default settings
		if (($settingsFile === false) || !is_file($settingsFile)) {
			$config['empty'] = "true";
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

		$config['getPersisted'] = "true";
		$ezUser->setConfig($config);
		return $config;
	}

	private static /*.array[string]string.*/ function getSettings() {
		$ezUser =& /*.(ezUser).*/ $_SESSION[self::PACKAGE];
		$config =& $ezUser->config();

		if (!is_array($config))			{$config = self::loadConfig();}
		if (!isset($config['getPersisted']))	{$config = self::loadConfig();}
		if ($config['getPersisted'] !== 'true')	{$config = self::loadConfig();}

		return $config;
	}

	private static /*.string.*/ function getSetting(/*.string.*/ $setting) {
		$config = self::getSettings();
		$thisSetting = (isset($config[$setting])) ? $config[$setting] : '';
		return $thisSetting;
	}

	private static /*.string.*/ function accountPage() {
		return self::getSetting('accountPage');
	}

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------
	private static /*.string.*/ function componentHeader() {return self::PACKAGE . "-component";}

	private static /*.void.*/ function sendContent(/*.string.*/ &$content, /*.string.*/ $component, $contentType = '') {
		// Send headers first
		if (!headers_sent()) {
			$package	= self::PACKAGE;
			$component	= ($component === $package) ? $component : "$package-$component";
			header("Package: $package");
			header(self::componentHeader() . ": $component");
			if ($contentType !== '') header("Content-type: $contentType");
		}

		// Send content
		echo $content;
	}

	private static /*.string.*/ function getDivId(/*.string.*/ $action) {
		return ($action === self::ACTION_MAIN) ? self::DIVID_MAIN : self::DIVID_ACCOUNT;
	}

	private static /*.string.*/ function htmlInputText() {
		return <<<HTML
					class		=	"ezUser-text"
					onkeypress	=	"ezUser_keyPress(event)"
					size		=	"40"
HTML;
	}

	private static /*.string.*/ function htmlButtonEvents() {
		return <<<HTML
					onclick		=	"ezUser_click(this)"
					onmouseover	=	"ezUser_setButtonState(this, 1, true)"
					onmouseout	=	"ezUser_setButtonState(this, 1, false)"
					onfocus		=	"ezUser_setButtonState(this, 2, true)"
					onblur		=	"ezUser_setButtonState(this, 2, false)"
HTML;
	}

	private static /*.string.*/ function htmlButton(/*.string.*/ $type, $verbose = false) {
		$classVerbose = ($verbose) ? " ezUser-preference-verbose" : "";

		return <<<HTML
					type		=	"button"
					class		=	"ezUser-button ezUser-$type$classVerbose ezUser-buttonstate-0"
HTML;
	}

	private static /*.string.*/ function htmlMessage($message = '', $fail = false, $instance = '') {
		if ($message === '') {
			$messageClass = '';
		} else {
			$messageClass = ($fail) ? " ezUser-message-fail" : " ezUser-message-info";
		}

		$id = "ezUser" . $instance . "-message";

		return <<<HTML
				<div id="$id" class="ezUser-message$messageClass" onclick="ezUser_click(this)">$message</div>
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
			case self::RESULT_NOEMAIL:		return "You need to provide an email address";
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

	public static /*.void.*/ function getStatusText(/*.int.*/ $status) {
		$text = self::statusText($status);
		self::sendContent($text, 'statusText');

	}

	public static /*.void.*/ function getResultText(/*.int.*/ $result) {
		$text = self::resultText($result);
		self::sendContent($text, 'resultText');
	}

	private static /*.string.*/ function htmlResultForm (/*.int.*/ $result) {
		$htmlButtonPreference	= self::htmlButton("preference");
		$htmlButtonEvents	= self::htmlButtonEvents();
		$message		= self::resultText($result);

		return <<<HTML
		<form onsubmit="return false">
			<fieldset class="ezUser-fieldset">
				<div id="ezUser-message"		onclick="ezUser_click(this)" class="ezUser-message ezUser-message-info">$message</div>
				<input id="ezUser-OK" value="OK"
					tabindex	=	"1"
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
		die;
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
		// 			be asking the user to re-enter certain values: the form might
		// 			therefore need to be populated with the attempted registration
		// 			details.

		$htmlButtonAction	= self::htmlButton('action');
		$htmlButtonEvents	= self::htmlButtonEvents();
		$htmlInputText		= self::htmlInputText();
		$instance		= '-account';
		$ezUser			=& /*.(ezUser).*/ $_SESSION[self::PACKAGE];
		$result			= $ezUser->result();

		if ($mode === 'result') $mode = ($result === parent::RESULT_VALIDATED) ? 'display' : 'edit';

		// Specific stuff for new user mode
		if ($mode === 'new') {
			$newFlag	= true;
			$htmlMessage	= self::htmlMessage('', false, $instance);

			$buttonID	= 'validate';
			$buttonText	= 'Register';

			$email		= '';
			$firstName	= '';
			$lastName	= '';
			$username	= '';
			$password	= '';
		} else {
			if (!isset($newFlag)) $newFlag = false;

			if ($result > parent::RESULT_VALIDATED) {
				// Show result information
				$htmlMessage = self::resultText($result);
				$htmlMessage = self::htmlMessage($htmlMessage, true, $instance);
			} else {
				// Show status information
				$status		 = $ezUser->status();
				$htmlMessage = ($status === parent::STATUS_CONFIRMED) ? '' : self::statusText($status);
				$htmlMessage = self::htmlMessage($htmlMessage, false, $instance);
			}

			$email		= $ezUser->email();
			$firstName	= $ezUser->firstName();
			$lastName	= $ezUser->lastName();
			$username	= $ezUser->username();
			$password	= ($ezUser->passwordHash() === hash(parent::HASH_FUNCTION, '')) ? '' : '************';
		}

		// Specific stuff for display mode
		if ($mode === 'display') {
			$buttonID	= 'edit';
			$buttonText	= 'Edit';
			$disabled	= ' disabled = "disabled"';
			$newValue	= 'false';
		} else {
			$disabled	= '';
			$newValue	= ($newFlag) ? 'true' : 'false';
		}

		// Specific stuff for edit mode
		if ($mode === 'edit') {
			$buttonID	= 'validate';
			$buttonText	= 'OK';

			$htmlCancelButton = <<<HTML
				<input id="ezUser-account-cancel" value="Cancel"
					tabindex	=	"8"
$htmlButtonAction
$htmlButtonEvents
				/>
HTML;
		} else {
			$htmlCancelButton = '';
		}

		// At this point we have finished with the result of any prior validation
		// so we can clear the result field
		$ezUser->setResult(parent::RESULT_UNDEFINED);

		return <<<HTML
		<form class="ezUser-form" onsubmit="return false">
			<fieldset class="ezUser-fieldset">
				<input id= "ezUser-account-email"
					tabindex	=	"1"
					value		=	"$email"
					type		=	"text"
$disabled
$htmlInputText
				/>
				<label class="ezUser-label" for="ezUser-account-email">* Email address:</label>
				<input id= "ezUser-account-firstName"
					tabindex	=	"2"
					value		=	"$firstName"
					type		=	"text"
$disabled
$htmlInputText
				/>
				<label class="ezUser-label" for="ezUser-account-firstName">First name:</label>
				<input id= "ezUser-account-lastName"
					tabindex	=	"3"
					value		=	"$lastName"
					type		=	"text"
$disabled
$htmlInputText
				/>
				<label class="ezUser-label" for="ezUser-account-lastName">Last name:</label>
				<input id= "ezUser-account-username"
					tabindex	=	"4"
					value		=	"$username"
					type		=	"text"
$disabled
$htmlInputText
				/>
				<label class="ezUser-label" for="ezUser-account-username">* Username:</label>
				<input id= "ezUser-account-password"
					tabindex	=	"5"
					value		=	"$password"
					type		=	"password"
$disabled
$htmlInputText
				/>
				<label class="ezUser-label" for="ezUser-account-password">* Password:</label>
				<input id= "ezUser-account-confirm"
					tabindex	=	"6"
					value		=	"$password"
					type		=	"password"
$disabled
$htmlInputText
				/>
				<label class="ezUser-label" for="ezUser-account-confirm">* Confirm password:</label>
			</fieldset>
			<fieldset class="ezUser-fieldset">
* = mandatory field
				<input id="ezUser-account-$buttonID" value="$buttonText"
					tabindex	=	"7"
$htmlButtonAction
$htmlButtonEvents
				/>
$htmlCancelButton
			</fieldset>
			<fieldset class="ezUser-fieldset">
$htmlMessage
				<input id="ezUser-account-new" type="hidden" value="$newValue" />
			</fieldset>
		</form>
HTML;
	}

	public static /*.void.*/ function getAccountForm(/*.string.*/ $mode, $newFlag = false) {
		$html = self::htmlAccountForm($mode, $newFlag);
		self::sendContent($html, 'account');
	}

// ---------------------------------------------------------------------------
	private static /*.string.*/ function htmlDashboard() {
		$htmlButtonPreference	= self::htmlButton("preference");
		$htmlButtonEvents	= self::htmlButtonEvents();
		$htmlMessage		= self::htmlMessage();
		$ezUser			=& /*.(ezUser).*/ $_SESSION[self::PACKAGE];
		$fullName		= $ezUser->fullName();

		return <<<HTML
		<form class="ezUser-form" onsubmit="return false">
			<fieldset class="ezUser-fieldset">
				<input id="ezUser-signOut" value="Sign out"
					tabindex	=	"2"
$htmlButtonPreference
$htmlButtonEvents
				/>
				<input id="ezUser-goaccount" value="My account"
					tabindex	=	"1"
$htmlButtonPreference
$htmlButtonEvents
				/>
				<div id="ezUser-fullName"
					class="ezUser-fullName">$fullName</div>
			</fieldset>
			<fieldset class="ezUser-fieldset">
$htmlMessage
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

		$htmlButtonAction	= self::htmlButton('action');
		$htmlButtonPreference	= self::htmlButton('preference');
		$htmlButtonEvents	= self::htmlButtonEvents();
		$htmlInputText		= self::htmlInputText();
		$ezUser 		=& /*.(ezUser).*/ $_SESSION[self::PACKAGE];
		$result			= $ezUser->result();

		if ($result <= parent::RESULT_SUCCESS) {
			$username = ($username === '') ? $ezUser->username() : $username;
			$message = self::htmlMessage();
			$verbose = "";
		} else {
			$username = $ezUser->username();
			$message = self::htmlMessage("Check username & password", true);

			if ($ezUser_verbose) {
				$verbose = self::htmlButton("preference", true);
				$verbose = <<<HTML
				<input id="ezUser-verbose" value="$result"
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
		<form class="ezUser-form" onsubmit="return false">
			<fieldset class="ezUser-fieldset">
				<input id= "ezUser-username"
					tabindex	=	"1"
					value		=	"$username"
					type		=	"text"
$htmlInputText
				/>
				<label class="ezUser-label" for="ezUser-username">Username:</label>
				<input id= "ezUser-password"
					tabindex	=	"2"
					value		=	"$password"
					type		=	"password"
					onfocus		=	"ezUser_passwordFocus(this)"
$htmlInputText
				/>
				<label class="ezUser-label" for="ezUser-password">Password:</label>
$verbose
			</fieldset>
			<fieldset class="ezUser-fieldset">
$message
				<input id="ezUser-signIn" value="Sign in"
					tabindex	=	"4"
$htmlButtonAction
$htmlButtonEvents
				/>
				<input id="ezUser-goaccount" value="Register"
					tabindex	=	"3"
$htmlButtonAction
$htmlButtonEvents
				/>
			</fieldset>
			<fieldset class="ezUser-fieldset">
				<input id="ezUser-staySignedIn"	value="Stay signed in"
					tabindex	=	"7"
$htmlButtonPreference
$htmlButtonEvents
				/>
				<input id="ezUser-rememberMe" value="Remember me"
					tabindex	=	"6"
$htmlButtonPreference
$htmlButtonEvents
				/>
				<input id="ezUser-reminder" value="Reset password"
					tabindex	=	"5"
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
		$divIDMain	= self::DIVID_MAIN;
		$divIDAccount	= self::DIVID_ACCOUNT;

		$css = <<<CSS
/**
 * @package	ezUser
 * @author	Dominic Sayers <dominic_sayers@hotmail.com>
 * @copyright	2009 Dominic Sayers
 * @license	http://www.opensource.org/licenses/cpal_1.0 Common Public Attribution License Version 1.0 (CPAL) license
 * @link	http://www.dominicsayers.com
 * @version	0.9 - Code tidy and unit testing started
 */
@charset "UTF-8";

div#$divIDMain {
	font-family:"Segoe UI", Calibri, Arial, Helvetica, "sans serif";
	font-size:11px;
	line-height:100%;
	width:286px;
	float:left;
}

div#$divIDAccount {
	font-family:"Segoe UI", Calibri, Arial, Helvetica, "sans serif";
	font-size:12px;
	line-height:100%;
	width:286px;
	float:left;
}

form.ezUser-form		{margin:0;}
fieldset.ezUser-fieldset	{padding:0;border:0;clear:right;}
label.ezUser-label		{float:right;padding:6px 3px;}

input.ezUser-text {
	float:right;
	font-size:11px;
	width:160px;
	margin-bottom:4px;
}

input.ezUser-button {
	float:right;
	padding:2px;
	font-family:"Segoe UI", Calibri, Arial, Helvetica, "sans serif";
	border-style:solid;
	border-width:1px;
	cursor:pointer;
}

input.ezUser-action {
	font-size:12px;
	width:52px;
	margin:0 0 0 6px;
}

input.ezUser-preference {
	font-size:10px;
	margin:4px 0 0 6px;
}

input.ezUser-preference-verbose {float:left;margin:0;}

input.ezUser-buttonstate-0 {background-color:#FFFFFF;color:#444444;border-color:#666666 #333333 #333333 #666666;}
input.ezUser-buttonstate-1 {background-color:#FFFFFF;color:#444444;border-color:#FF9900 #CC6600 #CC6600 #FF9900;}
input.ezUser-buttonstate-2 {background-color:#FFFFFF;color:#444444;border-color:#666666 #333333 #333333 #666666;}
input.ezUser-buttonstate-3 {background-color:#FFFFFF;color:#444444;border-color:#FF9900 #CC6600 #CC6600 #FF9900;}
input.ezUser-buttonstate-4 {background-color:#CCCCCC;color:#222222;border-color:#333333 #666666 #666666 #333333;}
input.ezUser-buttonstate-5 {background-color:#CCCCCC;color:#222222;border-color:#CC6600 #FF9900 #FF9900 #CC6600;}
input.ezUser-buttonstate-6 {background-color:#CCCCCC;color:#222222;border-color:#333333 #666666 #666666 #333333;}
input.ezUser-buttonstate-7 {background-color:#CCCCCC;color:#222222;border-color:#CC6600 #FF9900 #FF9900 #CC6600;}

div.ezUser-message {
/*	width:154px; */
	float:left;
	padding:6px;
	text-align:center;
	visibility:hidden;
}

div.ezUser-message-info {
	background-color:#FFCC00;
	color:#000000;
	font-weight:normal;
	visibility:visible;
}

div.ezUser-message-fail {
	background-color:#FF0000;
	color:#FFFFFF;
	font-weight:bold;
	visibility:visible;
}

div.ezUser-fullName {
	float:right;
	margin:4px 0 0 0;
	padding:6px;
	color:#555555;
	font-weight:bold;
}

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
		$sessionName		= ini_get('session.name');
		$remoteAddress		= $_SERVER['REMOTE_ADDR'];
		$URL			= self::thisURL();

		$cookieUsername		= parent::COOKIE_USERNAME;
		$cookiePassword		= parent::COOKIE_PASSWORD;
		$cookieStaySignedIn	= parent::COOKIE_STAYSIGNEDIN;

		$divIDMain		= self::DIVID_MAIN;
		$divIDAccount		= self::DIVID_ACCOUNT;

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
		$actionEdit		= self::ACTION_ACCOUNT . '=edit';

		$accountPage		= self::accountPage();
		$accountClick		= ($accountPage === '') ? $package . "_ajax[0].execute('$actionAccountInPanel')" : "window.location = '$accountPage'";
		$componentHeader	= self::componentHeader();

		$js = <<<JAVASCRIPT
/**
 * @package	ezUser
 * @author	Dominic Sayers <dominic_sayers@hotmail.com>
 * @copyright	2009 Dominic Sayers
 * @license	http://www.opensource.org/licenses/cpal_1.0 Common Public Attribution License Version 1.0 (CPAL) license
 * @link	http://www.dominicsayers.com
 * @version	0.9 - Code tidy and unit testing started
 */
/*global window, document, event, ActiveXObject */ // For JSLint
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

	if (control === null) {
		return;
	}

	var	baseClass	= control.className,
		stateClass	= 'ezUser-buttonstate-',
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
	this.persist = function (name, value, days) {
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

	this.acquire = function (name) {
		name = name + '=';
		var i, c, carray = document.cookie.split(';');

		for (i = 0; i < carray.length; i += 1) {
			c = carray[i];

			while (c.charAt(0) === ' ') {
				c = c.substring(1, c.length);
			}

			if (c.indexOf(name) === 0) {
				return c.substring(name.length, c.length);
			}
		}

		return null;
	};

	this.remove = function (name) {
		this.persist(name, '', -1);
	};
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
	this.username		= '';
	this.passwordHash	= '';
	this.sessionID		= '';

	// Public methods
// ---------------------------------------------------------------------------
	this.updateCookies = function () {
		this.username = document.getElementById('ezUser-username').value;

		var passwordHash = (this.passwordHash === '') ? ezUser_SHA256plusIP(document.getElementById('ezUser-password').value) : this.passwordHash;
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
	this.showMessage = function (message, fail) {
		var div = document.getElementById('ezUser-message');

		if (div === null) {
			return;
		}

		switch (arguments.length) {
		case 0:
			if (div.innerHTML === '') {
				return; // Nothing to do so do it quickly
			}

			message = '';
			fail = false;
			break;
		case 1:
			fail = false;
			break;
		}

		if (message === '') {
			div.className = 'ezUser-message';
		} else {
			div.className = (fail) ? 'ezUser-message ezUser-message-fail' : 'ezUser-message ezUser-message-info';
		}

		div.innerHTML = message;

		div = document.getElementById('ezUser-verbose');

		if (div !== null) {
			div.parentNode.removeChild(div);
		}
	};

// ---------------------------------------------------------------------------
	this.showPreferences = function () {
		ezUser_setButtonState(document.getElementById('ezUser-rememberMe'), 4, this.rememberMe);
		ezUser_setButtonState(document.getElementById('ezUser-staySignedIn'), 4, this.staySignedIn);
	};

// ---------------------------------------------------------------------------
	this.setFocus = function (containerID) {
		var id, textBox;

		switch (containerID) {
		case '$divIDMain':
			id = 'ezUser-username';
			break;
		case '$divIDAccount':
			id = 'ezUser-account-email';
			break;
		}

		textBox = document.getElementById(id);

		if (textBox !== null) {
			if (textBox.disabled !== 'disabled') {
				textBox.focus();
				textBox.select();
			}
		}
	};

// ---------------------------------------------------------------------------
	this.replaceHTML = function (id, html) {
		var newElement, originalElement;

		originalElement = document.getElementById(id);

		if (originalElement === null) {
			return;
		}

		if (typeof(originalElement) === 'undefined') {
			return;
		}

		newElement = originalElement.cloneNode(false);
		newElement.innerHTML = html;
		originalElement.parentNode.replaceChild(newElement, originalElement);
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
			try {
				return new ActiveXObject('MSXML3.XMLHTTP');
			} catch (errMSXML3) {}

			try {
				return new ActiveXObject('MSXML2.XMLHTTP.3.0');
			} catch (errMSXML2) {}

			try {
				return new ActiveXObject('Msxml2.XMLHTTP');
			} catch (errMsxml2) {}

			try {
				return new ActiveXObject('Microsoft.XMLHTTP');
			} catch (errMicrosoft) {}

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

				if (id === null) {
					id = '$divIDMain';
				}

				ezUser.replaceHTML(id, ajax.responseText);
				ezUser.setFocus(id);

				if (id === '$divIDMain') {
					ezUser.showPreferences();
				}
			} else {
				var fail = true;
				var message = 'Server error, please try later';
				ezUser.showMessage(message, fail);

				var cancelButton = document.getElementById('ezUser-cancel');

				if (cancelButton !== null) {
					cancelButton.id		= 'ezUser-signIn';
					cancelButton.value	= 'Sign in';
				}
			}

			// Unit testing callback function
			if (typeof ajaxUnit === 'function') {
				ajaxUnit(ajax);
			}
		}
	}

// ---------------------------------------------------------------------------
	function serverTalk(URL, requestType, requestData) {
		ajax.open(requestType, URL);
		ajax.onreadystatechange = handleServerResponse;

		if (requestType === 'POST') {
			ajax.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
		}

		ajax.send(requestData);
	}

// ---------------------------------------------------------------------------
	// Public methods
	this.execute = function (thisAction) {
		var passwordHash,
			requestData = '',
			requestType,
			URL = '$URL';

		var equalPos = thisAction.indexOf('=');
		action = (equalPos === -1) ? thisAction : thisAction.slice(0, equalPos);

		switch (action) {
		case '$actionSignIn':
			ezUser.showMessage('Signing in - please wait');
			document.getElementById('ezUser-signIn').id = 'ezUser-cancel';
			document.getElementById('ezUser-cancel').value = 'Cancel';
			ezUser.updateCookies();

			passwordHash	= (ezUser.passwordHash === '') ? ezUser_SHA256plusIP(document.getElementById('ezUser-password').value) : ezUser.passwordHash;
			passwordHash	= SHA256(ezUser.sessionID + passwordHash);
			requestData	= '$action='		+ action
					+ '&$cookieUsername='	+ document.getElementById('ezUser-username').value
					+ '&$cookiePassword='	+ passwordHash;
			requestType	= 'POST';

			break;
		case '$actionValidate':
			passwordHash	= SHA256(document.getElementById('ezUser-account-password').value);
			requestData	= '$action='		+ action
					+ '&new='		+ document.getElementById('ezUser-account-new').value
					+ '&email='		+ document.getElementById('ezUser-account-email').value
					+ '&firstname='	+ document.getElementById('ezUser-account-firstName').value
					+ '&lastname='	+ document.getElementById('ezUser-account-lastName').value
					+ '&$cookieUsername='	+ document.getElementById('ezUser-account-username').value
					+ '&$cookiePassword='	+ passwordHash;
			requestType	= 'POST';

			break;
		case '$actionMain':
			URL += '?' + action;

			if (ezUser.rememberMe === true) {
				URL += '=' + ezUser.username;
			}

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
		default:
			URL += '?' + thisAction;
			requestType = 'GET';
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

for (i=0; i<elementCount; i++) {
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
	case 'ezUser-signIn':
		ezUser_ajax[0].execute('$actionSignIn');
		break;
	case 'ezUser-signOut':
		ezUser_ajax[0].execute('$actionSignOut');
		break;
	case 'ezUser-goaccount':
		$accountClick;
		break;
	case 'ezUser-cancel':
		ezUser_ajax[0].execute('$actionCancel');
		break;
	case 'ezUser-rememberMe':
		ezUser.rememberMe	= (ezUser.rememberMe) ? false : true;
		ezUser.staySignedIn	= (ezUser.rememberMe) ? ezUser.staySignedIn : false;

		ezUser.showPreferences();
		ezUser.updateCookies();
		break;
	case 'ezUser-staySignedIn':
		ezUser.staySignedIn	= (ezUser.staySignedIn) ? false : true;
		ezUser.rememberMe	= (ezUser.staySignedIn) ? true : ezUser.rememberMe;

		ezUser.showPreferences();
		ezUser.updateCookies();
		break;
	case 'ezUser-verbose':
		ezUser_ajax[0].execute('$actionResultForm=' + control.value);
		break;
	case 'ezUser-OK':
		ezUser_ajax[0].execute('$actionMain');
		break;
	case 'ezUser-account-validate':
		ezUser_ajax[0].execute('$actionValidate');
		break;
	case 'ezUser-account-edit':
		ezUser_ajax[0].execute('$actionEdit');
		break;
	}
}

// ---------------------------------------------------------------------------
// 		ezUser_keyPress
// ---------------------------------------------------------------------------
// Responds to key presses on the ezUser form
// ---------------------------------------------------------------------------
function ezUser_keyPress(e) {
	var characterCode;

	if (e && e.which) {
		e = e;
		characterCode = e.which;
	} else {
		if (typeof(event) !== 'undefined') {
			e = event;
		}

		characterCode = e.keyCode;
	}

	if (characterCode === 13) {
		var id = e.target.parentNode.parentNode.parentNode.id;

		switch (id) {
		case '$divIDMain':
			ezUser_click(document.getElementById('ezUser-signIn'));
			break;
		case '$divIDAccount':
			var control = document.getElementById('ezUser-account-validate');

			if (control === null) {
				control = document.getElementById('ezUser-account-edit');
			}

			ezUser_click(control);
			break;
		}
	} else {
		ezUser.showMessage(); // Hide message
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
	ezUser_ajax[ezUser_ajax.push(new C_ezUser_AJAX()) - 1].execute(action);
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
		$actionJavascript	= self::ACTION_JAVASCRIPT;
		$divId			= self::getDivId($action);
		$URL			= self::thisURL();
		$jsVariable		= $package . '_ajax';
		$js			= $jsVariable . '[' . $jsVariable . '.push(new C_' . $package . "_AJAX()) - 1].execute('$action')";

		return <<<HTML
	<div id="$divId"></div>
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
}
// End of class ezUserUI
//$ - code for release package is inserted here

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
	if (!isset($_SESSION) || !is_array($_SESSION) || !is_object($_SESSION[ezUserUI::PACKAGE])) session_start();
}

if (!isset($_SESSION[ezUserUI::PACKAGE])) {
	$_SESSION[ezUserUI::PACKAGE] = new ezUser();
}

$ezUser =& /*.(ezUser).*/ $_SESSION[ezUserUI::PACKAGE];

// Is this script included in another page or is it the HTTP target itself?
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
	// This script has been called directly by the client

	// Attempt auto-signin?
	if (!$ezUser->authenticated()) {
		if (isset($_COOKIE[ezUsers::COOKIE_STAYSIGNEDIN]) && ($_COOKIE[ezUsers::COOKIE_STAYSIGNEDIN] === 'true')) {
			$user[ezUsers::COOKIE_USERNAME] = (string) $_COOKIE[ezUsers::COOKIE_USERNAME];
			$user[ezUsers::COOKIE_PASSWORD] = hash(ezUser::HASH_FUNCTION, (string) $_COOKIE[ini_get('session.name')] . (string) $_COOKIE[ezUsers::COOKIE_PASSWORD]);
			$ezUser = ezUsers::doSignIn($user);
		}
	}

	// First, deal with anything in $_GET
	if (is_array($_GET) && (count($_GET) > 0)) {
		if (isset($_GET[ezUserUI::ACTION_CONTAINER]))		ezUserUI::getContainer	  ((string)	$_GET[ezUserUI::ACTION_CONTAINER]);
		if (isset($_GET[ezUserUI::ACTION_MAIN]))		ezUserUI::getControlPanel ((string)	$_GET[ezUserUI::ACTION_MAIN]);
		if (isset($_GET[ezUserUI::ACTION_ACCOUNT]))		ezUserUI::getAccountForm  ((string)	$_GET[ezUserUI::ACTION_ACCOUNT]);
		if (isset($_GET[ezUserUI::ACTION_PANELACCOUNT]))	ezUserUI::getAccountForm  ((string)	$_GET[ezUserUI::ACTION_PANELACCOUNT]); // To do
		if (isset($_GET[ezUserUI::ACTION_STATUSTEXT]))		ezUserUI::getStatusText	  ((int)	$_GET[ezUserUI::ACTION_STATUSTEXT]);
		if (isset($_GET[ezUserUI::ACTION_RESULTTEXT]))		ezUserUI::getResultText	  ((int)	$_GET[ezUserUI::ACTION_RESULTTEXT]);
		if (isset($_GET[ezUserUI::ACTION_RESULTFORM]))		ezUserUI::getResultForm	  ((int)	$_GET[ezUserUI::ACTION_RESULTFORM]);
		if (isset($_GET[ezUserUI::ACTION_JAVASCRIPT]))		ezUserUI::getJavascript();
		if (isset($_GET[ezUserUI::ACTION_CSS]))			ezUserUI::getCSS();
		if (isset($_GET[ezUserUI::ACTION_ABOUT]))		ezUserUI::getAbout();
		if (isset($_GET[ezUserUI::ACTION_SOURCECODE]))		ezUserUI::getSourceCode();
		if (isset($_GET[ezUserUI::ACTION_SIGNOUT])) {
			$ezUser->signOut();
			ezUserUI::getControlPanel();
		}
	} else {
		// Now let's check $_POST
		if (is_array($_POST) && (count($_POST) > 0)) {
			if (isset($_POST[ezUserUI::ACTION])) {
				switch ((string) $_POST[ezUserUI::ACTION]) {
					case ezUserUI::ACTION_SIGNIN:
						$ezUser = ezUsers::doSignIn($_POST);
						ezUserUI::getControlPanel();
						break;
					case ezUserUI::ACTION_VALIDATE:
						ezUsers::validate($_POST, $ezUser);
						ezUserUI::getAccountForm('result',(bool) $_POST['new']);
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