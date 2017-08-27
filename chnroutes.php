#!/usr/bin/env php
<?php
/**
 * chnroutes PHP version
 *
 * @author Chon <ychongsaytc@gmail.com>
 * @copyright Young Ng <fivesheep@gmail.com>
 * @link https://github.com/fivesheep/chnroutes
 *
 * @package chnroutes
 */


ini_set( 'display_errors', 'On' );
error_reporting( E_ALL );


function _write_log( $content ) {
	print "\033[32m";
	print '- ';
	print $content;
	print "\033[0m";
	print PHP_EOL;
}


/**
 * some UI texts
 */
define( 'INFO_NO_PLATFORM_SPECIFIED', 'Please specify a platform.' );
define( 'INFO_WRONG_PLATFORM'       , 'Wrong platform given.' );
define( 'INFO_FETCHING'             , 'Fetching data from APNIC ...' );
define( 'INFO_ANALYSING'            , 'Analysing data ...' );
define( 'INFO_WRITING_FOR_MAC'      , 'Writing files for macOS ...' );
define( 'INFO_WRITING_FOR_OPENWRT'  , 'Writing files for OpenWrt ...' );
define( 'INFO_WRITING_PAC_FILE'     , 'Writing PAC file ...' );
define( 'INFO_DONE'                 , 'Done.' );


/** if no arguments given */
if ( ! isset( $argv[1] ) || ! in_array( $argv[1], array( 'all', 'macos', 'openwrt', 'pac' ) ) ) {
	_write_log( INFO_NO_PLATFORM_SPECIFIED );
	exit();
}


/**
 * @var array configuration
 */
$config_filepath = __DIR__ . '/config.php';
if ( file_exists( $config_filepath ) ) {
	$GLOBALS['config'] = require $config_filepath;
} else {
	$GLOBALS['config'] = require __DIR__ . '/config.sample.php';
}


/**
 * @var string the platform identifier
 */
$GLOBALS['platform'] = strtolower( $argv[1] );


/**
 * @var array the global network data fetched from APNIC
 */
$GLOBALS['ip_data'] = _fetch_ip_data();


switch ( $GLOBALS['platform'] ) {
	case 'macos':
		_generate_for_macos();
		break;
	case 'openwrt':
		_generate_for_openwrt();
		break;
	case 'pac':
		_generate_pac_file();
		break;
	case 'all':
		_generate_for_macos();
		_generate_for_openwrt();
		_generate_pac_file();
		break;
	default:
		_write_log( INFO_WRONG_PLATFORM );
		break;
}

_write_log( INFO_DONE );


/**
 * fetch ip data from APNIC
 *
 * @return array the formatted data
 */
function _fetch_ip_data() {
	_write_log( INFO_FETCHING );
	$raw = file_get_contents( 'http://ftp.apnic.net/apnic/stats/apnic/delegated-apnic-latest' );
	_write_log( INFO_ANALYSING );
	$matches = array();
	preg_match_all( '#\napnic\|CN\|ipv4\|([0-9\.]+)\|([0-9]+)\|([0-9]+)\|\S+#', $raw, $matches );
	$data = array();
	foreach ( array_keys( $matches[0] ) as $index ) {
		$ip_count = $matches[2][ $index ];
		$netmask = implode( '.', array_map( 'hexdec', str_split( dechex( 0xffffffff ^ ( $ip_count - 1 ) ), 2 ) ) );
		$data[] = array(
			'net'      => $matches[1][ $index ],
			'netmask'  => $netmask,
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
	foreach ( $files as $file_name => $file ) {
		if ( ! file_exists( $directory_path ) ) {
			mkdir( $directory_path );
		}
		$file_path = $directory_path . '/' . $file_name;
		file_put_contents( $file_path, $file['content'] );
		chmod( $file_path, $file['filemode'] );
	}
}


/**
 * process to generate executable files for Mac OS X
 */
function _generate_for_macos() {
	$files = array(
		'ip-up'   => array( 'content' => '', 'filemode' => 0755 ),
		'ip-down' => array( 'content' => '', 'filemode' => 0755 ),
	);
	$files['ip-up']['content'] = <<< 'EOF'
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
	$files['ip-down']['content'] = <<< 'EOF'
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
		$files['ip-up'  ]['content'] .= sprintf( 'route add %s/%d ${OLDGW}'    . PHP_EOL, $entry['net'], $entry['net_bits'] );
		$files['ip-down']['content'] .= sprintf( 'route delete %s/%d ${OLDGW}' . PHP_EOL, $entry['net'], $entry['net_bits'] );
	}
	foreach ( $GLOBALS['config']['whitelist'] as $net ) {
		$files['ip-up'  ]['content'] .= sprintf( 'route add %s/%d ${OLDGW}'    . PHP_EOL, $net, 32 );
		$files['ip-down']['content'] .= sprintf( 'route delete %s/%d ${OLDGW}' . PHP_EOL, $net, 32 );
	}
	$files['ip-down']['content'] .= PHP_EOL . 'rm "/tmp/pptp_original_gateway"' . PHP_EOL;
	_write_log( INFO_WRITING_FOR_MAC );
	_write_files( $files, 'macos' );
}


/**
 * process to generate executable files for OpenWrt
 */
function _generate_for_openwrt() {
	$files = array(
		'ip-pre-up'   => array( 'content' => '', 'filemode' => 0755 ),
		'ip-pre-down' => array( 'content' => '', 'filemode' => 0755 ),
	);
	$files['ip-pre-up']['content'] = <<< 'EOF'
#!/bin/sh
export PATH="/bin:/sbin:/usr/sbin:/usr/bin"

OLDGW=`ip route show | grep '^default' | sed -e 's/default via \([^ ]*\).*/\1/'`

if [ $OLDGW == '' ]; then
	exit 0
fi


EOF;
	$files['ip-pre-down']['content'] = <<< 'EOF'
#!/bin/sh
export PATH="/bin:/sbin:/usr/sbin:/usr/bin"


EOF;
	foreach ( $GLOBALS['ip_data'] as $entry ) {
		$files['ip-pre-up'  ]['content'] .= sprintf( 'route add -net %s netmask %s gw $OLDGW' . PHP_EOL, $entry['net'], $entry['netmask'] );
		$files['ip-pre-down']['content'] .= sprintf( 'route del -net %s netmask %s'           . PHP_EOL, $entry['net'], $entry['netmask'] );
	}
	foreach ( $GLOBALS['config']['whitelist'] as $net ) {
		$files['ip-pre-up'  ]['content'] .= sprintf( 'route add -net %s netmask %s gw $OLDGW' . PHP_EOL, $net, '255.255.255.255' );
		$files['ip-pre-down']['content'] .= sprintf( 'route del -net %s netmask %s'           . PHP_EOL, $net, '255.255.255.255' );
	}
	_write_log( INFO_WRITING_FOR_OPENWRT );
	_write_files( $files, 'openwrt' );
}


/**
 * process to generate PAC file
 */
function _generate_pac_file() {
	$files = array(
		'proxy.pac' => array( 'content' => '', 'filemode' => 0644 ),
	);
	$template = <<< 'EOF'

var proxy_direct = 'DIRECT';
var proxy_default = 'SOCKS5 127.0.0.1:1080; SOCKS 127.0.0.1:1080';

function FindProxyForURL( url, host ) {
	if ( false
		|| isPlainHostName( host )
		|| shExpMatch( host, '*.local' )
	) {
		return proxy_direct;
	}
	var ip = dnsResolve( host );
	var ipdata = %s;
	for ( var i in ipdata ) {
		if ( isInNet( ip, ipdata[i].net, ipdata[i].netmask ) ) {
			return proxy_direct;
		}
	}
	return proxy_default;
}


EOF;
	$ipdata = array_map( function ( $el ) {
		return [
			'net'     => $el['net'],
			'netmask' => $el['netmask'],
		];
	}, $GLOBALS['ip_data'] );
	$ipdata_for_pac = array_merge( [
		[
			'net'     => '10.0.0.0',
			'netmask' => '255.0.0.0',
		],
		[
			'net'     => '172.16.0.0',
			'netmask' => '255.240.0.0',
		],
		[
			'net'     => '192.168.0.0',
			'netmask' => '255.255.0.0',
		],
		[
			'net'     => '127.0.0.0',
			'netmask' => '255.255.255.0',
		],
	], $ipdata );
	foreach ( $GLOBALS['config']['whitelist'] as $net ) {
		$ipdata_for_pac[] = [
			'net'     => $net,
			'netmask' => '255.255.255.255',
		];
	}
	$files['proxy.pac']['content'] = sprintf( $template, json_encode( $ipdata_for_pac ) );
	_write_log( INFO_WRITING_PAC_FILE );
	_write_files( $files, 'pac' );
}

