
# A PHP version of chnroutes

- "Forked" from [chnroutes by fivesheep](https://github.com/fivesheep/chnroutes)
- Copyright [fivesheep](https://github.com/fivesheep)

## Features

- Add OpenWrt platform (different from general Linux platform)
- Allow to set whitelist for passing through the VPN gateway

## Usage

### Mac

Run script with `php-cli` installed

```
$ ./chnroutes.php mac
```

Move scripts to PPP directory

```
$ sudo mv mac/ip-up /etc/ppp/
$ sudo mv mac/ip-down /etc/ppp/
```

Modifiy permissions or scripts

```
$ sudo chmod a+x /etc/ppp/ip-up
$ sudo chmod a+x /etc/ppp/ip-down
```

### OpenWrt

Run script with `php-cli` installed

```
$ ./chnroutes.php openwrt
```

Upload to router

```
$ scp openwrt/ip-pre-up root@192.168.1.1:/root/
$ scp openwrt/ip-pre-down root@192.168.1.1:/root/
```

Modifiy permissions or scripts on router

```
$ chmod a+x /root/ip-pre-up
$ chmod a+x /root/ip-pre-down
```

- After WAN and before VPN, run `sh /root/ip-pre-up`
- before VPN disconnected, run `sh /root/ip-pre-down`
