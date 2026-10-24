## Database Design

This document explains how I designed the database for our Sports Tournament Organizer project. It covers what tables I created, why I created them, what data goes inside each one, and how they are connected to each other.

Think of the database like a set of Excel sheets. Each sheet (table) stores one type of information, and they are linked together using IDs.

---

## ER Diagram

> The diagram below shows all 7 tables and how they are connected to each other.

```mermaid
erDiagram
    TOURNAMENTS {
        int id PK
        varchar name UK
        varchar sport
        enum format
        enum status
        datetime created_at
    }

    TEAMS {
        int id PK
        varchar name UK
        varchar sport
        datetime created_at
    }

    TOURNAMENT_TEAMS {
        int id PK
        int tournament_id FK
        int team_id FK
    }

    GROUPS {
        int id PK
        int tournament_id FK
        varchar group_name
    }

    GROUP_MEMBERS {
        int id PK
        int tournament_id FK
        int group_id FK
        int team_id FK
    }

    MATCHES {
        int id PK
        int tournament_id FK
        int group_id FK
        int team1_id FK
        int team2_id FK
        datetime match_date
        varchar venue
        enum stage
        enum status
    }

    RESULTS {
        int id PK
        int match_id FK
        int team1_score
        int team2_score
        int winner_team_id FK
        enum outcome
        datetime recorded_at
    }

    TOURNAMENTS ||--o{ TOURNAMENT_TEAMS : "has"
    TEAMS ||--o{ TOURNAMENT_TEAMS : "joins"
    TOURNAMENTS ||--o{ GROUPS : "has"
    GROUPS ||--o{ GROUP_MEMBERS : "contains"
    TEAMS ||--o{ GROUP_MEMBERS : "assigned to"
    TOURNAMENTS ||--o{ MATCHES : "hosts"
    GROUPS ||--o{ MATCHES : "within"
    TEAMS ||--o{ MATCHES : "team1"
    TEAMS ||--o{ MATCHES : "team2"
    MATCHES ||--|| RESULTS : "has"
    TEAMS ||--o{ RESULTS : "winner"
```

---

## Table Descriptions

Here I explain each table in simple words, followed by the column details and validation rules.

---

### 1. `tournaments`

**What it does:**  
This table stores every tournament that is created in the system. For example, if someone creates "Inter-College Cricket Cup 2024", it gets saved here with its sport type, format (knockout or round-robin), and current status.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT, PK, AUTO_INCREMENT | Unique ID given to each tournament automatically |
| `name` | VARCHAR(100), UNIQUE | Name of the tournament (must be unique) |
| `sport` | VARCHAR(50) | Type of sport — Cricket, Football, Basketball, etc. |
| `format` | ENUM('knockout', 'round_robin', 'both') | How the tournament is played |
| `status` | ENUM('upcoming', 'ongoing', 'completed') | Current state of the tournament |
| `created_at` | DATETIME | When the tournament was created |

---

### 2. `teams`

**What it does:**  
This table stores all the teams that register in the system. A team registers once and can then be added to multiple tournaments.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT, PK, AUTO_INCREMENT | Unique ID for each team |
| `name` | VARCHAR(100), UNIQUE | Team name — must be unique so no duplicate registrations |
| `sport` | VARCHAR(50) | Sport the team plays |
| `created_at` | DATETIME | When the team registered |

---

### 3. `tournament_teams`

**What it does:**  
One team can participate in many tournaments, and one tournament can have many teams. This table acts as the bridge between them. It prevents duplicate registrations by ensuring a team cannot join the same tournament twice.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT, PK, AUTO_INCREMENT | Unique row ID |
| `tournament_id` | INT, FK → tournaments.id | Which tournament |
| `team_id` | INT, FK → teams.id | Which team |

> **Constraint:** `UNIQUE KEY unique_tournament_team (tournament_id, team_id)` prevents any team from registering twice into the same tournament.

---

### 4. `groups`

**What it does:**  
In a round-robin tournament, teams are divided into groups (Group A, Group B, etc.). This table stores those group names for each tournament.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT, PK, AUTO_INCREMENT | Unique group ID |
| `tournament_id` | INT, FK → tournaments.id | Which tournament this group belongs to |
| `group_name` | VARCHAR(50) | Name of the group e.g. "Group A", "Group B" |

> **Constraint:** `UNIQUE KEY unique_group_per_tournament (tournament_id, group_name)` prevents duplicate group names within the same tournament.

---

### 5. `group_members`

**What it does:**  
After the DSU algorithm divides teams into groups, this table saves which team is in which group. A team cannot be placed into more than one group in the same tournament.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT, PK, AUTO_INCREMENT | Unique row ID |
| `tournament_id` | INT, FK → tournaments.id | Which tournament |
| `group_id` | INT, FK → groups.id | Which group |
| `team_id` | INT, FK → teams.id | Which team is in that group |

> **Constraint:** `UNIQUE KEY unique_team_in_tournament_groups (tournament_id, team_id)` ensures a team can only belong to one group per tournament.

---

### 6. `matches`

**What it does:**  
This table stores every match scheduled in a tournament — who is playing against whom, on what date, at what venue, and at what stage (group stage, semifinal, final etc.).

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT, PK, AUTO_INCREMENT | Unique match ID |
| `tournament_id` | INT, FK → tournaments.id | Which tournament this match belongs to |
| `group_id` | INT, FK → groups.id *(nullable)* | Which group — left empty for knockout matches |
| `team1_id` | INT, FK → teams.id | First team playing |
| `team2_id` | INT, FK → teams.id | Second team playing |
| `match_date` | DATETIME | Date and time of the match |
| `venue` | VARCHAR(100) | Location where the match is played |
| `stage` | ENUM('group', 'quarterfinal', 'semifinal', 'final') | Stage of the tournament |
| `status` | ENUM('scheduled', 'ongoing', 'completed') | Current match status |

> **Constraint:** `CONSTRAINT chk_different_teams CHECK (team1_id <> team2_id)` guarantees that a team can never play against itself.

---

### 7. `results`

**What it does:**  
After a match is played, the score and winner are stored here. Every match has at most one result entry.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT, PK, AUTO_INCREMENT | Unique result ID |
| `match_id` | INT, FK → matches.id, UNIQUE | Which match this result belongs to (one result per match) |
| `team1_score` | INT | Score of team 1 |
| `team2_score` | INT | Score of team 2 |
| `winner_team_id` | INT, FK → teams.id *(nullable)* | The team that won — null if it's a draw |
| `outcome` | ENUM('team1_win', 'team2_win', 'draw') | Result of the match |
| `recorded_at` | DATETIME | When the result was entered |

---

## How the Tables Connect (Relationships)

| Relationship | Type | Simple Explanation |
|---|---|---|
| Tournament → Teams | Many-to-Many via `tournament_teams` | Many teams can join many tournaments |
| Tournament → Groups | One-to-Many | One tournament has multiple groups |
| Group → Teams | Many-to-Many via `group_members` | Many teams in many groups |
| Tournament → Matches | One-to-Many | One tournament has many matches |
| Group → Matches | One-to-Many | One group has multiple matches (nullable) |
| Match → Result | One-to-One | Each match has exactly one result |

---

## Flow of Data (How everything works together)

```
1. Admin creates a Tournament
        ↓
2. Teams register and join the Tournament  (tournament_teams)
        ↓
3. DSU algorithm divides teams into Groups  (groups + group_members)
        ↓
4. Matches are generated for each group  (matches)
        ↓
5. Scores are entered after each match  (results)
        ↓
6. Winner is determined from results
```

---

## Important Rules & Safeguards

- **No self-matches:** A team cannot play against itself (`team1_id != team2_id` enforced by MySQL `CHECK`).
- **No duplicate registrations:** A team cannot join the same tournament twice (`unique_tournament_team`).
- **No multi-group teams:** A team cannot be placed into two different groups in the same tournament (`unique_team_in_tournament_groups`).
- **No duplicate groups:** A tournament cannot have two groups with the identical name (`unique_group_per_tournament`).
- **One result per match:** Each match can only have one result row (`match_id` is UNIQUE).
- **Group is optional for knockout:** `group_id` in `matches` is `NULL` for quarterfinal, semifinal, and final rounds.
