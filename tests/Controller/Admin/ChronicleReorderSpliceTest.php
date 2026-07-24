<?php

namespace App\Tests\Controller\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Documents the filter-aware DnD splice used by ChronicleReorderController.
 */
final class ChronicleReorderSpliceTest extends TestCase
{
    /**
     * @param list<int> $allIds
     * @param list<int> $orderedVisible
     *
     * @return list<int>
     */
    private function splice(array $allIds, array $orderedVisible): array
    {
        $visibleSet = array_fill_keys($orderedVisible, true);
        $next = 0;
        $merged = [];
        foreach ($allIds as $id) {
            if (isset($visibleSet[$id])) {
                $merged[] = $orderedVisible[$next];
                ++$next;
            } else {
                $merged[] = $id;
            }
        }

        while ($next < \count($orderedVisible)) {
            $merged[] = $orderedVisible[$next];
            ++$next;
        }

        return $merged;
    }

    public function testReorderWithinFilteredSubsetKeepsOthersStable(): void
    {
        $all = [10, 20, 30, 40, 50, 60];
        $visibleReordered = [60, 20, 40]; // was 20,40,60

        self::assertSame([10, 60, 30, 20, 50, 40], $this->splice($all, $visibleReordered));
    }

    public function testFullPageReorder(): void
    {
        $all = [1, 2, 3];
        self::assertSame([3, 1, 2], $this->splice($all, [3, 1, 2]));
    }
}
