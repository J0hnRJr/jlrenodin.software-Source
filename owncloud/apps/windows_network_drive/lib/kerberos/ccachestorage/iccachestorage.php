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

namespace OCA\windows_network_drive\lib\kerberos\ccachestorage;

interface ICCacheStorage {
	/**
	 * Get the recommended location where the ccache for the serverId and userId
	 * should be created.
	 * This is intended to prevent unneeded file movements by creating the ccache
	 * in the right location directly.
	 * This method must return either the path where the ccache should be placed, or
	 * null if the ccache will be stored in a different place (a DB, another server, etc)
	 *
	 * As a safety messure, the caller should still call the `storeFrom` method to ensure
	 * the data will end in the right location. The `storeFrom` implementation should check
	 * if the $ccacheFilename used is the recommended one and move the data to the right
	 * place of not.
	 * There could be additional operations that the implementations are required to do
	 * in the `storeFrom` and `retrieveTo` methods, so just placing the ccache into the
	 * recommended location isn't enough to ensure proper behavior.
	 * @param string $serverId the serverId associated to the ccache
	 * @param string $userId the windows' user id associated to the ccache or the empty
	 * string if it's the ownCloud server
	 * @return string|null return the path where the ccache should be created
	 */
	public function getRecommendedLocation($serverId, $userId = '');

	/**
	 * Store the contents of the ccache. The contents of the $ccacheFilename will be
	 * copied to somewhere (a folder, a DB, another host, etc) based on the serverId and
	 * userId provided. This method shouldn't care about what happens to the $ccacheFilename
	 * afterwards, in particular it shouldn't remove the file.
	 * @param string $ccacheFilename the path to the ccache whose contents will be stored
	 * @param string $serverId the kerberos server id associated
	 * @param string $userId the windows' user id associated cache.
	 * Use the empty string if it's the ownCloud server the one associated to the ccache
	 * @return bool true if it's stored, false on error
	 */
	public function storeFrom($ccacheFilename, $serverId, $userId = ''): bool;

	/**
	 * Retrieve the data for the ccache associated with the storage id and user. The client
	 * will request the data to be placed in $ccacheFilename location, and the implementation
	 * should try to place the retrieved data there, but the actual location will be returned
	 * in the array.
	 * An empty array could be returned if the info couldn't be retrieved, likely due to
	 * missing associated data.
	 * @param string $ccacheFilename the path where the contents of the ccache will be stored to
	 * @param string $serverId the server id associated to the ccache
	 * @param string $userId the windows' user id associated to the ccache, the empty string
	 * if it's the server
	 * @return array an array containing the following keys: "ccacheFilename" -> the
	 * path where the ccache is; "creationTime" -> the timestamp when the ccache was stored
	 */
	public function retrieveTo($ccacheFilename, $serverId, $userId = ''): array;

	/**
	 * Delete the data for the ccache associated with the provided $serverId and $userId
	 * @param string $serverId the server id associated with the ccache
	 * @param string $userId the windows' user id associated to the ccache, the empty
	 * string if it's the server
	 * @return bool true on success, false on failure
	 */
	public function deleteEntry($serverId, $userId = ''): bool;

	/**
	 * Delete obsolete stored entries that are older than the provided timestamp
	 * @param int $timestamp
	 * @return int the number of entries deleted
	 */
	public function deleteEntriesOlderThan($timestamp): int;

	/**
	 * Delete all the data stored. This is expected to be used only for testing because
	 * the method is expected to remove everything.
	 */
	public function deleteAllEntries();
}
