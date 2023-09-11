/**
 *
 * @author Tom Needham <tom@owncoud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license GPL-2.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

(function (OCA) {

	OCA.Drawio = _.extend({}, OCA.Drawio);

	if (!OCA.Drawio.AppName) {
		OCA.Drawio = {
			AppName: "drawio",
			currentLockToken: null
		};
	}

	function getHrefNodeContents (node) {
		var nodes = node.getElementsByTagNameNS(OC.Files.Client.NS_DAV, 'href');
		if (!nodes.length) {
			return null;
		}
		return nodes[0].textContent;
	}

	OCA.Drawio.LoadEditorHandler = function (eventHandler, path, editWindow) {
		// Set page title for webbrowser tab window
		window.document.title = path.split("/").pop() + ' - ' + oc_defaults.title;
		// Handle the load event at the start of the page load
		var loadMsg = OC.Notification.show(t(OCA.Drawio.AppName, "Loading diagram..."));
		var fileClient = OC.Files.getClient();
		fileClient.lock(path)
			.then(function (status, contents) {
				const xml = contents.xhr.responseXML;
				const activelock = xml.getElementsByTagNameNS('DAV:', 'activelock');

				OCA.Drawio.currentLockToken = getHrefNodeContents(activelock[0].getElementsByTagNameNS('DAV:', 'locktoken')[0])

				fileClient.getFileContents(path)
					.then(function (status, contents) {
						// If the file is empty, then we start with a new file template
						if (contents === "") {
							editWindow.postMessage(JSON.stringify({
								action: "template",
								name: path
							}), "*");
						} else if (contents.indexOf("mxGraphModel") === -1 && contents.indexOf("mxfile") === -1) {
							// If the contents is something else, we just error and exit
							OC.Notification.show(t(OCA.Drawio.AppName, "Error: This is not a Drawio file!"));
						} else {
							// Load the xml from the file and setup drawio
							editWindow.postMessage(JSON.stringify({
								action: "load",
								xml: contents
							}), "*");
						}
					})
					// Loading failed
					.fail(function () {
						OC.Notification.show(t(OCA.Drawio.AppName, "Error: Failed to load the file!"));
					})
					// Loading done, hide message
					.done(function () {
						OC.Notification.hide(loadMsg);
					});
			})
			.fail(function () {
				OC.Notification.show(t(OCA.Drawio.AppName, "File is currently being used by somebody else"));
			})
	};

	OCA.Drawio.SaveFileHandler = function (eventHandler, path, payload) {
		// Handle the save event triggered by the user, use JS to save over WebDAV
		var saveNotif = OC.Notification.show(t(OCA.Drawio.AppName, "Saving..."));
		OCA.Drawio.putFileContents(
			path,
			payload.xml
		)
			// After save, show nice message
			.then(function () {
				OC.Notification.showTemporary(t(OCA.Drawio.AppName, "Saved"));
			})
			// Saving failed
			.fail(function () {
				OC.Notification.show(t(OCA.Drawio.AppName, "Error: Could not save file!"));
			})
			// Saving is done, hide original message
			.done(function () {
				OC.Notification.hide(saveNotif);
			});
	};

	OCA.Drawio.ExitHandler = function (eventHandler, path) {
		OCA.Drawio.unlock(path, OCA.Drawio.currentLockToken)
			.done(function () {
				// Stop listening
				window.removeEventListener("message", eventHandler);
				window.close();
				// If this doesn't work, fallback to opening the files app instead.
				OC.Files.getClient().getFileInfo(path)
					.then(function (status, fileInfo) {
						window.location.href = OC.generateUrl(
							"/apps/files/?dir={currentDirectory}&fileid={fileId}",
							{
								currentDirectory: fileInfo.path,
								fileId: fileInfo.id
							});
					})
					.fail(function () {
						window.location.href = OC.generateUrl("/apps/files");
					});
			})
	};

	OCA.Drawio.LoadEventHandler = function (editWindow, path, origin) {
		var eventHandler = function (evt) {
			if (evt.data.length > 0 && origin.includes(evt.origin)) {
				var payload = JSON.parse(evt.data);
				if (payload.event === "init") {
					OCA.Drawio.LoadEditorHandler(eventHandler, path, editWindow);
				} else if (payload.event === "save") {
					OCA.Drawio.SaveFileHandler(eventHandler, path, payload);
				} else if (payload.event === "exit") {
					OCA.Drawio.ExitHandler(eventHandler, path);
				}
			}
		};
		window.addEventListener("message", eventHandler);
	};

	OCA.Drawio.unlock = function (path, token) {
		var fileClient = OC.Files.getClient();
		var deferred = $.Deferred();
		var promise = deferred.promise();

		fileClient.getClient().request('UNLOCK',
			fileClient._buildUrl(path),
			{
				'Lock-Token': token
			}
		).then(
			function(result) {
				if (fileClient._isSuccessStatus(result.status)) {
					deferred.resolve(result.status, result);
				} else {
					result = _.extend(result, fileClient._getSabreException(result));
					deferred.reject(result.status, result);
				}
			}
		);
		return promise;
	}

	OCA.Drawio.putFileContents = function (path, body) {
		var fileClient = OC.Files.getClient();
		var headers = {
			'Content-Type': "x-application/drawio",
			'If': '(<' + OCA.Drawio.currentLockToken + '>)'
		}

		var deferred = $.Deferred();
		var promise = deferred.promise();

		fileClient.getClient().request(
			'PUT',
			fileClient._buildUrl(path),
			headers,
			body
		).then(
			function(result) {
				if (fileClient._isSuccessStatus(result.status)) {
					deferred.resolve(result.status, result);
				} else {
					result = _.extend(result, fileClient._getSabreException(result));
					deferred.reject(result.status, result);
				}
			}
		);
		return promise;
	}
})(OCA);
