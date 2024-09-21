<?php

/*
 * Nytris Boost
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/nytris/boost/
 *
 * Released under the MIT license.
 * https://github.com/nytris/boost/raw/main/MIT-LICENSE.txt
 */

declare(strict_types=1);

namespace Nytris\Boost\Environment;

/**
 * Class Environment.
 *
 * Abstraction over the execution environment.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class Environment implements EnvironmentInterface
{
    private float $startTime;

    public function __construct()
    {
        $this->startTime = isset($_SERVER['REQUEST_TIME']) ?
            (float) $_SERVER['REQUEST_TIME'] :
            $this->getTime();
    }

    /**
     * @inheritDoc
     */
    public function getCwd(): string
    {
        return getcwd();
    }

    /**
     * @inheritDoc
     */
    public function getGroupIdFromName(string $groupName): ?int
    {
        $groupInfo = posix_getgrnam($groupName);

        return $groupInfo !== false ? $groupInfo['gid'] : null;
    }

    /**
     * @inheritDoc
     */
    public function getStartTime(): float
    {
        return $this->startTime;
    }

    /**
     * @inheritDoc
     */
    public function getTime(): float
    {
        return microtime(as_float: true);
    }

    /**
     * @inheritDoc
     */
    public function getUserIdFromName(string $username): ?int
    {
        $userInfo = posix_getpwnam($username);

        return $userInfo !== false ? $userInfo['uid'] : null;
    }
}
