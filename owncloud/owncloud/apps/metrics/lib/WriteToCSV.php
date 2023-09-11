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
use OCA\Metrics\Metrics\FilesMetrics;
use OCA\Metrics\Metrics\QuotaMetrics;
use OCA\Metrics\Metrics\SharesMetrics;
use OCA\Metrics\Metrics\UserActiveMetrics;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDateTimeFormatter;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class WriteToCSV {
	/**
	 * @var UserActiveMetrics
	 */
	private $userActiveMetrics;
	/**
	 * @var FilesMetrics
	 */
	private $filesMetrics;
	/**
	 * @var SharesMetrics
	 */
	private $sharesMetrics;
	/**
	 * @var QuotaMetrics
	 */
	private $quotaMetrics;
	/**
	 * @var CsvEncoder
	 */
	private $csvEncoder;
	/**
	 * @var ITimeFactory
	 */
	private $timeFactory;
	/**
	 * @var IDateTimeFormatter
	 */
	private $dateTimeFormatter;
	/**
	 * @var CombinedUserMetrics
	 */
	private $combinedUserMetrics;

	/**
	 * WriteToCSV constructor.
	 *
	 * @param UserActiveMetrics $userActiveMetrics
	 * @param FilesMetrics $filesMetrics
	 * @param SharesMetrics $sharesMetrics
	 * @param QuotaMetrics $quotaMetrics
	 * @param CsvEncoder $csvEncoder
	 * @param ITimeFactory $timeFactory
	 * @param IDateTimeFormatter $dateTimeFormatter
	 * @param CombinedUserMetrics $combinedUserMetrics
	 */
	public function __construct(
		UserActiveMetrics $userActiveMetrics,
		FilesMetrics $filesMetrics,
		SharesMetrics $sharesMetrics,
		QuotaMetrics $quotaMetrics,
		CsvEncoder $csvEncoder,
		ITimeFactory $timeFactory,
		IDateTimeFormatter $dateTimeFormatter,
		CombinedUserMetrics $combinedUserMetrics
	) {
		$this->userActiveMetrics = $userActiveMetrics;
		$this->filesMetrics = $filesMetrics;
		$this->sharesMetrics = $sharesMetrics;
		$this->quotaMetrics = $quotaMetrics;
		$this->csvEncoder = $csvEncoder;
		$this->dateTimeFormatter = $dateTimeFormatter;
		$this->timeFactory = $timeFactory;
		$this->combinedUserMetrics = $combinedUserMetrics;
	}

	public function getUsersCSVData(): string {
		$overAllQuota = $this->quotaMetrics->getTotalQuotaUsage();
		$total = $overAllQuota['total'] ?? 0;

		$result = $this->combinedUserMetrics->getCombinedData($total);
		$encoder = [$this->csvEncoder];
		/**
		 * Couldn't inject ObjectNormalizer, an error was thrown. Hence, initialized
		 * here.
		 */
		$normalizer = [new ObjectNormalizer()];

		return (new Serializer($normalizer, $encoder))->serialize($result, 'csv');
	}

	/**
	 * Get the name of the attached file
	 *
	 * @return string returns the name of the file which can be downloaded by user
	 */
	public function getAttachFileName($type): string {
		return 'DataMetrics-' . $this->dateTimeFormatter->formatDateTime($this->timeFactory->getTime(), 'short') . '-' . $type . '.csv';
	}

	/**
	 * Get the system metrics data written in cvs file format
	 *
	 * @return string, if csv data is retrieved then its returned as string else false.
	 */
	public function getSystemCSVData(): string {
		$result = [];

		/** storage */
		$quota = $this->quotaMetrics->getTotalQuotaUsage();
		$result['freeStorage'] = $quota['free'];
		$result['usedStorage'] = $quota['used'];

		/** files */
		$files = $this->filesMetrics->getTotalFilesCount();
		$result['totalFiles'] = $files['totalFiles'];

		/** users */
		$result['registeredUsers'] = $this->userActiveMetrics->getTotalUserCount();
		$result['activeUsers'] = $this->userActiveMetrics->getCurrentActiveUsers();
		$result['concurrentUsers'] = $this->userActiveMetrics->getConcurrentUsers();

		/** shares **/
		$shares = $this->sharesMetrics->getTotalShares();
		$result['userShares'] = $shares['userShareCount'];
		$result['groupShares'] = $shares['groupShareCount'];
		$result['linkShares'] = $shares['linkShareCount'];
		$result['guestShares'] = $shares['guestShareCount'];
		$result['federatedShares'] = $shares['federatedShareCount'];

		$encoder = [$this->csvEncoder];
		/**
		 * Couldn't inject ObjectNormalizer, an error was thrown. Hence, initialized
		 * here.
		 */
		$normalizer = [new ObjectNormalizer()];

		return (new Serializer($normalizer, $encoder))->serialize($result, 'csv');
	}
}
