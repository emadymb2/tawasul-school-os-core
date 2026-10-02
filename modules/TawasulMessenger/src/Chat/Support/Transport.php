<?php
namespace Tos\Module\TawasulChat\Support;

/**
 * How a client learns that something has changed.
 *
 * TawasulOS has no persistent connection layer, so the shipped implementation
 * holds an HTTP request open. That is an implementation detail of delivery, not
 * of messaging: nothing in the services above this interface knows how the
 * change arrived. A WebSocket relay can be added later by implementing this
 * interface and changing one line in the container wiring, with no change to
 * MessageService, ChatService or the pages.
 *
 * The contract every implementation must honour:
 *
 *  - `since` is a MySQL datetime string, exclusive, and `serverTime` is the
 *    clock read *before* the queries ran. Returning the pre-query time is what
 *    makes polling lossless: a message written during the query is picked up by
 *    the next poll because it sorts after `since` and before the following
 *    `serverTime`. Returning the post-query time would silently drop it.
 *  - `wait()` returns null when nothing happened before the deadline, which is
 *    normal and not an error.
 */
interface Transport
{
    /**
     * Block until there is something to report, or the timeout lapses.
     *
     * @param  string $personID      the person waiting, as a zero-padded string
     * @param  string $since         exclusive lower bound, 'Y-m-d H:i:s'
     * @param  int    $timeoutSeconds
     * @return array|null            the change payload, or null on timeout
     */
    public function wait(string $personID, string $since, int $timeoutSeconds): ?array;

    /**
     * Collect everything that has changed since `$since`, without blocking.
     * This is what both the blocking wait and the immediate "catch me up" call
     * are built on.
     *
     * @return array{serverTime: string, messages: array, receipts: array, presence: array, typing: array}
     */
    public function changesSince(string $personID, string $since): array;
}
