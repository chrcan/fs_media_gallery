/**
 * Module: TYPO3/CMS/FsMediaGallery/ContextMenuActions
 * JavaScript to handle fs_media_gallery actions from context menu
 * Compatible with FsMediaGalleryProvider.php
 */
import Modal from "@typo3/backend/modal.js";
import Severity from "@typo3/backend/severity.js";

class ContextMenuActions {
	/**
	 * Show message when no media storage folder is provided
	 * @param {string} table
	 * @param {string} uid
	 * @param {object} dataset
	 */
	static missingMediaFolder(table, uid, dataset) {
		const title =
			dataset.title ||
			TYPO3.lang["fs_media_gallery.missingMediaFolder.title"] ||
			"Information";
		const message =
			dataset.message ||
			TYPO3.lang["fs_media_gallery.missingMediaFolder.message"] ||
			"You must first create a storage folder for your media albums!";

		Modal.advanced({
			type: Modal.types.info,
			title: title,
			content: message,
			severity: Severity.warning,
			buttons: [
				{
					text: TYPO3.lang["button.ok"] || "OK",
					active: true,
					btnClass: "btn-primary",
					trigger: () => Modal.dismiss(),
				},
			],
		});
	}

	/**
	 * Open media album edit form
	 * Compatible with FsMediaGalleryProvider.php data attributes
	 * @param {string} table
	 * @param {string} uid
	 * @param {object} dataset
	 */
	static mediaAlbum(table, uid, dataset) {
		// PHP Provider sends these exact attribute names (lowercase with dashes)
		const albumRecordUid = parseInt(dataset["album-record-uid"] || "0", 10);
		const pid = parseInt(dataset["pid"] || "0", 10);
		const parentUid = parseInt(dataset["parent-uid"] || "0", 10);
		const title = encodeURIComponent(dataset["title"] || "");
		const storage = parseInt(dataset["storage"] || "0", 10);
		const folder = encodeURIComponent(dataset["folder"] || "");

		// Build URL according to TYPO3 v14 FormEngine API
		let url;

		if (albumRecordUid > 0) {
			// Edit existing album - matches PHP provider logic
			url = this.buildEditUrl(albumRecordUid);
		} else {
			// Create new album - matches PHP provider logic
			url = this.buildCreateUrl(pid, parentUid, title, storage, folder);
		}

		// Use TYPO3 v14 navigation - preserves return URL handling
		this.navigateToUrl(url);
	}

	/**
	 * Build edit URL for existing album
	 * @param {number} albumRecordUid
	 * @return {string}
	 */
	static buildEditUrl(albumRecordUid) {
		const returnUrl = this.getReturnUrl();
		return (
			TYPO3.settings.ajaxUrls.record_edit +
			"&edit[sys_file_collection][" +
			albumRecordUid +
			"]=edit" +
			"&returnUrl=" +
			encodeURIComponent(returnUrl)
		);
	}

	/**
	 * Build create URL for new album
	 * @param {number} pid
	 * @param {number} parentUid
	 * @param {string} title
	 * @param {number} storage
	 * @param {string} folder
	 * @return {string}
	 */
	static buildCreateUrl(pid, parentUid, title, storage, folder) {
		const returnUrl = this.getReturnUrl();
		let url =
			TYPO3.settings.ajaxUrls.record_edit +
			"&edit[sys_file_collection][" +
			pid +
			"]=new" +
			"&defVals[sys_file_collection][parentalbum]=" +
			parentUid +
			"&defVals[sys_file_collection][title]=" +
			title +
			"&defVals[sys_file_collection][storage]=" +
			storage +
			"&defVals[sys_file_collection][folder]=" +
			folder +
			"&defVals[sys_file_collection][type]=folder" +
			"&returnUrl=" +
			encodeURIComponent(returnUrl);

		// Add additional parameters that might be expected
		if (storage > 0) {
			url += "&defVals[sys_file_collection][storage]=" + storage;
		}

		return url;
	}

	/**
	 * Get return URL - compatible with PHP provider expectations
	 * @return {string}
	 */
	static getReturnUrl() {
		// Try to get from current backend state
		if (
			typeof TYPO3 === "object" &&
			TYPO3.Backend &&
			TYPO3.Backend.NavigationContainer
		) {
			try {
				const currentModule =
					TYPO3.Backend.NavigationContainer.PageTree.getCurrentModule();
				if (currentModule) {
					return currentModule;
				}
			} catch (e) {
				// Fall through to default
			}
		}

		// Default: Current filelist module URL (matches PHP provider expectation)
		if (window.location.pathname.includes("/module/file/list")) {
			return window.location.pathname + window.location.search;
		}

		// Fallback to filelist module
		return TYPO3.settings.ajaxUrls.filelist || "/typo3/module/file/list";
	}

	/**
	 * Navigate to URL using TYPO3 v14 compatible method
	 * @param {string} url
	 */
	static navigateToUrl(url) {
		// Method 1: Use TYPO3 v14 module router if available
		if (typeof TYPO3 === "object" && TYPO3.ModuleMenu && TYPO3.ModuleMenu.App) {
			TYPO3.ModuleMenu.App.showModule("record_edit", url);
			return;
		}

		// Method 2: Use window.top for backend context (compatible with older pattern)
		if (
			window.top &&
			window.top.TYPO3 &&
			window.top.TYPO3.Backend &&
			window.top.TYPO3.Backend.ContentContainer
		) {
			try {
				window.top.TYPO3.Backend.ContentContainer.setUrl(url);
				return;
			} catch (e) {
				// Fall through
			}
		}

		// Method 3: Direct navigation
		window.location.href = url;
	}

	/**
	 * Register actions with TYPO3 backend context menu
	 */
	static register() {
		// Check for TYPO3 v14 ContextMenu API
		if (
			typeof TYPO3 === "object" &&
			TYPO3.Backend &&
			TYPO3.Backend.ContextMenu
		) {
			// Register missingMediaFolder action
			TYPO3.Backend.ContextMenu.registerAction(
				"fsmediagallery-missing-folder",
				{
					callback: (table, uid, dataset) =>
						this.missingMediaFolder(table, uid, dataset),
					label:
						"LLL:EXT:fs_media_gallery/Resources/Private/Language/locallang_be.xlf:module.buttons.createAlbum",
					additionalAttributes: {
						"data-callback-module":
							"TYPO3/CMS/FsMediaGallery/ContextMenuActions",
					},
				}
			);

			// Register mediaAlbum action
			TYPO3.Backend.ContextMenu.registerAction("fsmediagallery-edit-album", {
				callback: (table, uid, dataset) => this.mediaAlbum(table, uid, dataset),
				label:
					"LLL:EXT:fs_media_gallery/Resources/Private/Language/locallang_be.xlf:module.buttons.editAlbum",
				additionalAttributes: {
					"data-callback-module": "TYPO3/CMS/FsMediaGallery/ContextMenuActions",
				},
			});
		}

		// Also register the legacy way for compatibility
		if (typeof TYPO3 === "object" && TYPO3.ContextMenuActions) {
			TYPO3.ContextMenuActions.missingMediaFolder = this.missingMediaFolder;
			TYPO3.ContextMenuActions.mediaAlbum = this.mediaAlbum;
		}
	}
}

// Auto-initialize
document.addEventListener("DOMContentLoaded", () =>
	ContextMenuActions.register()
);

// Export for different module systems
if (typeof define === "function" && define.amd) {
	// RequireJS/AMD compatibility (for TYPO3 < v14)
	define(["TYPO3/CMS/Backend/Modal", "TYPO3/CMS/Backend/Severity"], function (
		Modal,
		Severity
	) {
		// Return object that matches PHP provider's expectation
		return {
			missingMediaFolder: ContextMenuActions.missingMediaFolder,
			mediaAlbum: ContextMenuActions.mediaAlbum,
		};
	});
}

// ES6 export for TYPO3 v14
export default ContextMenuActions;
