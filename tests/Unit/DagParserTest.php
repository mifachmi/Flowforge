<?php

use App\Services\DagParser;

describe('DagParser', function () {
    // tests/Unit/DagParserTest.php
    it('dapat melakukan topological sort dengan benar', function () {
        $dag = [
            'steps' => [
                ['id' => 'A', 'name' => 'Step A', 'type' => 'http'],
                ['id' => 'B', 'name' => 'Step B', 'type' => 'http'],
                ['id' => 'C', 'name' => 'Step C', 'type' => 'http'],
            ],
            'edges' => [
                ['from' => 'A', 'to' => 'B'],
                ['from' => 'A', 'to' => 'C'],
            ],
        ];

        $parser = (new DagParser)->parse($dag)->validate();
        $sorted = $parser->topologicalSort();

        expect($sorted[0])->toBe('A')
            ->and(in_array('B', $sorted))->toBeTrue()
            ->and(in_array('C', $sorted))->toBeTrue();
    });

    it('menolak DAG yang mengandung cycle', function () {
        $dag = [
            'steps' => [
                ['id' => 'A', 'type' => 'http'],
                ['id' => 'B', 'type' => 'http'],
            ],
            'edges' => [
                ['from' => 'A', 'to' => 'B'],
                ['from' => 'B', 'to' => 'A'], // cycle!
            ],
        ];

        expect(fn() => (new DagParser)->parse($dag)->validate())
            ->toThrow(InvalidArgumentException::class);
    });
});
