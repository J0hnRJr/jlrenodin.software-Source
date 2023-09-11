<?php

namespace OCA\windows_network_drive\lib;

class WNDNotifier {
	public const NOTIFY_PASSWORD_REMOVAL = 'password_removal';

	private static $singleton = null;
	public static function getSingleton() {
		if (self::$singleton === null) {
			self::$singleton = new WNDNotifier();
		}
		return self::$singleton;
	}

	private $listeners = [];
	public function registerWND(WND $wnd) {
		if (\version_compare(PHP_VERSION, '7.4.0') >= 0) {
			// store a weak reference to the object instead
			/* @phan-suppress-next-line PhanUndeclaredClassMethod on PHP before 7.4 */
			$this->listeners[] = \WeakReference::create($wnd);
		} else {
			$this->listeners[] = $wnd;
		}
	}

	/**
	 * @param $wnd the WND object that changed
	 */
	public function notifyChange(WND $wnd, $changeType) {
		if (\version_compare(PHP_VERSION, '7.4.0') >= 0) {
			foreach ($this->listeners as $key => $listeners) {
				/* @phan-suppress-next-line PhanUndeclaredMethod on PHP before 7.4 */
				$ref = $listeners->get();  // reference to the object might have disappeared
				if ($ref) {
					$ref->receiveNotificationFrom($wnd, $changeType);
				} else {
					// reference disappeared -> remove the weak reference
					unset($this->listeners[$key]);
				}
			}
		} else {
			foreach ($this->listeners as $listener) {
				$listener->receiveNotificationFrom($wnd, $changeType);
			}
		}
	}
}
