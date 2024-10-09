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
 * Interface EnvironmentInterface.
 *
 * Abstraction over the execution environment.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface EnvironmentInterface
{
    /**
     * Fetches the current working directory.
     */
    public function getCwd(): string;

    /**
     * Fetches the group ID for the given POSIX group name.
     */
    public function getGroupIdFromName(string $groupName): ?int;

    /**
     * Fetches the Unix timestamp of program start with microseconds.
     */
    public function getStartTime(): float;

    /**
     * Fetches the current Unix timestamp with microseconds.
     */
    public function getTime(): float;

    /**
     * Fetches the user ID for the given POSIX username.
     */
    public function getUserIdFromName(string $username): ?int;
}
