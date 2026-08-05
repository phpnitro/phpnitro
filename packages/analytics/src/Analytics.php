<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Analytics;

use Engine\Database\Database;

/**
 * Local, dependency-free event tracking — no Firebase Analytics/Mixpanel
 * account needed to know which screens/actions an app's own users
 * actually touch. Same SQLite-backed pattern as
 * Engine\Preferences\Preferences (Database::connection() is already a
 * real dependency everywhere in this framework, not a new one) — events
 * land in a plain table on-device, queryable with summary()/recent() for
 * a developer's own debug screen or export flow, same "stays local until
 * someone deliberately does something with it" philosophy as
 * CrashReporter.kt on the Kotlin side.
 *
 * public/index.php calls track('screen_view', ['screen' => $screen]) once
 * per real navigation automatically (see its own comment there for the
 * exact "what counts as a navigation" condition — the same one
 * setTransition() already uses) — every OTHER event (a button someone
 * cares about, a funnel step) is opt-in, call track() from wherever that
 * action is already handled.
 *
 * NOT a replacement for a real analytics SaaS if an app genuinely needs
 * cross-device aggregation, retention cohorts, funnels across users — this
 * is single-device, no upload, exactly as much as "I want to know what
 * MY installed copies of the app are actually doing" needs and no more.
 */
final class Analytics
{
    private static bool $schemaEnsured = false;

    /** @param array<string, scalar|null> $properties */
    public static function track(string $event, array $properties = []): void
    {
        self::ensureSchema();

        Database::connection()->insert('analytics_events', [
            'event' => $event,
            'properties' => json_encode($properties, JSON_THROW_ON_ERROR),
            'occurred_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    /**
     * Most recent first.
     *
     * @return array<int, array{event: string, properties: array<string, mixed>, occurredAt: string}>
     */
    public static function recent(int $limit = 50): array
    {
        self::ensureSchema();

        $rows = Database::connection()->createQueryBuilder()
            ->select('event', 'properties', 'occurred_at')
            ->from('analytics_events')
            ->orderBy('id', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            static fn (array $row): array => [
                'event' => $row['event'],
                'properties' => json_decode((string) $row['properties'], true) ?? [],
                'occurredAt' => $row['occurred_at'],
            ],
            $rows,
        );
    }

    public static function count(string $event): int
    {
        self::ensureSchema();

        return (int) Database::connection()->fetchOne(
            'SELECT COUNT(*) FROM analytics_events WHERE event = ?',
            [$event],
        );
    }

    /** @return array<string, int> event name => total count, most frequent first */
    public static function summary(): array
    {
        self::ensureSchema();

        $rows = Database::connection()->createQueryBuilder()
            ->select('event', 'COUNT(*) AS n')
            ->from('analytics_events')
            ->groupBy('event')
            ->orderBy('n', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map('intval', array_column($rows, 'n', 'event'));
    }

    public static function clear(): void
    {
        self::ensureSchema();
        Database::connection()->executeStatement('DELETE FROM analytics_events');
    }

    private static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        Database::connection()->executeStatement(
            'CREATE TABLE IF NOT EXISTS analytics_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event VARCHAR(255) NOT NULL,
                properties TEXT NOT NULL,
                occurred_at VARCHAR(32) NOT NULL
            )',
        );
        self::$schemaEnsured = true;
    }
}
