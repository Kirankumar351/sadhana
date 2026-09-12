<?php

declare(strict_types=1);

namespace App\Services\Agents\Exceptions;

use RuntimeException;

/**
 * An agent definition tried to bind a tool it must not have.
 *
 * Always a configuration error, never a runtime condition. It is raised at bind time —
 * before any model is called — and the agent definition test in CI runs every catalogued
 * agent through the registry precisely so this surfaces in a pull request rather than at
 * 4 AM during the news pipeline.
 */
final class UnsafeToolBindingException extends RuntimeException {}
