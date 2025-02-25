<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Profile\ProfileField\Collection;

use Friendica\BaseCollection;
use Friendica\Profile\ProfileField\Entity\ProfileField as ProfileFieldEntity;

class ProfileFields extends BaseCollection
{
	public function current(): ProfileFieldEntity
	{
		return parent::current();
	}

	public function map(callable $callback): ProfileFields
	{
		$class = get_class($this);

		return new $class(array_map($callback, $this->getArrayCopy()), $this->getTotalCount());
	}

	public function filter(?callable $callback = null, int $flag = 0): ProfileFields
	{
		$class = get_class($this);

		return new $class(array_filter($this->getArrayCopy(), $callback, $flag));
	}
}
