<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Database;

use Doctrine\DBAL\Connection;

/**
 * One schema change, forward and backward — the piece Repository's own
 * docblock explicitly named as out of scope ("no migrations... that's
 * Doctrine ORM territory"), but a plain up()/down() pair doesn't need an
 * ORM, an identity map, or entity hydration to be worth having: Repository
 * already solved "query an existing table" and each one's own
 * ensureSchema() solves "create it the first time" — this solves the gap
 * between those two, evolving a table that already has real rows in it.
 *
 * Deliberately just a Connection, not a schema-diffing abstraction this
 * package would have to build and maintain — Doctrine DBAL (already a
 * real dependency via Database::connection(), not a new one) hands back
 * a Connection that already speaks SQLite/MySQL/PostgreSQL uniformly for
 * plain executeStatement() calls, and its own Schema/SchemaManager API is
 * right there via $connection->createSchemaManager() for anyone who wants
 * portable DDL instead of raw SQL. Nothing here forces either style.
 */
abstract class Migration
{
    abstract public function up(Connection $connection): void;

    abstract public function down(Connection $connection): void;
}
