<?php

/**
 * @author Sujith Haridasan <sharidasan@owncloud.com>
 * @copyright (C) 2019 ownCloud GmbH
 * @license ownCloud Commercial License
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Metrics;

use OCA\Metrics\Metrics\CombinedUserMetrics;
use OCA\Metrics\Metrics\QuotaMetrics;
use OCP\IUserBackend;

class UserDataMetrics {
	/** @var QuotaMetrics */
	private $quotaMetrics;

	/** @var Helper */
	private $helper;
	/**
	 * @var CombinedUserMetrics
	 */
	private $combinedUserMetrics;

	/**
	 * UserDataMetrics constructor.
	 *
	 * @param QuotaMetrics $quotaMetrics
	 * @param CombinedUserMetrics $combinedUserMetrics
	 * @param Helper $helper
	 */
	public function __construct(
		QuotaMetrics $quotaMetrics,
		CombinedUserMetrics $combinedUserMetrics,
		Helper $helper
	) {
		$this->quotaMetrics = $quotaMetrics;
		$this->combinedUserMetrics = $combinedUserMetrics;
		$this->helper = $helper;
	}

	/**
	 * Gets the data of shares, files, quota of each user
	 *
	 * @param bool $includeFiles If files information should be included in the response
	 * @param bool $includeShares If shares information should be included in the response
	 * @param bool $includeQuota If quota information should be included in the response
	 * @param bool $includeUserInfo If user metadata should be included in the response
	 * @return array the array of user data
	 */
	public function getUserData($includeFiles, $includeShares, $includeQuota, $includeUserInfo): array {
		$result = [];

		$overAllQuota = $this->quotaMetrics->getTotalQuotaUsage();
		$total = $overAllQuota['total'] ?? 0;

		$data = $this->combinedUserMetrics->getCombinedData($total);
		foreach ($data as $d) {
			//Get the display name of user
			$result[$d->getUserId()]['displayName'] = $d->getUserDisplayName();
			$result[$d->getUserId()]['backend'] = $this->getBackendDisplayName($d->getUserBackend());

			if ($this->helper->isGuestUser($d->getUserId())) {
				$result[$d->getUserId()]['backend'] .= ' (Guest)';
			}

			//Get the total files count for the user
			if ($includeFiles) {
				$result[$d->getUserId()]['files'] = [
					'totalFiles' => $d->getFiles()
				];
			}

			//Get the shares count for the user
			if ($includeShares) {
				$result[$d->getUserId()]['shares'] = [
					'userShareCount' => $d->getSharesUser() - $d->getSharesGuest(),
					'groupShareCount' => $d->getSharesGroup(),
					'linkShareCount' => $d->getSharesLink(),
					'guestShareCount' => $d->getSharesGuest(),
					'federatedShareCount' => $d->getSharesFederated(),
				];
			}

			//Get the quota for the user
			if ($includeQuota) {
				$result[$d->getUserId()]['quota'] = [
					'free' => $d->getQuotaFree(),
					'total' => $d->getQuotaTotal(),
					'used' => $d->getQuotaUsed(),
					'relative' => 0,
				];
			}

			//Get the agents connected with the instance.
			if ($includeUserInfo) {
				$result[$d->getUserId()]['activeSessions'] = $d->getSessions();
				$result[$d->getUserId()]['lastLogin'] = $d->getLastLogin();
			}
		}

		return $result;
	}

	private function getBackendDisplayName(string $backendClass): string {
		$b = \OC::$server->getUserManager()->getBackend($backendClass);
		if ($b instanceof IUserBackend) {
			return $b->getBackendName();
		}
		return $backendClass;
	}
}
