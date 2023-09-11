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
use Doctrine\DBAL\Platforms\MySqlPlatform;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;

class QuotaMetrics {
	/**
	 * @var IRootFolder
	 */
	private $rootFolder;

	/**
	 * @var IDBConnection
	 */
	private $dbConnection;

	/** @var IConfig */
	private $config;

	/**
	 * QuotaMetrics constructor.
	 *
	 * @param IRootFolder $rootFolder
	 * @param IDBConnection $dbConnection
	 * @param IConfig $config
	 */
	public function __construct(
		IRootFolder $rootFolder,
		IDBConnection $dbConnection,
		IConfig $config
	) {
		$this->rootFolder = $rootFolder;
		$this->dbConnection = $dbConnection;
		$this->config = $config;
	}

	/**
	 * Provides total space used
	 * ref. https://doc.owncloud.com/webui/next/classic_ui/files/webgui/quota.html
	 *
	 * @return array
	 */
	public function getTotalQuotaUsage(): array {
		$usedTotal = $this->getUsedTotalSpace();
		$usedQuota = $this->getUsedQuotaSpace();
		$free = $this->rootFolder->getFreeSpace();

		// check if objectstorage is configured, as there the total space is available as config parameter
		$objectStorageConfig = $this->config->getSystemValue('objectstore', null);
		if ($objectStorageConfig && isset($objectStorageConfig['arguments']['availableStorage'])) {
			$free = $objectStorageConfig['arguments']['availableStorage'] - $usedTotal;
		}

		// when free is negative it is one of the
		// FileInfo::SPACE_NOT_COMPUTED, FileInfo::SPACE_UNKNOWN, FileInfo::SPACE_UNLIMITED etc.
		if ($free < 0) {
			$free = 0;
		}

		return [
			'used' => $usedTotal,
			'usedQuota' => $usedQuota,
			'usedOther' => $usedTotal - $usedQuota,
			'total' => $free + $usedTotal,
			'free' => $free,
			'relative' => 0,
		];
	}

	/**
	 * Queries the filecache for user file sizes (quota).
	 * ref. https://doc.owncloud.com/webui/next/classic_ui/files/webgui/quota.html
	 * @return int
	 */
	private function getUsedQuotaSpace(): int {
		$statement = null;
		try {
			$qb = $this->dbConnection->getQueryBuilder();
			if ($this->dbConnection->getDatabasePlatform() instanceof OraclePlatform) {
				// `size` is a reserved word in oracle db. need to escape it oracle-style.
				$qb->selectAlias($qb->createFunction('SUM(f."size")'), 'totalSize');
			} else {
				$qb->selectAlias($qb->createFunction('SUM(f.size)'), 'totalSize');
			}
			
			// base query
			$qb->from('filecache', 'f')
				->innerJoin('f', 'storages', 'st', $qb->expr()->eq('f.storage', 'st.numeric_id'))
				->innerJoin('st', 'mounts', 'mt', $qb->expr()->eq('mt.storage_id', 'st.numeric_id'));

			// only mounts for files (exclude all others like thumbnails)
			// mimetype = 2 => its a folder, exclude it.
			$qb
				->where($qb->expr()->like('f.path', $qb->createPositionalParameter('files/%')))
				->andWhere($qb->expr()->neq('f.mimetype', $qb->expr()->literal(2)));

			// only storages of users (exlude external storage)
			// we cannot just check for "files" path in filecache as external storages
			// can have such folder mounted
			if ($this->dbConnection->getDatabasePlatform() instanceof MySqlPlatform) {
				$f1 = $qb->createFunction("CONCAT('%::', mt.user_id)");
				$f2 = $qb->createFunction("CONCAT('object::user:', mt.user_id)");
			} else {
				$f1 = $qb->createFunction("('%::' || mt.`user_id`)");
				$f2 = $qb->createFunction("('object::user:' || mt.`user_id`)");
			}
			$or1 = $qb->expr()->orX();
			$or1->add($qb->expr()->like('st.id', $f1));
			$or1->add($qb->expr()->like('st.id', $f2));
			$qb->andWhere($or1);

			$statement = $qb->execute();
			/* @phan-suppress-next-line PhanDeprecatedFunction */
			return (int)$statement->fetch()['totalSize'];
		} finally {
			if ($statement) {
				/* @phan-suppress-next-line PhanDeprecatedFunction */
				$statement->closeCursor();
			}
		}
	}

	/**
	 * Queries the filecache for all root level folders and returns the sum of their sizes
	 * that includes user files, file versions, thumbnailsm, avatars etc.
	 *
	 * @return int
	 */
	private function getUsedTotalSpace(): int {
		$statement = null;
		try {
			$qb = $this->dbConnection->getQueryBuilder();
			if ($this->dbConnection->getDatabasePlatform() instanceof OraclePlatform) {
				// `size` is a reserved word in oracle db. need to escape it oracle-style.
				$qb->selectAlias($qb->createFunction('SUM(f."size")'), 'totalSize');
			} else {
				$qb->selectAlias($qb->createFunction('SUM(f.size)'), 'totalSize');
			}
			
			// base query
			$qb->from('filecache', 'f')
				->innerJoin('f', 'storages', 'st', $qb->expr()->eq('f.storage', 'st.numeric_id'))
				->innerJoin('st', 'mounts', 'mt', $qb->expr()->eq('mt.storage_id', 'st.numeric_id'));

			// check only root folders
			$qb->where($qb->expr()->eq('f.parent', $qb->expr()->literal(-1)))
				->andWhere($qb->expr()->gt('f.size', $qb->expr()->literal(0)));

			// only storages of users (exlude external storage)
			// we cannot just check for "files" path in filecache as external storages
			// can have such folder mounted
			if ($this->dbConnection->getDatabasePlatform() instanceof MySqlPlatform) {
				$f1 = $qb->createFunction("CONCAT('%::', mt.user_id)");
				$f2 = $qb->createFunction("CONCAT('object::user:', mt.user_id)");
			} else {
				$f1 = $qb->createFunction("('%::' || mt.`user_id`)");
				$f2 = $qb->createFunction("('object::user:' || mt.`user_id`)");
			}
			$or1 = $qb->expr()->orX();
			$or1->add($qb->expr()->like('st.id', $f1));
			$or1->add($qb->expr()->like('st.id', $f2));
			$qb->andWhere($or1);

			$statement = $qb->execute();
			/* @phan-suppress-next-line PhanDeprecatedFunction */
			return (int)$statement->fetch()['totalSize'];
		} finally {
			if ($statement) {
				/* @phan-suppress-next-line PhanDeprecatedFunction */
				$statement->closeCursor();
			}
		}
	}
}
