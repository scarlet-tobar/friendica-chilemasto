<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Navigation\Notifications\Collection;

use Friendica\BaseCollection;
use Friendica\Navigation\Notifications\Entity\Notification as NotificationEntity;

class Notifications extends BaseCollection
{
	public function current(): NotificationEntity
	{
		return parent::current();
	}

	public function setSeen(): Notifications
	{
		$class = get_class($this);

		return new $class(
			array_map(
				function (NotificationEntity $notification) {
					$notification->setSeen();
				},
				$this->getArrayCopy()
			),
			$this->getTotalCount(),
		);
	}

	public function setDismissed(): Notifications
	{
		$class = get_class($this);

		return new $class(
			array_map(
				function (NotificationEntity $notification) {
					$notification->setDismissed();
				},
				$this->getArrayCopy(),
			),
			$this->getTotalCount(),
		);

	}

	public function countUnseen(): int
	{
		return array_reduce($this->getArrayCopy(), function (int $carry, NotificationEntity $notification) {
			return $carry + ($notification->seen ? 0 : 1);
		}, 0);
	}
}
