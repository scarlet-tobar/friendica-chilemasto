<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Navigation\Notifications\Collection;

use Friendica\BaseCollection;
use Friendica\Navigation\Notifications\Entity\Notify as NotifyEntity;

class Notifies extends BaseCollection
{
	public function current(): NotifyEntity
	{
		return parent::current();
	}

	public function setSeen(): Notifies
	{
		$class = get_class($this);

		return new $class(array_map(
			function (NotifyEntity $notify) {
				$notify->setSeen();
			},
			$this->getArrayCopy()), $this->getTotalCount(),
		);
	}
}
