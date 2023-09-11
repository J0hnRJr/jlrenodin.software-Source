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
use Doctrine\DBAL\Types\Types;
use OCP\Migration\ISchemaMigration;

/**
 * Create table to store kerberos data in the DB if needed
 */
class Version20221005114724 implements ISchemaMigration {
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];
		if (!$schema->hasTable("{$prefix}wnd_krb5_data")) {
			$table = $schema->createTable("{$prefix}wnd_krb5_data");
			$table->addColumn('id', Types::INTEGER, [
				'notnull' => true,
				'autoincrement' => 1,
				'unsigned' => true
			]);
			$table->addColumn('server_id', Types::STRING, [
				'length' => 255,
				'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'length' => 255,
				'notnull' => false,
			]);
			$table->addColumn('creation_time', Types::INTEGER, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('ccache', Types::TEXT, [
				'notnull' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'userkrb5_index');
			$table->addIndex(['creation_time'], 'ctime_index');
		}
	}
}
