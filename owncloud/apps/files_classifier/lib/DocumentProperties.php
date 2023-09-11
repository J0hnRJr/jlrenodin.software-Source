<?php
/**
 *
 * @copyright Copyright (c) 2022, ownCloud GmbH
 * @license OCL
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\FilesClassifier;

use Laminas\Xml\Security;
use ZipArchive;
use SimpleXMLElement;

class DocumentProperties {
	/**
	 * Check whether document is supported for scaning of properties
	 *
	 * @param string $fileName
	 * @return bool
	 */
	public function isDocumentSupported($fileName) {
		$extension = \pathinfo($fileName, PATHINFO_EXTENSION);

		return \in_array($extension, ['docx','dotx','xlsx','xltx','pptx','ppsx','potx']);
	}

	/**
	 * Scan for document properties
	 *
	 * @param string $path
	 *
	 * @return array|SimpleXMLElement
	 */
	public function scan($path) {
		$zip = new ZipArchive();
		if ($zip->open($path) === true) {
			// get the custom.xml file from the office document
			$customXml = $zip->getFromName('docProps/custom.xml');
			$zip->close();
		} else {
			// Not a valid zip file
			$customXml = '';
		}

		$customXml = \str_replace('xmlns="', 'ns="', $customXml);

		/** @var SimpleXMLElement $properties */
		$properties = Security::scan($customXml);

		if (!$properties) {
			return [];
		}
		return $properties;
	}
}
