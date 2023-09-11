<?php
/**
 * ownCloud
 *
 * @author Juan Pablo Villafañez Ramos <jvillafanez@owncloud.com>
 * @copyright Copyright (c) 2023, ownCloud GmbH
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\windows_network_drive\lib\kerberos;

/**
 * Class to wrap methods related to the creation of the credential cache.
 * These methods can't be unit tested, so this class will help the unit tests
 * of the classes depending on this one.
 */
class CCacheCreator {
	/**
	 * Create a new credential cache in the target location. The cache will
	 * be initialized with the service, keytab and params provided.
	 * See KRB5CCache::initKeytab method for more info about the parameters
	 * @param string $targetLocation the location where the credential cache
	 * will be created
	 * @param string $service
	 * @param string $keytab
	 * @param array $params
	 */
	public function createNewCCache($targetLocation, $service, $keytab, $params) {
		$ccache = new \KRB5CCache();
		$ccache->initKeytab($service, $keytab, $params);
		$ccache->save($targetLocation);
	}

	/**
	 * Acquire a ticket for the remote user. The server's credentials cache will
	 * be copied to the target location so it can be overwritten safely by kvno
	 *
	 * This method will return the exit code and the output of the kvno command.
	 * The output will be an array of lines, as provided by the underlying exec call
	 *
	 * If the command fails, the target location will be removed since the credentials
	 * will be unusuable.
	 *
	 * This method relies on the `kvno` command (from the krb5-user ubuntu
	 * package), so it must be present and usable.
	 * @param string $targetLocation the location where the credentials cache
	 * for the server is. As said, you should use a copy of the cache because
	 * it will overwritten.
	 * @param string $remoteUser the remote user to whom we want to get a ticket.
	 * This is a windows / samba account
	 * @param string $remoteServer the remote server where we want to connect.
	 * This is usually a dns name (ips are likely to be rejected by kerberos)
	 * @param string $serverKeytab the location of the server's keytab file
	 */
	public function acquireTicketForUser($remoteUser, $remoteServer, $serverKeytab, $serverCCache, $targetLocation): array {
		\copy($serverCCache, $targetLocation);

		$escapedRemoteUser = \escapeshellarg($remoteUser);
		$escapedTargetLocation = \escapeshellarg($targetLocation);
		$escapedServerKeytab = \escapeshellarg($serverKeytab);
		$escapedRemoteCifsService = \escapeshellarg("cifs/{$remoteServer}");
		$command = "kvno -U {$escapedRemoteUser} -P -c {$escapedTargetLocation} -k {$escapedServerKeytab} {$escapedRemoteCifsService} 2>&1";
		exec($command, $output, $code);

		if ($code !== 0) {
			\unlink($targetLocation);
		}

		return [
			'exitCode' => $code,
			'output' => $output,
		];
	}
}
