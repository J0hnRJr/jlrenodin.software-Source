windows_network_drive
=====================

An app windows share mounting app with user password caching

### Notice
This app uses the a native smb library (libsmbclient) and PHP bindings to that library (libsmbclient-php). You'll need to have installed both and make the bindings available to PHP.

### Kerberos

#### Preparations in the ownCloud host
In order to use the kerberos authentication, the following packages must be installed (package names refer to ubuntu, other linux distributions might use different names):
- krb5-user (ubuntu package providing the `kvno` command)
- libkrb5-dev (in order to compile the php bindings for kerberos)
Use `pecl install krb5` to compile and install the php bindings. You'll need to enable module and restart apache as with any other php module.

The krb5.conf file might need to be adjusted.
Prepare krb5.conf including the info below to the relevant sections (data will need adjustments to the target realm, using MOUNTAIN.TREE.PRV as example)
```
[libdefaults]
  default_realm = MOUNTAIN.TREE.PRV
  kdc_timesync = 1
  ccache_type = 4
  forwardable = true
  proxiable = true
  fcc-mit-ticketflags = true

[realms]
  MOUNTAIN.TREE.PRV = {
    kdc = mountain.tree.prv
  }

[domain_realm]
  .mountain.tree.prv = MOUNTAIN.TREE.PRV
  mountain.tree.prv = MOUNTAIN.TREE.PRV
```

#### Creating a service account in Windows
1. Create new user "krb5httpoc" (for example). Set option "password never expired"
2. Create keytab file for the account
   (cmd shell) `ktpass /princ HTTP/ocserver.oc.com@OCSERVER.OC.COM /mapuser krb5httpoc +rndPass /out ocserver.oc.com.keytab /crypto all /ptype KRB5_NT_PRINCIPAL /mapop set`
3. Setup delegation
   Mark "Trust this user for delegation to specified services only"
   Add cifs/WIN-ABCDE.win.srv.com (choose the computer where the smb service is and select the cifs service)
4. Setup service account to be trusted to auth for delegation
   (powershell) `Set-ADAccountControl -Identity krb5httpoc -TrustedToAuthForDelegation $True`

The created keytab file in step 2 must be copied to the ownCloud host (it will be used as part of the configuration). Steps 3 and 4 are required to provide access, otherwise ownCloud won't be able to connect to the cifs service in Windows.

## QA metrics on master branch:

[![Build Status](https://drone.owncloud.com/api/badges/owncloud/windows_network_drive/status.svg)](https://drone.owncloud.com/owncloud/windows_network_drive)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=owncloud_windows_network_drive&metric=alert_status&token=209ba7740a4f62d94003c52cc7ff9ad4b8d090e5)](https://sonarcloud.io/dashboard?id=owncloud_windows_network_drive)
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=owncloud_windows_network_drive&metric=security_rating&token=209ba7740a4f62d94003c52cc7ff9ad4b8d090e5)](https://sonarcloud.io/dashboard?id=owncloud_windows_network_drive)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=owncloud_windows_network_drive&metric=coverage&token=209ba7740a4f62d94003c52cc7ff9ad4b8d090e5)](https://sonarcloud.io/dashboard?id=owncloud_windows_network_drive)
## Config.php options
```
    /**
     * The listener will reconnect to the DB after those seconds. This will
     * prevent the listener to crash if the connection to the DB is closed after
     * being idle for a long time.
     */
    'wnd.listen.reconnectAfterTime' => 28800,

    /**
     * Enable additional debug logging for WND
     */
    'wnd.logging.enable' => false,

    /**
     * Enable / disable the in-memory notifier for password changes. Having this
     * feature enabled implies that whenever we detect a wrong password in the
     * storage (maybe the password changed in the backend), all WND storage that
     * are in-memory will be notified in order to reset their passwords if applicable.
     * This is intended to prevent a password lockout for the user in the backend
     * Note that this feature can take a lot of memory resources because all the
     * WND storages will be in-memory.
     * Proper fix is expected to come with PHP 7.4. Alternatively, you can disable
     * the feature in order to reduce memory usage
     */
    'wnd.in_memory_notifier.enable' => true

    /**
     * Listen to the events triggered by the smb_acl app.
     * The current use is to update the WND storages (with "login credentials,
     * saved in DB" authentication) when an ACL changes via the smb_acl app
     */
    'wnd.listen_events.smb_acl' => false,

    /**
     * The way the file attributes for folders and files will be handled.
     * There are 3 possible values: "none", "stat" and "getxattr".
     * - "stat". This is the default in case the option is missing or has an invalid value.
     * This means that the file attributes will be evaluated only for files, NOT for folders.
     * Folders will be shown even if the "hidden" file attribute * is set.
     * - "none". This means that the file attributes won't be evaluated in any case. Both
     * hidden files and folders will be shown, and you can write on read-only files (the
     * action is available in ownCloud, but it will fail in the SMB server).
     * - "getxattr". This means that file attributes will be evaluated always. However, due to
     * problems in recent libsmbclient versions (4.11+, it might be earlier) it will cause
     * malfunctions in ownCloud; permissions are wrongly evaluated. So far, this mode works
     * with libsmbclient 4.7 but not with 4.11+ (not tested with any version in between)
     *
     * Note that the ACLs (if active) will be evaluated and applied on top of this mechanism.
     */
    'wnd.fileInfo.parseAttrs.mode' => 'stat',

    /**
     * The maximum number of items that the cache used by the permission managers
     * will allow. A higher number implies that more items are allowed, increasing
     * the memory usage.
     * Real memory usage per item varies because it depends on the path being cached.
     * Note that this is an in memory cache used per request.
     * The CachingProxyPermissionManager (used when asked for the ocLdapPermissionManager)
     * will use a cache instance shared among the instances of the same class. This means
     * that multiple mounts using the ocLdapPermissionManager will share the same
     * cache, limiting the maximum memory that will be used
     */
    'wnd.permissionmanager.cache.size' => 512,

    /**
     * The WND app doesn't have knowledge about the users or groups associated to ACLs.
     * This means that an ACL containing "admin" might refer to a user called "admin" or a
     * group called "admin".
     * By default, the group membership component consider the ACLs to target groups, and
     * as such, it will try to get the information of such group. This works fine if the
     * majority of the ACLs target groups.
     * If the majority of the ACLs contains users, this might be problematic. The cost of
     * getting the information of a group is usually higher than getting the information
     * of a user. As such, this option make the group membership component to assume the
     * ACL contains a user, and check whether there is a user in ownCloud with such name first.
     * If the name doesn't refer to a user, it will get the group information. Note that,
     * this will have performance implications if the group membership component can't discard
     * users in a good number of cases.
     * It's recommended to enable this option only if there is a high number of ACLs targeting
     * users
     */
    'wnd.groupmembership.checkUserFirst' => false,

    /**
     * TTL to be used to cache information for the cache wrapper for the WND2 (collaborative)
     * implementation. The value will be used by all WND2 storages.
     * Although the cache isn't exactly per user but per storage id, consider the cache to
     * be per user because it will be like that for common use cases.
     * Data will remain in the cache and won't be removed (from ownCloud), so aim for a low
     * ttl value in order to not fill the memcache completely
     * In order to disable caching, use -1 (or any negative value). 0 isn't considered a valid
     * value (we don't want to store values without ttl) and will also disable caching.
     */
    'wnd2.cachewrapper.ttl' => 1800,  // 30 minutes

    /**
     * Use NFD compatibility for WND collaborative the same way that it's done for external
     * storages. By default, no normalization will be performed.
     * In order to use this properly, the "NFD compatibility" checkbox must be checked for the
     * mount point, otherwise there could be problems.
     * Note that this option will apply to all the WND collaborative mount points in the system.
     */
    'wnd2.cachewrapper.normalize' => false,

    /**
     * Register an extension into the Activity app in order to send information about
     * what the wnd:process-queue command is doing.
     * The activity sent will be based on what the wnd:process-queue detects, and the
     * activity will be sent to each affected user. There won't be any activity being sent
     * outside of the wnd:process-queue command.
     * wnd:listen + wnd:process-queue + activity app is required for this to work properly.
     */
    'wnd.activity.registerExtension' => false,

    /**
     * The wnd:process-queue command will also send activity notifications to the sharees
     * if a WND file or folder is shared (or accessible via a share).
     * It's REQUIRED that the "wnd.activity.registerExtension" flag is set to true
     * (see above), otherwise this flag will be ignored.
     * This flag depends on the "wnd.activity.registerExtension", and it has the same
     * restrictions
     */
    'wnd.activity.sendToSharees' => false,

    /**
     * The timeout (in ms) for all the operations against the backend.
     * The same timeout will be applied for all the connections.
     * 
     * Increase it if requests to the server sometimes time out. This can happen when SMB3 
     * encryption is selected and smbclient is overwhelming the server with requests.
     */
    'wnd.connector.opts.timeout' => 20000,

    /**
     * A map of servers with the required data to get the kerberos credentials
     * in order to access them.
     *
     * Each key of the map must be unique and identifies a server. This id will
     * be used in the web UI to configure the mount points to use the kerberos
     * authentication. You can use any id (choose one meaningful and easy to remember).
     *
     * The data contained in each key is as follows:
     * + 'ockeytab' (required): The location of the keytab file that ownCloud
     * will use to access to the mounts using that server id. The keytab must
     * be for a service account with special privileges, in particular, it must
     * be able to impersonate the users. It highly recommended that the password
     * for this service account doesn't expire, otherwise you'll have to replace
     * the file manually before the expiration.
     * + 'ocservice' (required): The name of the service of the account. This matches
     * the spn of the windows / samba account. It usually is in the form "HTTP/<server>",
     * but it might be different.
     * + 'usermapping' (optional): The oc-to-windows user mapping to be used. See below
     * for available options. If no user mapping is provided, the "Noop" mapping will
     * be used. The mapping data contains the type of mapping and the parameters if any.
     * + 'ccachettl' (optional): The time (in seconds) that the credential cache
     * will be considered as valid from the ownCloud's side. This TTL MUST be
     * lower than the actual TTL. Once the TTL is over, new credentials will be
     * requested automatically. The default TTL is 9 hours, which is less than
     * the 10 hours set by windows by default.
     *
     * Available mapping types:
     * + 'Noop': Do not perform any mapping. The ownCloud user id will be
     * returned without changes, so it's expected that the ownCloud user id
     * matches the windows / samba user id.
     * + 'RemoveDomain': Remove the domain (if any) from the ownCloud user id.
     * This means that "user001@my.dom.com" will map to "user001". Note that
     * it's assumed that all users belong to the same domain, otherwise
     * "user001@my.dom.com" will be mapped to the same windows user as
     * "user001@not.mine.eu".
     */
    'wnd.kerberos.servers' => [
      'server1' => [
        'ockeytab' => '/var/www/owncloud/octest1.mountain.tree.prv.keytab',
        'ocservice' => 'HTTP/octest1.mountain.tree.prv',
        'usermapping' => ['type' => 'Noop'],
        'ccachettl' => 60 * 60 * 9,
      ],
      'server11' => [
        'ockeytab' => '/var/www/owncloud/octest1.mountain.tree.prv.keytab',
        'ocservice' => 'HTTP/octest1.mountain.tree.prv',
        'usermapping' => ['type' => 'RemoveDomain'],
        'ccachettl' => 60 * 60 * 9,
      ],
      'server2' => [
        'ockeytab' => '/var/www/owncloud/octest0.desert.sand.prv.keytab',
        'ocservice' => 'HTTP/octest0.desert.sand.prv',
        'usermapping' => ['type' => 'CustomFile', 'params' => ['mapfile' => '/var/www/owncloud/ocwin_krb5_map.json']],
        'cachettl' => 3600,
      ],
    ],
```
