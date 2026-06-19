# Architecture & Roadmap — laravel-bug-bot

> Gitignored working doc (like CONTEXT.md / AUDIT.md). Senior-engineering view of
> where the package is, the structural problems, and a phased plan to fix them.
> Written 2026-06-15, after the security/perf/concurrency hardening pass.

## TL;DR

The hardening pass fixed real bugs, but the package's shape limits how far it can
grow. Two structural issues are the root cause of every feature gap:

1. **`SlackBugReportHandler` is a God object** (~520 lines): fingerprinting,
   formatting, HTTP, state, throttling, redaction, thread-chunking — all in one class.
2. **`ReportState` is an all-static, facade-style class**: hidden dependencies,
   framework-coupled, hard to test or swap, scattered `catch (Throwable) {}`.

The visible gaps — broken dedup on dynamic messages, "pluggable reporters" that
aren't, a dashboard that stores rich data it never shows — are **symptoms**. Fix the
architecture and the features get cheap. This is a ~5-phase evolution, **not a rewrite**.

---

## Current state (as-is)

```
LogRecord ──> SlackBugReportHandler::write()
                 ├─ fingerprint()            (sha1 of class|message|file|line)
                 ├─ ReportState::recordOccurrence()  (static, DB upsert + occurrence)
                 ├─ ReportState::isIgnored() / throttle (static + Cache)
                 ├─ parentBlocks()/threadMessages()    (Slack block building)
                 ├─ redactUrl()/redactArray()          (S2 redaction)
                 └─ dispatchSlackWork(DeliverBugReport) (queued or inline)
DashboardController ──> ReportState (static) + raw aggregate queries
SlackActionController / ManagedSlackActionController ──> ReportState (static)
```

Strengths to preserve: dependency-light (no `illuminate/foundation`), Blade-only UI,
opt-in queue, HMAC-verified webhooks, bounded cache, atomic occurrence counting.

### Structural problems

| # | Problem | Consequence | Confidence |
|---|---|---|---|
| A | God-object handler | Every addition piles on; nothing is unit-testable in isolation | Certain |
| B | Static `ReportState` | Hidden deps, framework-coupled, can't mock/swap storage | Certain |
| C | Fingerprint = `class\|message\|file\|line` | Dynamic messages (IDs/UUIDs/timestamps) → new fingerprint every time → **dedup/throttle silently fail** | Certain |
| D | "Pluggable reporters" claimed, not built | Handler *is* Slack; Discord = a rewrite, not a driver | Certain |
| E | Rich data stored, never shown | `stack_trace`/`context`/`request` columns invisible in dashboard | Certain |
| F | Synchronous DB writes per error in request path | Reporter becomes a second outage under error storms; occurrences table unbounded | Likely |
| G | No env/release as first-class data | Can't filter prod vs staging or by version — not really error tracking | Certain |
| H | Jobs have no retry/backoff; Slack 429 ignored | Transient failures drop reports | Likely |
| I | No CI / static analysis / CHANGELOG | Multi-version lib shipped blind; breaking changes undocumented | Certain |

---

## Target architecture (to-be)

Decompose into collaborators that pass a single value object.

```
LogRecord
   └─ SlackBugReportHandler (thin)         build ExceptionContext, hand off
        └─ BugReportPipeline
             ├─ Fingerprinter              ExceptionContext -> fingerprint
             ├─ BugReportStore             record/throttle/ignore state (interface)
             └─ ReporterManager            fan-out to enabled Reporter drivers
                  ├─ SlackReporter   (+ SlackMessageFormatter, SlackDelivery)
                  └─ DiscordReporter (later)
```

### Key abstractions (sketches — not final signatures)

```php
// Normalized, serializable capture. Built once; flows through the system and
// is what gets queued (instead of rebuilding arrays in the handler/job).
final class ExceptionContext
{
    public string $exceptionClass;
    public string $message;          // raw
    public string $normalizedMessage;// digits/UUIDs/paths stripped, for grouping
    public ?string $file;
    public ?int $line;
    public string $level;
    public string $environment;
    public ?string $release;
    public ?array $request;          // already redacted
    public ?array $context;          // already redacted
    public ?string $stackTrace;
    public ?string $entity;
}

interface Fingerprinter
{
    public function fingerprint(ExceptionContext $context): string;
    public function version(): int;  // stored, so a scheme change can regroup cleanly
}

interface BugReportStore
{
    public function record(string $fingerprint, ExceptionContext $context): ?BugReport;
    public function isIgnored(string $fingerprint): bool;
    public function shouldThrottle(string $fingerprint, int $minutes): bool;
    public function solve(string $fingerprint): void;
    public function ignore(string $fingerprint, ?int $ttlDays): void;
    public function delete(string $fingerprint): void;
    public function storedMessages(string $fingerprint): array;
    public function rememberMessage(string $fingerprint, array $message): void;
}

interface Reporter            // a delivery channel (Slack, Discord, …)
{
    public function report(BugReport $report, ExceptionContext $context): void;
    public function updateStatus(BugReport $report, string $status, string $actor): void;
}

class ReporterManager extends \Illuminate\Support\Manager { /* drivers + fan-out */ }
```

- `SlackBugReportHandler` shrinks to ~30 lines: build `ExceptionContext`, call the pipeline.
- `ReportState` stays as a **thin BC facade** delegating to `BugReportStore`.
- Slack block-building + the `chat.update` ts-tracking concern move into the Slack driver.

### Fingerprinting (the #1 correctness fix)

Default `Fingerprinter`:
- normalize message — strip digits, UUIDs, hex, quoted strings, file paths, memory addresses;
- fall back to `class + top app-namespace frame`;
- allow a per-app override (closure / config);
- persist `fingerprint_version` so changing the scheme **regroups via backfill**, not a silent history fork.

### Domain model — promote what you filter on

Add first-class **indexed columns** (not JSON): `environment`, `release`, `assignee`,
`last_notified_at`, `fingerprint_version`. Define the real Eloquent relations
(`BugReport hasMany BugReportOccurrence`); today they're linked by fingerprint by hand.

### Ingestion & scale (deliberate decisions)

- Reporter must **not become a second outage**: option to queue the *whole* capture
  (push `ExceptionContext`, do DB + delivery in a worker) — weigh data-loss-if-queue-down.
- **Occurrence rollups**: one row per event is unbounded write amplification. At scale,
  store counts per fingerprint per time-bucket, not per-event rows.
- Ingestion rate-guard: cap inserts/sec/fingerprint, drop-with-counter beyond.

### Resilience on async

Jobs: `$tries`, exponential `backoff()`, `retryUntil()`, `failed()` hook, `ShouldBeUnique`
(collapse job storms), and honor Slack **429 `Retry-After`** (`chat.postMessage` ≈ 1/sec/channel).

### Observability of the reporter itself

- A **separate internal log channel** (never the app's — avoids recursive logging).
- Counters: delivered / failed / throttled / dropped.
- Grow `bug-reports:test` into `bug-reports:doctor` — validate config, check token scopes +
  channel membership via Slack API (surface `missing_scope` / `not_in_channel` clearly).

---

## Roadmap (leverage order — each phase ships independently)

| Phase | Scope | Why now | Risk | Rough size |
|---|---|---|---|---|
| **0** | CI matrix (L10–13 × PHP 8.2–8.4) + PHPStan/Larastan max + CHANGELOG/SemVer | Catch regressions *before* refactoring; document the `v2` managed-signature break | Low | Days |
| **1** | `Fingerprinter` + message normalization + `fingerprint_version` + config hook | Fixes the silent dedup failure (C). Contained, high value | Low–Med (regrouping) | Days |
| **2** | `ExceptionContext` DTO + `BugReportStore` interface (Eloquent impl, `ReportState` shim) | Unblocks testability and everything after | Med (internal BC) | ~1 wk |
| **3** | `Reporter` + `ReporterManager`; refactor Slack into a driver; contract tests | Makes "pluggable" real; Discord becomes a weekend | Med | ~1 wk |
| **4** | env/release columns + relations + dashboard **detail view + search/filter** | Turns stored data into product value (E, G) | Med | ~1–2 wk |
| **5** | Ingestion backpressure + occurrence rollups + job resilience (retry/429/unique) | Scale + reliability (F, H) | Med–High | ~2 wk |

Phase 0–1 removes the worst correctness/process risk in days. Phases 2–3 are the
architectural payoff. 4–5 are the product/scale maturity.

---

## Non-goals / what NOT to do

- **No Discord before Phase 3** — you'd bake Slack assumptions in twice.
- **Nothing more onto the 520-line handler.**
- **No SPA/Inertia dashboard** — keep deps light; Blade + a detail page is enough.
- **No more unqueryable JSON** — promote to columns what you filter on.
- **No big-bang rewrite** — ship phase by phase, keep tests green between.

## Cross-cutting risks

- `ReportState` → `BugReportStore` is a breaking *internal* change → keep the facade shim,
  deprecate over a minor version.
- New fingerprint scheme regroups history → gate with `fingerprint_version` + a backfill
  command; never resilently fork existing rows.
- Queue-the-whole-capture trades request latency for worker dependency + possible loss →
  make it opt-in, document the trade-off (as the current `bug-reports.queue` does).

## Already done (hardening pass — see AUDIT.md)

Security: action-bound managed HMAC, request/context redaction, no error leak in
dashboard, webhook throttle. Perf: HTTP timeouts, opt-in queued delivery, dedup'd
point lookups, cached dashboard aggregates, occurrence retention + indexes. Reliability:
bounded cache TTLs, concurrency-safe `recordOccurrence` (firstOrCreate + atomic increment).
