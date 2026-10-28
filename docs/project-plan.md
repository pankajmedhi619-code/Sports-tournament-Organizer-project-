
# Sports Tournament Organizer — Match and Results Logic

## 1. Tournament Setup

Each tournament has a name, sport, format, and status.
Supported formats in the existing database are:
- `round_robin`
- `knockout`
- `both`

Group-stage round-robin fixtures are generated only for
tournaments with format `round_robin` or `both`.

## 2. Group Integration

The match module reads saved groups and team memberships
from the existing `groups` and `group_members` tables.

It does not create a second grouping algorithm or replace
the DSU implementation.

Teams must already be registered in the tournament.

## 3. Round-Robin Match Generation

Every pair of teams in the same group plays once.

Formula:

Matches = n * (n - 1) / 2

Example:
- Team A vs Team B
- Team A vs Team C
- Team B vs Team C

For three teams, the total is three matches.

The generator skips existing fixtures and prevents a team
from being scheduled against itself.

## 4. Match Scheduling

The existing `matches` table stores:
- tournament_id
- group_id
- team1_id and team2_id
- match_date and venue
- stage and status

The backend validates team registration, different teams,
duplicate group fixtures, and venue/time conflicts.

Supported match statuses are `scheduled`, `ongoing`,
and `completed`, as defined by the current database schema.

## 5. Results

The existing `results` table stores each match's scores,
winner_team_id, outcome, and recorded_at.

The backend compares both scores:
- Higher score: that team wins.
- Equal scores: outcome is draw and winner_team_id is NULL.

Saving a result updates the result record and marks the
match as completed.

## 6. API Endpoints

- GET `matches.php?tournament_id=ID`
- POST `matches.php` action `generate_round_robin`
- POST `matches.php` action `schedule`
- PUT `matches.php?id=ID` to update status
- GET `results.php?tournament_id=ID`
- POST `results.php` to save or update scores

All responses use the existing JSON envelope:
`status`, `message`, and optional `data`.

## 7. Team Integration

- Rahimul: confirm saved group data and DSU integration.
- Divyanshu: review matches.php and results.php before merging.
- Shemphang: confirm results page fields and presentation.

## 8. Limitations

Knockout progression, qualified-team advancement, and
postponed status need agreed rules and database/API support.
They are not implemented by the group round-robin generator.

