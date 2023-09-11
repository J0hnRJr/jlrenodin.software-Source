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

use OCP\IDBConnection;
use Doctrine\DBAL\Platforms\MySqlPlatform;

class FilesMetrics {
	/**
	 * @var IDBConnection
	 */
	private $connection;

	/**
	 * FilesMetrics constructor.
	 *
	 * @param IDBConnection $connection
	 */
	public function __construct(
		IDBConnection $connection
	) {
		$this->connection = $connection;
	}

	/**
	 * Provides total file count for the oc instance
	 *
	 * @return mixed an array of total files in oC and/or average files count per user
	 */
	public function getTotalFilesCount() {
		$qb = $this->connection->getQueryBuilder();

		// base query
		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'totalFiles')
			->from('filecache', 'f')
			->innerJoin('f', 'storages', 'st', $qb->expr()->eq('f.storage', 'st.numeric_id'))
			->innerJoin('st', 'mounts', 'mt', $qb->expr()->eq('mt.storage_id', 'st.numeric_id'));

		// only mounts for files (exclude all others like thumbnails)
		// mimetype = 2 => its a folder, exclude it.
		$qb
			->where($qb->expr()->like('f.path', $qb->createPositionalParameter('files/%')))
			->andWhere($qb->expr()->neq('f.mimetype', $qb->expr()->literal(2)));

		// only storages of users (exlude external storage)
		// we cannot just check for "files" as external storages
		// can have such folder mounted
		if ($this->connection->getDatabasePlatform() instanceof MySqlPlatform) {
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

		$statement1 = $qb->execute();
		/* @phan-suppress-next-line PhanDeprecatedFunction */
		$result = $statement1->fetch();
		/* @phan-suppress-next-line PhanDeprecatedFunction */
		$statement1->closeCursor();

		$result['totalFiles'] = (int)$result['totalFiles'];

		return $result;
	}
}
