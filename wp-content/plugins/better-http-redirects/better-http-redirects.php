<?php
/**
 * Better HTTP Redirects
 *
 * Copyright 2010, 2011 by hakre <hakre.wordpress.com>, some rights reserved.
 *
 * Wordpress Plugin Header:
 *
 *   Plugin Name:    Better HTTP Redirects
 *   Plugin URI:     http://hakre.wordpress.com/plugins/better-http-redirects/
 *   Description:    Better HTTP Redirects makes your Blog's redirects to play more nicely the HTTP standards. Dual Mode Plugin, can be used as a standard Plugin or as a Must-Use Plugin.
 *   Version:        1.2.2
 *   Stable tag:     1.2.2
 *   Min WP Version: 2.9
 *   Author:         hakre
 *   Author URI:     http://hakre.wordpress.com/
 *   Donate link:    http://www.prisonradio.org/donate.htm
 *   Tags:           HTTP, redirect
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

return betterRedirectsPlugin::bootstrap();

class betterRedirectsPlugin {
	/** @var string queued hypertext message */
	private $message;
	/** @var bool perform a debug redirect */
	private $debug;
	/** @var betterRedirectsPlugin */
	static $__instance;
	final public static function bootstrap() {
		if (null==betterRedirectsPlugin::$__instance)
			betterRedirectsPlugin::$__instance = new betterRedirectsPlugin();
		return betterRedirectsPlugin::$__instance;
	}
	private function __construct() {
		function_exists('add_filter')
			&& add_filter('wp_redirect', array($this, 'wp_redirect'), 10, 2)
			&& add_filter('redirect_canonical', array($this, 'redirect_canonical'), 10, 2);
		$this->debug = WP_DEBUG;
	}
	/**
	 * redirect_canonical hook
	 *
	 * this hook prevents canonical redirects if only the case of
	 * letters is mixed.
	 *
	 * @-wp-hook redirect_canonical
	 * @-wp-ticket #13413
	 * @param int $status
	 * @param string $location
	 */
	public function redirect_canonical($redirect_url, $requested_url) {
		if (strtolower($redirect_url) === strtolower($requested_url))
			return false;
		return $redirect_url;
	}
	/**
	 * wp_redirect hook
	 *
	 * @-wp-hook wp_redirect
	 * @param string $location
	 * @param int $status
	 * @return string location
	 */
	public function wp_redirect($location, $status) {
		// give warning if a redirect is violating the RFC, see #14062
		if (! parse_url($location2 = wp_sanitize_redirect($location), PHP_URL_SCHEME)) {
			trigger_error(sprintf('Redirect location "%s" does not look like an absolute URL as requested by RFC 2616; 14.30 Location.', $location2), E_USER_WARNING);
		}
		$location && (3 === (int)($status/100)) 
			&& $this->responseBody($status, $location)
			;
		// do not automatically redirect on debug mode
		if ($this->debug) {
			$location = wp_sanitize_redirect($location);
			$status = (int) $status;
			header("X-Redir-Location: $location", true, $status);
			header("X-Redir-Status: $status", true, $status);
			$location = false; // make wordpress not sending any location header, so to prevent automatic redirects
		}
		return $location;
	}
	/**
	 * Provide Hypertext for the response body that
	 * will be output if the script finishes.
	 */
	public function responseBody($status, $location) {
		$status = (int) $status;
		$hypertext = $this->_hypertext($location, $status, $_SERVER['REQUEST_METHOD']);
		$hypertext = apply_filters('redirect_hypertext', $hypertext, $location, $status);
		$this->message = $hypertext;
	}
	/**
	 * format an argument
	 *
	 * @param mixed $arg
	 * @return string
	 */
	private function textArg($arg) {
		if (is_array($arg)) {
			$new = array();
			foreach($arg as $key => $value) {
				$dkey = $this->textArg($key);
				$value = $this->textArg($value);
				$new[] = "$dkey => $value";
			}
			return 'array ('.implode(', ', $new).')';
		}
		if (is_object($arg)) {
			return 'class '.get_class($arg);
		}
		if (is_null($arg)) {
			return 'NULL';
		}
		if (is_numeric($arg)) {
			return $arg;
		}
		if (is_string($arg)) {
			return "'$arg'";
		}
		if (is_bool($arg)) {
			return $arg ? 'true' : 'false';
		}
		throw new InvalidArgumentException(sprintf('Argument was not processed: %s', print_r($arg, true)));
	}
	/**
	 * print redirect backtrace
	 *
	 * (inspired by xedbug backtrace)
	 */
	private function printBacktrace() {
		$backtrace = array_reverse(debug_backtrace(false));
		array_splice($backtrace, -4);
		
		foreach($backtrace as $entry) if ('wp_redirect' === $entry['function']) break
			;
		
		echo '<table cellspacing="0" cellpadding="1" border="1" dir="ltr" class="redirect-backtrace">';
		echo '<tr><th align="left" bgcolor="#f57900" colspan="3"><span style="background-color: rgb(204, 0, 0); color: rgb(252, 233, 79); font-size: x-large;">( i )</span> Wordpress Redirect in '.$entry['file'].' on line <i>'.$entry['line'].'</i> </th></tr>';
		echo '<tr><th align="left" bgcolor="#e9b96e" colspan="3">Call Stack</th></tr>';
		echo '<tr bgcolor="#eeeeec" align="left"><th align="center">#</th><th>Function</th><th>Location</th></tr>';
		$i = 0;
		foreach($backtrace as $entry) {
			$args = implode(', ', array_map(array($this, 'textArg'), $entry['args']));
			$function = htmlspecialchars($entry['function']).'( '.htmlspecialchars($args).' )';
			isset($entry['type']) && $function = htmlspecialchars($entry['type']).$function;
			isset($entry['class']) && $function = htmlspecialchars($entry['class']).$function;
			$location = 'none';
			isset($entry['file']) && $location = '<span title="'.htmlspecialchars($entry['file']).'" style="white-space:nowrap;">../'.htmlspecialchars(basename(dirname($entry['file'])).'/'.basename($entry['file'])).'</span>';
			isset($entry['line']) && $location .= ':'.$entry['line'];
			echo '<tr bgcolor="#eeeeec">';
			echo '<td>', ++$i, '</td>';
			echo '<td>', $function, '</td>';
			echo '<td>', $location, '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
	/**
	 * generate a default hypertext message
	 *
	 * handles for non-HEAD requests with the following status:
	 *
	 *  301 Moved Permanently
	 *  302 Found
	 *  303 See Other
	 *  307 Temporary Redirect
	 *
	 * @param string  $location  redirect location
	 * @param int     $status    http status code
	 * @return string hypertext fragment for status code (empty string if there is none)
	 */
	private function _hypertext($location, $status, $method) {
		if ('HEAD' == $method) return '';
		$map      = array(301 => 0, 302 => 0, 303 => 1, 307 => 1);
		if (!isset($map[$status])) return '';
		$desc     = get_status_header_desc( $status );
		$version  = $GLOBALS['wp_version'];
		list($link, $title, $description, $charset) = array_map('get_bloginfo', array('wpurl', 'name', 'description', 'charset'));
		$homepage = sprintf('%s - %s on %s', esc_html( $title ), esc_html( $description ), esc_html( $link ) );
		$self     = array_map('esc_html', get_file_data(__FILE__, array('name' => 'Plugin Name', 'plugver' => 'Version')));
		$extras   = vsprintf('(with %s %s)', $self);
		$messages = array('The document has moved', 'The answer to your request is located');
		$message  = $messages[$map[$status]];
		
		$backtrace = '';
		if ($this->debug) {
			ob_start();
			$location2 = wp_sanitize_redirect($location);
			echo "\n<HR>\n";
			echo '<table>';
			echo '<table cellspacing="0" cellpadding="1" border="1" dir="ltr" class="redirect-location">';
			echo '<tr><th align="left" bgcolor="#f57900" colspan="3"><span style="background-color: rgb(204, 0, 0); color: rgb(252, 233, 79); font-size: x-large;">( i )</span> Wordpress Redirect Location </th></tr>';
			echo '<tr bgcolor="#eeeeec"><th align="left">Location (bare):</th><td><a href="', htmlspecialchars($location), '">', htmlspecialchars($location), '</a></td></tr>';
			echo '<tr bgcolor="#eeeeec"><th align="left">Location (sanitized):</th><td><a href="', htmlspecialchars($location2), '">', htmlspecialchars($location2), '</a></td></tr>';
			echo '</table>';
			echo "\n<HR>\n";
			$this->printBacktrace();
			if (function_exists('xdebug_print_function_stack'))
				echo xdebug_print_function_stack('Redirect Stack'), '<br>';
			$backtrace = ob_get_clean();
		}
		
		
		ob_start();
### Template Begin ###
?>
<HTML><HEAD><meta http-equiv="content-type" content="text/html;charset=<?php echo $charset; ?>">
<TITLE><?php echo $status; ?> <?php echo esc_html($desc); ?></TITLE></HEAD><BODY>
<H1><?php echo $status; ?> <?php echo esc_html($desc); ?></H1>
<?php echo esc_html($message); ?>

<A HREF="<?php echo esc_attr( $location ); ?>">here</A>.
<?php echo $backtrace; ?><HR>
<address>WordPress/<?php echo esc_html( $version ); ?> <?php echo $extras ?> at <?php echo $homepage; ?> .</address>
</BODY></HTML>
<?php
### Template End ###
		return ob_get_clean();
	}
	/**
	 * on shutdown, output the hypertext if available.
	 */
	public function __destruct() {
		echo $this->message;
	}
}

# EOF;