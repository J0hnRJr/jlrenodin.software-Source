# Kerberos

This ownCloud app allows authenticating users using [SPNEGO](https://en.wikipedia.org/wiki/SPNEGO) authentication 

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes. See deployment for notes on how to deploy the project on a live system.

### Prerequisites

#### Single host setup
* A properly set up (Samba4) Active Directory Domain Controller 
* [php-krb5](https://pecl.php.net/package/krb5) - Kerberos, GSSAPI and KADM5 PHP bindings
* FQDN must be the same for:
  -  `hostname -f`, eg. `cloud.example.com`
  - the apache `ServerName` for the oc vhost, eg `ServerName cloud.example.com`
  - the Service Principal Name (SPN) in the keytab, eg. `HTTP/cloud.example.com@EXAMPLE.COM`
    - If you are using samba the keytab can be created as root with e.g.:
    ```console
    $ samba-tool user create www-data --random-password
    $ samba-tool user setexpiry  www-data --noexpiry
    $ samba-tool spn add HTTP/cloud.example.com www-data
    $ samba-tool spn add HTTP/cloud.example.com@EXAMPLE.COM www-data
    $ samba-tool domain exportkeytab --principal=HTTP/cloud.example.com /etc/apache2/www-data.keytab
    ```

#### Load balanced setup
https://ssimo.org/blog/id_019.html

### Installation
  
Install via pecl or if your distribution has a package use that. The following applies to Ubuntu 20.04 with ownCloud installed in /var/www/owncloud - if you use another distribution your mileage may vary.

```console
$ sudo apt install libkrb5-dev
$ sudo pecl install krb5
```

Check if `/etc/php/7.4/mods-available/krb5.ini` is properly created with

```
extension=krb5.so
```

if not create the file with the content above.

Finish installation of Apache module:

```console
$ sudo phpenmod krb5
$ sudo service apache2 restart
```

Check if the module is enabled, e.g. on cli:

```console
$ php -i | grep Kerb   
Kerberos 5 support => enabled
Library version => Kerberos 5 release 1.17
```

Enable the app:
```console
$ sudo -u www-data /var/www/owncloud/occ app:enable kerberos
```

### Configuration

Add a `config/kerberos.config.php` for the config options: 
```php
<?php $CONFIG = [

    /**
     * path to keytab to use, default is '/etc/krb5.keytab'
     */
    'kerberos.keytab' => '/etc/apache2/www-data.keytab',

    /**
     * timeout before re-enabling spnego based auth after logout, default is 60
     */
    'kerberos.suppress.timeout' => 60,

    /**
     * the domain name - remove from principals to match the pure username
     * e.g. alice@corp.dir will look for the user alice in ldap if 'kerberos.domain' is set to 'corp.dir'
     */
    'kerberos.domain' => '',
    
    /**
     * Name of login button on login page
     */
    'kerberos.login.buttonName' => 'Windows Domain Login',
    
    /**
     * If set to true the login page will immediately try to log in via Kerberos
     */
    'kerberos.login.autoRedirect' => false
];
```

A special apache configuration to redirect users to the kerberos auth endpoint is no longer necessary.

## Todo
- [ ] add mapper mechanism to allow changing the principal, e.g. truncate or lowercase realm?

## Authors

* **Jörn Friedrich Dreyer** - *Initial app* - [butonic](https://github.com/butonic)

## License

This code is covered by the ownCloud Commercial License.

You should have received a copy of the ownCloud Commercial License
along with this program. If not, see https://owncloud.com/licenses/owncloud-commercial/.
