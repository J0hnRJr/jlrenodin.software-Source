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

use Doctrine\DBAL\Platforms\OraclePlatform;
use OC\Share\Constants;
use OCP\IDBConnection;

class SharesMetrics {
	/** @var IDBConnection */
	private $connection;

	/**
	 * SharesMetrics constructor.
	 *
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * Get total share count and individual share counts
	 * for user share, public link share, group share and
	 * guest share.
	 *
	 * @return array fetches the result of shares
	 */
	public function getTotalShares(): array {
		$result = [];
		$result['userShareCount'] = $this->countSharesByType(Constants::SHARE_TYPE_USER);
		$result['groupShareCount'] = $this->countSharesByType(Constants::SHARE_TYPE_GROUP);
		$result['linkShareCount'] = $this->countSharesByType(Constants::SHARE_TYPE_LINK);
		$result['guestShareCount'] = $this->countGuestShares();
		if ($result['guestShareCount'] > 0) {
			$result['userShareCount'] -= $result['guestShareCount'];
		}
		$result['federatedShareCount'] = $this->countSharesByType(Constants::SHARE_TYPE_REMOTE);
		return $result;
	}

	private function countSharesByType(int $shareType): int {
		$statement = null;
		try {
			$qb = $this->connection->getQueryBuilder();
			$qb->selectAlias($qb->createFunction('COUNT(*)'), 'count')
				->from('share')
				->where($qb->expr()->eq('share_type', $qb->expr()->literal($shareType)));
			$statement = $qb->execute();
			/* @phan-suppress-next-line PhanDeprecatedFunction */
			return (int)$statement->fetch()['count'];
		} finally {
			if ($statement) {
				/* @phan-suppress-next-line PhanDeprecatedFunction */
				$statement->closeCursor();
			}
		}
	}

	private function countGuestShares(): int {
		$statement = null;
		try {
			$qb = $this->connection->getQueryBuilder();
			$qb->selectAlias($qb->createFunction('COUNT(*)'), 'guest_share')
				->from('share', 's')
				->innerJoin('s', 'preferences', 'p', $qb->expr()->eq('p.userid', 's.share_with'))
				->where($qb->expr()->eq('s.share_type', $qb->expr()->literal(Constants::SHARE_TYPE_USER)))
				->andWhere($qb->expr()->eq('p.appid', $qb->expr()->literal('owncloud')))
				->andWhere($qb->expr()->eq('p.configkey', $qb->expr()->literal('isGuest')));
			if ($this->connection->getDatabasePlatform() instanceof OraclePlatform) {
				//oracle can only compare the first 4000 bytes of a CLOB column
				$qb->andWhere(
					$qb->expr()->eq(
						$qb->createFunction("dbms_lob.substr(`configvalue`, 4000)"),
						$qb->expr()->literal('1')
					)
				);
			} else {
				$qb->andWhere(
					$qb->expr()->eq(
						'p.configvalue',
						$qb->expr()->literal('1')
					)
				);
			}

			$statement = $qb->execute();
			/* @phan-suppress-next-line PhanDeprecatedFunction */
			return (int)$statement->fetch()['guest_share'];
		} finally {
			if ($statement) {
				/* @phan-suppress-next-line PhanDeprecatedFunction */
				$statement->closeCursor();
			}
		}
	}
}
