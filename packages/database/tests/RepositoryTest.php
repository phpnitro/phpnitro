<?php

namespace Engine\Database\Tests;

use Engine\Database\Database;
use Engine\Database\Repository;
use PHPUnit\Framework\TestCase;

final class RepositoryTest extends TestCase
{
    private string $path;

    private TaskRepositoryForTest $repository;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/phpnitro-repository-test-' . uniqid() . '.sqlite';
        Database::useSqlitePath($this->path);
        Database::connection()->executeStatement(
            'CREATE TABLE tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, done INTEGER)',
        );
        $this->repository = new TaskRepositoryForTest();
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(Database::class);
        $reflection->getProperty('connection')->setValue(null, null);
        $reflection->getProperty('sqlitePath')->setValue(null, null);
        @unlink($this->path);
    }

    public function testInsertAndFind(): void
    {
        $id = $this->repository->insert(['title' => 'Buy milk', 'done' => 0]);

        $row = $this->repository->find($id);

        $this->assertNotNull($row);
        $this->assertSame('Buy milk', $row['title']);
    }

    public function testFindMissingReturnsNull(): void
    {
        $this->assertNull($this->repository->find(999));
    }

    public function testAll(): void
    {
        $this->repository->insert(['title' => 'A', 'done' => 0]);
        $this->repository->insert(['title' => 'B', 'done' => 1]);

        $this->assertCount(2, $this->repository->all());
    }

    public function testWhereAndFirst(): void
    {
        $this->repository->insert(['title' => 'A', 'done' => 0]);
        $this->repository->insert(['title' => 'B', 'done' => 1]);

        $done = $this->repository->where(['done' => 1]);
        $this->assertCount(1, $done);
        $this->assertSame('B', $done[0]['title']);

        $first = $this->repository->first(['done' => 0]);
        $this->assertSame('A', $first['title']);

        $this->assertNull($this->repository->first(['done' => 99]));
    }

    public function testExistsAndCount(): void
    {
        $this->repository->insert(['title' => 'A', 'done' => 0]);

        $this->assertTrue($this->repository->exists(['title' => 'A']));
        $this->assertFalse($this->repository->exists(['title' => 'Z']));
        $this->assertSame(1, $this->repository->count());
        $this->assertSame(0, $this->repository->count(['done' => 1]));
    }

    public function testPaginate(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->repository->insert(['title' => "Task {$i}", 'done' => 0]);
        }

        $page1 = $this->repository->paginate(1, 2, orderBy: 'id');
        $this->assertCount(2, $page1['items']);
        $this->assertSame(5, $page1['total']);
        $this->assertSame(3, $page1['totalPages']);

        $page3 = $this->repository->paginate(3, 2, orderBy: 'id');
        $this->assertCount(1, $page3['items']);
    }

    public function testUpdateAndDelete(): void
    {
        $id = $this->repository->insert(['title' => 'A', 'done' => 0]);

        $this->repository->update($id, ['done' => 1]);
        $this->assertSame(1, $this->repository->find($id)['done']);

        $this->repository->delete($id);
        $this->assertNull($this->repository->find($id));
    }
}

final class TaskRepositoryForTest extends Repository
{
    protected function table(): string
    {
        return 'tasks';
    }
}
