<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @copyright (C) 2022 ownCloud GmbH
 * @license ownCloud Commercial License
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Metrics\Metrics;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use OCA\Metrics\MetricsSerializer;
use OCP\IDBConnection;
use OCP\IConfig;
use OCP\Util;

class CombinedUserMetrics {
	/**
	 * @var IDBConnection
	 */
	private $connection;

	/**
	 * @var IConfig
	 */
	private $config;

	/**
	 * CombinedUserMetrics constructor.
	 *
	 * @param IDBConnection $connection
	 * @param IConfig $config
	 */
	public function __construct(
		IDBConnection $connection,
		IConfig $config
	) {
		$this->connection = $connection;
		$this->config = $config;
	}

	/**
	 * @param int $totalDiskSpaceAvailable
	 * @return MetricsSerializer[]
	 */
	public function getCombinedData(int $totalDiskSpaceAvailable): array {
		$stmt = $this->connection->executeQuery($this->getUsersSql());

		$result = [];
		/* @phan-suppress-next-line PhanDeprecatedFunction */
		while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			$metricsSerializer = new MetricsSerializer();
			// user info
			$metricsSerializer->setUserId($row['user_id']);
			$metricsSerializer->setUserDisplayName($row['display_name']);
			$metricsSerializer->setLastLogin($row['last_login']);
			$metricsSerializer->setSessions($row['sessions'] ?? 0);
			$metricsSerializer->setUserBackend($row['backend']);
			// quota
			$quotaSpec = $row['quota'];
			if ($quotaSpec === null || $quotaSpec === 'default') {
				$quotaSpec = $this->config->getAppValue('files', 'default_quota', 'none');
			}
			if ($quotaSpec === null || $quotaSpec === 'none') {
				$quota = Util::computerFileSize($totalDiskSpaceAvailable . ' b');
			} else {
				$quota = Util::computerFileSize($quotaSpec);
			}
			$free = $quota - $row['used_space'];
			$metricsSerializer->setQuotaUsed($row['used_space']);
			$metricsSerializer->setQuotaFree($free);
			$metricsSerializer->setQuotaTotal((int)$quota);
			// files
			$metricsSerializer->setFiles($row['total_files']);
			// shares
			$metricsSerializer->setSharesUser($row['user_share_count']);
			$metricsSerializer->setSharesGroup($row['group_share_count']);
			$metricsSerializer->setSharesLink($row['link_share_count']);
			$metricsSerializer->setSharesGuest($row['guest_share_count']);
			$metricsSerializer->setSharesFederated($row['federated_share_count']);
			// append user row
			$result[] = $metricsSerializer;
		}

		/* @phan-suppress-next-line PhanDeprecatedFunction */
		$stmt->closeCursor();

		return $result;
	}

	private function getUsersSql() {
		# any non-mysql
		$mountpoint = "'/' || `a`.`user_id` || '/'";
		$userStorageFS = "'%::' || `a`.`user_id`";
		$userStorageOBJ = "'object::user:' || `a`.`user_id`";
		if ($this->connection->getDatabasePlatform() instanceof MySqlPlatform) {
			$mountpoint = "concat('/', `a`.`user_id`, '/')";
			$userStorageFS = "concat('%::', `a`.`user_id`)";
			$userStorageOBJ = "concat('object::user:', `a`.`user_id`)";
		}

		// only storages of users "files" (exlude external storage)
		return <<<SQL
select `a`.`user_id`, `a`.`display_name`, `a`.`quota`, `a`.`last_login`, `a`.`backend`, `f`.`size` as `used_space`,
       coalesce(`f1`.`files`, 0) as `total_files`, coalesce(`s`.`sessions`, 0) as sessions, coalesce(`shu`.`count`, 0) as `user_share_count`,
       coalesce(`shg`.`count`, 0) as `group_share_count`, coalesce(`shl`.`count`, 0) as `link_share_count`, 
       coalesce(`shguest`.`count`, 0) as `guest_share_count`, coalesce(`shfed`.`count`, 0) as `federated_share_count` from `*PREFIX*accounts` `a`
join `*PREFIX*mounts` `om` on `a`.`user_id` = `om`.`user_id`
join `*PREFIX*filecache` `f` on `om`.`storage_id` = `f`.`storage`
join `*PREFIX*storages` `st` on `st`.`numeric_id` = `f`.`storage`
left join (
    select `storage`, count(*) as `files` from `*PREFIX*filecache`
    where `path` like 'files/%' and `mimetype` <> (select `id` from `*PREFIX*mimetypes` where `mimetype` = 'httpd/unix-directory')
    group by `storage`
    ) `f1` on `f`.`storage` = `f1`.`storage`
left join (
    select `uid`, count(`token`) as `sessions` from `*PREFIX*authtoken`
    group by `uid`
    ) `s` on `s`.`uid` = `a`.`user_id`
left join (
    select `uid_owner`, count(`id`) as `count` from `*PREFIX*share`
    where `share_type` = 0
    group by `uid_owner`
) `shu` on `shu`.`uid_owner` = `a`.`user_id`
left join (
    select `uid_owner`, count(`id`) as `count` from `*PREFIX*share`
    where `share_type` = 1
    group by `uid_owner`
) `shg` on `shg`.`uid_owner` = `a`.`user_id`
left join (
    select `uid_owner`, count(`id`) as `count` from `*PREFIX*share`
    where `share_type` = 3
    group by `uid_owner`
) `shl` on `shl`.`uid_owner` = `a`.`user_id`
left join (
    select `uid_owner`, count(`id`) as `count` from `*PREFIX*share`
    where `share_type` = 4
    group by `uid_owner`
) `shguest` on `shguest`.`uid_owner` = `a`.`user_id`
left join (
    select `uid_owner`, count(`id`) as `count` from `*PREFIX*share`
    where `share_type` = 6
    group by `uid_owner`
) `shfed` on `shfed`.`uid_owner` = `a`.`user_id`
where `om`.`mount_point` = $mountpoint and (`st`.`id` LIKE $userStorageFS or `st`.`id` LIKE $userStorageOBJ)
and `f`.`path` = 'files'
SQL;
	}
}
