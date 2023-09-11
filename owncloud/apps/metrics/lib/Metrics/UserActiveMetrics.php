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

namespace OCA\Metrics\Metrics;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;

class UserActiveMetrics {
	/** @var ITimeFactory */
	private $timeFactory;

	/** @var IDBConnection */
	private $connection;

	/** @var IUserManager */
	private $userManager;

	/** @var int */
	public const TWO_WEEKS_AS_SECONDS = (14 * 24 * 60 * 60);

	/**
	 * UserActiveMetrics constructor.
	 *
	 * @param ITimeFactory $timeFactory
	 * @param IDBConnection $connection
	 * @param IUserManager $userManager
	 */
	public function __construct(
		ITimeFactory $timeFactory,
		IDBConnection $connection,
		IUserManager $userManager
	) {
		$this->timeFactory = $timeFactory;
		$this->connection = $connection;
		$this->userManager = $userManager;
	}

	/**
	 * Returns current timesamp
	 *
	 * @return int
	 */
	public function getTimeStamp(): int {
		return $this->timeFactory->getTime();
	}

	/**
	 * Get the total users in the oC instance
	 *
	 * @return int
	 */
	public function getTotalUserCount(): int {
		return \count($this->userManager->search(''));
	}

	/**
	 * Get the count of users who have active sessions
	 *
	 * @return int active users count
	 */
	public function getCurrentActiveUsers(): int {
		$activeUsers = 0;
		$currentTime = $this->timeFactory->getTime();
		// User is considered as active if they logged in within 2 weeks
		$activeTimeLimit = $currentTime - self::TWO_WEEKS_AS_SECONDS;
		$this->userManager->callForAllUsers(function (IUser $user) use (&$activeUsers, $activeTimeLimit) {
			$lastLogin = $user->getLastLogin();
			// if user is logged in within last 2 weeks then it iss an active user
			if ($lastLogin >= $activeTimeLimit) {
				$activeUsers++;
			}
		});

		return $activeUsers;
	}

	/**
	 * Gives the total users who have at least one active session
	 *
	 * @return int currently logged-in users count
	 */
	public function getConcurrentUsers(): int {
		$qb = $this->connection->getQueryBuilder();

		/**
		 * Expectation here is authtoken will have the list of users had
		 * connected in the past or still connected. The SQL query used is of
		 * the form:
		 * select DISTINCT uid from oc_authtoken;
		 *
		 */
		$qb->selectDistinct('uid')
			->from('authtoken');

		$concurrentUsersStatement = $qb->execute();
		/* @phan-suppress-next-line PhanDeprecatedFunction */
		$result = $concurrentUsersStatement->fetchAll();
		/* @phan-suppress-next-line PhanDeprecatedFunction */
		$concurrentUsersStatement->closeCursor();

		return \count($result);
	}
}
