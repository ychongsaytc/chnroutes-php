
## DEPRECATED

This project is deprecated and no longer being maintained, please use the [overthewall](https://github.com/ychongsaytc/docker-images/tree/master/overthewall) Docker Containers instaed.

# chnroutes-php

Chnroutes written in PHP, forked from [chnroutes by fivesheep](https://github.com/fivesheep/chnroutes).

## Features

- Supports PAC (Automatic Proxy Configuration)
- Supports [OpenWrt](https://openwrt.org/) platform (different from general Linux platform)
- Allows to set whitelist for passing through the VPN gateway or Proxy

## Usage

Run script with `php-cli` installed

### Mac

```sh
$ php -f chnroutes.php macos
```

Move scripts to PPP directory

```sh
$ sudo mv mac/ip-up /etc/ppp/
$ sudo mv mac/ip-down /etc/ppp/
```

Modifiy permissions or scripts

```sh
$ sudo chmod a+x /etc/ppp/ip-up
$ sudo chmod a+x /etc/ppp/ip-down
```

### OpenWrt

```sh
$ php -f chnroutes.php openwrt
```

Upload to router

```sh
$ scp openwrt/ip-pre-up root@192.168.1.1:/root/
$ scp openwrt/ip-pre-down root@192.168.1.1:/root/
```

Modifiy permissions or scripts on router

```sh
$ chmod a+x /root/ip-pre-up
$ chmod a+x /root/ip-pre-down
```

- After WAN and before VPN, run `sh /root/ip-pre-up`
- before VPN disconnected, run `sh /root/ip-pre-down`

### PAC for Auto Proxy

```sh
$ php -f chnroutes.php pac
```

Serve the configuration file for Automatic Proxy Configuration in your system preferences

```
pac/proxy.pac
```

