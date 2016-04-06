<?php
/*
 * BLCheckResult.inc.php
 * 
 * Copyright 2016 Ed Hynan <edhynan@gmail.com>
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

/* text editor: use real tabs of 4 column width, LF line ends */
/* human coder: keep line length <= 72 columns; break at params */

/**********************************************************************\
 * A structure to hold results of address check -- all public for     *
 * easy access, as in a C struct.                                     *
 *                                                                    *
 * This would have been preferable as a class nested within the using *
 * class, but that cannot be done in PHP presently.                   *
\**********************************************************************/

class BLCheckResult {
	// help detect class name conflicts; called by using code
	const evh_opt_id = 0xED00AA33;
	public static function id_token () {
		return self::evh_opt_id;
	}

	// type of hit producing this result: null until initialized,
	// false for non-hit, else descriptive short string
	public $type;			// (null, false, string)

	// data per result type
	public $dat;			// (null, variable)
	
	// ctor
	public function __construct($type = null, $dat = null) {
		$this->type = $type;
		$this->dat =  $dat;
	}
}

/**********************************************************************\
 * A structure to hold results of DNSBL check -- when structure       *
 * BLCheckResult->type is "DNSBL",  BLCheckResult->dat should be an   *
 * instance of DNSBLCheckResult.                                      *
\**********************************************************************/

class DNSBLCheckResult {
	// true if return from DNS query passed hit test
	public $is_hit;			// (boolean)

	// return from DNS
	public $dns_ret;		// (string [ip address])
	
	// the domain used in query
	public $dns_dom;		// (string [dns domain])
	
	// ctor
	public function __construct(
					$is_hit = null, $dns_ret = null, $dns_dom = null) {
		$this->is_hit  = $is_hit;
		$this->dns_ret = $dns_ret;
		$this->dns_dom = $dns_dom;
	}
}

?>
