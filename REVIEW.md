# Code Review: WorkflowExecutor.php

**PR:** `feature/workflow-executor`
**Reviewer:** Fardhana
**Date:** 2026-04-28

---

## Overview

Terima kasih sudah mengerjakan ini! Implementasi dasar executor-nya sudah jalan, tapi ada
beberapa isu kritis yang perlu diperbaiki sebelum merge — terutama soal keamanan, reliability,
dan maintainability. Saya breakdown per kategori di bawah.

---

## 🔴 Critical Issues (Must Fix)

### 1. SQL Injection via Raw Query

```php
// ❌ Kode saat ini
$results = DB::select("SELECT * FROM workflows WHERE tenant_id = '$tenantId' AND status = '$status'");
```

**Masalah:** `$tenantId` dan `$status` langsung diinterpolasi ke query string. Kalau `$tenantId`
datang dari user input, ini adalah SQL injection vulnerability yang sangat serius.

**Fix:**

```php
// ✅ Gunakan parameter binding
$results = DB::select(
    'SELECT * FROM workflows WHERE tenant_id = ? AND status = ?',
    [$tenantId, $status]
);

// Atau lebih baik, gunakan Eloquent/Query Builder:
$results = Workflow::where('tenant_id', $tenantId)
    ->where('status', $status)
    ->get();
```

---

### 2. Tidak Ada Error Handling di Eksekusi Step

```php
// ❌ Kode saat ini
public function executeStep(array $step): void
{
    $response = Http::get($step['config']['url']);
    $this->markStepDone($step['id']);
}
```

**Masalah:**

- Tidak ada try-catch — kalau HTTP request gagal, exception tidak tertangkap dan workflow
  langsung crash tanpa mencatat error ke DB
- Tidak ada retry logic — step gagal sekali langsung dianggap final
- `markStepDone` dipanggil tanpa cek apakah response sukses

**Fix:**

```php
public function executeStep(array $step): void
{
    $maxRetries = $step['config']['max_retries'] ?? 3;

    try {
        $response = Http::retry($maxRetries, fn($attempt) => 1000 * (2 ** $attempt))
            ->timeout(30)
            ->get($step['config']['url']);

        if ($response->failed()) {
            throw new \RuntimeException(
                "HTTP {$step['config']['method']} failed with status {$response->status()}"
            );
        }

        $this->markStepSuccess($step['id'], $response->json());
    } catch (\Throwable $e) {
        $this->markStepFailed($step['id'], $e->getMessage());
        throw $e; // re-throw supaya job bisa handle retry di level workflow
    }
}
```

---

### 3. N+1 Query Problem

```php
// ❌ Kode saat ini
$workflows = Workflow::all();
foreach ($workflows as $workflow) {
    $steps = $workflow->versions->last()->dag_definition['steps'];
    // ...
}
```

**Masalah:** Setiap iterasi melakukan query baru untuk `versions`. Dengan 100 workflow,
ini akan jadi 101 queries.

**Fix:**

```php
// ✅ Eager load relasi
$workflows = Workflow::with('activeVersion')->get();
foreach ($workflows as $workflow) {
    $steps = $workflow->activeVersion->dag_definition['steps'];
    // ...
}
```

---

## 🟡 Medium Issues (Should Fix)

### 4. Tenant ID Tidak Divalidasi

```php
// ❌ Kode saat ini
public function execute(Request $request): JsonResponse
{
    $tenantId = $request->header('X-Tenant-ID');
    $workflows = Workflow::where('tenant_id', $tenantId)->get();
    // ...
}
```

**Masalah:** `X-Tenant-ID` diambil langsung dari header tanpa validasi. User bisa set header
ke tenant ID lain dan mengakses data tenant tersebut.

**Fix:** Ambil `tenant_id` dari JWT token yang sudah terverifikasi, bukan dari header:

```php
$tenantId = auth()->user()->tenant_id; // dari authenticated user
```

---

### 5. Magic Numbers Tanpa Konstanta

```php
// ❌ Kode saat ini
if ($run->duration_ms > 300000) {
    $this->markTimeout($run->id);
}
```

**Fix:**

```php
// ✅ Definisikan sebagai konstanta atau config
const WORKFLOW_TIMEOUT_MS = 300_000; // 5 menit

if ($run->duration_ms > self::WORKFLOW_TIMEOUT_MS) {
    $this->markTimeout($run->id);
}

// Atau lebih baik, jadikan configurable per workflow:
$timeout = $workflow->timeout_ms ?? config('flowforge.default_timeout_ms', 300_000);
```

---

### 6. Tidak Ada Return Type & Docblock

```php
// ❌ Kode saat ini
public function buildGraph($steps, $edges)
{
    // ...
}
```

**Fix:**

```php
/**
 * Build adjacency list from DAG definition.
 *
 * @param  array<int, array{id: string, type: string, config: array}>  $steps
 * @param  array<int, array{from: string, to: string}>                 $edges
 * @return array<string, string[]>  adjacency list keyed by step ID
 */
public function buildGraph(array $steps, array $edges): array
{
    // ...
}
```

---

## 🟢 Minor Suggestions (Nice to Have)

### 7. Method Terlalu Panjang

Method `run()` memiliki 80+ baris yang menangani parsing, validasi, eksekusi, dan logging
sekaligus. Sebaiknya dipecah:

```php
// Sebelum: satu method raksasa
public function run(WorkflowRun $workflowRun): void { /* 80 lines */ }

// Sesudah: tanggung jawab terpisah
public function run(WorkflowRun $workflowRun): void
{
    $graph  = $this->dagParser->parse($workflowRun->dag_definition)->validate();
    $groups = $graph->getParallelGroups();

    foreach ($groups as $parallelGroup) {
        $this->executeParallelGroup($parallelGroup, $workflowRun);
    }
}

private function executeParallelGroup(array $stepIds, WorkflowRun $run): void { ... }
private function executeStep(string $stepId, WorkflowRun $run): void { ... }
private function handleStepFailure(\Throwable $e, string $stepId, WorkflowRun $run): void { ... }
```

---

### 8. Tidak Ada Logging untuk Debugging

Tambahkan structured logging agar lebih mudah debug di production:

```php
Log::info('Executing step', [
    'run_id'    => $run->id,
    'step_id'   => $step['id'],
    'step_type' => $step['type'],
    'tenant_id' => $run->tenant_id,
]);
```

---

## Summary

| Severity    | Count | Status                  |
| ----------- | ----- | ----------------------- |
| 🔴 Critical | 3     | Must fix before merge   |
| 🟡 Medium   | 3     | Should fix in this PR   |
| 🟢 Minor    | 2     | Can be follow-up ticket |

Overall kode ini adalah titik awal yang bagus — logic utamanya sudah benar dan struktur
class-nya masuk akal. Dengan perbaikan di atas, terutama SQL injection dan error handling,
ini akan production-ready. Happy to pair on any of these if helpful!
