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

namespace Blate\Tests\Utils;

class SampleInfo
{
	public static $links = [
		[
			'href'    => 'https://twitter.com/silassare',
			'caption' => 'Twitter',
			'type'    => 'social',
		],
		[
			'href'    => 'https://github.com/silassare',
			'caption' => 'Github',
			'type'    => 'project',
		],
		[
			'href'    => 'mailto:emile.silas@gmail.com',
			'caption' => 'Email',
			'type'    => 'mail',
		],
	];

	public function getFirstName()
	{
		return 'Emile Silas';
	}

	public function getLastName()
	{
		return 'SARE';
	}
}
