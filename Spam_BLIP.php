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
function Spam_BLIP_plugin_paranoid_require_class ($cl) {
	$id = 0xED00AA33;
	$meth = 'id_token';
	if ( ! class_exists($cl) ) {
		$d = plugin_dir_path(__FILE__).'/'.$cl.'.inc.php';
		require_once $d;
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
 *  misc. functions                                                   *
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
	// for debugging: set false for release
	const DBG = true;
	
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
	// pass, or 'whitelist', TOR exit nodes?
	const opttorpass = 'torpass';
	// record non-hit DNS lookups?
	const optnonhrec = 'nonhrec';
	// check existing comments marked as spam?
	const optchkexst = 'chkexst';
	// keep rbl hit data?
	const optrecdata = 'recdata';
	// use rbl hit data?
	const optusedata = 'usedata';
	// rbl hit data ttl
	const optttldata = 'ttldata';
	// rbl maximum data records
	const optmaxdata = 'maxdata';
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
	// delete data store on uninstall
	const optdelstor = 'delstor';
	
	// table name suffix for the plugin data store
	const data_suffix  = 'Spam_BLIP_plugin1_datastore';
	// version for store table layout: simple incrementing integer
	const data_vs      = 3;
	// option name for data store version
	const data_vs_opt  = 'Spam_BLIP_plugin1_data_vers';

	// verbose (helpful?) section descriptions?
	const defverbose = 'true';
	// filter comments_open?
	const defcommflt = 'true';
	// filter pingss_open?
	const defpingflt = 'true';
	// pass, or 'whitelist', TOR exit nodes?
	const deftorpass = 'false';
	// record non-hit DNS lookups?
	const defnonhrec = 'false';
	// check existing comments marked as spam?
	const defchkexst = 'true';
	// keep rbl hit data?
	const defrecdata = 'true';
	// use rbl hit data?
	const defusedata = 'true';
	// rbl hit data ttl
	const defttldata = '86400'; // 1 day, seconds
	// rbl maximum data records
	const defmaxdata = '50';
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
	// delete data store on uninstall
	const defdelstor = 'true';
	
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
	// across callback methods; or set with result from
	// data store lookup as array(true||false)
	protected $rbl_result;
	// set array(true||false) when a data store lookup has been done
	// but not rbl
	protected $dbl_result;

	// if true do data store maintenance in shutdown hook
	protected $do_db_maintain;

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
	private static $instance = null;

	// data store table name; built with $wpdb->prefix
	private $data_table = null;

	// this instance is fully initialized? (__construct($init == true))
	private $full_init;

	// correct file path (possibly needed due to symlinks)
	public static $plugindir  = null;
	public static $pluginfile = null;

	public function __construct($init = true) {
		// admin or public invocation?
		$adm = is_admin();

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
		$this->dbl_result = false;
		$this->do_db_maintain = false;
		$this->ipchk = new IPReservedCheck_0_0_1();

		if ( ($this->full_init = $init) !== true ) {
			// must do this
			$this->init_opts();
			return;
		}
		
		$cl = __CLASS__;

		if ( $adm ) {
			// add 'Settings' link on the plugins page entry
			// cannot be in activate hook
			$name = plugin_basename($pf);
			add_filter("plugin_action_links_$name",
				array($cl, 'plugin_page_addlink'));
		}

		// some things are to be done in init hook: add
		// hooks for shortcode and widget, and optionally
		// posts processing to scan attachments, etc...
		add_action('init', array($this, 'init_hook_func'));

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
		if ( $chkonly === true ) {
			return array(
				self::optverbose => self::defverbose,
				self::optcommflt => self::defcommflt,
				self::optpingflt => self::defpingflt,
				self::opttorpass => self::deftorpass,
				self::optnonhrec => self::defnonhrec,
				self::optchkexst => self::defchkexst,
				self::optrecdata => self::defrecdata,
				self::optusedata => self::defusedata,
				self::optplugwdg => self::defplugwdg,
				self::optipnglog => self::defipnglog,
				self::optbliplog => self::defbliplog,
				self::optbailout => self::defbailout,
				self::optdelopts => self::defdelopts,
				self::optdelstor => self::defdelstor,
			);
		}
		
		return array(
			self::optverbose => self::defverbose,
			self::optcommflt => self::defcommflt,
			self::optpingflt => self::defpingflt,
			self::opttorpass => self::deftorpass,
			self::optnonhrec => self::defnonhrec,
			self::optchkexst => self::defchkexst,
			self::optrecdata => self::defrecdata,
			self::optusedata => self::defusedata,
			self::optttldata => self::defttldata,
			self::optmaxdata => self::defmaxdata,
			self::optplugwdg => self::defplugwdg,
			self::optipnglog => self::defipnglog,
			self::optbliplog => self::defbliplog,
			self::optbailout => self::defbailout,
			self::optdelopts => self::defdelopts,
			self::optdelstor => self::defdelstor,
		);
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

	// initialize options/settings page
	protected function init_settings_page() {
		if ( $this->opt ) {
			return;
		}
		$items = self::get_opt_group();

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
		$fields[$nf++] = new $Cf(self::opttorpass,
				self::wt(__('Whitelist (pass) TOR exit nodes:', 'spambl_l10n')),
				self::opttorpass,
				$items[self::opttorpass],
				array($this, 'put_torpass_opt'));
		$fields[$nf++] = new $Cf(self::optchkexst,
				self::wt(__('Check existing comment spam:', 'spambl_l10n')),
				self::optchkexst,
				$items[self::optchkexst],
				array($this, 'put_chkexst_opt'));

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
				self::wt(__('Keep data:', 'spambl_l10n')),
				self::optrecdata,
				$items[self::optrecdata],
				array($this, 'put_recdata_opt'));
		$fields[$nf++] = new $Cf(self::optusedata,
				self::wt(__('Use data:', 'spambl_l10n')),
				self::optusedata,
				$items[self::optusedata],
				array($this, 'put_usedata_opt'));
		$fields[$nf++] = new $Cf(self::optttldata,
				self::wt(__('Data records TTL:', 'spambl_l10n')),
				self::optttldata,
				$items[self::optttldata],
				array($this, 'put_ttldata_opt'));
		$fields[$nf++] = new $Cf(self::optmaxdata,
				self::wt(__('Maximum data records:', 'spambl_l10n')),
				self::optmaxdata,
				$items[self::optmaxdata],
				array($this, 'put_maxdata_opt'));
		$fields[$nf++] = new $Cf(self::optnonhrec,
				self::wt(__('Store (and use) non-hit addresses:', 'spambl_l10n')),
				self::optnonhrec,
				$items[self::optnonhrec],
				array($this, 'put_nonhrec_opt'));

		// data store usage
		$sections[$ns++] = new $Cs($fields,
				'Spam_BLIP_plugin1_datasto_section',
				'<a name="data_store">' .
					self::wt(__('Data Store Options', 'spambl_l10n'))
					. '</a>',
				array($this, 'put_datastore_desc'));
		
		// options for miscellaneous items
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
				self::wt(__('Delete setup options on uninstall:', 'spambl_l10n')),
				self::optdelopts,
				$items[self::optdelopts],
				array($this, 'put_del_opts'));
		$fields[$nf++] = new $Cf(self::optdelstor,
				self::wt(__('Delete database table on uninstall:', 'spambl_l10n')),
				self::optdelstor,
				$items[self::optdelstor],
				array($this, 'put_del_stor'));

		// inst sections
		$sections[$ns++] = new $Cs($fields,
				'Spam_BLIP_plugin1_inst_section',
				'<a name="install">' .
					self::wt(__('Plugin Install Settings', 'spambl_l10n'))
					. '</a>',
				array($this, 'put_inst_desc'));

		// prepare admin page specific hooks per page. e.g.:
		// (now set to false, but code remains for reference)
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
			self::wt(__('Spam BLIP Plugin', 'spambl_l10n')),
			self::wt(__('Spam BLIP Configuration Settings', 'spambl_l10n')),
			array(__CLASS__, 'validate_opts'),
			/* pagetype = 'options' */ '',
			/* capability = 'manage_options' */ '',
			array($this, 'setting_page_output_callback')/* callback '' */,
			/* 'hook_suffix' callback array */ $suffix_hooks,
			self::wt(__('<em>Spam BLIP</em> Plugin Settings', 'spambl_l10n')),
			self::wt(__('Options controlling <em>Spam BLIP</em> functions.', 'spambl_l10n')),
			self::wt(__('Save Settings', 'spambl_l10n')));
		
		$Co = self::mk_aclv('Options');
		$this->opt = new $Co($page);
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

		if ( $opts && $opts[self::optdelstor] != 'false' ) {
			$pg = self::get_instance();
			// bye data
			$pg->db_delete_table();
			delete_option(self::data_vs_opt);
		}

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
		if ( self::get_widget_option() == 'false' ) {
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
		$this->init_opts();

		$pf = self::mk_pluginfile();
		// admin or public invocation?
		$adm = is_admin();

		$cl = __CLASS__; // for static methods

		if ( $adm ) {
			// keep it clean: {de,}activation
			if ( current_user_can('activate_plugins') ) {
				$aa = array($cl, 'on_deactivate');
				register_deactivation_hook($pf, $aa);
				$aa = array($cl, 'on_activate');
				register_activation_hook($pf,   $aa);
			}
			if ( current_user_can('install_plugins') ) {
				$aa = array($cl, 'on_uninstall');
				register_uninstall_hook($pf,    $aa);
			}
	
			$aa = array($cl, 'filter_admin_print_scripts');
			add_action('admin_print_scripts', $aa);
	
			// Settings/Options page setup
			if ( current_user_can('manage_options') ) {
				$this->init_settings_page();
			}
	
			// this will create/update table as nec. if user set
			// the option (which defaults to false)
			if ( self::get_recdata_option() != 'false' ||
				 self::get_usedata_option() != 'false' ) {
				$this->db_create_table();
	
				if ( defined('WP_ALLOW_REPAIR') ) {
					$aa = array($this, 'filter_tables_to_repair');
					add_filter('tables_to_repair', $aa, 100);
				}
			}
		} else { // if ( $adm )
			$aa = array($this, 'action_pre_comment_on_post');
			add_action('pre_comment_on_post', $aa, 100);
	
			$aa = array($this, 'action_comment_closed');
			add_action('comment_closed', $aa, 100);
	
			$aa = array($this, 'filter_comments_open');
			add_filter('comments_open', $aa, 100);
	
			$aa = array($this, 'filter_pings_open');
			add_filter('pings_open', $aa, 100);
		} // if ( $adm )

		// WP does this hook from a php register_shutdown_function()
		// callback, so it's invoked even after wp_die()
		$aa = array($this, 'action_shutdown');
		add_action('shutdown', $aa, 200);
	}

	// add_filter('tables_to_repair', $scf, 1);
	// Allows adding table name to WP core table repair routing
	public function filter_tables_to_repair($tbls) {
		$tbls[] = $this->db_tablename();
		return $tbls;
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

	// rx check for IP6 address; return boolean
	public static function check_ip6_address($addr) {
		$addr = trim($addr);

		$pat = '[A-Fa-f0-9]{1,4}';
		if ( preg_match(sprintf('/^(::%s|%s::)$/', $pat, $pat), $addr) ) {
			return true;
		}
		if ( preg_match(sprintf('/^%s::%s$/', $pat, $pat), $addr) ) {
			return true;
		}

		if ( ! preg_match(sprintf('/^%s:.*:%s$/', $pat, $pat), $addr) ) {
			return false;
		}

		$a = explode(':', $addr);
		$c = count($a);
		if ( $c < 3 || $c > 8 ) {
			return false;
		}

		$x = $c - 1;
		$blank = false;
		for ( $i = 1; $i < $x; $i++ ) {
			if ( $a[$i] == '' ) {
				if ( $blank ) { // allow one '::'
					return false;
				}
				$blank = true;
				continue;
			}
			if ( ! preg_match(sprintf('/^%s$/', $pat), $a[$i]) ) {
				return false;
			}
		}

		return true;
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
	
	// debug messages for development: tests class const 'DBG'
	public static function dbglog($err) {
		if ( self::DBG ) {
			self::errlog('DBG: ' . $err);
		}
	}
	
	// helper to make self
	public static function instantiate($init = true) {
		if ( ! self::$instance ) {
			$cl = __CLASS__;
			self::$instance = new $cl($init);
		}
		return self::$instance;
	}

	// helper get instance of this class
	public static function get_instance($init = false) {
		global $Spam_BLIP_plugin1_evh_instance_1;
		$pg = null;

		if ( ! isset($Spam_BLIP_plugin1_evh_instance_1)
			|| $Spam_BLIP_plugin1_evh_instance_1 == null ) {
			$pg = self::instantiate($init);
		} else {
			$pg = $Spam_BLIP_plugin1_evh_instance_1;
		}

		return $pg;
	}

	// get microtime() if possible, else just time()
	public static function best_time() {
		if ( function_exists('microtime') ) {
			// PHP 4 better be dead
			// PHP 5: arg true gets a float return
			return microtime(true);
		}
		return (int)time();
	}

	// optional additional response to unexpected REMOTE_ADDR;
	// after errlog()
	protected function handle_REMOTE_ADDR_error($msg) {
		// TODO: make option; send email
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
			if ( ! array_key_exists($k, $a_orig) ) {
				// this happens for the IDs of extra form items
				// in use, such as self::optttldata . '_text'
				continue;
			}
			$ot = trim($v);
			$oo = trim($a_orig[$k]);

			switch ( $k ) {
				// Option buttons
				case self::optttldata . '_text': // FPO; see below:
					break;
				case self::optttldata:
					switch ( $ot ) {
						case ''.(3600):       //'One (1) hour'
						case ''.(3600*6):     //'Six (6) hours'
						case ''.(3600*12):    //'Twelve (12) hours'
						case ''.(3600*24):    //'One (1) day'
						case ''.(3600*24*7):  //'One (1) week'
							$a_out[$k] = $ot;
							$nupd += ($ot === $oo) ? 0 : 1;
							break;
						default:               //'Set a value:'
							$ot = trim($opts[self::optttldata.'_text']);
							// 9 decimal digits > 30 years in secs
							$re = '/^[+-]?[0-9]{1,9}$/';
							if ( preg_match($re, $ot) == 1 ) {
								if ( (int)$ot < 0 ) { $ot = '0'; }
								$a_out[$k] = ltrim($ot, '+');
								$nupd += ($ot === $oo) ? 0 : 1;
								break;
							}
							$e = __('bad TTL option: "%s"', 'swfput_l10n');
							$e = sprintf($e, $ot);
							self::errlog($e);
							$t = __('TTL option', 'swfput_l10n');
							add_settings_error(self::wt($t),
								sprintf('%s[%s]', self::opt_group, $k),
								self::wt($e), 'error');
							$a_out[$k] = $oo;
							$nerr++;
							break;
					}
					break;
				case self::optmaxdata . '_text': // FPO; see below:
					break;
				case self::optmaxdata:
					switch ( $ot ) {
						case '10':
						case '50':
						case '100':
						case '500':
						case '1000':
							$a_out[$k] = $ot;
							$nupd += ($ot === $oo) ? 0 : 1;
							break;
						default:               //'Set a value:'
							$ot = trim($opts[self::optmaxdata.'_text']);
							// 9 decimal digits, billion - 1; plenty
							$re = '/^[+-]?[0-9]{1,9}$/';
							if ( preg_match($re, $ot) == 1 ) {
								if ( (int)$ot < 0 ) { $ot = '0'; }
								$a_out[$k] = ltrim($ot, '+');
								$nupd += ($ot === $oo) ? 0 : 1;
								break;
							}
							$e = __('bad maximum: "%s"', 'swfput_l10n');
							$e = sprintf($e, $ot);
							self::errlog($e);
							$t = __('Maximum records option', 'swfput_l10n');
							add_settings_error(self::wt($t),
								sprintf('%s[%s]', self::opt_group, $k),
								self::wt($e), 'error');
							$a_out[$k] = $oo;
							$nerr++;
							break;
					}
					break;
				// Checkboxes
				case self::optverbose:
				case self::optcommflt:
				case self::optpingflt:
				case self::opttorpass:
				case self::optnonhrec:
				case self::optchkexst:
				case self::optrecdata:
				case self::optusedata:
				case self::optplugwdg:
				case self::optipnglog:
				case self::optbliplog:
				case self::optbailout:
				case self::optdelopts:
				case self::optdelstor:
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
			$str = $nerr == 0 ?
				__('Settings updated successfully', 'swfput_l10n') :
				sprintf(_n('One (%d) setting updated',
					'Some settings (%d) updated',
					$nupd, 'swfput_l10n'), $nupd);
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

		$t = self::wt(__('The "Blacklist check for comments" option 
			enables the main functionality of the plugin. When
			<em>WordPress</em> core code checks whether comments
			are open or closed, this plugin will check the connecting
			IP address against DNS-based blacklists of weblog
			comment spammers, and if it is found, will tell
			<em>WordPress</em> that comments are
			closed.', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");

		$t = self::wt(__('The "Blacklist check for pings" option 
			is similar to "Blacklist check for comments",
			but for pings.', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");

		$t = self::wt(__('The "Whitelist (pass) TOR exit nodes" option 
			enables a special lookup to try to determine if the
			connecting address is a TOR exit node (there are some
			false negatives). If it is found to be one, it is
			allowed to comment or ping. This option might be
			important if your site has content that is political,
			or in some way controversial, as visitors you would
			welcome might like to use TOR. TOR is an important
			tool for Internet anonymity, but unfortunately spammers
			have abused it, and  so some DNS blacklist operators
			include any TOR address. This option probably will let
			more spam comments be posted, but it might work well
			along with another sort of spam blocker, such as one
			that analyses comment content, as a second line of
			defense.', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");

		$t = self::wt(__('With "Check existing comment spam"
			enabled connecting addresses are checked against
			comments already stored by <em>WordPress</em> and
			marked as spam. If a match is found with a comment
			that is not too old (according to the TTL setting,
			see "Data records TTL" below),
			the connection
			is considered a spammer, and the address is added
			to the hit data store (if enabled).
			The default is true.', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");

		$t = self::wt(__('Go forward to save button.', 'spambl_l10n'));
		printf('<p><a href="#aSubmit">%s</a></p>%s', $t, "\n");
	}

	// callback: store and use data section
	public function put_datastore_desc() {
		$t = self::wt(__('Enable, disable, configure data store:', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");

		$cnt = $this->db_get_rowcount();
		if ( $cnt ) {
			$t = self::wt(
				_n('(There is %u record in the data store)',
				   '(There are %u records in the data store)',
				   $cnt, 'spambl_l10n')
			);
			printf('<p>%s</p>%s', sprintf($t, $cnt), "\n");
		}

		if ( self::get_verbose_option() !== 'true' ) {
			return;
		}

		$t = self::wt(__('These options enable or disable
			the storage of blacklist lookup results in the
			<em>WordPress</em> database, or the use of the
			stored data to before DNS lookup.', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");

		$t = self::wt(__('The "Keep data" option enables recording of
			hit data such as the connecting IP address, and the times
			the address was first seen and last seen.
			(This data is also used if included widget is
			enabled.)', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");

		$t = self::wt(__('The "Use data" option enables a check in the
			stored data; if a hit is found there then the
			DNS lookup is not performed.', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");

		$t = self::wt(__('"Data records TTL" sets an expiration time for
			records in the data store. The records should not be kept
			permanently, or even for very long, because the IP
			address might not belong to the spammer, but rather
			a conscientious ISP (also a victim of abuse by the spammer)
			that must be able to reuse the IP address. DNS
			blacklist operators might use a low TTL (Time To Live) in
			the records of relevant lists for this reason. The default
			value is one day (86400 seconds). If you do not want
			any of the presets, the text field accepts a value
			in seconds, where zero (0) or less will disable the
			TTL.', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");

		$t = self::wt(__('The "Maximum data records" option limits how
			many records will be kept in the database. It is likely that
			as the data grow larger, the oldest records will no
			longer be needed. Records are judged old based on
			the time last seen. Use your judgement with this:
			if you always get large amounts of spam, a larger
			value might be warranted.', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");

		$t = self::wt(__('The "Store (and use) non-hit addresses"
			option will cause commenter addresses to be stored even
			if the address was not found in the spammer lists. This
			will save additional DNS lookups for repeat commenters.
			This should only be used if there is a perceptible delay
			caused by the DNS lookups, because an address might
			turn out to be associated with a spammer and subsequently
			be added to the online spam blacklists, but this option
			would allow that address to post comments until its
			record expired from the plugin data store. Also, an
			address might be dynamic and therefore an association
			with a welcome commenter would not be valid.
			The default is false.', 'spambl_l10n'));
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

		$t = self::wt(__('The "Use the included widget" option enables
			whether the multi-widget included with the plugin is
			enabled. The widget will display some counts of the
			stored data, if the store is enabled. You should consider
			whether you want that data on public display, but
			if you find that acceptable, the widget should give
			a convenient view of the effectiveness of the plugin.
			', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");

		$t = self::wt(__('The "Log bad IP addresses" option enables
			log messages when
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
			message is issued to the error log.
			For a site on the "real" Internet, there is probably
			no reason to turn this option off. In fact, if
			these log messages are seen (look for "REMOTE_ADDR"),
			the hosting administrator
			or technical contact should be notified that their
			system has a bug.
			This option should be off when developing a site on
			a private network or single machine, because in this
			case error log messages might be issued for addresses
			that are valid on the network. With this option off,
			the plugin will still check the address and skip
			the blacklist DNS lookup if the address is reserved.
			', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");

		$t = self::wt(__('"Log blacklisted IP addresses" selects logging
			of blacklist hits with the remote IP address. This
			is only informative, and will add unneeded lines
			in the error log. New plugin users might like to
			enable this temporarily to see the effect the plugin
			has had.', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");

		$t = self::wt(__('The "Bail out on blacklisted IP"
			option will have the plugin terminate the blog output
			when the connecting IP address is blacklisted. The
			default is to only disable comments, and allow the
			page to be produced normally. This option will save
			some amount of network load (and use that you might
			pay for), and spammers do not want or need your
			content anyway, but if there is a rare false positive,
			the visitor, also a spam victim in this case, will
			miss your content.
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
			features for plugin install or uninstall. Currently,
			the only options are whether to remove the plugin\'s
			setup options and data storage from the 
			<em>WordPress</em> database when the plugin is deleted.
			There is probably no reason to leave the these data in
			place if you intend to delete the plugin permanently.
			If you intend to delete and then reinstall the plugin,
			possibly for a new version or update, then keeping the
			these data might be a good idea.', 'spambl_l10n'));
		printf('<p>%s</p>%s', $t, "\n");

		$t = self::wt(__('The "Delete setup options" option and the
			"Delete database table" option are independent;
			one may be deleted while the other is saved.
			', 'spambl_l10n'));
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

	// callback, pass/whitelist TOR exit nodes?
	public function put_torpass_opt($a) {
		$tt = self::wt(__('Whitelist TOR addresses', 'spambl_l10n'));
		$k = self::opttorpass;
		$this->put_single_checkbox($a, $k, $tt);
	}

	// store and use non-hit addresses to avoid addl. DNS lookups?
	public function put_nonhrec_opt($a) {
		$tt = self::wt(__('Store non-hit addresses for repeats', 'spambl_l10n'));
		$k = self::optnonhrec;
		$this->put_single_checkbox($a, $k, $tt);
	}

	// check exising comments?
	public function put_chkexst_opt($a) {
		$tt = self::wt(__('Check address in existing comments', 'spambl_l10n'));
		$k = self::optchkexst;
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

	// callback, ttl data store
	public function put_ttldata_opt($a) {
		$tt = self::wt(__('Set "Time To Live" of data store records', 'spambl_l10n'));
		$k = self::optttldata;
		$group = self::opt_group;
		$va = array(
			array(__('One (1) hour', 'spambl_l10n'), ''.(3600)),
			array(__('Six (6) hours', 'spambl_l10n'), ''.(3600*6)),
			array(__('Twelve (12) hours', 'spambl_l10n'), ''.(3600*12)),
			array(__('One (1) day', 'spambl_l10n'), ''.(3600*24)),
			array(__('One (1) week', 'spambl_l10n'), ''.(3600*24*7)),
			array(__('Set a value in seconds:', 'spambl_l10n'), ''.(0))
		);

		$v = trim('' . $a[$k]);
		$bhit = false;
		$txtval = ''.(3600*24);

		foreach ( $va as $oa ) {
			$txt = self::wt($oa[0]);
			$tim = $oa[1];
			$chk = '';
			if ( $tim === '0' ) { // field entry
				if ( ! $bhit ) {
					$chk = 'checked="checked" ';
					$txtval = $v;
				}
			} else if ( $v === $tim ) { // radio val matched
				$bhit = true;
				$chk = 'checked="checked" ';
			}

			printf(
				"\n".'<label><input type="radio" id="%s" ', $k
			);
			printf(
				'name="%s[%s]" value="%s" %s/>', $group, $k, $tim, $chk
			);
			printf(
				'&nbsp;%s</label>%s'."\n", $txt,
				$tim === '0' ? '' : '<br/>'
			);
		}

		// text input associated with the last option radio button
		// note the "[${k}_text]" in the name attribute
		echo "&nbsp;&nbsp;&nbsp;<input id=\"{$k}\" name=\""
			. "{$group}[${k}_text]\" size=\"10\" type=\"text\""
			. " value=\"{$txtval}\" />\n\n";
	}

	// callback, ttl data store max records
	public function put_maxdata_opt($a) {
		$tt = self::wt(__('Set maximum data store records to keep', 'spambl_l10n'));
		$k = self::optmaxdata;
		$group = self::opt_group;
		$va = array(
			array(__('Ten (10)', 'spambl_l10n'), '10'),
			array(__('Fifty (50)', 'spambl_l10n'), '50'),
			array(__('One hundred (100)', 'spambl_l10n'), '100'),
			array(__('Five hundred (500)', 'spambl_l10n'), '500'),
			array(__('One thousand (1000)', 'spambl_l10n'), '1000'),
			array(__('Set a value:', 'spambl_l10n'), '0')
		);

		$v = trim('' . $a[$k]);
		$bhit = false;
		$txtval = '50';

		foreach ( $va as $oa ) {
			$txt = self::wt($oa[0]);
			$tim = $oa[1];
			$chk = '';
			if ( $tim === '0' ) { // field entry
				if ( ! $bhit ) {
					$chk = 'checked="checked" ';
					$txtval = $v;
				}
			} else if ( $v === $tim ) { // radio val matched
				$bhit = true;
				$chk = 'checked="checked" ';
			}

			printf(
				"\n".'<label><input type="radio" id="%s" ', $k
			);
			printf(
				'name="%s[%s]" value="%s" %s/>', $group, $k, $tim, $chk
			);
			printf(
				'&nbsp;%s</label>%s'."\n", $txt,
				$tim === '0' ? '' : '<br/>'
			);
		}

		// text input associated with the last option radio button
		// note the "[${k}_text]" in the name attribute
		echo "&nbsp;&nbsp;&nbsp;<input id=\"{$k}\" name=\""
			. "{$group}[${k}_text]\" size=\"10\" type=\"text\""
			. " value=\"{$txtval}\" />\n\n";
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

	// callback, install section field: data delete
	public function put_del_stor($a) {
		$tt = self::wt(__('Permanently delete stored data (drop table)', 'spambl_l10n'));
		$k = self::optdelstor;
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

	// ttl of stored data; seconds (time)
	public static function get_ttldata_option() {
		return self::opt_by_name(self::optttldata);
	}

	// max number of stored data
	public static function get_maxdata_option() {
		return self::opt_by_name(self::optmaxdata);
	}

	// should the filter_comments_open() rbl check be done
	public static function get_comments_open_option() {
		return self::opt_by_name(self::optcommflt);
	}

	// should the filter_pings_open() rbl check be done
	public static function get_pings_open_option() {
		return self::opt_by_name(self::optpingflt);
	}

	// for whether to pass/whitelist tor exit nodes
	public static function get_torwhite_option() {
		return self::opt_by_name(self::opttorpass);
	}

	// for whether to store non-hit lookups
	public static function get_rec_non_option() {
		return self::opt_by_name(self::optnonhrec);
	}

	// for whether to check WP stored comments
	public static function get_chkexist_option() {
		return self::opt_by_name(self::optchkexst);
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

	// anything scheduled for just before PHP shutdow: WP
	// calls this action from its own registered
	// PHP register_shutdown_function() callback
	public function action_shutdown() {
		if ( $this->do_db_maintain ) {
			$this->do_db_maintain = false;
			$this->db_tbl_real_maintain();
		}
	}

	// add_action('pre_comment_on_post', $scf, 1);
	// This action is called from the last 'else' in
	// and if/else chain starting with a test of comments_open()
	// which applies filter 'comments_open', see filter_comments_open()
	// below. If comments are open, this gets called. DNS RBL
	// lookup is done here because the wait for result will
	// only affect the commenter, not every page load. A real
	// human commenter will probably not find a delay of a couple
	// seconds after submitting comment as noticeable as a
	// similar delay in the whole page load; it will just
	// seem like processing of comment at server (which it is).
	// This does not get called if post status is 'trash' or
	// if it is a draft or requires password -- all those cause
	// an exit (after an action hook call), so spam should
	// not get through in those cases.
	public function action_pre_comment_on_post($comment_post_ID) {
		if ( self::get_comments_open_option() != 'true' ) {
			return;
		}		

		self::dbglog('enter action_pre_comment_on_post');

		// was rbl check called already? if so,
		// use stored result
		$prev = $this->get_rbl_result();
		
		// if not done already
		if ( $prev === null ) {
			$this->do_db_bl_check(true, 'comments') ;
			$prev = $this->get_rbl_result();
		}
		
		if ( $prev !== false ) {
			self::dbglog('BAILING FROM action_pre_comment_on_post');
			// TRANSLATORS: polite rejection message
			// in response to blacklisted IP address
			wp_die(__('Sorry, but no, thank you.', 'spambl_l10n'));
		}
	}

	// this action is invoked in wp-trackback.php, last action
	// before trackback_response(0); just after
	// wp_new_comment($commentdata), so it is too late
	// to prevent spam
	//public function action_trackback_post($insert_ID) {
		//if ( self::get_pings_open_option() != 'true' ) {
			//return;
		//}		

		//self::dbglog('enter action_trackback_post');

		//// was rbl check called already? if so,
		//// use stored result
		//$prev = $this->get_rbl_result();
		
		//// if not done already
		//if ( $prev === null ) {
			//$this->do_db_bl_check(true, 'pings') ;
			//$prev = $this->get_rbl_result();
		//}
		
		//if ( $prev !== false ) {
			//self::dbglog('BAILING FROM action_trackback_post');
			//// TRANSLATORS: polite rejection message
			//// in response to blacklisted IP address
			//wp_die(__('Sorry, but no, thank you.', 'spambl_l10n'));
		//}
	//}

	// add_action('comment_closed', $scf, 1);
	// This gets called if comments_open(), filtered below,
	// yields not true. The block ends with wp_die(), so it is
	// not needed here, and would exclude subsequent hooks if
	// done here. An additional message might be printed, even
	// though there's not much point to it
	public function action_comment_closed($comment_post_ID) {
		if ( self::get_comments_open_option() != 'true' ) {
			return;
		}
		
		if ( $this->get_rbl_result() === true ) {
			// TRANSLATORS: polite rejection message
			// in response to blacklisted IP address
			echo __('Sorry, but no, thank you.', 'spambl_l10n') .'<hr>';
		}
	}

	// add_filter('comments_open', $scf, 1);
	// NOTE: this may/will be called many times per page,
	// for each comment link on page.
	// This should not be used for the DNS RBL lookup because
	// waiting for the response can caused a noticeable stall
	// of page loading in client.
	// action_pre_comment_on_post is used for the RBL lookup;
	// see comment there.
	// OTOH, this filter will look in the hit db, which is fast,
	// and nip it in the bud early if a hit is found.
	public function filter_comments_open($open) {
		if ( self::get_comments_open_option() != 'true' ) {
			return $open;
		}		

		// was data store check called already? if so,
		// use stored result
		$prev = $this->dbl_result;
		
		// if not done already
		if ( $prev === false || ! is_array($prev) ) {
			return $this->do_db_bl_check($open, 'comments', false);
		}
		
		// if already done, but not a hit
		if (  $prev[0] === false ) {
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
			return $this->do_db_bl_check($open, 'pings');
		}
		
		// if already done, but not a hit
		if ( $prev === false ) {
			return $open;
		}

		// already got a hit on this IP addr		
		return false;
	}

	// internal BL check for use by e.g., filters
	// Returns false for a BL hit, else returns arg $def
	public function do_db_bl_check($def, $statype, $rbl = true) {
		$addr = self::get_conn_addr();

		if ( $addr === false ) {
			$addr = $_SERVER["REMOTE_ADDR"];
			$fmt = self::check_ip6_address($addr) ?
				__('Got IP version 6 address "%s"; sorry, only IP4 handled currently', 'spambl_l10n')
				:
				__('Invalid remote address; "REMOTE_ADDR" contains "%s"', 'spambl_l10n');
			self::errlog(sprintf($fmt, $addr));
			return $def;
		}
		
		// Check for not non-routable CGI/1.1 envar REMOTE_ADDR
		// as can actually happen with some hosting hacks.
		$ret = $this->ipchk_done ? false
			: $this->ipchk->chk_resv_addr($addr);
		$this->ipchk_done = true;
		if ( $ret !== false ) {
			if ( self::get_ip_log_option() != 'false' ) {
				// TRANSLATORS: word for ietf/iana reserved network
				$rsz = __('RESERVED', 'spambl_l10n');
				// TRANSLATORS: word for ietf/iana loopback network
				$lpb = __('LOOPBACK', 'spambl_l10n');
				$ret = $ret ? $rsv : $lpb;
				// TRANSLATORS: %1$s is either "RESERVED" or "LOOPBACK";
				// see comments above.
				// %2$s is an IPv4 dotted quad address
				$fmt = __('Got %1$s IPv4 address "%2$s" in "REMOTE_ADDR".', 'spambl_l10n');
				$ret = sprintf($fmt, $ret, $addr);
				self::errlog($ret);
				// TODO: email admin
				$this->handle_REMOTE_ADDR_error($ret);
			}
			// Well, can't continue; set result false
			$this->rbl_result = array(false);
			return $def;
		}

		// option to whitelist addresses that TOR lists as exit nodes
		if ( $this->tor_non_optional_whitelist($addr, $rbl) ) {
			// flag this like db check w/o a hit
			$this->dbl_result = array(false);
			return $def;
		}
		
		$pretime = self::best_time();

		// optional data store check
		if ( self::get_usedata_option() != 'false' ) {
			$d = $this->db_get_address($addr);
			$posttime = self::best_time();

			$hit = false;
			if ( is_array($d) ) {
				if ( $d['lasttype'] === 'comments' ||
					 $d['lasttype'] === 'pings' ) {
					$hit = true;
				}
			}

			if ( $hit ) {
				// optional hit logging
				if ( self::get_hitlog_option() != 'false' ) {
					// TRANSLATORS: see "TRANSLATORS: %1$s is type..."
					$ptxt = __('pings', 'spambl_l10n');
					// TRANSLATORS: see "TRANSLATORS: %1$s is type..."
					$ctxt = __('comments', 'spambl_l10n');
		
					$dtxt = $statype === 'pings' ? $ptxt :
						($statype === 'comments' ? $ctxt : $statype);
		
					$fmt =
					// TRANSLATORS: %1$s is type "comments" or "pings"
					// %2$s is IP4 address dotted quad
					// %3$s is first seen date; in UTC, formatted
					//      in *site host* machine's locale
					// %4$s is last seen date; as above
					// %5$u is integer number of times seen (hitcount)
					// %6$f is is time (float) used in database check
					_n('%1$s denied for address %2$s, first seen %3$s, last seen %4$s, previously seen %5$u time; (db time %6$f)',
					   '%1$s denied for address %2$s, first seen %3$s, last seen %4$s, previously seen %5$u times; (db time %6$f)',
					   (int)$d['hitcount'], 'spambl_l10n');
					$fmt = sprintf($fmt, $dtxt, $addr,
						gmdate(DATE_RFC2822, (int)$d['seeninit']),
						gmdate(DATE_RFC2822, (int)$d['seenlast']),
						(int)$d['hitcount'], $posttime - $pretime);
					self::errlog($fmt);
				}		

				// optionally record stats
				if ( self::get_recdata_option() != 'false' ) {
					$this->db_update_array(
						$this->db_make_array(
							$addr, 1, (int)$pretime, $statype
						)
					);
					// maintain table
					$this->db_tbl_maintain();
				}
				
				// optionally die
				self::hit_optional_bailout($addr, $statype);

				// set the result; checked in various places
				$this->rbl_result = array(true);
				$this->dbl_result = array(true);
				return false;
			}
		}

		// optional check in WP stored comments
		if ( $this->chk_comments($addr, (int)$pretime) === true ) {
			// set the result; checked in various places
			$this->rbl_result = array(true);
			$this->dbl_result = array(true);
			return false;
		}

		// if not $rbl only the optional data store check is
		// wanted, for routines that should not wait on DNS
		if ( $rbl !== true ) {
			$this->dbl_result = array(false);
			return $def;
		}

		// time again, in case last block was slow,
		// and do lookup
		$pretime = self::best_time();
		$ret = $this->bl_check_addr($addr);
		$posttime = self::best_time();
		self::dbglog(
			'DNS lookup in ' . ($posttime - $pretime) . ' secs');

		if ( $ret === false ) {
			// not a RBL hit
			if ( self::get_rec_non_option() != 'false' &&
			     self::get_recdata_option() != 'false' ) {
				$this->db_update_array(
					$this->db_make_array(
						$addr, 1, (int)$pretime, 'non'
					)
				);
			}
			return $def;
		}
		
		// We have a hit!
		$ret = false;
		
		// optionally record stats
		if ( self::get_recdata_option() != 'false' ) {
			$this->db_update_array(
				$this->db_make_array(
					$addr, 1, (int)$pretime, $statype
				)
			);
			// maintain table
			$this->db_tbl_maintain();
		}

		// optional hit logging
		if ( self::get_hitlog_option() != 'false' ) {
			$difftime = $posttime - $pretime;
			// TRANSLATORS: see "TRANSLATORS: %1$s is type..."
			$ptxt = __('pings', 'spambl_l10n');
			// TRANSLATORS: see "TRANSLATORS: %1$s is type..."
			$ctxt = __('comments', 'spambl_l10n');

			$dtxt = $statype === 'pings' ? $ptxt :
				($statype === 'comments' ? $ctxt : $statype);

			if ( is_array($this->rbl_result[0]) ) {
				$doms = $this->chkbl->get_dom_array();
				$fmt =
					// TRANSLATORS: %1$s is type "comments" or "pings"
					// %2$s is IP4 address dotted quad
					// %3$s is DNS blacklist lookup domain
					// %4$s is IP4 blacklist lookup result
					// %5$f is lookup time in seconds (float)
					__('%1$s denied for address %2$s, list at "%3$s", result %4$s in %5$f', 'spambl_l10n');
				$fmt = sprintf($fmt, $dtxt, $addr,
					$doms[ $this->rbl_result[0][0] ][0],
					$this->rbl_result[0][1], $difftime);
				self::errlog($fmt);
			} else {
				$fmt =
					// TRANSLATORS: %1$s is type "comments" or "pings"
					// %2$s is IP4 address dotted quad
					// %3$f is lookup time in seconds (float)
					__('%1$s denied for address %2$s in %3$f', 'spambl_l10n');
				$fmt = sprintf($fmt, $dtxt, $addr, $difftime);
				self::errlog($fmt);
			}
		}		
		
		// optionally die
		self::hit_optional_bailout($addr, $statype);

		return $ret;
	}


	// optionally check comments saved by WP for those marked
	// as spam and having address $addr and having GMT >=
	// $tm - TTL option
	protected function chk_comments($addr, $tm) {
		$opt = self::get_chkexist_option();
		if ( $opt == 'false' ) {
			return false;
		}

		global $wpdb;
		$q = sprintf("SELECT %s FROM %s WHERE %s = '%s' AND %s = '%s'", 
			'UNIX_TIMESTAMP(comment_date_gmt), comment_type',
				$wpdb->comments,
			'comment_approved', 'spam',
			'comment_author_IP', $addr
		);
		$r = $wpdb->get_results($q, ARRAY_A);

		if ( is_array($r) && isset($r[0]) ) {
			$ttl = (int)self::get_ttldata_option();
			if ( $ttl < 1 ) {
				$ttl = $tm;
			}
			$old = $tm - $ttl;

			foreach ( $r as $a ) {
				if ( (int)$a['comment_date_gmt'] < $old ) {
					continue;
				}
				// hit, not too old . . .
				if ( self::get_recdata_option() != 'false' ) {
					$ty = trim($a['comment_type']) == ''
						? 'comments' : 'pings';
					$this->db_update_array(
						$this->db_make_array(
							$addr, 1, (int)$tm, $ty
						)
					);
					// maintain table
					$this->db_tbl_maintain();
					self::dbglog('FOUND spam comment, type "' .
						$ty . '", address ' . $addr .
						', from "' . $a['comment_date_gmt'] . ' GMT"'
					);
				}
				// . . . one is all we need
				return true;
			}
		}

		return false;
	}

	// if option to whitelist TOR is set and address is *found*
	// to be a TOR exit node (there are false negatives), then
	// return true; else return false
	protected function tor_non_optional_whitelist($addr, $dns = true) {
		// if opt
		$toro = self::get_torwhite_option();
		$nono = self::get_rec_non_option();
		if ( $toro == 'false' && $nono == 'false' ) {
			return false;
		}

		$t = '';
		if ( self::get_usedata_option() != 'false' ) {
			$d = $this->db_get_address($addr);
			if ( is_array($d) ) {
				$t = $d['lasttype'];
			}

			if ( $t === 'non' ) {
				if ( $nono == 'false' ) $t = '';
			} else if ( $t === 'torx' ) {
				if ( $toro == 'false' ) $t = '';
			}

			if ( $t === 'torx' ) {
				if ( self::get_hitlog_option() != 'false' ) {
					// TRANSLATORS: %1$s is IP4 address; %2$u is the
					// number of times adress was seen previously
					$m = __('Found "%1$s" to be a tor exit, %2$u hits in data -- passed per option', 'spambl_l10n');
					self::errlog(sprintf($m, $addr, $d['hitcount']));
				}
			}
			if ( $t === 'torx' || $t === 'non' ) {
				// optionally record stats
				if ( self::get_recdata_option() != 'false' ) {
					$this->db_update_array(
						$this->db_make_array(
							$addr, 1, (int)time(), $t
						)
					);
				}
			
				// mark for this invocation
				$this->rbl_result = array(false);
				return true;
			}
		}

		// remainder is only for tor, and only if DNS check is wanted
		if ( $toro == 'false' || $dns !== true ) {
			return false;
		}

		$s = $_SERVER["SERVER_ADDR"];
		if ( $this->ipchk->chk_resv_addr($s) ) {
			// broken proxy/cache/frontend in shared hosting?
			// hopefully this DNS query will return with success
			// very quickly, as the domain should be handled
			// on this net or close to it; note the lack of
			// trailing dot, too
			$s = gethostbyname($_SERVER["SERVER_NAME"]);
			// test PHP's peculiar error return
			if ( $s == $_SERVER["SERVER_NAME"] ) {
				$s = false;
			}
		}
		if ( $s && ChkBL_0_0_1::chk_tor_exit($addr, $s) ) {
			if ( self::get_hitlog_option() != 'false' ) {
				// TRANSLATORS: %s is IP4 address; DNS is the
				// domain name system
				$m = __('Found "%s" to be a tor exit, by DNS -- passed per option', 'spambl_l10n');
				self::errlog(sprintf($m, $addr));
			}
			// optionally record stats
			if ( self::get_recdata_option() != 'false' ) {
				// use 'torx' for tor exit node
				$this->db_update_array(
					$this->db_make_array(
						$addr, 1, (int)time(), 'torx'
					)
				);
			}
			
			// mark for this invocation
			$this->rbl_result = array(false);
			return true;
		}
		
		return false;
	}

	protected static function hit_optional_bailout($addr, $statype) {
		if ( self::get_bailout_option() != 'false' ) {
			// Allow additional action from elsewhere, however unlikely.
			do_action('spamblip_hit_bailout', $addr, $statype);
			// TODO: make message text an option
			wp_die(__('Sorry, but no, thank you.', 'spambl_l10n'));
		}
	}

	/**
	 * methods for optional data store
	 */
	 
	// get db table name
	protected function db_tablename() {
		global $wpdb;
		
		// const data_suffix
		if ( $this->data_table === null ) {
			$this->data_table = $wpdb->prefix . self::data_suffix;
		}
		
		return $this->data_table;
	}
	
	// lock table for some ops, in case concurrent page requests
	// cause intermixed calls to these routines from different sessions
	// *DO* unlock when done: MySQL docs say the lock will prevent
	// access to *other* tables, which would prevent WP in any
	// subsequent DB ops.
	// UPDATE: we possibly lack privilege for "LOCK TABLES",
	// so use this advisory form; unlocking is less critical,
	// but of course still should not be forgotten
	protected function db_lock_table($type = 'WRITE') {
		global $wpdb;
		$tbl = $this->db_tablename();
		$lck = 'lck_' . $tbl;
		$r = $wpdb->get_results(
			"SELECT GET_LOCK('{$lck}',10);", ARRAY_N
		);
		if ( is_array($r) && is_array($r[0]) ) {
			return (int)$r[0][0];
		}
		self::errlog("FAILED SELECT GET_LOCK('{$lck}',10);");
		return false;
	}
	
	// unlock locked table: DO NOT FORGET
	protected function db_unlock_table($type = 'WRITE') {
		global $wpdb;
		$tbl = $this->db_tablename();
		$lck = 'lck_' . $tbl;
		$r = $wpdb->get_results(
			"SELECT RELEASE_LOCK('{$lck}');", ARRAY_N
		);
		if ( is_array($r) && is_array($r[0]) ) {
			return (int)$r[0][0];
		}
		self::errlog("FAILED SELECT RELEASE_LOCK('{$lck}');");
		return false;
	}
	
	// maintain table: just set flag; act on shutdown hook
	protected function db_tbl_maintain() {
		// flag maintenance at shutdown
		$this->do_db_maintain = true;
	}
	
	// maintain table: trim according to TTL and max rows options
	private function db_tbl_real_maintain() {
		$tm = self::best_time();
		global $wpdb;
		
		$r1 = $r2 = false;
		//$wpdb->show_errors();
		$c = self::get_ttldata_option();
		// 0 (or less) disables
		if ( (int)$c >= 1 ) {
			$c = (int)time() - (int)$c;
			if ( $c > 0 ) {
				$f = $r1 = $this->db_remove_older_than($c);
				if ( $f === false ) $f = 'false';
				self::dbglog('GOT from db_remove_older_than: ' . $f);
			}
		}

		$c = self::get_maxdata_option();
		// 0 (or less) disables
		if ( (int)$c >= 1 ) {
			$f = $r2 = $this->db_remove_above_max($c);
			if ( $f === false ) $f = 'false';
			self::dbglog('GOT from db_remove_above_max: ' . $f);
		}
		
		// if records were removed ...
		if ( $r1 || $r2 ) {
			// ... optimize
			$this->db_optimize();
		}
		//$wpdb->hide_errors();

		$tm = self::best_time() - $tm;
		self::dbglog('table maintenance in ' . $tm . ' seconds');
	}

	// do optimize if free percent too great,
	// or optional analyze
	protected function db_optimize($analyze = true) {
		global $wpdb;
		$tbl = $this->db_tablename();
		$db = DB_NAME;
		$fpct = 0;
		$len = 0;

		$r = $wpdb->get_results(
			"SELECT data_length, data_free "
			. "FROM information_schema.TABLES "
			. "where TABLE_SCHEMA = '{$db}' "
				. "AND TABLE_NAME = '{$tbl}';",
			ARRAY_N
		);

		if ( is_array($r) && isset($r[0]) && isset($r[0][0]) ) {
			$len = (int)$r[0][0];
			$free = (int)$r[0][1];
			if ( $len > 0 ) {
				$fpct = ($free * 100) / $len;
			}
			self::dbglog(
				sprintf('OPT: length %d, free %d', $len, $free));
		}
		
		// TODO: make an option
		$fragmax = 15;
		// TODO: tune, make user notification or something --
		// observe time cost of optimization of table sizes so that
		// this max can be tuned -- the operation is regarded as
		// expensive, so this value should be the max that can
		// be considered reasonable for an automatic action --
		// first guess: 5mb; if records require ~ 30 bytes,
		// (as found with db's overhead on one test system)
		// simplistic figuring gives ~ 175k records in 5mb;
		// 175k IP4 addresses listed at blog comment spam RBLs.
		// will this plugin's data ever get there? who knows
		$lengthmax = 1024 * 1024 * 5;

		if ( $len <= $lengthmax && $fpct > $fragmax ) {
			$wpdb->query("OPTIMIZE TABLE {$tbl}");
		} else if ( $analyze ) {
			$wpdb->query("ANALYZE TABLE {$tbl}");
		}
	}
	
	// create the data store table
	protected function db_delete_table() {
		global $wpdb;
		$tbl = $this->db_tablename();
		// 'IF EXISTS' should suppress error if never created
		// drop table removes associated files and data,
		// indices and format, too
		return $wpdb->query("DROP TABLE IF EXISTS {$tbl}");
	}
	
	// create the data store table; use dbDelta, see:
	// https://codex.wordpress.org/Creating_Tables_with_Plugins
	protected function db_create_table() {
		$o = get_option(self::data_vs_opt);
		$v = 0;

		// init version const is/was 1
		if ( ! $o ) {
			// opt did not exist, needs adding
			add_option(self::data_vs_opt, ''.self::data_vs);
		} else {
			$v = 0 + $o;
		}

		// if existing version is not less, leave it be
		if ( $v >= self::data_vs ) {
			return true;
		}

		// opt already existed, needs update
		if ( $o ) {
			update_option(self::data_vs_opt, ''.self::data_vs);
		}
		
		$tbl = $this->db_tablename();

// Nice indenting must be suspended now
// want a table like so:
// address  == dotted IP4 address ; primary key
// hitcount == count of hits
// seeninit == *epoch* time of 1st recorded hit
// seenlast == *epoch* time of last recorded hit
// lasttype == enum('comments', 'pings', 'torx', 'x1', 'x2', 'non', 'white', 'black')
//		set type: torx for whitelist option, non for recording non-hits
//                option, white||black for user entered addresses
//                and a couple for expansion
// varispam == bool set true if lasttype != current type
// 
// charset ascii with case senitive binary collation is suitable
// for the IP address column, and enum 'lasttype' is constrained
// to that, and can reasonably be *assumed* to have the fastest possible
// comparisons
$qs = <<<EOQ
CREATE TABLE $tbl (
  address char(15) CHARACTER SET ascii COLLATE ascii_bin NOT NULL default '0.0.0.0',
  hitcount int(11) UNSIGNED NOT NULL default '0',
  seeninit int(11) UNSIGNED NOT NULL default '0',
  seenlast int(11) UNSIGNED NOT NULL default '0',
  lasttype enum('comments', 'pings', 'torx', 'x1', 'x2', 'non', 'white', 'black') CHARACTER SET ascii COLLATE ascii_bin NOT NULL default 'comments',
  varispam tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (address),
  KEY seenlast (seenlast),
  KEY lasttype (lasttype),
  KEY complast (seenlast, lasttype)
);

EOQ;

		// back to pretty-pretty indents!
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($qs);

		return true;
	}
	
	// cache var for the following db_get_address method
	private $db_get_addr_cache = null;

	// get record for an IP address; returns null
	// (as $wpdb->get_row() is documented to do),
	// or associative array
	protected function db_get_address($addr) {
		if ( $this->db_get_addr_cache !== null
			&& $this->db_get_addr_cache[0] === $addr ) {
			return $this->db_get_addr_cache[1];
		}

		global $wpdb;
		$tbl = $this->db_tablename();
		
		$q = "SELECT * FROM {$tbl} WHERE address = '{$addr}'";
		$r = $wpdb->get_row($q, ARRAY_A);

		if ( is_array($r) ) {
			$this->db_get_addr_cache = array($addr, $r);
		} else {
			$this->db_get_addr_cache = null;
		}

		return $r;
	}
	
	// get number of records -- checks the store version options
	// first for whether the table should exist -- returns
	// false if the option does not exist
	protected function db_get_rowcount() {
		global $wpdb;
		$tbl = $this->db_tablename();

		$r = $wpdb->get_results(
			"SELECT COUNT(*) FROM {$tbl}", ARRAY_N
		);

		if ( is_array($r) && isset($r[0]) && isset($r[0][0]) ) {
			return $r[0][0];
		}
		
		return false;
	}

	// general function of select
	protected function db_FUNC($f, $where = null, $group = null) {
		global $wpdb;
		$tbl = $this->db_tablename();
		
		$q = sprintf("SELECT %s FROM %s", $f, $tbl);
		if ( $where !== null ) {
			$q .= ' WHERE ' . $where;
		}
		if ( $group !== null ) {
			$q .= ' GROUP BY ' . $group;
		}

		$r = $wpdb->get_results($q, ARRAY_N);

		if ( is_array($r) ) {
			return $r;
		}
		
		return false;
	}

	// remove where seenlast is < $ts
	protected function db_remove_older_than($ts) {
		global $wpdb;
		$tbl = $this->db_tablename();
		
		$ts = sprintf('%u', 0 + $ts);

		$this->db_lock_table();
		// NOTE: address <> '0.0.0.0' was necessary with mysql
		// commandline client:
		// "safe update [...] without a WHERE that uses a KEY column";
		// and at testing address was the only key. Although this
		// did not prove necessary in WP test installations,
		// it's added for 'noia's sake, and should not affect
		// results as address should never be '0.0.0.0'
		$noid = "address <> '0.0.0.0' AND ";
		$wpdb->get_results(
			"DELETE IGNORE FROM {$tbl} WHERE {$noid}seenlast < {$ts};",
			ARRAY_N
		);
		$r = $wpdb->get_results(
			"SELECT ROW_COUNT();",
			ARRAY_N
		);
		$this->db_unlock_table();

		if ( is_array($r) && isset($r[0]) && isset($r[0][0]) ) {
			return $r[0][0];
		}
		
		return false;
	}

	// remove older rows so that row count == $max
	protected function db_remove_above_max($mx) {
		$ret = false;
		
		// these several ops should lock out other sessions
		$this->db_lock_table();

		// 'row_count'
		$c = $this->db_get_rowcount();

		do {
			if ( $c === false ) {
				// break rather than return, to get the unlock
				break;
			}
			
			if ( (int)$c <= ((int)$mx+self::db_get_max_pad($mx)) ) {
				// break rather than return, to get the unlock
				$ret = 0;
				break;
			}
			
			global $wpdb;
			$tbl = $this->db_tablename();
			
			// make difference; number to remove
			$c = sprintf('%u', (int)$c - (int)$mx);
	
			// MySQL docs claim LIMIT is MySQL specific;
			// if WP ever supports other DB this will have to
			// be redone
			// NOTE: address <> '0.0.0.0' was necessary with mysql
			// commandline client:
			// "safe update [...] without a WHERE that uses a KEY column";
			// and at testing address was the only key. Although this
			// did not prove necessary in WP test installations,
			// it's added for 'noia's sake, and should not affect
			// results as address should never be '0.0.0.0'
			$noid = "WHERE address <> '0.0.0.0' ";
			$wpdb->get_results(
				"DELETE FROM {$tbl} {$noid}ORDER BY seenlast LIMIT {$c};",
				ARRAY_N
			);
			$r = $wpdb->get_results(
				"SELECT ROW_COUNT();",
				ARRAY_N
			);
	
			if ( is_array($r) && isset($r[0]) && isset($r[0][0]) ) {
				$ret = (int)$r[0][0];
			}
		} while ( false );

		$this->db_unlock_table();
		
		return $ret;
	}

	// return a pad value for the maximum data store row count option
	// to avoid the condition at max that each new insert triggers
	// another deletion, which is wasteful; the way of figuring the
	// value will always be subject to tuning, and might eventually
	// be made an option
	// pass the actual max option in $mx
	public static function db_get_max_pad($mx) {
		if ( (int)$mx < 50 ) {
			return 5;
		}
		if ( (int)$mx < 1000 ) {
			return (int)$mx / 10;
		}
		return 100;
	}

	// delete record from address -- uses method
	// added in WP 3.4.0
	protected function db_remove_address($addr) {
		if ( $this->db_get_addr_cache !== null
			&& $this->db_get_addr_cache[0] === $addr ) {
			$this->db_get_addr_cache = null;
		}

		global $wpdb;
		$tbl = $this->db_tablename();
		$r = false;

		$this->db_lock_table();
		if ( ! method_exists($wpdb, 'delete') ) {
			// w/o delete method use query
			$q = "DELETE * FROM {$tbl} WHERE address = '{$addr}'";
			$r = $wpdb->get_results($q, ARRAY_N);
		} else {
			$wh = array('address' => $addr);
			$r = $wpdb->delete($tbl, $wh, array('%s'));
		}
		$this->db_unlock_table();

		return $r;
	}

	// insert record from an associative array
	// $check1st may be false if caller is certain
	// the existence of the record need not be checked
	// NOTE: does *not* lock!
	protected function db_insert_array($a, $check1st = true) {
		// optional check for record first
		if ( $check1st !== false ) {
			$r = $this->db_get_address($a['address']);
			if ( is_array($r) ) {
				return false;
			}
		}

		global $wpdb;
		$tbl = $this->db_tablename();

		$r = $wpdb->insert($tbl, $a,
			array('%s','%d','%d','%d','%s','%d')
		);

		return $r;
	}
	
	// update record from an associative array
	// will insert record that doesn't exist if $insert is true
	protected function db_update_array($a, $insert = true) {
		$this->db_lock_table();
		// insert if record dies not exist
		$r = $this->db_get_address($a['address']);
		if ( ! is_array($r) ) {
			if ( $insert === true ) {
				$r = $this->db_insert_array($a, false);
				$this->db_unlock_table();
				return $r;
			}
			$this->db_unlock_table();
			return false;
		}

		global $wpdb;
		$tbl = $this->db_tablename();

		// cache holds record that is changed, so clear it
		$this->db_get_addr_cache = null;

		// update get values in $r with those passsed in $a
		// leave address and seeninit alone
		// compare lasttype, set varispam 1 if lasttype differs
		if ( $r['lasttype'] !== $a['lasttype'] ) {
			if ( $r['lasttype'] == 'comments'
				&& $a['lasttype'] == 'pings') {
				$r['varispam'] = 1;
			} else if ( $a['lasttype'] == 'comments'
				&& $r['lasttype'] == 'pings') {
				$r['varispam'] = 1;
			}
		}
		// set lasttype, seenlast
		$r['lasttype'] = $a['lasttype'];
		$r['seenlast'] = $a['seenlast'];
		// add hitcount
		$r['hitcount'] = (int)$r['hitcount'] + (int)$a['hitcount'];
		
		$wh = array('address' => $a['address']);
		$r = $wpdb->update($tbl, $r, $wh,
			array('%s','%d','%d','%d','%s','%d'),
			array('%s')
		);

		$this->db_unlock_table();
		return $r;
	}
	
	// make insert/update array from separate args
	protected function db_make_array(
		$addr, $hitincr, $time, $type = 'comments')
	{
		// setup the enum field "lasttype"; avoid assumption
		// that arg and enum keys will match, although
		// they should -- this can be made helpful or fuzzy, later
		$t = 'x1';
		if ( $type === 'comments' ) { $t = 'comments'; }
		if ( $type === 'pings' )    { $t = 'pings'; }
		if ( $type === 'torx' )   { $t = 'torx'; }
		if ( $type === 'x2' )   { $t = 'x2'; }
		if ( $type === 'non' )   { $t = 'non'; }
		if ( $type === 'white' )   { $t = 'white'; }
		if ( $type === 'black' )   { $t = 'black'; }

		return array(
			'address'  => $addr,
			'hitcount' => $hitincr,
			'seeninit' => $time,
			'seenlast' => $time,
			'lasttype' => $t,
			'varispam' => 0
		);
	}

	// public: get some info on the data store; e.g., for
	// the widget -- return map where ['k'] is an array
	// of avalable keys, not including 'k'
	public function get_db_info() {
		$r = array(
			'k' => array()
		);
		
		//global $wpdb;
		//$wpdb->show_errors();
		// 'row_count'
		$c = $this->db_get_rowcount();
		if ( $c === false ) {
			return false;
		}
		
		$r['k'][] = 'row_count';
		$r['row_count'] = $c;
		
		// common values in locals
		$hour = 3600;
		$day = $hour * 24;
		$week = $day * 7;
		$tf = self::best_time();
		$tm = (int)$tf;
		$types = "lasttype = 'pings' OR lasttype = 'comments'";

		$w = '' . ($tm - $hour);
		$a = $this->db_FUNC('COUNT(*)',
			"seenlast > {$w} AND ({$types})");
		if ( $a !== false && is_array($a[0]) && (int)$a[0][0] > 0 ) {
			$r['k'][] = 'hour';
			$r['hour'] = $a[0][0];
		}
		$a = $this->db_FUNC('COUNT(*)',
			"seeninit > {$w} AND ({$types})");
		if ( $a !== false && is_array($a[0]) && (int)$a[0][0] > 0 ) {
			$r['k'][] = 'hourinit';
			$r['hourinit'] = $a[0][0];
		}

		$w = '' . ($tm - $day);
		$a = $this->db_FUNC('COUNT(*)',
			"seenlast > {$w} AND ({$types})");
		if ( $a !== false && is_array($a[0]) && (int)$a[0][0] > 0 ) {
			$r['k'][] = 'day';
			$r['day'] = $a[0][0];
		}
		$a = $this->db_FUNC('COUNT(*)',
			"seeninit > {$w} AND ({$types})");
		if ( $a !== false && is_array($a[0]) && (int)$a[0][0] > 0 ) {
			$r['k'][] = 'dayinit';
			$r['dayinit'] = $a[0][0];
		}

		$w = '' . ($tm - $week);
		$a = $this->db_FUNC('COUNT(*)',
			"seenlast > {$w} AND ({$types})");
		if ( $a !== false && is_array($a[0]) && (int)$a[0][0] > 0 ) {
			$r['k'][] = 'week';
			$r['week'] = $a[0][0];
		}
		$a = $this->db_FUNC('COUNT(*)',
			"seeninit > {$w} AND ({$types})");
		if ( $a !== false && is_array($a[0]) && (int)$a[0][0] > 0 ) {
			$r['k'][] = 'weekinit';
			$r['weekinit'] = $a[0][0];
		}

		$a = $this->db_FUNC("SUM(hitcount)", "{$types}");
		if ( $a !== false && is_array($a[0]) && (int)$a[0][0] > 0 ) {
			$r['k'][] = 'htotal';
			$r['htotal'] = $a[0][0];
		}

		$w = 'torx';
		$a = $this->db_FUNC('COUNT(*)', "lasttype = '{$w}'");
		if ( $a !== false && is_array($a[0]) && (int)$a[0][0] > 0 ) {
			$r['k'][] = 'tor';
			$r['tor'] = $a[0][0];
		}
		
		$tf = self::best_time() - $tf;
		self::dbglog('data store info gathered in ' . $tf . ' seconds');
		return $r;
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
	// an instance of the main plugun class
	protected $plinst;
	
	public function __construct() {
		$this->plinst = Spam_BLIP_class::get_instance(false);
	
		$cl = __CLASS__;
		// Label shown on widgets page
		$lb =  __('Spam_BLIP Plugin Data', 'spambl_l10n');
		// Description shown under label shown on widgets page
		$desc = __('Display comment and ping spam blacklist stored data information', 'spambl_l10n');
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
		
		extract($args);

		$bc  = $this->plinst->get_comments_open_option();
		$bp  = $this->plinst->get_pings_open_option();
		$ud  = $this->plinst->get_usedata_option();
		$inf = false;
		if ( $ud != 'false' && ($bc != 'false' || $bp != 'false') ) {
			$inf = $this->plinst->get_db_info();
		}
		
		// note *no default* for title; allow empty title so that
		// user may place this below another widget with
		// apparent continuity (subject to filters)
		$title = apply_filters('widget_title',
			empty($instance['title']) ? '' : $instance['title'],
			$instance, $this->id_base);

		$cap = array_key_exists('caption', $instance)
			? $instance['caption'] : false;

		echo $before_widget;

		if ( $title ) {
			echo $before_title . $title . $after_title;
		}

		// use no class, but do use deprecated align
		$code = 'widget-div';
		$dv = '<div id="'.$code.'" align="left">';
		echo "\n<!-- Spam BLIP plugin: info widget div -->\n{$dv}";

		$wt = 'wptexturize';  // display with char translations
		$htype = 'h6';        // depends on css of theme; who knows?

		if ( $bc != 'false' || $bp != 'false' ) {
			$tw  = $this->plinst->get_torwhite_option();

			echo "\n\t<ul>";
			if ( $bc != 'false' ) {
				printf("\n\t\t<li>%s</li>",
					$wt(__('Checking for comment spam', 'spambl_l10n'))
				);
			}
			if ( $bp != 'false' ) {
				printf("\n\t\t<li>%s</li>",
					$wt(__('Checking for ping spam', 'spambl_l10n'))
				);
			}
			if ( $tw != 'false' ) {
				printf("\n\t\t<li>%s</li>",
					$wt(__('Whitelisting TOR exits', 'spambl_l10n'))
				);
			}
			echo "\n\t</ul>\n";
		}
		
		if ( $inf ) {
			printf("\n\t<{$htype}>%s</{$htype}><ul>",
				$wt(__('Information:', 'spambl_l10n'))
			);
			foreach ( $inf['k'] as $k ) {
				$v = $inf[$k];
				switch ( $k ) {
					case 'row_count':
						printf("\n\t\t<li>%s</li>",
							sprintf($wt(_n('%d address listed',
							   '%d addresses listed',
							   $v, 'spambl_l10n')), $v)
						);
						break;
					case 'tor':
						printf("\n\t\t<li>%s</li>",
							sprintf($wt(_n('%d tor exit node',
							   '%d tor exit nodes',
							   $v, 'spambl_l10n')), $v)
						);
						break;
					case 'hour':
						printf("\n\t\t<li>%s</li>",
							sprintf($wt(_n('%d address in the past hour',
							   '%d addresses in the past hour',
							   $v, 'spambl_l10n')), $v)
						);
						break;
					case 'hourinit':
						printf("\n\t\t<li>%s</li>",
							sprintf($wt(_n('%d new address in the past hour',
							   '%d new addresses in the past hour',
							   $v, 'spambl_l10n')), $v)
						);
						break;
					case 'day':
						printf("\n\t\t<li>%s</li>",
							sprintf($wt(_n('%d address in the past day',
							   '%d addresses in the past day',
							   $v, 'spambl_l10n')), $v)
						);
						break;
					case 'dayinit':
						printf("\n\t\t<li>%s</li>",
							sprintf($wt(_n('%d new address in the past day',
							   '%d new addresses in the past day',
							   $v, 'spambl_l10n')), $v)
						);
						break;
					case 'week':
						printf("\n\t\t<li>%s</li>",
							sprintf($wt(_n('%d address in the past week',
							   '%d addresses in the past week',
							   $v, 'spambl_l10n')), $v)
						);
						break;
					case 'weekinit':
						printf("\n\t\t<li>%s</li>",
							sprintf($wt(_n('%d new address in the past week',
							   '%d new addresses in the past week',
							   $v, 'spambl_l10n')), $v)
						);
						break;
					case 'htotal':
						printf("\n\t\t<li>%s</li>",
							sprintf($wt(_n('%d hit in all records',
							   'total of %d hits in all records',
							   $v, 'spambl_l10n')), $v)
						);
						break;
					default:
						break;
				}
			}
			echo "\n\t</ul>\n";
		}

		if ( $cap ) {
			echo '<p><span align="center">' .$wt($cap). '</span></p>';
		}
		echo "\n</div>\n";
		echo "<!-- Spam BLIP plugin: info widget div ends -->\n";

		echo $after_widget;
	}

	public function update($new_instance, $old_instance) {
		$i = array('title' => '', 'caption' => '');
		
		if ( is_array($old_instance) ) {
			array_merge($i, $old_instance);
		}
		
		if ( is_array($new_instance) ) {
			// for pesky checkboxes; not present if unchecked, but
			// present 'false' is wanted
			foreach ( $i as $k => $v ) {
				if ( array_key_exists($k, $new_instance) ) {
					$t = $new_instance[$k];
					$i[$k] = $t;
				}
			}
		}

		if ( ! array_key_exists('caption', $i) ) {
			$i['caption'] = '';
		}
		if ( ! array_key_exists('title', $i) ) {
			$i['title'] = '';
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

		$val = array('title' => '', 'caption' => '');
		$instance = wp_parse_args((array)$instance, $val);

		$val = '';
		if ( array_key_exists('title', $instance) ) {
			$val = $wt($instance['title']);
		}
		$id = $this->get_field_id('title');
		$nm = $this->get_field_name('title');
		$tl = $wt(__('Widget title:', 'spambl_l10n'));

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
	}
} // End class Spam_BLIP_widget_class
else :
	wp_die('class name conflict: Spam_BLIP_widget_class in ' . __FILE__);
endif; // if ( ! class_exists('Spam_BLIP_widget_class') ) :


/**********************************************************************\
 *  plugin 'main()' level code                                        *
\**********************************************************************/

// Instance not needed (or wanted) if uninstalling; the registered
// uninstall hook is saved by WP in an option so it is presistent,
// and the plugin's static uninstall method will be called.
// Else, make an instance, which triggers running.
if ( ! defined('WP_UNINSTALL_PLUGIN')
	&& $Spam_BLIP_plugin1_evh_instance_1 === null ) {
	$Spam_BLIP_plugin1_evh_instance_1 = Spam_BLIP_class::instantiate();
}

// End PHP script:
?>
