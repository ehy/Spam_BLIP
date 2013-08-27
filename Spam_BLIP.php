<?php
/*
Plugin Name: Spam_BLIP
Plugin URI: http://agalena.nfshost.com/b1/?page_id=<CHANGE_ME>
Description: Spam_BLIP plugin for WordPress
Version: 1.0.0
Author: Ed Hynan
Author URI: http://agalena.nfshost.com/b1/
License: GNU GPLv3 (see http://www.gnu.org/licenses/gpl-3.0.html)
Text Domain: spambl_l10n
*/

/*
 *      Spam_BLIP.php
 *      
 *      Copyright 2013 Ed Hynan <edhynan@gmail.com>
 *      
 *      This program is free software; you can redistribute it and/or modify
 *      it under the terms of the GNU General Public License as published by
 *      the Free Software Foundation; specifically version 3 of the License.
 *      
 *      This program is distributed in the hope that it will be useful,
 *      but WITHOUT ANY WARRANTY; without even the implied warranty of
 *      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *      GNU General Public License for more details.
 *      
 *      You should have received a copy of the GNU General Public License
 *      along with this program; if not, write to the Free Software
 *      Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 *      MA 02110-1301, USA.|g
 */


/* text editor: use real tabs of 4 column width, LF line ends */
/* human coder: keep line length <= 72 columns; break at params */


/**********************************************************************\
 *  requirements                                                      *
\**********************************************************************/


// supporting classes found in files named "${cl}.inc.php"
// each class must define static method id_token() which returns
// the correct int, to help avoid name clashes
if ( ! function_exists( 'Spam_BLIP_plugin_paranoid_require_class' ) ) :
function Spam_BLIP_plugin_paranoid_require_class ($cl, $rfunc = 'require_once') {
	$id = 0xED00AA33;
	$meth = 'id_token';
	if ( ! class_exists($cl) ) {
		$d = plugin_dir_path(__FILE__).'/'.$cl.'.inc.php';
		switch ( $rfunc ) {
			case 'require_once':
				require_once $d;
				break;
			case 'require':
				require $d;
				break;
			case 'include_once':
				include_once $d;
				break;
			case 'include':
				include $d;
				break;
			default:
				$s = '' . $rfunc;
				$s = sprintf('%s: what is %s?', __FUNCTION__, $s);
				wp_die($s);
				break;
		}
	}
	if ( method_exists($cl, $meth) ) {
		$t = call_user_func(array($cl, $meth));
		if ( $t !== $id ) {
			wp_die('class name conflict: ' . $cl . ' !== ' . $id);
		}
	} else {
		wp_die('class name conflict: ' . $cl);
	}
}
endif;

// these support classes are in separate files as they are
// not specific to this plugin, and may be used in others
Spam_BLIP_plugin_paranoid_require_class('OptField_0_0_2a');
Spam_BLIP_plugin_paranoid_require_class('OptSection_0_0_2a');
Spam_BLIP_plugin_paranoid_require_class('OptPage_0_0_2a');
Spam_BLIP_plugin_paranoid_require_class('Options_0_0_2a');
Spam_BLIP_plugin_paranoid_require_class('ChkBL_0_0_1');

/**********************************************************************\
 *  missing functions that must be visible for definitions            *
\**********************************************************************/

/**
 * Only until PHP 5.2 compat is abandoned:
 * a non-class method that can be aliased (by string)
 * to a $var; 5.2 *cannot* call class methods, static or
 * not, through any alias
 */
if ( ! function_exists( 'Spam_BLIP_plugin_php52_htmlent' ) ) :
function Spam_BLIP_plugin_php52_htmlent ($text, $cset = null)
{
	// try to use get_option('blog_charset') only once;
	// it's not cheap enough even with WP's cache for
	// the number of times this might be called
	global $Spam_BLIP_blog_charset;
	if ( ! isset($Spam_BLIP_blog_charset) ) {
		$Spam_BLIP_blog_charset = get_option('blog_charset');
		if ( ! $Spam_BLIP_blog_charset ) {
			$Spam_BLIP_blog_charset = 'UTF-8';
		}
	}

	if ( $cset === null ) {
		$cset = $Spam_BLIP_blog_charset;
	}

	return htmlentities($text, ENT_QUOTES, $cset);
}
endif;


/**********************************************************************\
 *  Class defs: main plugin. widget, and support classes              *
\**********************************************************************/


/**
 * class providing flash video for WP pages
 */
if ( ! class_exists('Spam_BLIP_class') ) :
class Spam_BLIP_class {
	// the widget class name
	const Spam_BLIP_plugin_widget = 'Spam_BLIP_widget_class';
	
	// identifier for settings page
	const settings_page_id = 'Spam_BLIP_plugin1_settings_page';
	
	// option group name in the WP opt db
	const opt_group  = '_evh_Spam_BLIP_plugin1_opt_grp';
	// WP option names/keys
	// verbose (helpful?) section descriptions?
	const optverbose = 'verbose';
	// filter comments_open?
	const optcommflt = 'commflt';
	// filter pings_open?
	const optpingflt = 'pingflt';
	// keep rbl hit data?
	const optrecdata = 'recdata';
	// use rbl hit data?
	const optusedata = 'usedata';
	// optplugwdg -- use plugin's widget
	const optplugwdg = 'widget'; // plugin widget
	// log (and possibly mail notice) resv. IPs in REMOTE_ADDR?
	const optipnglog = 'ip_ng';
	// log blacklist hits?
	const optbliplog = 'log_hit';
	// bail out (wp_die()) on blacklist hits?
	const optbailout = 'bailout';
	// delete options on uninstall
	const optdelopts = 'delopts';
	
	// option group name for the plugin data store
	const data_group  = '_evh_Spam_BLIP_plugin1_data_grp';

	// verbose (helpful?) section descriptions?
	const defverbose = 'true';
	// filter comments_open?
	const defcommflt = 'true';
	// filter pingss_open?
	const defpingflt = 'true';
	// keep rbl hit data?
	const defrecdata = 'true';
	// use rbl hit data?
	const defusedata = 'true';
	// optplugwdg -- use plugin's widget
	const defplugwdg = 'false';  // plugin widget
	// log (and possibly mail notice) resv. IPs in REMOTE_ADDR?
	const defipnglog = 'true';
	// log blacklist hits?
	const defbliplog = 'false';
	// bail out (wp_die()) on blacklist hits?
	const defbailout = 'false';
	// delete options on uninstall
	const defdelopts = 'true';
	
	// autoload class version suffix
	const aclv = '0_0_2a';

	// object of class to handle options under WordPress
	protected $opt = null;
	
	// An instance of the blacklist check class ChkBL_0_0_1
	protected $chkbl = null;

	// An instance of the bad IP check class IPReservedCheck_0_0_1
	protected $ipchk = null;
	protected $ipchk_done = false;

	// array of rbl lookup result is put here for reference
	// across callback methods
	protected $rbl_result;

	// Spam_BLIP_plugin program css name
	protected static $Spam_BLIP_cssname = 'Spam_BLIP.css';
	// Spam_BLIP_plugin program css path
	protected $Spam_BLIP_css;

	// Spam_BLIP_plugin js subdirectory
	protected static $Spam_BLIP_jsdir = 'js';
	// Spam_BLIP_plugin js shortcode editor helper name
	protected static $Spam_BLIP_jsname = 'Spam_BLIP.js';
	// Spam_BLIP_plugin program js path
	protected $Spam_BLIP_js;
	
	// hold an instance
	private static $instance;

	// int, incr while wrapping WP do_shortcode(), decr when done
	private $in_wdg_do_shortcode;

	// this instance is fully initialized? (__construct($init == true))
	private $full_init;

	// correct file path (possibly needed due to symlinks)
	public static $plugindir  = null;
	public static $pluginfile = null;

	public function __construct($init = true) {
		// if arg $init is false then this instance is just
		// meant to provide options and such
		$pf = self::mk_pluginfile();
		// URL setup
		$t = self::$plugindir . '/' . self::$Spam_BLIP_cssname;
		$this->Spam_BLIP_css = plugins_url($t, $pf);
		$t = self::$plugindir . '/' . self::$Spam_BLIP_jsdir
			. '/' . self::$Spam_BLIP_jsname;
		$this->Spam_BLIP_js = plugins_url($t, $pf);
		
		$this->rbl_result = false;
		$this->ipchk = new IPReservedCheck_0_0_1();

		if ( ($this->full_init = $init) !== true ) {
			// must do this
			$this->init_opts();
			return;
		}
		
		// keep it clean: {de,}activation
		$cl = __CLASS__;
		register_deactivation_hook($pf, array($cl, 'on_deactivate'));
		register_activation_hook($pf,   array($cl,   'on_activate'));
		register_uninstall_hook($pf,    array($cl,  'on_uninstall'));

		// some things are to be done in init hook: add
		// hooks for shortcode and widget, and optionally
		// posts processing to scan attachments, etc...
		add_action('init', array($this, 'init_hook_func'));

		// add 'Settings' link on the plugins page entry
		// cannot be in activate hook
		$name = plugin_basename($pf);
		add_filter("plugin_action_links_$name",
			array($cl, 'plugin_page_addlink'));
		add_action('admin_print_scripts',
			array($cl, 'filter_admin_print_scripts'));

		// it's not enough to add this action in the activation hook;
		// that alone does not work.  IAC administrative
		// {de,}activate also controls the widget
		add_action('widgets_init', array($cl, 'regi_widget'));//, 1);
	}

	public function __destruct() {
		$this->opt = null;
	}
	
	// get array of defaults for the plugin options; if '$chkonly'
	// is true include only those options associated with a checkbox
	// on the settings page -- useful for the validate function
	protected static function get_opts_defaults($chkonly = false) {
		$items = array(
			self::optverbose => self::defverbose,
			self::optcommflt => self::defcommflt,
			self::optpingflt => self::defpingflt,
			self::optrecdata => self::defrecdata,
			self::optusedata => self::defusedata,
			self::optplugwdg => self::defplugwdg,
			self::optipnglog => self::defipnglog,
			self::optbliplog => self::defbliplog,
			self::optbailout => self::defbailout,
			self::optdelopts => self::defdelopts,
		);
		
		if ( $chkonly !== true ) {
			// TODO: so far there are only checkboxes
		}
		
		return $items;
	}
	
	// initialize plugin options from defaults or WPDB
	protected function init_opts() {
		$items = self::get_opts_defaults();
		$opts = self::get_opt_group();
		// note values converted to string
		if ( $opts ) {
			$mod = false;
			foreach ($items as $k => $v) {
				if ( ! array_key_exists($k, $opts) ) {
					$opts[$k] = '' . $v;
					$mod = true;
				}
				if ( $opts[$k] == '' && $v !== '' ) {
					$opts[$k] = '' . $v;
					$mod = true;
				}
			}
			if ( $mod === true ) {
				update_option(self::opt_group, $opts);
			}
		} else {
			$opts = array();
			foreach ($items as $k => $v) {
				$opts[$k] = '' . $v;
			}
			add_option(self::opt_group, $opts);
		}
		return $opts;
	}

	// initialize options/settings page, only if $this->full_init==true
	// ($this->full_init set and checked in ctor)
	protected function init_settings_page() {
		if ( ! $this->opt ) {
			$items = $this->init_opts();
			
			// use Opt* classes for page, sections, and fields
			
			// mk_aclv adds a suffix to class names
			$Cf = self::mk_aclv('OptField');
			$Cs = self::mk_aclv('OptSection');
			// prepare fields to appear under various sections
			// of admin page
			$ns = 0;
			$sections = array();

			// General options section
			$nf = 0;
			$fields = array();
			$fields[$nf++] = new $Cf(self::optverbose,
					self::wt(__('Show verbose descriptions:', 'spambl_l10n')),
					self::optverbose,
					$items[self::optverbose],
					array($this, 'put_verbose_opt'));
			$fields[$nf++] = new $Cf(self::optcommflt,
					self::wt(__('Blacklist check for comments:', 'spambl_l10n')),
					self::optcommflt,
					$items[self::optcommflt],
					array($this, 'put_comments_opt'));
			$fields[$nf++] = new $Cf(self::optpingflt,
					self::wt(__('Blacklist check for pings:', 'spambl_l10n')),
					self::optpingflt,
					$items[self::optpingflt],
					array($this, 'put_pings_opt'));

			// section object includes description callback
			$sections[$ns++] = new $Cs($fields,
					'Spam_BLIP_plugin1_general_section',
					'<a name="general">' .
						self::wt(__('General Options', 'spambl_l10n'))
						. '</a>',
					array($this, 'put_general_desc'));

			// data section:
			$nf = 0;
			$fields = array();
			$fields[$nf++] = new $Cf(self::optrecdata,
					self::wt(__('Keep Data:', 'spambl_l10n')),
					self::optrecdata,
					$items[self::optrecdata],
					array($this, 'put_recdata_opt'));
			$fields[$nf++] = new $Cf(self::optusedata,
					self::wt(__('Use Data:', 'spambl_l10n')),
					self::optusedata,
					$items[self::optusedata],
					array($this, 'put_usedata_opt'));

			// misc
			$sections[$ns++] = new $Cs($fields,
					'Spam_BLIP_plugin1_datasto_section',
					'<a name="data_store">' .
						self::wt(__('Data Store Options', 'spambl_l10n'))
						. '</a>',
					array($this, 'put_datastore_desc'));
			
			// options for widget areas
			$nf = 0;
			$fields = array();
			$fields[$nf++] = new $Cf(self::optplugwdg,
					self::wt(__('Use the included widget:', 'spambl_l10n')),
					self::optplugwdg,
					$items[self::optplugwdg],
					array($this, 'put_widget_opt'));
			$fields[$nf++] = new $Cf(self::optipnglog,
					self::wt(__('Log bad IP addresses:', 'spambl_l10n')),
					self::optipnglog,
					$items[self::optipnglog],
					array($this, 'put_iplog_opt'));
			$fields[$nf++] = new $Cf(self::optbliplog,
					self::wt(__('Log blacklisted IP addresses:', 'spambl_l10n')),
					self::optbliplog,
					$items[self::optbliplog],
					array($this, 'put_bliplog_opt'));
			$fields[$nf++] = new $Cf(self::optbailout,
					self::wt(__('Bail out on blacklisted IP:', 'spambl_l10n')),
					self::optbailout,
					$items[self::optbailout],
					array($this, 'put_bailout_opt'));

			// misc
			$sections[$ns++] = new $Cs($fields,
					'Spam_BLIP_plugin1_misc_section',
					'<a name="misc_sect">' .
						self::wt(__('Miscellaneous Options', 'spambl_l10n'))
						. '</a>',
					array($this, 'put_misc_desc'));
			
			// install opts section:
			// field: delete opts on uninstall?
			$nf = 0;
			$fields = array();
			$fields[$nf++] = new $Cf(self::optdelopts,
					self::wt(__('When the plugin is uninstalled:', 'spambl_l10n')),
					self::optdelopts,
					$items[self::optdelopts],
					array($this, 'put_del_opts'));

			// prepare sections to appear under admin page
			$sections[$ns++] = new $Cs($fields,
					'Spam_BLIP_plugin1_inst_section',
					'<a name="install">' .
						self::wt(__('Plugin Install Settings', 'spambl_l10n'))
						. '</a>',
					array($this, 'put_inst_desc'));

			// prepare admin page specific hooks per page. e.g.:
			// (now set to false, but code remains for reference;
			// see comment '// hook&filter to make shortcode form for editor'
			// in __construct())
			if ( false ) {
				$suffix_hooks = array(
					'admin_head' => array($this, 'admin_head'),
					'admin_print_scripts' => array($this, 'admin_js'),
					'load' => array($this, 'admin_load')
					);
			} else {
				$suffix_hooks = '';
			}
			
			// prepare admin page
			// Note that validator applies to all options,
			// necessitating a big switch on option keys
			$Cp = self::mk_aclv('OptPage');
			$page = new $Cp(self::opt_group, $sections,
				self::settings_page_id,
				self::wt(__('Spam_BLIP Plugin', 'spambl_l10n')),
				self::wt(__('Spam_BLIP Configuration', 'spambl_l10n')),
				array(__CLASS__, 'validate_opts'),
				/* pagetype = 'options' */ '',
				/* capability = 'manage_options' */ '',
				array($this, 'setting_page_output_callback')/* callback '' */,
				/* 'hook_suffix' callback array */ $suffix_hooks,
				self::wt(__('Configuration of Spam_BLIP Plugin', 'spambl_l10n')),
				self::wt(__('Display and Runtime Settings.', 'spambl_l10n')),
				self::wt(__('Save Settings', 'spambl_l10n')));
			
			$Co = self::mk_aclv('Options');
			$this->opt = new $Co($page);
		}
	}
	
	// This function is placed here below the function that sets-up
	// the options page so that it is easy to see from that function.
	// It exists only for the echo "<a name='aSubmit'/>\n";
	// line which mindbogglingly cannot be printed from
	// Options::admin_page() -- it is somehow *always* stripped out!
	// After hours I cannot figure this out; but, having added this
	// function as the page callback, I can add the anchor after
	// calling $this->opt->admin_page() (which is Options::admin_page())
	// BUT it still does not show in the page if the echo is moved
	// into Options::admin_page() and placed just before return!
	// Baffled.
	public function setting_page_output_callback() {
		$r = $this->opt->admin_page();
		echo "<a name='aSubmit'/>\n";
		return $r;
	}

	/**
	 * General hook/filter callbacks
	 */
	
	// register shortcode editor forms javascript
	public static function filter_admin_print_scripts() {
		// cap check: not sure if this is necessary here,
		// hope it doesn't cause failures for legit users
	    if ( false && $GLOBALS['editing'] && current_user_can('edit_posts') ) {
			$jsfn = 'Spam_BLIP_plugin_java_object';
			$pf = self::mk_pluginfile();
			$t = self::$swfjsdir . '/' . self::$swfxedjsname;
			$jsfile = plugins_url($t, $pf);
	        wp_enqueue_script($jsfn, $jsfile, array('jquery'), 'xed');
	    }
	}

	// deactivate cleanup
	public static function on_deactivate() {
		$wreg = __CLASS__;
		$name = plugin_basename(self::mk_pluginfile());
		$arf = array($wreg, 'plugin_page_addlink');
		remove_filter("plugin_action_links_$name", $arf);

		self::unregi_widget();

		unregister_setting(self::opt_group, // option group
			self::opt_group, // opt name; using group passes all to cb
			array($wreg, 'validate_opts'));
	}

	// activate setup
	public static function on_activate() {
		$wreg = __CLASS__;
		add_action('widgets_init', array($wreg, 'regi_widget'), 1);
	}

	// uninstall cleanup
	public static function on_uninstall() {
		self::unregi_widget();
		
		$opts = self::get_opt_group();
		if ( $opts && $opts[self::optdelopts] != 'false' ) {
			delete_option(self::opt_group);
		}
	}

	// add link at plugins page entry for the settings page
	public static function plugin_page_addlink($links) {
		$opturl = '<a href="' . get_option('siteurl');
		$opturl .= '/wp-admin/options-general.php?page=';
		$opturl .= self::settings_page_id;
		$opturl .= '">' . __('Settings', 'spambl_l10n') . '</a>';
		// Add a link to this plugin's settings page
		array_unshift($links, $opturl); 
		return $links; 
	}

	// register the Spam_BLIP_plugin widget
	public static function regi_widget ($fargs = array()) {
		global $wp_widget_factory;
		if ( ! isset($wp_widget_factory) ) {
			return;
		}
		if ( function_exists('register_widget') ) {
			$cl = self::Spam_BLIP_plugin_widget;
			register_widget($cl);
		}
	}

	// unregister the Spam_BLIP_plugin widget
	public static function unregi_widget () {
		global $wp_widget_factory;
		if ( ! isset($wp_widget_factory) ) {
			return;
		}
		if ( function_exists('unregister_widget') ) {
			$cl = self::Spam_BLIP_plugin_widget;
			unregister_widget($cl);
		}
	}

	// to be done at WP init stage
	public function init_hook_func () {
		self::load_translations();

		// Settings/Options page setup
		$this->init_settings_page();

		$scf = array($this, 'action_pre_comment_on_post');
		add_action('pre_comment_on_post', $scf, 1);

		$scf = array($this, 'action_comment_closed');
		add_action('comment_closed', $scf, 1);

		$scf = array($this, 'filter_comments_open');
		add_filter('comments_open', $scf, 1);

		$scf = array($this, 'filter_pings_open');
		add_filter('pings_open', $scf, 1);
	}

	public static function load_translations () {
		// The several load*() calls here are inspired by this:
		//   http://geertdedeckere.be/article/loading-wordpress-language-files-the-right-way
		// So, provide for custom *.mo installed in either
		// WP_LANG_DIR or WP_PLUGIN_DIR/languages or WP_PLUGIN_DIR,
		// and do translations in the plugin directory last.
		
		// The globals are a hack: want to keep this static,
		// yet test whether .mo load call has been done
		global $spambl_load_WP_textdomain_done;
		global $spambl_load_plugin_langdir_textdomain_done;
		global $spambl_load_plugin_dir_textdomain_done;
		global $spambl_load_plugin_textdomain_done;

		$dom = 'spambl_l10n';

		if ( ! isset($spambl_load_WP_textdomain_done)
			&& defined(WP_LANG_DIR) ) {
			$loc = apply_filters('plugin_locale', get_locale(), $dom);
			// this file path is built in the manner shown at the
			// URL above -- it does look strange
			$t = sprintf('%s/%s/%s-%s.mo', WP_LANG_DIR, $dom, $dom, $loc);
			$spambl_load_WP_textdomain_done = load_textdomain($dom, $t);
		}
		if ( ! isset($spambl_load_plugin_langdir_textdomain_done) ) {
			$t = 'languages/';
			$spambl_load_plugin_langdir_textdomain_done =
				load_plugin_textdomain($dom, false, $t);
		}
		if ( ! isset($spambl_load_plugin_dir_textdomain_done) ) {
			$spambl_load_plugin_dir_textdomain_done =
				load_plugin_textdomain($dom, false, false);
		}
		if ( ! isset($spambl_load_plugin_textdomain_done) ) {
			$t = basename(trim(self::mk_plugindir(), '/')) . '/locale/';
			$spambl_load_plugin_textdomain_done =
				load_plugin_textdomain($dom, false, $t);
		}
	}

	/**
	 * Settings page callback functions:
	 * validators, sections, fields, and page
	 */

	/**
	 * Utility and misc. helper procs
	 */
	
	// get other end IP address
	public static function get_conn_addr() {
		$addr = $_SERVER['REMOTE_ADDR'];
		if ( $addr && (count(explode('.', $addr)) == 4) ) {
			return $addr;
		}
		return false;
	}

	// append version suffix for Options classes names
	protected static function mk_aclv($pfx) {
		$s = $pfx . '_' . self::aclv;
		return $s;
	}
	
	// help for plugin file path/name; __FILE__ alone
	// is not good enough -- see comment in body
	public static function mk_plugindir() {
		if ( self::$plugindir !== null ) {
			return self::$plugindir;
		}
	
		$pf = __FILE__;
		// using WP_PLUGIN_DIR due to symlink problems in
		// some installations; after much grief found fix at
		// https://wordpress.org/support/topic/register_activation_hook-does-not-work
		// in a post by member 'silviapfeiffer1' -- she nailed
		// it, and noone even replied to her!
		if ( defined('WP_PLUGIN_DIR') ) {
			$ad = explode('/', rtrim(plugin_dir_path($pf), '/'));
			$pd = $ad[count($ad) - 1];
			$pf = WP_PLUGIN_DIR . '/' . $pd;
		} else {
			// this is similar to common methods w/  __FILE__; but
			// can cause regi* failures due to symlinks in path
			$pf = rtrim(plugin_dir_path($pf), '/');
		}
		
		// store and return corrected file path
		return self::$plugindir = $pf;
	}
	
	// See comment above
	public static function mk_pluginfile() {
		if ( self::$pluginfile !== null ) {
			return self::$pluginfile;
		}
	
		$pf = self::mk_plugindir();
		$ff = basename(__FILE__);
		
		// store and return corrected file path
		return self::$pluginfile = $pf . '/' . $ff;
	}

	// escape symbol for use in jQuery selector or similar; see
	//     http://api.jquery.com/category/selectors/
	public static function esc_jqsel($sym, $include_dash = false) {
		$chr = '!"#$%&\'()*+,.\/:;<=>?@\[\]\^`{|}~';
		if ( $include_dash === true )
			$chr .= '-';
		$pat = '/([' . $chr . '])/';
		$rep = '\\\\\\\$1';
		return preg_replace($pat, $rep, $sym);
	}

	// hex encode a text string
	public static function et($text) {
		return rawurlencode($text);
	}
	
	// 'html-ize' a text string
	public static function ht($text, $cset = null) {
		// try to use get_option('blog_charset') only once;
		// it's not cheap enough even with WP's cache for
		// the number of times this might be called
		global $Spam_BLIP_blog_charset;
		if ( ! isset($Spam_BLIP_blog_charset) ) {
			$Spam_BLIP_blog_charset = get_option('blog_charset');
			if ( ! $Spam_BLIP_blog_charset ) {
				$Spam_BLIP_blog_charset = 'UTF-8';
			}
		}
	
		if ( $cset === null ) {
			$cset = $Spam_BLIP_blog_charset;
		}

		return htmlentities($text, ENT_QUOTES, $cset);
	}
	
	// 'html-ize' a text string; with WordPress char translations
	public static function wt($text) {
		if ( function_exists('wptexturize') ) {
			return wptexturize($text);
		}
		return self::ht($text);
	}
	
	// error messages; where {wp_}die is not suitable
	public static function errlog($err) {
		$e = sprintf('Spam_BLIP WP plugin: %s', $err);
		error_log($e, 0);
	}
	
	// helper to make self
	public static function instantiate($init = true) {
		$cl = __CLASS__;
		self::$instance = new $cl($init);
		return self::$instance;
	}

	// get microtime() if possible, else just time()
	public static function best_time() {
		if ( function_exists('microtime') ) {
			return microtime(true); // PHP 4 better be dead
		}
		return time();
	}

	// optional additional response to unexpectd REMOTE_ADDR;
	// after errlog()
	protected function handle_REMOTE_ADDR_error($msg) {
		// TODO: make option; send email
	}
	/**
	 * encode a path for a URL, e.g. from parse_url['path']
	 * leaving '/' un-encoded
	 * $func might also be urlencode(), or user defined
	 * inheritable
	 */
	public static function upathencode($p, $func = 'rawurlencode') {
		return implode('/',
			array_map($func,
				explode('/', $p) ) );
	}

	/**
	 * Settings page callback functions:
	 * validators, sections, fields, and page
	 */

	// static callback: validate options main
	public static function validate_opts($opts) {	
		$a_out = array();
		$a_orig = self::get_opt_group();
		$nerr = 0;
		$nupd = 0;

		// empty happens if all fields are checkboxes and none checked
		if ( empty($opts) ) {
			$opts = array();
		}
		// checkboxes need value set - nonexistant means false
		$ta = self::get_opts_defaults();
		foreach ( $ta as $k => $v ) {
			if ( array_key_exists($k, $opts) ) {
				continue;
			}
			$opts[$k] = 'false';
		}
	
		foreach ( $opts as $k => $v ) {
			$ot = trim($v);
			$oo = trim($a_orig[$k]);

			switch ( $k ) {
				case self::optverbose:
				case self::optcommflt:
				case self::optpingflt:
				case self::optrecdata:
				case self::optusedata:
				case self::optplugwdg:
				case self::optipnglog:
				case self::optbliplog:
				case self::optbailout:
				case self::optdelopts:
					if ( $ot != 'true' && $ot != 'false' ) {
						$e = sprintf('bad option: %s[%s]', $k, $v);
						self::errlog($e);
						add_settings_error('Spam_BLIP checkbox option',
							sprintf('%s[%s]', self::opt_group, $k),
							self::wt($e),
							'error');
						$a_out[$k] = $oo;
						$nerr++;
					} else {
						$a_out[$k] = $ot;
						$nupd += ($oo === $ot) ? 0 : 1;
					}
					break;
				default:
					$e = "funny key in validate opts: '" . $k . "'";
					self::errlog($e);
					add_settings_error('internal error, WP broken?',
						sprintf('%s[%s]', self::opt_group, ''),
						self::wt($e),
						'error');
					$nerr++;
			}
		}

		// now register updates
		if ( $nupd > 0 ) {
			$str = $nerr == 0 ? __('Settings updated successfully', 'spambl_l10n') :
				sprintf(__('Some settings (%d) updated', 'spambl_l10n'), $nupd);
			add_settings_error(self::opt_group, self::opt_group,
				self::wt($str), 'updated');
		}
		
		return $a_out;
	}

	/**
	 * Options section callbacks
	 */
	
	// callback: put html for placement field description
	public function put_general_desc() {
		$t = self::wt(__('General Spam_BLIP plugin options:', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");
		if ( self::get_verbose_option() !== 'true' ) {
			return;
		}

		$t = self::wt(__('The verbose option selects whether
			verbose descriptions
			should be displayed with the various settings
			sections. The long descriptions, of which 
			this paragraph is an example,
			will not be shown if the option is not
			selected.', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");

		$t = self::wt(__('The "Check blacklist for comments" option 
			enables the main functionality of the plugin. When
			<em>WordPress</em> code checks whether comments are open
			or closed, this plugin will check the connecting IP
			address against a DNS-based blacklists of weblog
			comment spammers, and if it is found, will tell
			<em>WordPress</em> that comments are
			closed.', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");

		$t = self::wt(__('The "Check blacklist for pings" option 
			is similar to "Check blacklist for comments",
			but for pings.', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");

		$t = self::wt(__('Go forward to save button.', 'spambl_l10n'));
		printf('<p><a href="#aSubmit">%s</a></p>%s', $t, "\n");
	}

	// callback: store and use data section
	public function put_datastore_desc() {
		$t = self::wt(__('Enable/disable data store:', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");
		if ( self::get_verbose_option() !== 'true' ) {
			return;
		}

		$t = self::wt(__('These options enable or disable
			the storage of blacklist lookup results in the
			<em>WordPress</em> database, or the use of the
			stored data to before DNS lookup.
			</p><p>
			The "Store" option enables recording of <em>hit</em> data
			such as the connecting IP address, and the DNS
			blacklist domain that listed the address.
			(This data is also used if included widget is
			enabled.)
			</p><p>
			The "Use" option enables a check in any available
			stored data; if a hit is found there then the
			DNS lookup is not performed.', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");
		$t = self::wt(__('Go forward to save button.', 'spambl_l10n'));
		printf('<p><a href="#aSubmit">%s</a></p>%s', $t, "\n");
		$t = self::wt(__('Go back to top (General section).', 'spambl_l10n'));
		printf('<p><a href="#general">%s</a></p>%s', $t, "\n");
	}

	// callback: put html for placement field description
	public function put_misc_desc() {
		$t = self::wt(__('Miscellaneous options:', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");
		if ( self::get_verbose_option() !== 'true' ) {
			return;
		}

		$t = self::wt(__('The "included widget" option selects 
			whether the multi-widget included with the plugin is
			enabled. The widget will display some of the
			stored data, if that is enabled. You should consider
			whether you want that data on public display, but
			if you find that acceptable, the widget should give
			a convenient view of the effectiveness of the plugin.
			</p><p>
			The "Log bad IP" option selects log messages when
			the remote IP address provided in the CGI/1.1
			environment variable "REMOTE_ADDR" is wrong. Software
			used in a hosting arrangement can cause this, even
			while the connection ultimately works. (Although
			this condition is, hopefully, rare, this option was
			added because the author has encountered it.) This
			plugin checks whether the connecting address is in
			a reserved, loopback, or other special purpose
			network range. If it is, the DNS blacklist check
			is not performed, as it would be pointless, and a
			message is issued to the error log if this option
			is set.
			For a site on the "real" Internet, there is probably
			no reason to turn this logging off. In fact, if
			such log messages are seen, the hosting administrator
			or technical contact should be notified (and their response
			should be that a fix is on the way).
			This option should be off when developing a site on
			a private network or single machine, because in this
			case error log messages are not needed. The plugin
			will still check the address and skip the blacklist
			DNS lookup if the address is reserved.
			</p><p>
			The "Log blacklisted IP addresses" option selects logging
			of blacklist hits with the remote IP address. This
			is only informative, and will add unneeded lines
			in the error log. New plugin users might like to
			enable this temporarily to see the effect the plugin
			has had.
			</p><p>
			The "Bail out on blacklisted" option will have the
			plugin terminate the blog output (with "wp_die()")
			when the connecting IP address is blacklisted. The
			default is to only disable comments, and allow the
			page to be produced normally.
			', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");
		$t = self::wt(__('Go forward to save button.', 'spambl_l10n'));
		printf('<p><a href="#aSubmit">%s</a></p>%s', $t, "\n");
		$t = self::wt(__('Go back to top (General section).', 'spambl_l10n'));
		printf('<p><a href="#general">%s</a></p>%s', $t, "\n");
	}

	// callback: put html install field description
	public function put_inst_desc() {
		$t = self::wt(__('Install options:', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");
		if ( self::get_verbose_option() !== 'true' ) {
			return;
		}

		$t = self::wt(__('This section includes optional
			features for plugin install or uninstall. Presently
			the only option is whether to remove the plugin\'s
			set of options from the database when
			the plugin is deleted.
			There is probably no reason to leave the options in
			place if you intend to delete the plugin permanently;
			you may simply deactivate the plugin if
			you want it off temporarily.
			If you intend to delete and then reinstall the plugin,
			possibly for a new version or update, then keeping the
			options might be helpful.', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");
		$t = self::wt(__('Go forward to save button.', 'spambl_l10n'));
		printf('<p><a href="#aSubmit">%s</a></p>%s', $t, "\n");
		$t = self::wt(__('Go back to top (General section).', 'spambl_l10n'));
		printf('<p><a href="#general">%s</a></p>%s', $t, "\n");
	}
	
	/**
	 * Options page fields callbacks
	 */
	
	// callback helper, put single checkbox
	public function put_single_checkbox($a, $opt, $label) {
		$group = self::opt_group;
		$c = $a[$opt] == 'true' ? "checked='CHECKED' " : "";

		//echo "\n		<!-- {$opt} checkbox-->\n";

		echo "		<label><input type='checkbox' id='{$opt}' ";
		echo "name='{$group}[{$opt}]' value='true' {$c}/> ";
		echo "{$label}</label><br />\n";
	}

	// callback, put verbose section descriptions?
	public function put_verbose_opt($a) {
		$tt = self::wt(__('Show verbose descriptions', 'spambl_l10n'));
		$k = self::optverbose;
		$this->put_single_checkbox($a, $k, $tt);
	}

	// callback, rbl filter comments?
	public function put_comments_opt($a) {
		$tt = self::wt(__('Check blacklist for comments', 'spambl_l10n'));
		$k = self::optcommflt;
		$this->put_single_checkbox($a, $k, $tt);
	}

	// callback, rbl filter pings?
	public function put_pings_opt($a) {
		$tt = self::wt(__('Check blacklist for pings', 'spambl_l10n'));
		$k = self::optpingflt;
		$this->put_single_checkbox($a, $k, $tt);
	}

	// callback, rbl data store?
	public function put_recdata_opt($a) {
		$tt = self::wt(__('Store blacklist lookup results', 'spambl_l10n'));
		$k = self::optrecdata;
		$this->put_single_checkbox($a, $k, $tt);
	}

	// callback, use data store?
	public function put_usedata_opt($a) {
		$tt = self::wt(__('Use stored blacklist lookup results', 'spambl_l10n'));
		$k = self::optusedata;
		$this->put_single_checkbox($a, $k, $tt);
	}

	// callback, use plugin's widget?
	public function put_widget_opt($a) {
		$tt = self::wt(__('Enable the included widget', 'spambl_l10n'));
		$k = self::optplugwdg;
		$this->put_single_checkbox($a, $k, $tt);
	}

	// callback, log non-routable remate addrs?
	public function put_iplog_opt($a) {
		$tt = self::wt(__('Log bad addresses in "REMOTE_ADDR"', 'spambl_l10n'));
		$k = self::optipnglog;
		$this->put_single_checkbox($a, $k, $tt);
	}

	// callback, log blacklist hits?
	public function put_bliplog_opt($a) {
		$tt = self::wt(__('Log blacklist hits', 'spambl_l10n'));
		$k = self::optbliplog;
		$this->put_single_checkbox($a, $k, $tt);
	}

	// callback, die blacklist hits?
	public function put_bailout_opt($a) {
		$tt = self::wt(__('Bail (wp_die()) on blacklist hits', 'spambl_l10n'));
		$k = self::optbailout;
		$this->put_single_checkbox($a, $k, $tt);
	}

	// callback, install section field: opt delete
	public function put_del_opts($a) {
		$tt = self::wt(__('Permanently delete settings (clean db)', 'spambl_l10n'));
		$k = self::optdelopts;
		$this->put_single_checkbox($a, $k, $tt);
	}

	/**
	 * WP options specific helpers
	 */

	// get the plugins main option group
	public static function get_opt_group() {
		return get_option(self::opt_group); /* WP get_option() */
	}
	
	// get an option value by name/key
	public static function opt_by_name($name) {
		$opts = self::get_opt_group();
		if ( $opts && array_key_exists($name, $opts) ) {
			return $opts[$name];
		}
		return null;
	}

	// for settings section descriptions
	public static function get_verbose_option() {
		return self::opt_by_name(self::optverbose);
	}

	// for whether to use widget
	public static function get_widget_option() {
		return self::opt_by_name(self::optplugwdg);
	}

	// for whether to log reserved remote addresses
	public static function get_ip_log_option() {
		return self::opt_by_name(self::optipnglog);
	}

	// for whether to log BL hits
	public static function get_hitlog_option() {
		return self::opt_by_name(self::optbliplog);
	}

	// for whether to die on BL hits
	public static function get_bailout_option() {
		return self::opt_by_name(self::optbailout);
	}

	// for whether to store hit data
	public static function get_recdata_option() {
		return self::opt_by_name(self::optrecdata);
	}

	// for whether to use stored data
	public static function get_usedata_option() {
		return self::opt_by_name(self::optusedata);
	}

	// should the filter_comments_open() rbl check be done
	public static function get_comments_open_option() {
		return self::opt_by_name(self::optcommflt);
	}

	// should the filter_pings_open() rbl check be done
	public static function get_pings_open_option() {
		return self::opt_by_name(self::optpingflt);
	}

	/**
	 * core functionality
	 */

	public function bl_check_addr($addr) {
		if ( $this->chkbl === null ) {
			// TODO: add strict options
			if ( false ) {
				$this->chkbl =
					new ChkBL_0_0_1(ChkBL_0_0_1::get_strict_array());
			} else {
				$this->chkbl = new ChkBL_0_0_1();
			}
		}
		
		if ( ! $this->chkbl ) {
			self::errlog(__('cannot allocate BL check object', 'spambl_l10n'));
			return false;
		}
		
		$ret = false;
		// TODO: add options
		if ( true ) {
			$this->rbl_result = $this->chkbl->check_all($addr, 1);
			if ( ! empty($this->rbl_result) ) {
				$ret = $this->rbl_result[0][2];
			} else {
				// place false in empty array
				$this->rbl_result[] = false;
			}
		} else {
			// in ctor $rbl_result is assigned false, so if
			// other code finds it false, this code has not
			// been reached
			$this->rbl_result = array();
			$ret = $this->chkbl->check_simple($addr);
			// DEVEL: remove
			if ( false && $addr === '192.168.1.187' ) {
				$ret = true;
			}
			// simple case: put result in [0]
			$this->rbl_result[] = $ret;
		}
		
		return $ret;
	}

	// add_action('pre_comment_on_post', $scf, 1);
	public function action_pre_comment_on_post($comment_post_ID) {
		// TODO: an action if aother tests passed
		// (wp-comments-post.php, last in if-chain)
	}

	// add_action('comment_closed', $scf, 1);
	public function action_comment_closed($comment_post_ID) {
		// this gets called if WP core 'comments_open()' is false,
		// but we only act here if our filter returned false
		// and found an rbl hit.
		if ( false ) { // not at all sure about code paths to this!
			if ( self::get_comments_open_option() != 'true' ) {
				return;
			}
			
			$r = $this->get_rbl_result();
			
			// was rbl check called? did it fail internally
			if ( $r === null ) {
				return;
			}		
			// was simple rbl check false?
			if ( $r === false ) {
				return;
			}		
			
			// TODO: make option
			$txt = __('<h1>DENIED</h1><h3>IP address %s is associated with spam</h3>', 'spambl_l10n');
			printf($txt, $_SERVER["REMOTE_ADDR"]);
		}
	}

	// helper: get previous BL result, return:
	// null if no previous result, else the
	// boolean result (true||false)
	public function get_rbl_result() {
		if ( ! is_array($this->rbl_result) ) {
			return null;
		}
		if ( is_array($this->rbl_result[0]) ) {
			return $this->rbl_result[0][2];
		}
		return $this->rbl_result[0];
	}

	// add_filter('comments_open', $scf, 1);
	// NOTE: this may/will be called many times per page
	public function filter_comments_open($open) {
		if ( self::get_comments_open_option() != 'true' ) {
			return $open;
		}		

		// was rbl check called already? if so,
		// use stored result
		$prev = $this->get_rbl_result();
		
		// if not done already
		if ( $prev === null ) {
			return $this->filter_ip4_bl_internal($open, 'comments');
		}
		
		// if already done, but not a hit
		if ( $prev === false ) {
			return $open;
		}

		// already got a hit on this IP addr		
		return false;
	}

	// add_filter('pings_open', $scf, 1);
	public function filter_pings_open($open) {
		if ( self::get_pings_open_option() != 'true' ) {
			return $open;
		}		

		// was rbl check called already? if so,
		// use stored result
		$prev = $this->get_rbl_result();
		
		// if not done already
		if ( $prev === null ) {
			return $this->filter_ip4_bl_internal($open, 'pings');
		}
		
		// if already done, but not a hit
		if ( $prev === false ) {
			return $open;
		}

		// already got a hit on this IP addr		
		return false;
	}

	// internal BL check for use by e.g., filters
	// Returns false fo a BL hit, else returns arg $def
	public function filter_ip4_bl_internal($def, $statype) {
		$addr = self::get_conn_addr();

		if ( $addr === false ) {
			self::errlog(
				sprintf(
				__('cannot get remote address; $_SERVER["REMOTE_ADDR"] has "%s"', 'spambl_l10n'),
					$_SERVER["REMOTE_ADDR"])
			);
			return $def;
		}
		
		// Check for not non-routable CGI/1.1 envar REMOTE_ADDR
		// as can actually happen with some hosting hacks.
		$ret = $this->ipchk_done ? false
			: $this->ipchk->chk_resv_addr($addr);
		$this->ipchk_done = true;
		if ( $ret !== false ) {
			if ( self::get_ip_log_option() != 'false' ) {
				$ret = $ret ? 'RESERVED' : 'LOOPBACK';
				$ret = sprintf('Got %s IPv4 address "%s" in '
					. 'php $_SERVER["REMOTE_ADDR"].', $ret, $addr);
				self::errlog($ret);
				$ret .= "\nCGI/1.1 specifies that environment variable"
					. "REMOTE_ADDR holds the\n"
					. "IP address of the remote host making the request.\n"
					. "\nThe environment variable REMOTE_ADDR is needed and"
					. "widely used, and should hold a real address\n(not"
					. "e.g., the address of a proxy used by a hosting "
					. "provider network).\n\nThe site administrator "
					. "should be contacted about this\n.";
				// TODO: email admin
				$this->handle_REMOTE_ADDR_error($ret);
			}
			// Well, can't continue; set result false
			$this->rbl_result = array(false);
			return $def;
		}

		$pretime = self::best_time();
		$ret = $this->bl_check_addr($addr);
		if ( $ret === false ) {
			return $def;
		}
		$posttime = self::best_time();
		
		// We have a hit!
		$ret = false;
		
		// TODO: record stats
		if ( self::get_recdata_option() != 'false' ) {
		}

		// optional hit logging
		if ( self::get_hitlog_option() != 'false' ) {
			if ( is_array($this->rbl_result[0]) ) {
				$doms = $this->chkbl->get_dom_array();
				$fmt = "denied address %s, list at '%s', result %s";
				$fmt = sprintf($fmt, $addr,
					$doms[$this->rbl_result[0][0]][0],
					$this->rbl_result[0][1]);
				self::errlog($fmt);
			} else {
				self::errlog("denied address " . $addr);
			}
		}		
		
		// optionally die
		if ( self::get_bailout_option() != 'false' ) {
			// Allow additional action from elsewhere, however unlikely.
			do_action('spamblip_hit_bailout', $addr);
			// TODO: make message text an option
			wp_die(__('Sorry, but no, thank you.', 'spambl_l10n'));
		}

		return $ret;
	}
} // End class Spam_BLIP_class

// global instance of plugin class
global $Spam_BLIP_plugin1_evh_instance_1;
if ( ! isset($Spam_BLIP_plugin1_evh_instance_1) ) :
	$Spam_BLIP_plugin1_evh_instance_1 = null;
endif; // global instance of plugin class

else :
	wp_die('class name conflict: Spam_BLIP_class in ' . __FILE__);
endif; // if ( ! class_exists('Spam_BLIP_class') ) :


/**
 * class for Spam_BLIP as widget
 */
if ( ! class_exists('Spam_BLIP_widget_class') ) :
class Spam_BLIP_widget_class extends WP_Widget {
	// main plugin class name
	const Spam_BLIP_plugin_plugin = 'Spam_BLIP_class';
	// an instance of the main plugun class
	protected $plinst;
	
	public function __construct() {
		global $Spam_BLIP_plugin1_evh_instance_1;
		if ( ! isset($Spam_BLIP_plugin1_evh_instance_1) ) {
			$cl = self::Spam_BLIP_plugin_plugin;
			$this->plinst = new $cl(false);
		} else {
			$this->plinst = &$Spam_BLIP_plugin1_evh_instance_1;
		}
	
		$cl = __CLASS__;
		// Label shown on widgets page
		$lb =  __('Spam_BLIP Plugin Stats', 'spambl_l10n');
		// Description shown under label shown on widgets page
		$desc = __('Display comment spam blacklist hit counts', 'spambl_l10n');
		$opts = array('classname' => $cl, 'description' => $desc);

		// control opts width affects the parameters form,
		// height is ignored.  Width 400 allows long text fields
		// (not as log as most URL's), and informative (long) labels
		//$copts = array('width' => 400, 'height' => 500);
		$copts = array();

		parent::__construct($cl, $lb, $opts, $copts);
	}

	public function widget($args, $instance) {
		$opt = $this->plinst->get_widget_option();
		if ( $opt != 'true' ) {
			return;
		}
		
		$pr = self::Spam_BLIP_plugin_params;
		$pr = new $pr();
		$pr->setnewarray($instance);

		if ( ! $pr->getvalue('width') ) {
			$pr->setvalue('width', self::defwidth);
		}
		if ( ! $pr->getvalue('height') ) {
			$pr->setvalue('height', self::defheight);
		}
		$pr->sanitize();
		$w = $pr->getvalue('width');
		$h = $pr->getvalue('height');
		$bh = $pr->getvalue('barheight');

		$cap = $this->plinst->wt($pr->getvalue('caption'));
		if ( $this->plinst->should_use_ming() ) {
			$uswf = $this->plinst->get_swf_url('widget', $w, $h);
		} else {
			$uswf = $this->plinst->get_swf_binurl($bh);
		}

		$code = 'widget-div';
		$dw = $w + 3;
		// use no class, but do use deprecated align
		$dv = '<p><div id="'.$code.'" align="center"';
		$dv .= ' style="width: '.$dw.'px">';

		extract($args);

		// note *no default* for title; allow empty title so that
		// user may place this below another widget with
		// apparent continuity (subject to filters)
		$title = apply_filters('widget_title',
			empty($instance['title']) ? '' : $instance['title'],
			$instance, $this->id_base);

		echo $before_widget;

		if ( $title ) {
			echo $before_title . $title . $after_title;
		}

		echo $dv;
		$this->plinst->put_swf_tags($uswf, $pr);
		if ( $cap ) {
			echo '</p><p><span align="center">' .$cap. '</span></p><p>';
		}
		echo '</div></p>';

		echo $after_widget;
	}

	public function update($new_instance, $old_instance) {
		$pr = self::Spam_BLIP_plugin_params;
		$pr = new $pr();
		
		if ( is_array($old_instance) ) {
			$pr->setnewarray($old_instance);
		}
		if ( is_array($new_instance) ) {
			$pr->setnewarray($new_instance);
		}
		
		$pr->sanitize();
		$i = $pr->getparams();
		if ( is_array($new_instance) ) {
			// for pesky checkboxes; not present if unchecked, but
			// present 'false' is wanted
			foreach ( $i as $k => $v ) {
				if ( ! array_key_exists($k, $new_instance) ) {
					$t = $pr->getdefault($k);
					// booleans == checkboxes
					if ( $t == 'true' || $t == 'false' ) {
						$i[$k] = 'false';
					}
				}
			}
		}

		if ( ! array_key_exists('caption', $i) ) {
			$i['caption'] = '';
		}
		if ( ! array_key_exists('title', $i) ) {
			$i['title'] = '';
		}
		if ( ! $i['width'] ) {
			$i['width'] = self::defwidth;
		}
		if ( ! $i['height'] ) {
			$i['height'] = self::defheight;
		}

		return $i;
	}

	public function form($instance) {
		$wt = 'wptexturize';  // display with char translations
		// still being 5.2 compatible; anon funcs appeared in 5.3
		//$ht = function($v) { return htmlentities($v, ENT_QUOTES, 'UTF-8'); };
		$ht = 'Spam_BLIP_plugin_php52_htmlent'; // just escape without char translations
		// NOTE on encoding: do *not* use JS::unescape()!
		// decodeURIComponent() should use the page charset (which
		// still leaves room for error; this code assumes UTF-8 presently)
		$et = 'rawurlencode'; // %XX -- for transfer

		$pr = self::Spam_BLIP_plugin_params;
		$pr = new $pr(array('width' => self::defwidth,
			'height' => self::defheight));
		$instance = wp_parse_args((array)$instance, $pr->getparams());

		$val = '';
		if ( array_key_exists('title', $instance) ) {
			$val = $wt($instance['title']);
		}
		$id = $this->get_field_id('title');
		$nm = $this->get_field_name('title');
		$tl = $wt(__('Widget title:', 'spambl_l10n'));

		// file select by ext pattern
		$mpat = $this->plinst->get_mfilter_pat();
		// files array from uploads dirs (empty if none)
		$rhu = $this->plinst->r_find_uploads($mpat['m'], true);
		$af = &$rhu['rf'];
		$au = &$rhu['wu'];
		$aa = &$rhu['at'];
		// url base for upload dirs files
		$ub = rtrim($au['baseurl'], '/') . '/';
		// directory base for upload dirs files
		$up = rtrim($au['basedir'], '/') . '/';
		$slfmt =
			'<select class="widefat" name="%s" id="%s" onchange="%s">';
		$sgfmt = '<optgroup label="%s">' . "\n";
		$sofmt = '<option value="%s">%s</option>' . "\n";
		// expect jQuery to be loaded by WP (tried $() invocation
		// but N.G. w/ MSIE. Sheesh.)
		$jsfmt = "jQuery('[id=%s]').val";
		// BAD
		//$jsfmt .= '(unescape(this.options[selectedIndex].value))';
		// better
		$jsfmt .= '(decodeURIComponent(this.options[selectedIndex].value))';
		$jsfmt .= '; return false;';

		?>

		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>"
			type="text" value="<?php echo $val; ?>" /></p>

		<?php
		$val = $wt($instance['caption']);
		$id = $this->get_field_id('caption');
		$nm = $this->get_field_name('caption');
		$tl = $wt(__('Caption:', 'spambl_l10n'));
		?>
		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>"
			type="text" value="<?php echo $val; ?>" /></p>

		<?php
		$val = $instance['url'];
		$id = $this->get_field_id('url');
		$nm = $this->get_field_name('url');
		$tl = $wt(__('Url or media library ID:', 'spambl_l10n'));
		?>
		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>"
			type="text" value="<?php echo $val; ?>" /></p>

		<?php // selects for URLs and attachment id's
		// escape url field id for jQuery selector
		$id = $this->plinst->esc_jqsel($id);
		$js = sprintf($jsfmt, $id);
		// optional print <select >
		if ( count($af) > 0 ) {
			$id = $this->get_field_id('files');
			$k = $this->get_field_name('files');
			$tl = $wt(__('Url from uploads directory:', 'spambl_l10n'));
			printf('<p><label for="%s">%s</label>' . "\n", $id, $tl);
			// <select>
			printf($slfmt . "\n", $k, $id, $js);
			// <options>
			printf($sofmt, '', $wt(__('none', 'spambl_l10n')));
			foreach ( $af as $d => $e ) {
				$hit = array();
				for ( $i = 0; $i < count($e); $i++ )
					if ( preg_match($mpat['av'], $e[$i]) )
						$hit[] = &$af[$d][$i];
				if ( empty($hit) )
					continue;
				printf($sgfmt, $ht($d));
				foreach ( $hit as $fv ) {
					$tu = rtrim($ub, '/') . '/' . $d . '/' . $fv;
					$fv = $ht($fv);
					printf($sofmt, $et($tu), $fv);
				}
				echo "</optgroup>\n";
			}
			// end select
			echo "</select></td></tr>\n";
		} // end if there are upload files
		if ( ! empty($aa) ) {
			$id = $this->get_field_id('atch');
			$k = $this->get_field_name('atch');
			$tl = $wt(__('Select ID from media library:', 'spambl_l10n'));
			printf('<p><label for="%s">%s</label>' . "\n", $id, $tl);
			// <select>
			printf($slfmt . "\n", $k, $id, $js);
			// <options>
			printf($sofmt, '', $wt(__('none', 'spambl_l10n')));
			foreach ( $aa as $fn => $fi ) {
				$m = basename($fn);
				if ( ! preg_match($mpat['av'], $m) )
					continue;
				$ts = $m . " (" . $fi . ")";
				printf($sofmt, $et($fi), $ht($ts));
			}
			// end select
			echo "</select></td></tr>\n";
		} // end if there are upload files
		?>

		<?php
		$val = $instance['playpath'];
		$id = $this->get_field_id('playpath');
		$nm = $this->get_field_name('playpath');
		$tl = $wt(__('Playpath (rtmp) or co-video (mp3):', 'spambl_l10n'));
		?>
		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>"
			type="text" value="<?php echo $val; ?>" /></p>

		<?php
		$val = $instance['iimage'];
		$id = $this->get_field_id('iimage');
		$nm = $this->get_field_name('iimage');
		$tl = $wt(__('Url of initial image file (optional):', 'spambl_l10n'));
		?>
		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>"
			type="text" value="<?php echo $val; ?>" /></p>

		<?php // selects for URLs and attachment id's
		// escape url field id for jQuery selector
		$id = $this->plinst->esc_jqsel($id);
		$js = sprintf($jsfmt, $id);
		// optional print <select >
		if ( count($af) > 0 ) {
			$id = $this->get_field_id('ifiles');
			$k = $this->get_field_name('ifiles');
			$tl = $wt(__('Load image from uploads directory:', 'spambl_l10n'));
			printf('<p><label for="%s">%s</label>' . "\n", $id, $tl);
			// <select>
			printf($slfmt . "\n", $k, $id, $js);
			// <options>
			printf($sofmt, '', $wt(__('none', 'spambl_l10n')));
			foreach ( $af as $d => $e ) {
				$hit = array();
				for ( $i = 0; $i < count($e); $i++ )
					if ( preg_match($mpat['i'], $e[$i]) )
						$hit[] = &$af[$d][$i];
				if ( empty($hit) )
					continue;
				printf($sgfmt, $ht($d));
				foreach ( $hit as $fv ) {
					$tu = rtrim($ub, '/') . '/' . $d . '/' . $fv;
					$fv = $ht($fv);
					printf($sofmt, $et($tu), $fv);
				}
				echo "</optgroup>\n";
			}
			// end select
			echo "</select></td></tr>\n";
		} // end if there are upload files
		if ( ! empty($aa) ) {
			$id = $this->get_field_id('iatch');
			$k = $this->get_field_name('iatch');
			$tl = $wt(__('Load image ID from media library:', 'spambl_l10n'));
			printf('<p><label for="%s">%s</label>' . "\n", $id, $tl);
			// <select>
			printf($slfmt . "\n", $k, $id, $js);
			// <options>
			printf($sofmt, '', $wt(__('none', 'spambl_l10n')));
			foreach ( $aa as $fn => $fi ) {
				$m = basename($fn);
				if ( ! preg_match($mpat['i'], $m) )
					continue;
				$ts = $m . " (" . $fi . ")";
				printf($sofmt, $et($fi), $ht($ts));
			}
			// end select
			echo "</select></td></tr>\n";
		} // end if there are upload files
		?>

		<?php
		$val = $instance['audio'];
		$id = $this->get_field_id('audio');
		$nm = $this->get_field_name('audio');
		$ck = $val == 'true' ? ' checked="checked"' : ''; $val = 'true';
		$tl = $wt(__('Medium is audio (e.g. *.mp3):', 'spambl_l10n'));
		?>
		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>" style="width:16%;" type="checkbox"
			value="<?php echo $val; ?>"<?php echo $ck; ?> /></p>

		<?php
		$val = $wt($instance['width']);
		$id = $this->get_field_id('width');
		$nm = $this->get_field_name('width');
		$tl = $wt(__('Width (default ', 'spambl_l10n').self::defwidth.__('):', 'spambl_l10n'));
		?>
		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>" style="width:16%;"
			type="text" value="<?php echo $val; ?>" /></p>

		<?php
		$val = $wt($instance['height']);
		$id = $this->get_field_id('height');
		$nm = $this->get_field_name('height');
		$tl = $wt(__('Height (default ', 'spambl_l10n').self::defheight.__('):', 'spambl_l10n'));
		?>
		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>" style="width:16%;"
			type="text" value="<?php echo $val; ?>" /></p>

		<?php
		$val = $instance['aspectautoadj'];
		$id = $this->get_field_id('aspectautoadj');
		$nm = $this->get_field_name('aspectautoadj');
		$ck = $val == 'true' ? ' checked="checked"' : ''; $val = 'true';
		$tl = $wt(__('Auto aspect (e.g. 360x240 to 4:3):', 'spambl_l10n'));
		?>
		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>" style="width:16%;" type="checkbox"
			value="<?php echo $val; ?>"<?php echo $ck; ?> /></p>

		<?php
		$val = $instance['displayaspect'];
		$id = $this->get_field_id('displayaspect');
		$nm = $this->get_field_name('displayaspect');
		$tl = $wt(__('Display aspect (e.g. 4:3, precludes Auto):', 'spambl_l10n'));
		?>
		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>" style="width:16%;"
			type="text" value="<?php echo $val; ?>" /></p>

		<?php
		$val = $instance['pixelaspect'];
		$id = $this->get_field_id('pixelaspect');
		$nm = $this->get_field_name('pixelaspect');
		$tl = $wt(__('Pixel aspect (e.g. 8:9, precluded by Display):', 'spambl_l10n'));
		?>
		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>" style="width:16%;"
			type="text" value="<?php echo $val; ?>" /></p>

		<?php
		$val = $wt($instance['volume']);
		$id = $this->get_field_id('volume');
		$nm = $this->get_field_name('volume');
		$tl = $wt(__('Initial volume (0-100):', 'spambl_l10n'));
		?>
		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>" style="width:16%;"
			type="text" value="<?php echo $val; ?>" /></p>

		<?php
		$val = $instance['play'];
		$id = $this->get_field_id('play');
		$nm = $this->get_field_name('play');
		$ck = $val == 'true' ? ' checked="checked"' : ''; $val = 'true';
		$tl = $wt(__('Play on load (else waits for play button):', 'spambl_l10n'));
		?>
		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>" style="width:16%;" type="checkbox"
			value="<?php echo $val; ?>"<?php echo $ck; ?> /></p>

		<?php
		$val = $instance['loop'];
		$id = $this->get_field_id('loop');
		$nm = $this->get_field_name('loop');
		$ck = $val == 'true' ? ' checked="checked"' : ''; $val = 'true';
		$tl = $wt(__('Loop play:', 'spambl_l10n'));
		?>
		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>" style="width:16%;" type="checkbox"
			value="<?php echo $val; ?>"<?php echo $ck; ?> /></p>

		<?php
		$val = $instance['hidebar'];
		$id = $this->get_field_id('hidebar');
		$nm = $this->get_field_name('hidebar');
		$ck = $val == 'true' ? ' checked="checked"' : ''; $val = 'true';
		$tl = $wt(__('Hide control bar initially:', 'spambl_l10n'));
		?>
		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>" style="width:16%;" type="checkbox"
			value="<?php echo $val; ?>"<?php echo $ck; ?> /></p>

		<?php
		$val = $instance['disablebar'];
		$id = $this->get_field_id('disablebar');
		$nm = $this->get_field_name('disablebar');
		$ck = $val == 'true' ? ' checked="checked"' : ''; $val = 'true';
		$tl = $wt(__('Hide and disable control bar:', 'spambl_l10n'));
		?>
		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>" style="width:16%;" type="checkbox"
			value="<?php echo $val; ?>"<?php echo $ck; ?> /></p>

		<?php
		$val = $instance['allowfull'];
		$id = $this->get_field_id('allowfull');
		$nm = $this->get_field_name('allowfull');
		$ck = $val == 'true' ? ' checked="checked"' : ''; $val = 'true';
		$tl = $wt(__('Allow full screen:', 'spambl_l10n'));
		?>
		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>" style="width:16%;" type="checkbox"
			value="<?php echo $val; ?>"<?php echo $ck; ?> /></p>

		<?php
		$val = $ht($instance['barheight']);
		$id = $this->get_field_id('barheight');
		$nm = $this->get_field_name('barheight');
		$tl = $wt(__('Control bar Height (20-50):', 'spambl_l10n'));
		?>
		<p><label for="<?php echo $id; ?>"><?php echo $tl; ?></label>
		<input class="widefat" id="<?php echo $id; ?>"
			name="<?php echo $nm; ?>" style="width:16%;"
			type="text" value="<?php echo $val; ?>" /></p>

		<?php
	}
} // End class Spam_BLIP_widget_class
else :
	wp_die('class name conflict: Spam_BLIP_widget_class in ' . __FILE__);
endif; // if ( ! class_exists('Spam_BLIP_widget_class') ) :


/**********************************************************************\
 *  plugin 'main()' level code                                        *
\**********************************************************************/

/**
 * 'main()' here
 */
if (!defined('WP_UNINSTALL_PLUGIN')&&$Spam_BLIP_plugin1_evh_instance_1 === null) {
	$Spam_BLIP_plugin1_evh_instance_1 = Spam_BLIP_class::instantiate();
}

// End PHP script:
?>
