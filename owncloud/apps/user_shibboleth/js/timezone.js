/**
 * ownCloud
 *
 * @author Thomas Müller <deepdiver@owncloud.com>
 * @copyright (C) 2014-2018 ownCloud GmbH
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */
$(document).ready(function() {
	var visitorTimeZoneOffset = (-new Date().getTimezoneOffset() / 60);
	var visitorTimeZone = jstz.determine().name();

	$.post(
		OC.generateUrl('/apps/user_shibboleth/timezone'),
		{
			'timezone-offset': visitorTimeZoneOffset,
			'timezone': visitorTimeZone
		}
	);
});
