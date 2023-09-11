<?php
/**
 * ownCloud - Ransomware Protection
 *
 * @copyright 2022 ownCloud GmbH
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Ransomware_Protection\BlacklistFetcher;

interface IBlacklistFetcher {
	/**
	 * Get the blacklist from the target location. The specification
	 * of the target location might depend on the implementation.
	 * For example, if the implementation will fetch the blacklist from a
	 * file, the location is expected to be a path pointing to that file.
	 * If the implementation will fetch it from an external site, the
	 * location could be an url
	 *
	 * The method will return an array with the wildcard patterns of the
	 * blacklist, one item per entry.
	 * For example ['*.foobar', '*.barfoo', 'ex.*']
	 *
	 * If any error happens, a BlacklistFetcherException must be thrown
	 *
	 * @param string $location the location where the blacklist is. The
	 * location must be understood by the implementation
	 * @return array the list of patterns making the blacklist
	 * @throws BlacklistFetcherException in case of error
	 */
	public function fetchBlacklist(string $location);
}
