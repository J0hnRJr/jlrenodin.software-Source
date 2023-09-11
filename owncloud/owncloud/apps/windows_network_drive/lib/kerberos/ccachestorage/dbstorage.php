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

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class DBStorage implements ICCacheStorage {
	/** @var IDBConnection */
	private $dbConnection;
	/** @var ITimeFactory */
	private $timeFactory;

	public function __construct(IDBConnection $dbConnection, ITimeFactory $timeFactory) {
		$this->dbConnection = $dbConnection;
		$this->timeFactory = $timeFactory;
	}

	public function getRecommendedLocation($serverId, $userId = '') {
		return null;
	}

	public function storeFrom($ccacheFilename, $serverId, $userId = ''): bool {
		$encodedContents = \base64_encode(\file_get_contents($ccacheFilename));  // expect a file of a few kb

		// convert empty userId to null for Oracle
		if ($userId === '') {
			$userId = null;
		}

		$this->dbConnection->upsert(
			'*PREFIX*wnd_krb5_data',
			[
				'server_id' => $serverId,
				'user_id' => $userId,
				'creation_time' => $this->timeFactory->getTime(),
				'ccache' => $encodedContents,
			],
			['user_id', 'server_id']
		);
		return true;
	}

	public function retrieveTo($ccacheFilename, $serverId, $userId = ''): array {
		$qb = $this->dbConnection->getQueryBuilder();
		if ($userId === '') {
			// check for null instead of empty string
			$query = $qb->select('*')
				->from('wnd_krb5_data')
				->where($qb->expr()->eq('server_id', $qb->createNamedParameter($serverId)))
				->andWhere($qb->expr()->isNull('user_id'));
		} else {
			$query = $qb->select('*')
				->from('wnd_krb5_data')
				->where($qb->expr()->eq('server_id', $qb->createNamedParameter($serverId)))
				->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		}
		$result = $query->execute();

		$resultData = $result->fetchAssociative();
		if ($resultData === false) {
			return [];
		}

		\file_put_contents($ccacheFilename, \base64_decode($resultData['ccache']));
		return [
			'ccacheFilename' => $ccacheFilename,
			'creationTime' => (int)$resultData['creation_time'],
		];
	}

	public function deleteEntry($serverId, $userId = ''): bool {
		$qb = $this->dbConnection->getQueryBuilder();
		if ($userId === '') {
			$query = $qb->delete('wnd_krb5_data')
				->where($qb->expr()->eq('server_id', $qb->createNamedParameter($serverId)))
				->andWhere($qb->expr()->isNull('user_id'));
		} else {
			$query = $qb->delete('wnd_krb5_data')
				->where($qb->expr()->eq('server_id', $qb->createNamedParameter($serverId)))
				->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		}
		$result = $query->execute();
		return $result > 0;
	}

	public function deleteEntriesOlderThan($timestamp): int {
		$qb = $this->dbConnection->getQueryBuilder();
		$query = $qb->delete('wnd_krb5_data')
			->where($qb->expr()->lt('creation_time', $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT)));
		return $query->execute();
	}

	public function deleteAllEntries() {
		$qb = $this->dbConnection->getQueryBuilder();
		$query = $qb->delete('wnd_krb5_data');
		$result = $query->execute();
		return $result > 0;
	}
}
