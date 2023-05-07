<?php

namespace Smolblog\WP\Helpers;

use Smolblog\Framework\Messages\Listener;
use Smolblog\Framework\Infrastructure\AsyncWrappedMessage;

class AsyncHelper implements Listener {
	public function onAsyncWrappedMessage(AsyncWrappedMessage $message) {
		as_enqueue_async_action( 'smolblog_async_dispatch', [ $message ], 'smolblog' );
	}
}