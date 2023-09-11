/**
 * ownCloud Wopi
 *
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Piotr Mrowczynski <piotr@owncloud.com>
 * @copyright 2018-2021 ownCloud GmbH.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */
(function ($, OC, OCA) {

	OCA.Wopi = {

		loadDiscovery: function () {
			return new Promise(function (resolve, reject) {
				var sessionStore = window.sessionStorage;
				if (sessionStore.getItem('discovery.json')) {
					resolve(JSON.parse(sessionStore.getItem('discovery.json')));
				} else {
					$.ajax({
						type: "get",
						url: OC.generateUrl('/apps/wopi/discovery.json'),
						success: function(data) {
							sessionStore.setItem('discovery.json', JSON.stringify(data));
							resolve(data);
						},
						error: function(xhr, status) {
							reject(Error(status));
						}
					});
				}
			});
		}

	};

	OCA.Wopi.FileList = {

		attach: function(fileList) {
			if (fileList.id == "trashbin") {
                return;
            }

			var isPublic = $("#isPublic").val();

			OCA.Wopi.loadDiscovery().then(function (config) {
				_.keys(config.view).forEach(function (key) {
					if (key === 'application/octet-stream') {
						return
					}
					fileList.fileActions.registerAction({
						name: 'ViewOfficeOnline',
						displayName: t('wopi', 'View in Office Online'),
						mime: key,
						permissions: OC.PERMISSION_ALL,
						iconClass: 'icon-wopi',
						actionHandler: function (fileName, context) {
							var fileId = context.$file.attr('data-id');
							var mime = context.$file.attr('data-mime');
							var ext = fileName
								.substr(fileName.lastIndexOf('.')+1)
								.toLowerCase();
							var size = context.$file.attr('data-size');
							var actionUrl = config.view[mime][ext];
							if (typeof actionUrl === 'undefined') {
								return;
							}

							if (size === "0") {
								OC.Notification.showTemporary(
									t('wopi', 'Cannot view empty file. Please edit it first to initialize file.')
								);
							} else if (isPublic) {
								var shareToken = $("#sharingToken").val();
								var url = OC.generateUrl('/apps/wopi/office/view/share/{shareToken}?fileId={fileId}', {
									shareToken: encodeURIComponent(shareToken),
									fileId: fileId
								});
								window.open(url, '_blank');
							} else {
								var url = OC.generateUrl('/apps/wopi/office/view/{fileId}', {
									fileId: fileId
								});
								window.open(url, '_blank');
							}
						}
					});
					// adding multiple default file actions is supported only since 10.9
					fileList.fileActions.setDefault(key, 'ViewOfficeOnline');
				});

				if (config.edit) {
					_.keys(config.edit).forEach(function (key) {
						// add wopi validator action, do not add to default file action
						if (key === 'application/octet-stream' && !isPublic) {
							fileList.fileActions.registerAction({
								name: 'RunWopiValidator',
								displayName: t('wopi', 'Run Wopi Validator'),
								mime: key,
								permissions: OC.PERMISSION_UPDATE,
								iconClass: 'icon-wopi',
								actionHandler: function (fileName, context) {
									var fileId = context.$file.attr('data-id');
									var mime = context.$file.attr('data-mime');
									var ext = fileName
										.substr(fileName.lastIndexOf('.')+1)
										.toLowerCase();
									var size = context.$file.attr('data-size');
									var actionUrl = config.edit[mime][ext];
									if (typeof actionUrl === 'undefined') {
										return;
									}

									var url = OC.generateUrl('/apps/wopi/office/edit/{fileId}', {
										fileId: fileId
									});
									window.open(url, '_blank');
								}
							});
							return;
						}

						fileList.fileActions.registerAction({
							name: 'EditOfficeOnline',
							displayName: t('wopi', 'Edit in Office Online'),
							mime: key,
							permissions: OC.PERMISSION_UPDATE,
							iconClass: 'icon-wopi',
							actionHandler: function (fileName, context) {
								var fileId = context.$file.attr('data-id');
								var mime = context.$file.attr('data-mime');
								var ext = fileName
									.substr(fileName.lastIndexOf('.')+1)
									.toLowerCase();
								var size = context.$file.attr('data-size');
								var actionUrl = config.edit[mime][ext];
								if (typeof actionUrl === 'undefined') {
									return;
								}

								var url;
								if (size === "0" && !isPublic) {
									url = OC.generateUrl('/apps/wopi/office/editnew/{fileId}', {
										fileId: fileId
									});
								} else if (isPublic) {
									var shareToken = $("#sharingToken").val();
									var url = OC.generateUrl('/apps/wopi/office/edit/share/{shareToken}?fileId={fileId}', {
										shareToken: encodeURIComponent(shareToken),
										fileId: fileId
									});
								} else {
									url = OC.generateUrl('/apps/wopi/office/edit/{fileId}', {
										fileId: fileId
									});
								}
								window.open(url, '_blank');
							}
						});
						// adding multiple default file actions is supported only since 10.9
						fileList.fileActions.setDefault(key, 'EditOfficeOnline');
					});
				}
			}, function (error) {
				console.error(error);
			});
		}
	};

	OCA.Wopi.NewFileMenuPlugin = {

		attach: function(menu) {
			var fileList = menu.fileList;

			// only attach to main file list, public view is not supported
			// would require "save as" put relative functionality that is currently 
			// only allowed for authenticated users currently, implementing this feature for 
			// public link (fileList.id === 'files.public') would require quite high effort
			if (fileList.id !== 'files') {
				return;
			}

			var menuEntries = [
				{ext: 'docx', icon: 'icon-office365-word', app: 'Word'},
				{ext: 'xlsx', icon: 'icon-office365-excel', app: 'Excel'},
				{ext: 'pptx', icon: 'icon-office365-powerpoint', app: 'PowerPoint'}
			];
			menuEntries.forEach(function (data) {
				// register the new menu entry
				menu.addMenuEntry({
					id: 'office-',
					displayName: t('wopi', '{app} Document', data),
					templateName: t('wopi', 'New {app} file.{ext}', data),
					iconClass: data.icon,
					fileType: 'file',
					actionHandler: function(name) {
						fileList.createFile(name).then(function(status, data) {
							var fileId = data.id;
							var mime = data.mimetype;
							var ext = data.name
								.substr(data.name.lastIndexOf('.')+1)
								.toLowerCase();
							OCA.Wopi.loadDiscovery().then(function (config) {
								var actionUrl = config.editnew[mime][ext];
								if (typeof actionUrl !== 'undefined') {
									var url = OC.generateUrl('/apps/wopi/office/editnew/{fileId}', {
										fileId: fileId
									});
									window.open(url, '_blank');
								}
							});
						});
					}
				});
			});
		}
	};

	$(document).ready(function() {
		var mime = $("#mimetype").val();
		var isPublic = $("#isPublic").val();

		if (isPublic && mime !== 'httpd/unix-directory'){
			// public link share should have sharePermission assigned
			var publicLinkSharePermission = parseInt($("#sharePermission").val());

			// register for public link on a file
			OCA.Wopi.loadDiscovery().then(function (config) {
				if (config.view) {
					var actionUrl = config.view[mime];
					if (typeof actionUrl === 'undefined') {
						return;
					}
					
					// view button
					var button = document.createElement("a");
					button.href = OC.generateUrl("/apps/wopi/office/view/share/{shareToken}", {
						shareToken: encodeURIComponent($("#sharingToken").val())
					});
					button.className = 'button';
					button.innerText = t('wopi', 'View in Office Online')
					button.target = '_blank';
					$("#preview").append(button);
				}

				// note: edit permission in public link is possible as of 10.12
				if (config.edit && ((publicLinkSharePermission & OC.PERMISSION_UPDATE) === OC.PERMISSION_UPDATE)) {
					var actionUrl = config.edit[mime];
					if (typeof actionUrl === 'undefined') {
						return;
					}
					
					var button = document.createElement("a");
					button.href = OC.generateUrl("/apps/wopi/office/edit/share/{shareToken}", {
						shareToken: encodeURIComponent($("#sharingToken").val())
					});
					button.className = 'button';
					button.innerText = t('wopi', 'Edit in Office Online')
					button.target = '_blank';
					$("#preview").append(button);
				}

		
			}, function (error) {
				console.error(error);
			});
		} else {
			// register for folder view (both view and edit possible)

			OC.Plugins.register('OCA.Files.NewFileMenu', OCA.Wopi.NewFileMenuPlugin);
			OC.Plugins.register('OCA.Files.FileList', OCA.Wopi.FileList);
		}
	})


})(jQuery, OC, OCA);
