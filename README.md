# Geoiplookup2 for GeoLite2 Country (mmdb)

A simple drop-in replacement for the standard `geoiplookup` to use the free
**[GeoLite2 Country](https://dev.maxmind.com/geoip/geoip2/geolite2/)** mmdb database.
It is written in PHP and provides a cli binary Phar (executable) file for easy use.

Geoiplookup2 uses the `symfony/console` and `geoip2/geoip2` open source libraries.

## Features

- Drop in replacement for the legacy `geoiplookup` tool (`geoiplookup 8.8.8.8`)
- Built-in functionality to update GeoLite2-Country (free) database (`geoiplookup db-update`)
- Supports IPv4, IPv6 and FQDN (`geoiplookup ubuntu.com`)
- Allow return of just the country name or ISO code (`geoiplookup 8.8.8.8 -i`)
- Built-in binary update option (`geoiplookup self-update`)


## Requirements

- php-cli (>=5.6) with at least the following modules: php-phar, php-curl, php-openssl, php-json & php-iconv
- Free Maxmind GeoLite2 Country database (downloaded/updated via `geoiplookup db-update`)


## Installing

```shell
curl -sS https://raw.githubusercontent.com/axllent/geoiplookup2/master/install | php -- /usr/local/bin
```

The final argument is the directory that the script will be loaded into. If omitted, the geoiplookup script will be installed
into the current directory. If you don't have permission to write to the directory, `sudo` will be used to escalate permissions.


## Usage

See `geoiplookup -h` for all options.

```shell
$ geoiplookup 8.8.8.8
GeoIP Country Edition: US, United States

$ geoiplookup 8.8.8.8 -c
United States

$ geoiplookup 8.8.8.8 -i
US
```

## Updating the database

```shell
$ geoiplookup db-update
```

By default the update command will try move the database to `/usr/share/GeoIP/GeoLite2-Country.mmdb`.


## Updating geoiplookup

```shell
$ geoiplookup self-update
```

This will compare the current version to the latest release on github.
By default the update command will try install to `/usr/local/bin/geoiplookup`.


## Building the binary

In most cases you can just run:

```
sh build.sh
```

And this will download `box`, use `composer` to install the required packages, and build `geoiplookup.phar`.
