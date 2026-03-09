<?php

/**
 * Copyright (c) 2021-present, Emile Silas Sare
 *
 * This file is part of Blate package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

return [
	// XSS payload: all five HTML-sensitive chars must be encoded.
	'xss'       => '<script>alert("xss")</script> & \'world\'',

	// Multibyte content: accented chars must NOT be corrupted to named entities.
	// This distinguishes htmlspecialchars (correct) from htmlentities (wrong).
	'multibyte' => 'Héllo café',

	// HTML boolean attrs: false/null are omitted, true is a standalone attribute.
	'booleans'  => ['disabled' => true, 'readonly' => false, 'checked' => null, 'id' => 'btn1'],

	// data-* attrs in raw mode: false -> "false", null -> "", string passthrough.
	'custom'    => ['data-active' => false, 'data-count' => null, 'data-label' => 'hello'],

	// JSON with HTML injection vectors: < > & must be unicode-escaped.
	'xss_array' => ['</script>', '&'],
];
