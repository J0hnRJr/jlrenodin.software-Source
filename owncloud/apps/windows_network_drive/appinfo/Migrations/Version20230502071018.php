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
namespace OCA\windows_network_drive\Migrations;

use Doctrine\DBAL\Schema\Schema;
use OCP\Migration\ISchemaMigration;

/**
 * Old server + user index has problems in some mysql DBs because the index
 * was too long, over the 767 char limit and was usable only with dynamic
 * format which allows bigger indexes (this problem wasn't detected in
 * other DBs)
 * This migration will remove the server + user index and use only the
 * user index, which will fit in that limit.
 * Note that the migration also takes into account new installations which
 * shouldn't have the old index. The result in all the cases is the same
 */
class Version20230502071018 implements ISchemaMigration {
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];
		if ($schema->hasTable("{$prefix}wnd_krb5_data")) {
			$table = $schema->getTable("{$prefix}wnd_krb5_data");

			if ($table->hasIndex('server_user_index')) {
				$table->dropIndex('server_user_index');
			}

			if (!$table->hasIndex('userkrb5_index')) {
				$table->addIndex(['user_id'], 'userkrb5_index');
			}
		}
	}
}
