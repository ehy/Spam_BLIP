<?php
#! /usr/bin/env php
/*
 *  NetMisc_0_0_1.inc.php
 *  
 *  Copyright 2014 Ed Hynan <edhynan@gmail.com>
 *  
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; specifically version 3 of the License.
 *  
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 *  MA 02110-1301, USA.
 */

/*
* Description: class with miscellaneous network functions
* Version: 0.0.1
* Author: Ed Hynan
* License: GNU GPLv3 (see http://www.gnu.org/licenses/gpl-3.0.html)
*/

/* text editor: use real tabs of 4 column width, LF line ends */

/**********************************************************************\
 *  Class defs                                                        *
\**********************************************************************/

/**
 * class for checking IPv4 addresses against a set
 * of RBL domains
 */
if ( ! class_exists('NetMisc_0_0_1') ) :
class NetMisc_0_0_1 {
	// help detect class name conflicts; called by using code
	private static $evh_opt_id = 0xED00AA33;
	public static function id_token () {
		return self::$evh_opt_id;
	}

	
	// test stubs to support what follows
	public static function is_IP4_addr($addr)
	{
		$r = filter_var($addr
			, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
		return ($r !== false);
	}
	
	// take arg in $addr in either CIDR (int) or
	// dotted quad form check for errors, return
	// CIDR in $aout[0] snd dotted quad in $aout[1]
	// return true, or false on error
	public static function netmask_norm($addr, &$aout)
	{
		$m = $mc = $addr;
	
		// check CIDR mask
		if ( preg_match('/^[0-9]{1,2}$/', $m) ) {
			$mi = (int)$m;
			if ( $mi < 1 || $mi > 32 ) {
				return false;
			}
			$mi = ~((1 << (32 - $mi)) - 1);
			$mc = long2ip($mi);
		// check traditional mask
		} else if ( self::is_IP4_addr($m) ) {
			$mc = $m;
			$mi = ip2long($m);
			$m = 32;
			// mechanical approach: bit counting loop;
			// PHP lacks log2(3), or we'd start with
			// 32 - (int)log2(~$mi + 1);
			while ( ! ($mi & 1) ) {
				if ( --$m === 0 ) {
					return false;
				}
				$mi >>= 1;
			}
			//checks
			if ( $m === 32 && ~$mi !== 0 ) {
				return false;
			} else if ( $m !== 32 && $mi !== ((1 << $m) - 1) ) {
				return false;
			}
			$m = '' . $m;
		// mask error
		} else {
			return false;
		}
	
		$aout = array($m, $mc);
	
		return true;
	}
	
	// normalize an IP4 addr with netmask, sep'd by '/'
	// if mask is missing it is considered /32; arg may
	// have second '/' to allow both classful and CIDR
	// mask expressions, and they may be in either order,
	// but IAC the first is used for normalization; if
	// arg "aout" is an array its [0] is assigned addr,
	// [1] gets CIDR (bitwidth) mask, [2] gets classful
	// (dotted quad) mask; returns string of form
	// "ADDR/CIDRMASK/CLASSFULMASK"
	// (BTW, speaking of classful masks does not imply one
	// must be actually classful; it may express a CIDR
	// bitwidth too)
	// The address before the firs '/' is not checked at all
	// and may even be absent, but the '/' must be present
	// so that explode() will work
	public static function netaddr_norm($addr, &$aout = null, $chk2 = false)
	{
		$np = explode('/', $addr);
		if ( ! is_array($np) || count($np) < 1 ) {
			return false;
		}
		$a = $np[0];
		$m = (count($np) < 2) ? '32' : $np[1];
		$mc = (count($np) > 2) ? $np[2] : false;
	
		$o = array();
		if ( self::netmask_norm($m, $o) !== true ) {
			if ( $chk2 === false ||
			     $mc === false ||
			     self::netmask_norm($mc, $o) !== true ) {
				return false;
			}
		}
		$m = $o[0];
		$mc = $o[1];
	
		if ( is_array($aout) ) {
			$aout[0] = $a;
			$aout[1] = $m;
			$aout[2] = $mc;
		}
	
		return '' . $a . '/' . $m . '/' . $mc;
	}
	
	// check whether IP4 address $addr is in the
	// network $net when $mask is applied; $mask
	// may be an integer (CIDR) or dotted quad
	public static function is_addr_in_net($addr, $net, $mask)
	{
		if ( ($a = ip2long($addr)) === false ) {
			return false;
		}
		if ( ($n = ip2long($net)) === false ) {
			return false;
		}
		$o = array();
		if ( self::netmask_norm('' . $mask, $o) !== true ) {
			return false;
		}
		if ( ($m = ip2long($o[1])) === false ) {
			return false;
		}
	
		return (($a & $m) === ($n & $m));
	}
	
}
endif; // if ( ! class_exists() ) :

if ( php_sapi_name() === 'cli' ) {
	$C = 'NetMisc_0_0_1';

	// checks
	$chks = array(
		'46.118.113.0/255.255.0.0/15',
		'46.118.113.0/255.254.0.0/15',
		'46.118.113.0/16/255.254.0.0',
		'46.118.113.0/15/255.254.0.0',
		'46.118.113.0/255.255.255.254/31',
		'46.118.113.0/31/255.255.255.254',
		'46.118.113.0/128.0.0.0/1',
		'46.118.113.0/1/128.0.0.0',
		'46.118.113.0/29',
		'46.118.113.0/255.255.255.248/29',
		'46.118.113.0/255.255.255.247/29',
		'46.118.113.0/255.255.255.249/29',
		'46.118.113.0/255.240.0.0/12',
		'46.118.113.0/12',
		'46.118.113.0/255.255.248.0/29',
		'46.118.113.0/255.248.0.0/29',
		'46.118.113.0/14/255.232.0.0',
		'46.118.113.0/11/255.232.0.0',
		'46.118.113.0/255.224.0.0',
		'255.255.127.63'
	);
	$o = array();
	foreach ( $chks as $v ) {
		$r = $C::netaddr_norm($v);
		if ( $r === false ) {
			$r = 'false';
		}
		printf("arg '%s' === '%s'\n", $v, '' . $r);
	}
	
	// checks
	$chks = array(
		array('46.118.113.0', '12',
			array('46.118.113.140', '46.118.127.49',
				'46.119.113.12', '46.119.125.183')
		),
		array('46.118.113.0', '12',
			array('46.18.113.140', '46.218.127.49',
				'46.19.113.12', '46.219.125.183')
		),
		array('46.118.113.0', '10',
			array('46.118.113.140', '46.118.127.49',
				'46.119.113.12', '46.119.125.183')
		),
		array('46.118.113.0', '16',
			array('46.118.113.140', '46.118.127.49',
				'46.119.113.12', '46.119.125.183')
		)
	);
	
	foreach ( $chks as $aa ) {
		$net = $aa[0];
		$mask = $aa[1];
		foreach ( $aa[2] as $a ) {
			$t = $C::is_addr_in_net($a, $net, $mask);
			printf(
				"%s -- %s is %sin net %s with mask %s\n",
				$t ? 'True' : 'False', $a, $t ? '' : 'not ',
				$net, $mask
			);
		}
	}
}

?>
