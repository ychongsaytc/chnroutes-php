#!/usr/bin/env php
<?php
/**
 * chnroutes PHP version
 *
 * @author Yuan CHong <ychongsaytc@gmail.com>
 * @copyright Young Ng <fivesheep@gmail.com>
 * @link https://github.com/fivesheep/chnroutes
 *
 * @package chnroutes
 */


/**
 * some UI texts
 */
define( 'INFO_NO_PLATFORM_SPECIFIED', '- Please specify a platform.' );
define( 'INFO_WRONG_PLATFORM'       , '- Wrong platform given.' );
define( 'INFO_FETCHING'             , '- Fetching data from APNIC ...' );
define( 'INFO_ANALYSING'            , '- Analysing data ...' );
define( 'INFO_WRITING'              , '- Writing files ...' );
define( 'INFO_WRITING_FOR_MAC'      , '- Writing files for Mac OS ...' );
define( 'INFO_WRITING_FOR_OPENWRT'  , '- Writing files for OpenWrt ...' );
define( 'INFO_DONE'                 , '- Done.' );


/** if no arguments given */
if ( ! isset( $argv[1] ) ) {
	print INFO_NO_PLATFORM_SPECIFIED . "\n";
	exit();
}


/**
 * @var array configuration
 */
$GLOBALS['config'] = require __DIR__ . '/config.php';


/**
 * @var string the platform identifier
 */
$GLOBALS['platform'] = $argv[1];


/**
 * @var array the global network data fetched from APNIC
 */
$GLOBALS['ip_data'] = _fetch_ip_data();


switch ( $GLOBALS['platform'] ) {
	case 'mac':
		_generate_for_mac();
		break;
	case 'openwrt':
		_generate_for_openwrt();
		break;
	case 'all':
		_generate_for_mac();
		_generate_for_openwrt();
		break;
	default:
		print INFO_WRONG_PLATFORM . "\n";
		break;
}

print INFO_DONE . "\n";


/**
 * fetch ip data from APNIC
 *
 * @return array the formatted data
 */
function _fetch_ip_data() {
	print INFO_FETCHING . "\n";
	$raw = file_get_contents( 'http://ftp.apnic.net/apnic/stats/apnic/delegated-apnic-latest' );
	print INFO_ANALYSING . "\n";
	$matches = array();
	preg_match_all( '#\napnic\|CN\|ipv4\|([0-9\.]+)\|([0-9]+)\|([0-9]+)\|\S+#', $raw, $matches );
	$data = array();
	foreach ( array_keys( $matches[0] ) as $index ) {
		$ip_count = $matches[2][ $index ];
		$netmask = implode( '.', array_map( 'hexdec', str_split( dechex( 0xffffffff ^ ( $ip_count - 1 ) ), 2 ) ) );
		$data[] = array(
			'net' => $matches[1][ $index ],
			'netmask' => $netmask,
			'net_bits' => ( 32 - log( $ip_count, 2 ) ),
		);
	}
	return $data;
}


/**
 * write files
 *
 * @param array $files the file names and contents
 */
function _write_files( $files, $directory ) {
	$directory_path = dirname( __FILE__ ) . '/' . $directory;
	foreach ( $files as $filename => $content ) {
		if ( ! file_exists( $directory_path ) ) {
			mkdir( $directory_path );
		}
		file_put_contents( $directory_path . '/' . $filename, $content );
	}
}


/**
 * process to generate executable files for Mac OS X
 */
function _generate_for_mac() {
	$files = array();
	$files['ip-up'] = <<< 'EOF'
#!/bin/sh
export PATH="/bin:/sbin:/usr/sbin:/usr/bin"

OLDGW=`netstat -nr | grep '^default' | grep -v 'ppp' | sed 's/default *\([0-9\.]*\) .*/\1/' | awk '{if($1){print $1}}'`

if [ ! -e "/tmp/pptp_original_gateway" ]; then
    echo ${OLDGW} > "/tmp/pptp_original_gateway"
fi

dscacheutil -flushcache

route add 10.0.0.0/8 ${OLDGW}
route add 172.16.0.0/12 ${OLDGW}
route add 192.168.0.0/16 ${OLDGW}


EOF;
	$files['ip-down'] = <<< 'EOF'
#!/bin/sh
export PATH="/bin:/sbin:/usr/sbin:/usr/bin"

if [ ! -e "/tmp/pptp_original_gateway" ]; then
        exit 0
fi

ODLGW=`cat "/tmp/pptp_original_gateway"`

route delete 10.0.0.0/8 ${OLDGW}
route delete 172.16.0.0/12 ${OLDGW}
route delete 192.168.0.0/16 ${OLDGW}


EOF;
	foreach ( $GLOBALS['ip_data'] as $entry ) {
		$files['ip-up'] .= sprintf( 'route add %s/%d ${OLDGW}' . "\n", $entry['net'], $entry['net_bits'] );
		$files['ip-down'] .= sprintf( 'route delete %s/%d ${OLDGW}' . "\n", $entry['net'], $entry['net_bits'] );
	}
	foreach ( $GLOBALS['config']['whitelist'] as $net ) {
		$files['ip-up'] .= sprintf( 'route add %s/%d ${OLDGW}' . "\n", $net, 32 );
		$files['ip-down'] .= sprintf( 'route delete %s/%d ${OLDGW}' . "\n", $net, 32 );
	}
	$files['ip-down'] .= "\n" . 'rm "/tmp/pptp_original_gateway"' . "\n";
	print INFO_WRITING_FOR_MAC . "\n";
	_write_files( $files, 'mac' );
}


/**
 * process to generate executable files for OpenWrt
 */
function _generate_for_openwrt() {
	$files = array();
	$files['ip-pre-up'] = <<< 'EOF'
#!/bin/sh
export PATH="/bin:/sbin:/usr/sbin:/usr/bin"

OLDGW=`ip route show | grep '^default' | sed -e 's/default via \([^ ]*\).*/\1/'`

if [ $OLDGW == '' ]; then
	exit 0
fi


EOF;
	$files['ip-pre-down'] = <<< 'EOF'
#!/bin/sh
export PATH="/bin:/sbin:/usr/sbin:/usr/bin"


EOF;
	foreach ( $GLOBALS['ip_data'] as $entry ) {
		$files['ip-pre-up'] .= sprintf( 'route add -net %s netmask %s gw $OLDGW' . "\n", $entry['net'], $entry['netmask'] );
		$files['ip-pre-down'] .= sprintf( 'route del -net %s netmask %s' . "\n", $entry['net'], $entry['netmask'] );
	}
	foreach ( $GLOBALS['config']['whitelist'] as $net ) {
		$files['ip-pre-up'] .= sprintf( 'route add -net %s netmask %s gw $OLDGW' . "\n", $net, '255.255.255.255' );
		$files['ip-pre-down'] .= sprintf( 'route del -net %s netmask %s' . "\n", $net, '255.255.255.255' );
	}
	print INFO_WRITING_FOR_OPENWRT . "\n";
	_write_files( $files, 'openwrt' );
}

