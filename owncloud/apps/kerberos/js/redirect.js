/**
 * ownCloud
 *
 * @author Jörn Friedrich Dreyer <jfd@owncloud.com>
 * @copyright (C) 2018 ownCloud GmbH
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

(function() {

	function getQueryParams(qs)
	{
		qs = qs.split('+').join(' ');

		var params = {},
			tokens,
			re = /[?&]?([^=]+)=([^&]*)/g;

		while (tokens = re.exec(qs)) {
			params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
		}

		return params;
	}

	// disable kerberos auth
	document.cookie = 'oc_suppress_spnego=true;max-age=' + oc_appconfig.kerberos.suppress_timeout + ';path=' + OC.getRootPath();
	document.cookie = 'oc_suppress_spnego=true;max-age=' + oc_appconfig.kerberos.suppress_timeout + ';path=' + OC.getRootPath() + '/';

	// go back to default page / will redirect to login
	var qp = getQueryParams(document.location.search);
	if (qp.redirect_url) {
		window.location = OC.generateUrl('login?redirect_url={redirect_url}', {redirect_url: qp.redirect_url});
	} else {
		window.location = OC.generateUrl('login');
	}

})();