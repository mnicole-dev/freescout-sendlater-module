# FreeScout Send Later Module

Schedule replies to be sent later. Free alternative to the paid send-later module.

## Features
- "Send Later…" entry in the Send button dropdown — pick a date/time or use presets (in 1 hour, tomorrow 9am, Monday 9am). Times use the agent's browser timezone.
- The reply is stored as a regular draft with the schedule in the thread's native `meta` field — no extra tables.
- A badge on the scheduled draft shows the send time, with **Send now** and **Cancel schedule** actions.
- At the due time, a scheduled command publishes the draft and fires FreeScout's native `UserReplied` event — the email goes through the standard send chain (jobs, notifications).
- If an agent sends a new reply while a message is scheduled, the scheduled message is sent immediately (prevents out-of-order replies).
- Not available for chat conversations.

## Requirements
FreeScout ≥ 1.8.0 with a working cron/scheduler (`schedule:run` every minute). No composer dependencies.

## Installation
```bash
cd Modules
git clone https://github.com/mnicole-dev/freescout-sendlater-module SendLater
```
Activate **SendLater** in **Manage → Modules**.

> If installing via CLI in a container, run `artisan` as the web user, not root.

## License
AGPL-3.0 — see [LICENSE](LICENSE).
