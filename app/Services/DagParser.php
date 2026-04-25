<?php

namespace App\Services;

use InvalidArgumentException;

class DagParser
{
    private array $nodes = [];
    private array $edges = [];

    public function parse(array $dagDefinition): self
    {
        $this->nodes = collect($dagDefinition['steps'])->keyBy('id')->toArray();
        $this->edges = $dagDefinition['edges'] ?? [];
        return $this;
    }

    public function validate(): self
    {
        // Cek semua edge merujuk ke node yang ada
        foreach ($this->edges as $edge) {
            if (!isset($this->nodes[$edge['from']]) || !isset($this->nodes[$edge['to']])) {
                throw new InvalidArgumentException("Edge merujuk ke step yang tidak ada: {$edge['from']} -> {$edge['to']}");
            }
        }

        // Cek tidak ada cycle (DAG = no cycle)
        if ($this->hasCycle()) {
            throw new InvalidArgumentException("Workflow definition mengandung cycle — bukan DAG yang valid.");
        }

        return $this;
    }

    public function topologicalSort(): array
    {
        $inDegree = array_fill_keys(array_keys($this->nodes), 0);
        $adjacency = array_fill_keys(array_keys($this->nodes), []);

        foreach ($this->edges as $edge) {
            $adjacency[$edge['from']][] = $edge['to'];
            $inDegree[$edge['to']]++;
        }

        // Kahn's Algorithm
        $queue = [];
        foreach ($inDegree as $nodeId => $degree) {
            if ($degree === 0) $queue[] = $nodeId;
        }

        $sorted = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $current;

            foreach ($adjacency[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        if (count($sorted) !== count($this->nodes)) {
            throw new InvalidArgumentException("Cycle terdeteksi saat topological sort.");
        }

        return $sorted;
    }

    public function getParallelGroups(): array
    {
        // Kelompokkan step yang bisa dijalankan paralel
        $inDegree = array_fill_keys(array_keys($this->nodes), 0);
        $adjacency = array_fill_keys(array_keys($this->nodes), []);

        foreach ($this->edges as $edge) {
            $adjacency[$edge['from']][] = $edge['to'];
            $inDegree[$edge['to']]++;
        }

        $groups = [];
        $remaining = $inDegree;

        while (!empty($remaining)) {
            $group = array_keys(array_filter($remaining, fn($d) => $d === 0));
            if (empty($group)) break;

            $groups[] = $group;
            foreach ($group as $nodeId) {
                unset($remaining[$nodeId]);
                foreach ($adjacency[$nodeId] as $neighbor) {
                    if (isset($remaining[$neighbor])) {
                        $remaining[$neighbor]--;
                    }
                }
            }
        }

        return $groups;
    }

    private function hasCycle(): bool
    {
        $visited = [];
        $recursionStack = [];

        $dfs = function (string $nodeId) use (&$dfs, &$visited, &$recursionStack): bool {
            $visited[$nodeId] = true;
            $recursionStack[$nodeId] = true;

            foreach ($this->edges as $edge) {
                if ($edge['from'] !== $nodeId) continue;
                $neighbor = $edge['to'];

                if (!isset($visited[$neighbor]) && $dfs($neighbor)) return true;
                if (isset($recursionStack[$neighbor])) return true;
            }

            unset($recursionStack[$nodeId]);
            return false;
        };

        foreach (array_keys($this->nodes) as $nodeId) {
            if (!isset($visited[$nodeId]) && $dfs($nodeId)) return true;
        }

        return false;
    }

    public function getNodes(): array
    {
        return $this->nodes;
    }
}
