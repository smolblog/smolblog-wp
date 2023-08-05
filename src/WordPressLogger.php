<?php

namespace Smolblog\WP;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

class WordPressLogger extends AbstractLogger {
	public function log($level, string|Stringable $message, array $context = []): void
	{
		if (!WP_DEBUG && $level === LogLevel::DEBUG) {
			return;
		}
		
		wp_insert_post([
			'post_title' => strval($message),
			'post_content' => print_r($context, true),
			'post_type' => 'log',
			'tax_input' => [ 'log_level' => strval($level) ],
		], true);
	}
}