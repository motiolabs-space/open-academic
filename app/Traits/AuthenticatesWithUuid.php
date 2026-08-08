<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Makes the UUID — not the auto-increment id — the authentication identifier.
 *
 * Two reasons, one of them load-bearing.
 *
 * The load-bearing one: Open Academic has three separate identity tables, so
 * `id` is not unique across the people who can sign in. Student #1 and lecturer
 * #1 both exist. An OAuth subject built on `id` would hand two different human
 * beings the same identifier, and a consumer that keyed its own records on it
 * would silently merge them. The UUID is unique across all three tables.
 *
 * The secondary one: CLAUDE.md §7 already says UUIDs are the public identity
 * and auto-increment ids are internal. An OAuth `sub` travels to third-party
 * systems — there is no more public identifier in the whole application.
 *
 * Session authentication follows along: the session cookie now carries a UUID
 * instead of a sequential id, which is strictly better anyway.
 */
trait AuthenticatesWithUuid
{
    public function getAuthIdentifierName(): string
    {
        return 'uuid';
    }

    public function getAuthIdentifier(): string
    {
        return (string) $this->getAttribute('uuid');
    }
}
