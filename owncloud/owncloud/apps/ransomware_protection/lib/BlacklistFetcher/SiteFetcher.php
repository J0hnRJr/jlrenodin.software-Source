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

use OCP\Http\Client\IClientService;

class SiteFetcher implements IBlacklistFetcher {
	/** @var IClientService */
	private $clientService;

	/**
	 * @param IClientService $clientService
	 */
	public function __construct(IClientService $clientService) {
		$this->clientService = $clientService;
	}

	/**
	 * Fetch the blacklist from an external http site. Such site must
	 * be accessible by either http or https protocol. All the information
	 * needed to access must be in the url, this is important in case of
	 * authentication.
	 * The site should return a successful http code, and the body must be
	 * json-formatted and contain a 'filters' key with the whole list of patterns
	 * for the blacklist.
	 * https://fsrm.experiant.ca/api/v1/get is used as base. All the target sites
	 * are expected to behave the same.
	 *
	 * @param string $location an http site such as https://fsrm.experiant.ca/api/v1/get
	 * Note that it must respond with the same format
	 * @return array the patterns making the blacklist
	 * @throws BlacklistFetcherException if a 400 or 500 code is got, or the body
	 * doesn't contain the target 'filters' key in the json response.
	 */
	public function fetchBlacklist(string $location) {
		$client = $this->clientService->newClient();
		$response = $client->get($location);
		$statusCode = $response->getStatusCode();
		if ($statusCode >= 400) {
			throw new BlacklistFetcherException("Request to the site failed with status code {$statusCode}");
		}

		$body = $response->getBody();
		$jsonData = \json_decode($body, true);
		if ($jsonData === false || !isset($jsonData['filters'])) {
			throw new BlacklistFetcherException("Unexpected data found in the response");
		}

		return $jsonData['filters'];
	}
}
